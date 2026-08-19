<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_Ajax_UnitTestCase;

/**
 * Regression test for the double-taxation bug in remaining-payment WC orders.
 *
 * Bug history (2026-05-15): WC tax-inclusive mode + 20% KDV. The handler
 * passed $remaining_amount (gross/tax-inclusive) directly into
 * WC_Order_Item::set_subtotal()/set_total(). WC interpreted those as net
 * and then calculate_totals() added tax on top, producing
 * gross + tax_on_gross (e.g. 9544.50 -> 11453.40).
 *
 * The fix uses wc_get_price_excluding_tax() to convert gross -> net before
 * setting the line item, so calculate_totals() rebuilds the correct gross.
 *
 * This test asserts: order total == $remaining_amount (gross), not inflated.
 */
final class RemainingPaymentTaxTest extends WP_Ajax_UnitTestCase
{
    use WooCommerceFixtures;
    use WooCommerceOptionSandbox;

    /** @var int */
    private $customer_id;
    /** @var int */
    private $booking_id;
    /** @var int */
    private $product_id;
    /** @var int */
    private $tax_rate_id;
    /**
     * @var string
     */
    protected $_last_response;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        // Enable WC tax-inclusive mode (matches production setup).
        $this->sandbox_option('woocommerce_calc_taxes', 'yes');
        $this->sandbox_option('woocommerce_prices_include_tax', 'yes');
        $this->sandbox_option('woocommerce_tax_based_on', 'base');

        // Insert a single 20% KDV tax rate.
        $this->tax_rate_id = \WC_Tax::_insert_tax_rate(array(
            'tax_rate_country'  => '',
            'tax_rate_state'    => '',
            'tax_rate'          => '20.0000',
            'tax_rate_name'     => 'KDV',
            'tax_rate_priority' => 1,
            'tax_rate_compound' => 0,
            'tax_rate_shipping' => 0,
            'tax_rate_order'    => 0,
            'tax_rate_class'    => '',
        ));

        // Booking product (must exist with the SKU the handler looks up).
        $product          = $this->ensure_booking_product();
        $product->set_tax_status('taxable');
        $this->product_id = $product->save();
        $this->product_id = $product->save();

        // Customer + booking.
        $this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
        $this->booking_id  = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', $this->customer_id);
        update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'deposit');
        update_post_meta($this->booking_id, '_mhmrentiva_total_price', 10605);
        update_post_meta($this->booking_id, '_mhmrentiva_deposit_amount', 1060.5);
        update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 9544.5);

        // Register handler so the ajax action is wired up.
        RemainingPaymentHandler::register();

        wp_set_current_user($this->customer_id);
    }

    public function tearDown(): void
    {
        if ($this->tax_rate_id) {
            \WC_Tax::_delete_tax_rate($this->tax_rate_id);
        }
        $this->restore_sandboxed_options();
        wp_logout();
        parent::tearDown();
    }

    /**
     * Remaining order total must equal the booking's tax-inclusive
     * remaining amount, not gross + tax_on_gross.
     */
    public function test_remaining_order_total_is_not_double_taxed(): void
    {
        $_POST['action']     = 'mhmrentiva_pay_remaining';
        $_POST['booking_id'] = $this->booking_id;
        $_POST['nonce']      = wp_create_nonce('mhmrentiva_pay_remaining_' . $this->booking_id);

        try {
            $this->_handleAjax('mhmrentiva_pay_remaining');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected — WC AJAX handlers always wp_die after sending JSON.
        }

        $response = json_decode((string) $this->_last_response, true);
        $this->assertIsArray($response);
        $this->assertTrue(
            (bool) ( $response['success'] ?? false ),
            'AJAX call did not succeed: ' . wp_json_encode($response)
        );

        $order_id = (int) get_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', true);
        $this->assertGreaterThan(0, $order_id, 'Remaining order ID was not persisted.');

        $order = wc_get_order($order_id);
        $this->assertNotFalse($order, 'Remaining order could not be loaded.');

        $expected = 9544.50;
        $actual   = (float) $order->get_total();

        $this->assertEqualsWithDelta(
            $expected,
            $actual,
            0.01,
            sprintf(
                'Remaining order total (%.2f) does not match booking remaining_amount (%.2f). ' .
                'Double-taxation regression — gross was passed into set_subtotal() without ' .
                'wc_get_price_excluding_tax() conversion.',
                $actual,
                $expected
            )
        );

        // Sanity: tax should be split out of the gross, not added on top.
        // For 9544.50 gross at 20% KDV: net=7953.75, tax=1590.75.
        $this->assertEqualsWithDelta(7953.75, (float) $order->get_subtotal(), 0.01, 'Subtotal (net) mismatch.');
        $this->assertEqualsWithDelta(1590.75, (float) $order->get_total_tax(), 0.01, 'Tax amount mismatch.');
    }
}
