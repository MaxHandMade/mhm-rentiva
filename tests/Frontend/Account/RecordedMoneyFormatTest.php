<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Account\BookingTimeline;
use MHMRentiva\Admin\Frontend\Account\CancellationView;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * PR D Task 4, fix round 1 (controller money-format ruling): money that moved
 * (paid, refunded, the refund promise, payment steps) is the recorded gross in
 * its recorded currency; only the price composition (breakdown rows, total)
 * follows the cart's tax display. On a store that shows cart prices excluding
 * tax, the old path showed the net of a payment that already included tax.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\BookingTimeline::recorded_html
 * @covers \MHMRentiva\Admin\Frontend\Account\CancellationView::for_booking
 */
final class RecordedMoneyFormatTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	/** @var list<int> */
	private array $rate_ids = array();

	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		$this->sandbox_option('woocommerce_calc_taxes', 'yes');
		$this->sandbox_option('woocommerce_prices_include_tax', 'yes');
		$this->sandbox_option('woocommerce_tax_based_on', 'base');
		$this->sandbox_option('woocommerce_tax_display_cart', 'excl');
		$this->sandbox_option('woocommerce_tax_display_shop', 'excl');
		$this->rate_ids[] = (int) \WC_Tax::_insert_tax_rate(array(
			'tax_rate_country'  => '',
			'tax_rate'          => '20.0000',
			'tax_rate_name'     => 'VAT',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => '',
		));

		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($this->customer_id);
	}

	public function tearDown(): void
	{
		foreach ($this->rate_ids as $rate_id) {
			\WC_Tax::_delete_tax_rate($rate_id);
		}
		$this->restore_sandboxed_options();
		parent::tearDown();
	}

	public function test_moved_money_is_recorded_gross_while_breakdown_follows_the_cart(): void
	{
		$id     = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		$pickup = ( new \DateTimeImmutable('@' . ( time() + 5 * DAY_IN_SECONDS )) )->setTimezone(wp_timezone());
		foreach (array(
			'_mhmrentiva_customer_user_id' => $this->customer_id,
			'_mhmrentiva_status'           => 'confirmed',
			'_mhmrentiva_payment_status'   => 'paid',
			'_mhmrentiva_payment_type'     => 'deposit',
			'_mhmrentiva_total_price'      => '1200.00',
			'_mhmrentiva_deposit_amount'   => '120.00',
			'_mhmrentiva_remaining_amount' => '1080.00',
			'_mhmrentiva_rental_days'      => 3,
			'_mhmrentiva_pickup_date'      => $pickup->format('Y-m-d'),
			'_mhmrentiva_start_time'       => $pickup->format('H:i'),
		) as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		$order = $this->create_paid_order_for_booking($id, '120');
		$gross = wc_price( (float) $order->get_total(), array( 'currency' => $order->get_currency() ));

		// Fixture guard: on this store the cart display of an amount is not the amount.
		$this->assertNotSame(wc_price(1200.0), PriceDisplay::for_cart(1200.0)['html'], 'Fixture guard: tax-exclusive cart display.');
		$this->assertNotSame(PriceDisplay::for_cart( (float) $order->get_total())['html'], $gross, 'Fixture guard: the old path would differ.');

		// Refund promise: the recorded gross.
		$view = CancellationView::for_booking($id, $this->customer_id);
		// (printed through PriceDisplay's kses list, like every amount on the page)
		$shown = wp_kses($gross, PriceDisplay::allowed_html());
		$this->assertSame($shown, $view['refund_html']);

		// Payment step: the recorded gross, in the order's currency.
		$steps = array_column(BookingTimeline::for_booking($id), null, 'key');
		$this->assertSame('Deposit paid · ' . wp_strip_all_tags($gross), $steps['deposit_paid']['label']);

		// Detail page: "Paid" row is the gross; the breakdown total is the cart display.
		$html = AccountRenderer::render_booking_detail($id, true);
		$this->assertMatchesRegularExpression('/rvs-account-detail__money--paid">\s*<span>Paid<\/span>\s*<span>' . preg_quote($shown, '/') . '<\/span>/', $html);
		$this->assertStringContainsString('<span class="rvs-breakdown__amount">' . wp_kses(PriceDisplay::for_cart(1200.0)['html'], PriceDisplay::allowed_html()) . '</span>', $html);
	}

	public function test_each_paid_order_step_uses_its_own_currency(): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($id, '_mhmrentiva_payment_type', 'deposit');
		$deposit   = $this->create_paid_order_for_booking($id, '100');
		$remaining = $this->create_paid_order_for_booking($id, '200');
		update_post_meta($id, '_mhmrentiva_remaining_order_id', $remaining->get_id());
		$deposit->set_currency('TRY');
		$deposit->save();
		$remaining->set_currency('EUR');
		$remaining->save();

		$steps = array_column(BookingTimeline::for_booking($id), null, 'key');

		$this->assertSame('Deposit paid · ' . wp_strip_all_tags(wc_price( (float) $deposit->get_total(), array( 'currency' => 'TRY' ))), $steps['deposit_paid']['label']);
		$this->assertSame('Balance paid · ' . wp_strip_all_tags(wc_price( (float) $remaining->get_total(), array( 'currency' => 'EUR' ))), $steps['balance_paid']['label']);
	}
}
