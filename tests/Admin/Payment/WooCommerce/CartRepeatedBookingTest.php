<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\SiteClock;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR-B -- the same rental sent to the cart twice.
 *
 * Customer lens r2 (11-11): "Book now" -> checkout -> browser Back -> "Book
 * now" again left two identical lines, double total and deposit (#1). The
 * checkout then refused the order saying "booked by another customer", while
 * the clash was the customer's own second line (#2). A customer who lost a
 * real race saw two "no longer available" sentences, and the cart behind
 * the page was emptied without a word (#3).
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::add_booking_data_to_cart
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_checkout_availability
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::validate_cart_availability
 */
final class CartRepeatedBookingTest extends WP_UnitTestCase
{
	use SiteClock;
	use WooCommerceFixtures;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();
		$this->ensure_booking_product();
		WC()->cart->empty_cart();
		wc_clear_notices();
		$this->pin_site_timezone('Europe/Istanbul');

		$this->vehicle_id = $this->vehicle('Repeat Sedan');
	}

	public function tearDown(): void
	{
		if (function_exists('WC') && isset(WC()->cart)) {
			WC()->cart->empty_cart();
		}
		if (function_exists('wc_clear_notices')) {
			wc_clear_notices();
		}
		remove_all_actions('woocommerce_checkout_process');
		$this->release_site_clock();
		parent::tearDown();
	}

	public function test_the_same_rental_sent_twice_leaves_one_line(): void
	{
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04'), 200.0));
		$this->assertTrue(WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04'), 200.0));

		// WooCommerce merges identical item data into ONE line and raises its
		// quantity: that, not a second line, is how the total doubled.
		$this->assertCount(1, $this->rental_lines(), 'Back + "Book now" again must not double the cart.');
		$this->assertSame(1, (int) $this->rental_lines()[0]['quantity'], 'One rental is one unit, never two.');
		$this->assertEqualsWithDelta(200.0, $this->line_prices_total(), 0.001);
	}

	public function test_a_changed_choice_for_overlapping_dates_replaces_the_line(): void
	{
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04'), 200.0);
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-03', '2027-03-06'), 300.0);

		$lines = $this->rental_lines();
		$this->assertCount(1, $lines, 'The newer choice of the same vehicle wins.');
		$this->assertSame('2027-03-03', $lines[0]['mhmrentiva_booking_data']['pickup_date']);
		$this->assertEqualsWithDelta(300.0, $this->line_prices_total(), 0.001);
		$this->assertSame(1, wc_notice_count('notice'), 'The customer is told the line was updated.');
	}

	public function test_other_rentals_stay_in_the_cart(): void
	{
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04'), 200.0);
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-10', '2027-03-12'), 200.0);
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04', $this->vehicle('Second Hatch')), 150.0);

		$this->assertCount(3, $this->rental_lines(), 'Other dates and other vehicles are separate rentals.');
		$this->assertSame(0, wc_notice_count('notice'));
	}

	public function test_back_to_back_rentals_of_one_vehicle_do_not_replace_each_other(): void
	{
		// Return at 10:00 on the 4th, the next pickup at 10:00 on the 4th: no overlap.
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04'), 200.0);
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-04', '2027-03-06'), 200.0);

		$this->assertCount(2, $this->rental_lines());
	}

	public function test_a_cart_clashing_with_itself_says_so_not_another_customer(): void
	{
		// A cart built before this fix (a saved session) can still hold both lines.
		$this->force_line($this->rental('2027-03-02', '2027-03-04'));
		$this->force_line($this->rental('2027-03-03', '2027-03-05'));

		WooCommerceBridge::validate_checkout_availability();

		$this->assertSame(1, wc_notice_count('error'), 'The clash stops checkout once.');
		$message = $this->first_error();
		$this->assertStringContainsString('more than once', $message);
		$this->assertStringNotContainsString('another customer', $message);
	}

	public function test_a_lost_race_is_one_message_and_the_line_stays_until_the_cart_is_seen(): void
	{
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04'), 200.0);
		// Another customer's booking lands meanwhile.
		$this->confirmed_booking('2027-03-01', '2027-03-05');

		// WC_Checkout::process_checkout(): woocommerce_checkout_process, then
		// validate_checkout() -> check_cart_items() -> woocommerce_check_cart_items.
		add_action('woocommerce_checkout_process', array( WooCommerceBridge::class, 'validate_checkout_availability' ));
		do_action('woocommerce_checkout_process');
		WooCommerceBridge::validate_cart_availability();

		$this->assertSame(1, wc_notice_count('error'), 'One refusal, not two sentences saying the same.');
		$this->assertCount(1, $this->rental_lines(), 'The checkout request does not empty the cart behind the page.');

		// The cart page (a later request) removes the line and says why.
		wc_clear_notices();
		global $wp_actions;
		unset($wp_actions['woocommerce_checkout_process']);
		WooCommerceBridge::validate_cart_availability();

		$this->assertCount(0, $this->rental_lines());
		$this->assertSame(1, wc_notice_count('error'));
	}

	public function test_a_refused_add_keeps_the_line_it_would_have_replaced(): void
	{
		WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-02', '2027-03-04'), 200.0);

		// WooCommerce refuses the new line (add_to_cart() answers false).
		add_filter('woocommerce_is_purchasable', '__return_false');
		$added = WooCommerceBridge::add_booking_data_to_cart($this->rental('2027-03-03', '2027-03-06'), 300.0);
		remove_filter('woocommerce_is_purchasable', '__return_false');

		$this->assertFalse($added, 'A refused add is reported as one, not sent to an empty checkout.');
		$lines = $this->rental_lines();
		$this->assertCount(1, $lines, 'The earlier choice is not lost to a refused one.');
		$this->assertSame('2027-03-02', $lines[0]['mhmrentiva_booking_data']['pickup_date']);
		$this->assertSame(0, wc_notice_count('notice'), 'Nothing was replaced, so nothing is announced.');
	}

	public function test_a_line_removed_for_another_customer_does_not_take_its_neighbour(): void
	{
		// A saved cart: two overlapping lines of one vehicle; the first one's
		// dates were then taken by another customer, the second one's were not.
		// (A day apart from the other booking, clear of any turnaround buffer.)
		$this->force_line($this->rental('2027-03-01', '2027-03-04'));
		$this->force_line($this->rental('2027-03-03', '2027-03-06'));
		$this->confirmed_booking('2027-02-26', '2027-03-02');

		WooCommerceBridge::validate_cart_availability();

		$lines = $this->rental_lines();
		$this->assertCount(1, $lines, 'Only the refused line goes.');
		$this->assertSame('2027-03-03', $lines[0]['mhmrentiva_booking_data']['pickup_date']);
	}

	private function vehicle( string $title ): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => $title,
		));
		update_post_meta($id, '_mhmrentiva_price_per_day', '100');
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');

		return $id;
	}

	private function confirmed_booking( string $pickup, string $dropoff ): void
	{
		$start = (int) SiteTime::instant($pickup, '10:00');
		$end   = (int) SiteTime::instant($dropoff, '10:00');
		$id    = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));

		update_post_meta($id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($id, '_mhmrentiva_vehicle_id', $this->vehicle_id);
		update_post_meta($id, '_mhmrentiva_pickup_date', $pickup);
		update_post_meta($id, '_mhmrentiva_pickup_time', '10:00');
		update_post_meta($id, '_mhmrentiva_dropoff_date', $dropoff);
		update_post_meta($id, '_mhmrentiva_dropoff_time', '10:00');
		update_post_meta($id, '_mhmrentiva_start_ts', $start);
		update_post_meta($id, '_mhmrentiva_end_ts', $end);
	}

	/** A line put in the cart directly, past add_booking_data_to_cart(). */
	private function force_line( array $rental ): void
	{
		$this->assertNotFalse(WC()->cart->add_to_cart(
			WooCommerceBridge::get_booking_product_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $rental,
				'mhmrentiva_booking_price'   => 200.0,
				'mhmrentiva_booking_pending' => true,
			)
		), 'Fixture: the line reached the cart.');
	}

	/** @return array<int, array<string, mixed>> */
	private function rental_lines(): array
	{
		return array_values(array_filter(
			WC()->cart->get_cart(),
			static fn( $item ): bool => ! empty($item['mhmrentiva_booking_pending'])
		));
	}

	private function line_prices_total(): float
	{
		return array_sum(array_map(static fn( $item ): float => (float) $item['mhmrentiva_booking_price'] * (int) $item['quantity'], $this->rental_lines()));
	}

	private function first_error(): string
	{
		$first = wc_get_notices('error')[0] ?? array();

		return (string) ( is_array($first) ? ( $first['notice'] ?? '' ) : $first );
	}

	/** @return array<string, mixed> */
	private function rental( string $pickup, string $dropoff, ?int $vehicle_id = null ): array
	{
		return array(
			'vehicle_id'          => $vehicle_id ?? $this->vehicle_id,
			'pickup_date'         => $pickup,
			'pickup_time'         => '10:00',
			'dropoff_date'        => $dropoff,
			'dropoff_time'        => '10:00',
			'guests'              => 1,
			'customer_user_id'    => 0,
			'customer_name'       => 'Jane Customer',
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Customer',
			'customer_email'      => 'jane@example.com',
			'customer_phone'      => '5551234567',
			'payment_type'        => 'full',
			'payment_method'      => 'woocommerce',
			'total_price'         => 200,
			'rental_days'         => 2,
			'selected_addons'     => array(),
		);
	}
}
