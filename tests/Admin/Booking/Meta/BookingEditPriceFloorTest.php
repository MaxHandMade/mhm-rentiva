<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Core\BookingPricingInputs;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingEditNotice;
use MHMRentiva\Admin\Booking\Meta\BookingEditPriceFloor;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\Payment\Core\PaymentState;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * An admin Update through the real save chain: the difference becomes owed,
 * and the four cases that cannot be written yet keep the price inputs stored
 * while the rest of the Update is saved (function scan H2 face 2; plan r7-r10).
 *
 * The booking: three days paid in full offline at an old rate (2,550); today's
 * rate is 1,000 a day.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditPriceFloor
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::recalculate_booking_costs
 */
final class BookingEditPriceFloorTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $vehicle;

	private int $booking;

	public function setUp(): void
	{
		parent::setUp();
		BookingPricingInputs::reset();
		BookingEditPriceFloor::reset();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

		$this->vehicle = $this->vehicle_at('1000');

		$this->booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		foreach (array(
			'_mhmrentiva_vehicle_id'       => (string) $this->vehicle,
			'_mhmrentiva_status'           => 'confirmed',
			'_mhmrentiva_payment_status'   => 'paid',
			'_mhmrentiva_payment_type'     => 'full',
			'_mhmrentiva_total_price'      => '2550',
			'_mhmrentiva_deposit_amount'   => '2550',
			'_mhmrentiva_remaining_amount' => '0',
			'_mhmrentiva_pickup_date'      => '2099-05-04',
			'_mhmrentiva_start_time'       => '10:00',
			'_mhmrentiva_dropoff_date'     => '2099-05-07',
			'_mhmrentiva_end_time'         => '10:00',
		) as $key => $value) {
			update_post_meta($this->booking, $key, $value);
		}

		add_action('save_post', array( BookingPricingInputs::class, 'open' ), 1, 2);
		add_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' ), 5);
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		add_action('save_post', array( BookingPricingInputs::class, 'close' ), 99, 2);
		add_action('mhmrentiva_booking_meta_updated', array( BookingMeta::class, 'on_booking_meta_updated' ), 10, 3);
	}

	public function tearDown(): void
	{
		$_POST = array();
		BookingPricingInputs::reset();
		BookingEditPriceFloor::reset();
		parent::tearDown();
	}

	private function vehicle_at(string $per_day): int
	{
		$vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle, '_mhmrentiva_price_per_day', $per_day);
		update_post_meta($vehicle, '_mhmrentiva_vehicle_status', 'active');

		return $vehicle;
	}

	/**
	 * @param array<string, string> $override
	 */
	private function update(array $override = array()): void
	{
		BookingEditPriceFloor::reset();
		$_POST = $override + array(
			'_wpnonce'                      => wp_create_nonce('update-post_' . $this->booking),
			'mhmrentiva_edit_vehicle_id'    => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'   => '2099-05-04',
			'mhmrentiva_edit_pickup_time'   => '10:00',
			'mhmrentiva_edit_dropoff_date'  => '2099-05-07',
			'mhmrentiva_edit_dropoff_time'  => '10:00',
			'mhmrentiva_edit_status'        => 'confirmed',
			'mhmrentiva_edit_special_notes' => 'Note from this Update',
		);
		wp_update_post(array( 'ID' => $this->booking, 'post_title' => 'Updated' ));
	}

	private function meta(string $key): string
	{
		return (string) get_post_meta($this->booking, $key, true);
	}

	private function assert_price_inputs_kept(): void
	{
		$this->assertSame('2099-05-07', $this->meta('_mhmrentiva_dropoff_date'), 'dates kept');
		$this->assertSame((string) $this->vehicle, $this->meta('_mhmrentiva_vehicle_id'), 'vehicle kept');
		$this->assertSame('2550', $this->meta('_mhmrentiva_total_price'), 'price kept');
		$this->assertSame('Note from this Update', $this->meta('_mhmrentiva_special_notes'), 'the rest of the Update is saved');
	}

	public function test_an_extension_of_a_paid_offline_booking_owes_the_difference(): void
	{
		// 4 days at today's 1,000.
		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-08' ));

		$this->assertSame('2099-05-08', $this->meta('_mhmrentiva_dropoff_date'));
		$this->assertSame(4000.0, (float) $this->meta('_mhmrentiva_total_price'));
		$this->assertSame('deposit', $this->meta('_mhmrentiva_payment_type'));
		$this->assertSame(2550.0, (float) $this->meta('_mhmrentiva_deposit_amount'));
		$this->assertSame(1450.0, (float) $this->meta('_mhmrentiva_remaining_amount'));
		$this->assertSame(Money::toMinor(2550), PaymentState::forBooking($this->booking)->paid(), 'collected stays 2,550 (it read 4,000 before)');
		$this->assertSame('', BookingEditNotice::peek($this->booking));
	}

	public function test_a_shortening_below_the_collected_amount_is_refused(): void
	{
		// 2 days at 1,000 = 2,000 < 2,550 collected.
		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-06' ));

		$this->assert_price_inputs_kept();
		$this->assertSame('0', $this->meta('_mhmrentiva_remaining_amount'));
		$this->assertStringContainsString('less than what was collected', BookingEditNotice::peek($this->booking));
	}

	public function test_a_remaining_payment_order_freezes_a_change_of_the_total(): void
	{
		$this->require_woocommerce();
		$order = wc_create_order(array( 'status' => 'pending' ));
		$order->update_meta_data('_mhmrentiva_booking_id', $this->booking);
		$order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$order->save();
		// The booking's own pointer can be gone (a failed order deletes it): the order meta is asked.
		delete_post_meta($this->booking, '_mhmrentiva_remaining_order_id');

		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-08' ));

		$this->assert_price_inputs_kept();
		$this->assertStringContainsString('remaining-payment link', BookingEditNotice::peek($this->booking));
	}

	public function test_a_remaining_payment_order_lets_a_same_priced_vehicle_through(): void
	{
		$this->require_woocommerce();
		$order = wc_create_order(array( 'status' => 'pending' ));
		$order->update_meta_data('_mhmrentiva_booking_id', $this->booking);
		$order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$order->save();
		// 3 days x 850 = 2,550: the stored total.
		$same_price = $this->vehicle_at('850');

		$this->update(array( 'mhmrentiva_edit_vehicle_id' => (string) $same_price ));

		$this->assertSame((string) $same_price, $this->meta('_mhmrentiva_vehicle_id'), 'the vehicle change is saved');
		$this->assertSame(2550.0, (float) $this->meta('_mhmrentiva_total_price'));
		$this->assertSame('', BookingEditNotice::peek($this->booking));
	}

	public function test_a_trashed_remaining_order_still_counts(): void
	{
		$this->require_woocommerce();
		$order = wc_create_order(array( 'status' => 'pending' ));
		$order->update_meta_data('_mhmrentiva_booking_id', $this->booking);
		$order->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$order->save();
		$order->delete(false);
		$this->assertSame('trash', $order->get_status(), 'PREMISE: in the trash, not deleted');

		$this->assertTrue(BookingEditPriceFloor::has_remaining_order($this->booking), 'any status, the trash included, as in the posts store (Codex r11 2)');
	}

	public function test_another_bookings_remaining_order_does_not_count(): void
	{
		$this->require_woocommerce();
		$other = wc_create_order(array( 'status' => 'pending' ));
		$other->update_meta_data('_mhmrentiva_booking_id', $this->booking + 1000);
		$other->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$other->save();

		$this->assertFalse(BookingEditPriceFloor::has_remaining_order($this->booking));
	}

	public function test_a_refunded_booking_cannot_be_raised(): void
	{
		update_post_meta($this->booking, '_mhmrentiva_payment_status', 'partially_refunded');

		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-08' ));

		$this->assert_price_inputs_kept();
		$this->assertStringContainsString('has been refunded', BookingEditNotice::peek($this->booking));
	}

	public function test_a_mixed_currency_booking_keeps_its_price_inputs(): void
	{
		$this->require_woocommerce();
		$first  = $this->create_paid_order_for_booking($this->booking, '500');
		$second = $this->create_paid_order_for_booking($this->booking, '2050');
		update_post_meta($this->booking, '_mhmrentiva_remaining_order_id', $second->get_id());
		$first->set_currency('TRY');
		$first->save();
		$second->set_currency('EUR');
		$second->save();
		$this->assertTrue(PaymentState::forBooking($this->booking)->isMixedCurrency(), 'PREMISE');

		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-08' ));

		$this->assert_price_inputs_kept();
		$this->assertStringContainsString('more than one currency', BookingEditNotice::peek($this->booking));
	}

	public function test_an_order_shared_with_another_booking_keeps_the_price_inputs(): void
	{
		$this->require_woocommerce();
		// One cart, two bookings at 1,000 each: an order of 2,000 with one line per booking.
		$order = $this->create_paid_order_for_booking($this->booking, '1000');
		$other = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		$items = array_values($order->get_items());
		$items[0]->update_meta_data('_mhmrentiva_booking_id', $this->booking);
		$items[0]->save();
		$second = new \WC_Order_Item_Product();
		$second->set_product($this->ensure_booking_product('1000'));
		$second->set_quantity(1);
		$second->set_subtotal(1000.0);
		$second->set_total(1000.0);
		$second->update_meta_data('_mhmrentiva_booking_id', $other);
		$order->add_item($second);
		$order->calculate_totals();
		$order->save();
		update_post_meta($this->booking, '_mhmrentiva_total_price', '1000');
		$this->assertSame(Money::toMinor(2000), PaymentState::forBooking($this->booking)->paid(), 'PREMISE: paid() reads the whole order');
		$this->assertTrue(BookingEditPriceFloor::shares_an_order($this->booking, PaymentState::forBooking($this->booking)), 'PREMISE');

		// 4 days at 1,000: above this booking's 1,000 but below the order's 2,000.
		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-08' ));

		$this->assertSame('2099-05-07', $this->meta('_mhmrentiva_dropoff_date'), 'dates kept');
		$this->assertSame('1000', $this->meta('_mhmrentiva_total_price'), 'price kept');
		$this->assertSame('full', $this->meta('_mhmrentiva_payment_type'), 'not turned into a deposit of the whole order');
		$this->assertStringContainsString('also contains other bookings or products',BookingEditNotice::peek($this->booking));
	}

	public function test_an_order_of_this_booking_alone_is_not_shared(): void
	{
		$this->require_woocommerce();
		$order = $this->create_paid_order_for_booking($this->booking, '2550');
		$items = array_values($order->get_items());
		$items[0]->update_meta_data('_mhmrentiva_booking_id', $this->booking);
		$items[0]->save();

		$this->assertFalse(BookingEditPriceFloor::shares_an_order($this->booking, PaymentState::forBooking($this->booking)));
	}

	public function test_another_product_in_the_order_makes_it_shared(): void
	{
		$this->require_woocommerce();
		$order = $this->create_paid_order_for_booking($this->booking, '2550');
		$items = array_values($order->get_items());
		$items[0]->update_meta_data('_mhmrentiva_booking_id', $this->booking);
		$items[0]->save();
		$extra = new \WC_Order_Item_Product();
		$extra->set_product($this->ensure_booking_product('99'));
		$extra->set_quantity(1);
		$extra->set_subtotal(99.0);
		$extra->set_total(99.0);
		$order->add_item($extra);
		$order->calculate_totals();
		$order->save();

		$this->assertTrue(BookingEditPriceFloor::shares_an_order($this->booking, PaymentState::forBooking($this->booking)), 'paid() counts the other product too');
	}

	public function test_a_shared_order_with_an_unchanged_total_does_not_reopen_a_paid_debt(): void
	{
		$this->require_woocommerce();
		// Codex r10 3: deposit booking 3,000 (deposit 500), everything paid, the
		// original order shared with another booking.
		$other = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		$order = $this->create_paid_order_for_booking($this->booking, '500');
		$items = array_values($order->get_items());
		$items[0]->update_meta_data('_mhmrentiva_booking_id', $this->booking);
		$items[0]->save();
		$second = new \WC_Order_Item_Product();
		$second->set_product($this->ensure_booking_product('700'));
		$second->set_quantity(1);
		$second->set_subtotal(700.0);
		$second->set_total(700.0);
		$second->update_meta_data('_mhmrentiva_booking_id', $other);
		$order->add_item($second);
		$order->calculate_totals();
		$order->save();
		foreach (array(
			'_mhmrentiva_payment_type'     => 'deposit',
			'_mhmrentiva_total_price'      => '3000',
			'_mhmrentiva_deposit_amount'   => '500',
			'_mhmrentiva_remaining_amount' => '0',
		) as $key => $value) {
			update_post_meta($this->booking, $key, $value);
		}
		// 3 days x 1,000 = 3,000: a vehicle change with the same total.
		$same_price = $this->vehicle_at('1000');

		$this->update(array( 'mhmrentiva_edit_vehicle_id' => (string) $same_price ));

		$this->assertSame((string) $same_price, $this->meta('_mhmrentiva_vehicle_id'), 'the vehicle change is saved');
		$this->assertSame(3000.0, (float) $this->meta('_mhmrentiva_total_price'));
		$this->assertSame(0.0, (float) $this->meta('_mhmrentiva_remaining_amount'), 'not 2,500 owed again');
		$this->assertSame(500.0, (float) $this->meta('_mhmrentiva_deposit_amount'));
	}

	public function test_the_posts_store_finds_only_this_bookings_remaining_order(): void
	{
		$orders = array();
		foreach (array( array( $this->booking, '1' ), array( $this->booking, '' ), array( $this->booking + 1000, '1' ) ) as $i => $case) {
			$orders[ $i ] = (int) self::factory()->post->create(array(
				'post_type'   => 'shop_order',
				'post_status' => 'wc-pending',
			));
			update_post_meta($orders[ $i ], '_mhmrentiva_booking_id', (string) $case[0]);
			if ('' !== $case[1]) {
				update_post_meta($orders[ $i ], '_mhmrentiva_is_remaining_payment', $case[1]);
			}
		}
		$this->assertTrue(BookingEditPriceFloor::remaining_order_in_posts($this->booking), 'its own remaining order');

		wp_delete_post($orders[0], true);
		$this->assertFalse(BookingEditPriceFloor::remaining_order_in_posts($this->booking), 'its plain order and another booking\'s remaining order do not count (Codex r10 2)');
	}

	public function test_a_reversed_range_and_a_cheaper_vehicle_say_both(): void
	{
		$cheaper = $this->vehicle_at('500');

		$this->update(array(
			'mhmrentiva_edit_vehicle_id'   => (string) $cheaper,
			'mhmrentiva_edit_dropoff_date' => '2099-05-01',
		));

		$this->assert_price_inputs_kept();
		$notice = BookingEditNotice::peek($this->booking);
		$this->assertStringContainsString('less than what was collected', $notice);
		$this->assertStringContainsString(\MHMRentiva\Admin\Booking\Meta\BookingEditDates::notice(), $notice, 'the date refusal is kept in the same notice');
	}

	public function test_a_full_payment_through_woocommerce_without_a_remaining_order_owes_the_difference(): void
	{
		$this->require_woocommerce();
		update_post_meta($this->booking, '_mhmrentiva_payment_status', 'paid');
		$this->create_paid_order_for_booking($this->booking, '2550');
		$this->assertSame(Money::toMinor(2550), PaymentState::forBooking($this->booking)->paid(), 'PREMISE: WooCommerce channel');

		$this->update(array( 'mhmrentiva_edit_dropoff_date' => '2099-05-08' ));

		$this->assertSame(4000.0, (float) $this->meta('_mhmrentiva_total_price'));
		$this->assertSame('deposit', $this->meta('_mhmrentiva_payment_type'));
		$this->assertSame(1450.0, (float) $this->meta('_mhmrentiva_remaining_amount'));
		$this->assertSame(Money::toMinor(2550), PaymentState::forBooking($this->booking)->paid());
	}

	public function test_a_transfer_is_left_to_its_own_rules(): void
	{
		update_post_meta($this->booking, '_mhmrentiva_service_type', 'transfer');
		$this->assertFalse(BookingEditPriceFloor::refuse($this->booking, $this->vehicle, '2099-05-04', '10:00', '2099-05-05', '10:00', \MHMRentiva\Admin\Addons\AddonSelection::for_booking($this->booking)));
	}
}
