<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * Re-pricing a booking whose deposit was paid through WooCommerce: the new
 * remaining amount is the new total less what was collected, and both must be
 * on the same side of tax.
 *
 * The booking's total, deposit and remaining amount are on the basis prices are
 * entered in. With prices entered without tax, the paid order's total carries
 * the tax on top: 10 net collected as 12. Taking the order total as "collected"
 * against a net total left the remaining amount short by the deposit's tax.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::recalculate_booking_costs
 */
final class RepriceCollectedTaxBasisTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;
	use WooCommerceOptionSandbox;

	private int $vehicle_id = 0;

	private int $tax_rate_id = 0;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		$this->vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');
		update_post_meta($this->vehicle_id, '_mhmrentiva_deposit', '10');

		$this->sandbox_option('woocommerce_calc_taxes', 'yes');
		$this->sandbox_option('woocommerce_tax_based_on', 'base');
		$this->sandbox_option('woocommerce_default_customer_address', 'base');
		$this->sandbox_option('woocommerce_tax_round_at_subtotal', 'no');

		$this->tax_rate_id = (int) \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '20.0000',
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 1,
				'tax_rate_class'    => '',
			)
		);
	}

	public function tearDown(): void
	{
		parent::tearDown();
		\WC_Tax::_delete_tax_rate($this->tax_rate_id);
		$this->restore_sandboxed_options();
	}

	/**
	 * One day at 100 with a 10% deposit, the deposit paid through WooCommerce.
	 */
	private function booking_with_a_paid_deposit(string $order_line = '10'): int
	{
		$booking = (int) self::factory()->post->create(
			array(
				'post_type'  => 'mhmrentiva_booking',
				'meta_input' => array(
					'_mhmrentiva_vehicle_id'       => $this->vehicle_id,
					'_mhmrentiva_payment_type'     => 'deposit',
					'_mhmrentiva_deposit_type'     => 'percentage',
					'_mhmrentiva_payment_status'   => 'partially_paid',
					'_mhmrentiva_start_time'       => '10:00',
					'_mhmrentiva_end_time'         => '10:00',
					'_mhmrentiva_total_price'      => 100,
					'_mhmrentiva_deposit_amount'   => 10,
					'_mhmrentiva_remaining_amount' => 90,
				),
			)
		);
		$this->create_paid_order_for_booking($booking, $order_line);

		return $booking;
	}

	public function test_prices_without_tax_owe_the_new_total_less_the_net_deposit(): void
	{
		$this->sandbox_option('woocommerce_prices_include_tax', 'no');
		$booking = $this->booking_with_a_paid_deposit();
		$this->assertSame(12.0, (float) wc_get_order((int) get_post_meta($booking, '_mhmrentiva_woocommerce_order_id', true))->get_total(), 'PREMISE: 10 net is charged as 12');

		// One day -> two days: 200 before tax.
		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-04');

		$this->assertSame(200.0, (float) get_post_meta($booking, '_mhmrentiva_total_price', true));
		$this->assertSame(190.0, (float) get_post_meta($booking, '_mhmrentiva_remaining_amount', true), '200 less the 10 collected before tax, not less the 12 with tax');
	}

	public function test_the_collected_amount_is_read_on_the_price_basis(): void
	{
		$this->sandbox_option('woocommerce_prices_include_tax', 'no');
		$booking = $this->booking_with_a_paid_deposit();
		$state   = \MHMRentiva\Admin\Payment\Core\PaymentState::forBooking($booking);

		// What arrived stays what arrived; what is set against the total is net.
		$this->assertSame(\MHMRentiva\Admin\Payment\Core\Money::toMinor(12), $state->paid());
		$this->assertSame(\MHMRentiva\Admin\Payment\Core\Money::toMinor(10), $state->paidOnPriceBasis());
	}

	public function test_prices_with_tax_owe_the_new_total_less_the_order_total(): void
	{
		$this->sandbox_option('woocommerce_prices_include_tax', 'yes');
		// An order line is stored before tax: 10 with tax is 8.3333 plus 1.6667.
		$booking = $this->booking_with_a_paid_deposit((string) ( 10 / 1.2 ));
		$this->assertSame(10.0, (float) wc_get_order((int) get_post_meta($booking, '_mhmrentiva_woocommerce_order_id', true))->get_total(), 'PREMISE: 10 with tax is charged as 10');

		BookingMeta::recalculate_booking_costs($booking, '2026-11-02', '2026-11-04');

		$this->assertSame(200.0, (float) get_post_meta($booking, '_mhmrentiva_total_price', true));
		$this->assertSame(190.0, (float) get_post_meta($booking, '_mhmrentiva_remaining_amount', true));
	}
}
