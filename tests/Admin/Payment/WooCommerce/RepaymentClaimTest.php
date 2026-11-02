<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR K final fixes, Codex 1 and 2: a booking id on the order is not enough.
 *
 * 1. A failed payment cancels the booking; another customer books the dates;
 *    the first order is paid again. Every path that can take that money --
 *    the Store API retry, the classic order-pay form, a classic checkout that
 *    resumes the order, a status change that confirms -- asks under the
 *    vehicle's lock whether the booking still holds its dates.
 * 2. A retry of the same order does not collide with its own booking.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class RepaymentClaimTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	private int $vehicle_id;

	/** @var list<string> START TRANSACTION / COMMIT / ROLLBACK, in order. */
	private array $transactions = array();

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();
		$this->product    = $this->ensure_booking_product('100');
		$this->vehicle_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Fiat Egea' ));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', 100);

		wc_load_cart();
		WC()->cart->empty_cart();
		wc_clear_notices();

		// As LockerRobustnessTest: a nested START TRANSACTION would commit the
		// test's own transaction, so transaction control is neutralised.
		$this->transactions = array();
		add_filter('query', function ($query) {
			if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query, $m)) {
				$this->transactions[] = strtoupper($m[1]);
				return 'SELECT 1';
			}
			return $query;
		});

		add_action('woocommerce_store_api_checkout_order_processed', array( WooCommerceBridge::class, 'store_api_order_processed' ), 10, 1);
		add_action('woocommerce_checkout_order_processed', array( WooCommerceBridge::class, 'create_booking_from_order' ), 10, 3);
	}

	public function tearDown(): void
	{
		remove_action('woocommerce_store_api_checkout_order_processed', array( WooCommerceBridge::class, 'store_api_order_processed' ), 10);
		remove_action('woocommerce_checkout_order_processed', array( WooCommerceBridge::class, 'create_booking_from_order' ), 10);
		if (function_exists('WC') && WC()->cart) {
			WC()->cart->empty_cart();
		}
		if (function_exists('WC') && WC()->session) {
			WC()->session->set('order_awaiting_payment', null);
			WC()->session->set('store_api_draft_order', null);
		}
		wc_clear_notices();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function snapshot(array $extra = array()): array
	{
		return array_merge(
			array(
				'vehicle_id'          => $this->vehicle_id,
				'pickup_date'         => '2031-03-10',
				'pickup_time'         => '10:00',
				'dropoff_date'        => '2031-03-13',
				'dropoff_time'        => '10:00',
				'guests'              => 2,
				'customer_user_id'    => 0,
				'customer_name'       => 'Jane Customer',
				'customer_first_name' => 'Jane',
				'customer_last_name'  => 'Customer',
				'customer_email'      => 'jane@example.com',
				'customer_phone'      => '5551234567',
				'payment_type'        => 'deposit',
				'deposit_amount'      => 200.0,
				'remaining_amount'    => 800.0,
				'payment_display'     => 'Deposit',
				'total_price'         => 1000.0,
				'rental_days'         => 3,
				'selected_addons'     => array(),
			),
			$extra
		);
	}

	/**
	 * @param array<string, mixed> $snapshot
	 */
	private function pending_line(\WC_Order $order, array $snapshot): void
	{
		$item = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_subtotal(200.0);
		$item->set_total(200.0);
		$item->add_meta_data('_mhmrentiva_booking_data', $snapshot);
		$item->add_meta_data('_mhmrentiva_booking_pending', true);
		$order->add_item($item);
	}

	private function pending_order(): \WC_Order
	{
		$order = wc_create_order();
		$this->pending_line($order, $this->snapshot());
		$order->calculate_totals();
		$order->set_status('pending');
		$order->save();

		return $order;
	}

	private function store_api(\WC_Order $order): void
	{
		do_action('woocommerce_store_api_checkout_order_processed', $order);
	}

	/**
	 * An order whose first payment failed: booked, then the failure cancelled
	 * the booking (handle_order_status_change(), `failed`).
	 *
	 * @return array{0: \WC_Order, 1: int}
	 */
	private function failed_order(): array
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');
		$this->assertGreaterThan(0, $booking_id);

		$order->update_status('failed');
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'pending', 'failed', $order);
		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));

		return array( wc_get_order($order->get_id()), $booking_id );
	}

	/**
	 * Another customer takes the same dates.
	 */
	private function someone_else_books(): int
	{
		$other = $this->pending_order();
		$this->store_api($other);
		$booking_id = (int) wc_get_order($other->get_id())->get_meta('_mhmrentiva_booking_id');
		$this->assertGreaterThan(0, $booking_id, 'A cancelled booking holds nothing.');

		return $booking_id;
	}

	private function bookings_of_vehicle(): int
	{
		$ids = get_posts(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_mhmrentiva_vehicle_id',
				'meta_value'  => $this->vehicle_id,
			)
		);

		return count($ids);
	}

	/**
	 * @return list<string>
	 */
	private function notes(\WC_Order $order): array
	{
		return array_map(static fn($n) => (string) $n->content, wc_get_order_notes(array( 'order_id' => $order->get_id() )));
	}

	// ------------------------------------------------- Codex 1: double booking

	public function test_failed_then_booked_by_another_then_paid_again_on_the_store_api_is_stopped(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		try {
			$this->store_api($order);
			$this->fail('POST /checkout/{id} must stop before the gateway.');
		} catch (RouteException $e) {
			$this->assertSame(400, $e->getCode());
			$this->assertStringContainsString('Fiat Egea', $e->getMessage());
		}

		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true), 'Not revived over the other booking.');
		$this->assertNotEmpty(array_filter($this->notes($order), static fn($n) => str_contains($n, 'Booking refused')), 'The order says why it was not paid.');
	}

	public function test_failed_then_paid_again_with_the_dates_still_free_takes_them_back(): void
	{
		list($order, $booking_id) = $this->failed_order();

		$this->store_api($order);

		$this->assertSame('pending', get_post_meta($booking_id, '_mhmrentiva_status', true));
		$this->assertGreaterThan(current_time('mysql', true), (string) get_post_meta($booking_id, '_mhmrentiva_payment_deadline', true), 'A fresh deadline: the dates are held again.');
		$this->assertSame(1, $this->bookings_of_vehicle(), 'No second booking.');

		WooCommerceBridge::handle_order_status_change($order->get_id(), 'failed', 'processing', $order);
		$this->assertSame('confirmed', get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	public function test_the_classic_order_pay_form_is_stopped_before_the_gateway(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		WooCommerceBridge::claim_before_pay($order);

		$this->assertSame(1, wc_notice_count('error'), 'WC_Form_Handler::pay_action() pays only while there is no error notice.');
		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	public function test_the_classic_order_pay_form_passes_a_booking_that_holds_its_dates(): void
	{
		$order = $this->pending_order();
		$this->store_api($order);

		WooCommerceBridge::claim_before_pay(wc_get_order($order->get_id()));

		$this->assertSame(0, wc_notice_count('error'));
	}

	public function test_a_classic_checkout_resuming_the_failed_order_is_stopped(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		// WC_Checkout::create_order() resumes a pending|failed order: every line
		// removed and added again from the cart, the order meta left as it was.
		$order->remove_order_items();
		$this->pending_line($order, $this->snapshot());
		$order->save();

		try {
			do_action('woocommerce_checkout_order_processed', $order->get_id(), array(), $order);
			$this->fail('The request must stop: WC_Checkout turns this into a notice, before the gateway.');
		} catch (\Exception $e) {
			$this->assertStringContainsString('Fiat Egea', $e->getMessage());
		}

		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
		$this->assertSame(2, $this->bookings_of_vehicle(), 'No third booking.');
	}

	public function test_a_payment_landing_on_a_booking_that_lost_its_dates_confirms_nothing(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		// A gateway's late callback, or an operator marking the order paid.
		$order->update_status('processing');
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'failed', 'processing', $order);

		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true), 'Not confirmed over the other booking.');
		$this->assertNotEmpty(array_filter($this->notes(wc_get_order($order->get_id())), static fn($n) => str_contains($n, 'was not confirmed')), 'The operator is told to return the money.');
	}

	public function test_a_pending_booking_past_its_deadline_is_asked_too(): void
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');

		// Past its payment deadline it holds nothing (BookingConflicts PAYMENT_SQL).
		update_post_meta($booking_id, '_mhmrentiva_payment_deadline', gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS));
		$this->someone_else_books();

		WooCommerceBridge::claim_before_pay($order);

		$this->assertSame(1, wc_notice_count('error'));
	}

	public function test_a_remaining_payment_never_revives_a_cancelled_booking(): void
	{
		list($order, $booking_id) = $this->failed_order();

		$remaining = wc_create_order();
		$item      = new \WC_Order_Item_Product();
		$item->set_product($this->product);
		$item->set_quantity(1);
		$item->set_total(800.0);
		$item->add_meta_data('_mhmrentiva_booking_id', $booking_id, true);
		$remaining->add_item($item);
		$remaining->update_meta_data('_mhmrentiva_booking_id', $booking_id);
		$remaining->update_meta_data('_mhmrentiva_is_remaining_payment', '1');
		$remaining->calculate_totals();
		$remaining->save();

		WooCommerceBridge::claim_before_pay($remaining);

		$this->assertSame(1, wc_notice_count('error'), 'Free dates or not: the deposit side of that money is not known.');
		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	// ------------------------------------------------ Codex 2: self conflict

	/**
	 * The cart line of a checkout retry: the gateway threw after the booking
	 * was made, the order stays pending and the line still says pending.
	 *
	 * @return array{0: \WC_Order, 1: int, 2: string}
	 */
	private function retried_checkout(string $session_key): array
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$booking_id = (int) wc_get_order($order->get_id())->get_meta('_mhmrentiva_booking_id');

		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $this->snapshot(),
				'mhmrentiva_booking_price'   => 200.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString($key);
		WC()->session->set($session_key, $order->get_id());

		return array( wc_get_order($order->get_id()), $booking_id, $key );
	}

	public function test_a_store_api_retry_does_not_collide_with_its_own_booking(): void
	{
		$this->retried_checkout('store_api_draft_order');

		$errors = new \WP_Error();
		WooCommerceBridge::store_api_cart_errors($errors, WC()->cart);

		$this->assertFalse($errors->has_errors(), 'The customer is not refused their own booking.');
	}

	public function test_a_classic_retry_does_not_collide_with_its_own_booking(): void
	{
		list(, , $key) = $this->retried_checkout('order_awaiting_payment');

		WooCommerceBridge::validate_checkout_availability();
		$this->assertSame(0, wc_notice_count('error'));

		WooCommerceBridge::validate_cart_availability();
		$this->assertSame(0, wc_notice_count('error'));
		$this->assertArrayHasKey($key, WC()->cart->get_cart(), 'The line is not removed from the cart.');
	}

	public function test_a_retry_still_collides_with_someone_elses_booking(): void
	{
		// The other order's booking first, then a retry of an order that made
		// none: only the retried order's own booking is left out.
		$this->store_api($this->pending_order());

		$mine = wc_create_order();
		$mine->set_status('pending');
		$mine->save();
		WC()->session->set('store_api_draft_order', $mine->get_id());
		WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $this->snapshot(),
				'mhmrentiva_booking_price'   => 200.0,
				'mhmrentiva_booking_pending' => true,
			)
		);

		$errors = new \WP_Error();
		WooCommerceBridge::store_api_cart_errors($errors, WC()->cart);

		$this->assertTrue($errors->has_errors());
	}

	public function test_a_resumed_classic_order_relinks_its_own_booking(): void
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');

		$order->remove_order_items();
		$this->pending_line($order, $this->snapshot());
		$order->save();

		do_action('woocommerce_checkout_order_processed', $order->get_id(), array(), $order);

		$order = wc_get_order($order->get_id());
		$item  = current($order->get_items());
		$this->assertSame($booking_id, (int) $item->get_meta('_mhmrentiva_booking_id'), 'The fresh line carries the booking, so the status handler finds it.');
		$this->assertFalse((bool) $item->get_meta('_mhmrentiva_booking_pending'));
		$this->assertSame(1, $this->bookings_of_vehicle());
		$this->assertSame(Status::PENDING, get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	// ------------------------------------- Codex delta H1: deadline race

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function paid_arms(): array
	{
		return array(
			'processing' => array( 'processing' ),
			'completed'  => array( 'completed' ),
		);
	}

	/**
	 * A's payment arrives one tick before its deadline. Between the arrival and
	 * the confirm write the deadline runs out and B takes the dates (another
	 * request: it runs at the last moment before A's handler touches the
	 * database for the booking -- before A takes the vehicle's lock, or, without
	 * one, before A writes its status). Only one of them may hold the dates.
	 *
	 * @dataProvider paid_arms
	 */
	public function test_a_deadline_running_out_during_the_payment_cannot_confirm_over_another(string $arm): void
	{
		$a = $this->pending_order();
		$this->store_api($a);
		$a       = wc_get_order($a->get_id());
		$booking = (int) $a->get_meta('_mhmrentiva_booking_id');
		$this->assertGreaterThan(current_time('mysql', true), (string) get_post_meta($booking, '_mhmrentiva_payment_deadline', true), 'A holds its dates when the payment arrives.');

		global $wpdb;
		$vehicle = $this->vehicle_id;
		$fired   = false;
		$other   = 0;
		$race    = function ($query) use (&$fired, &$other, $vehicle, $booking, $wpdb) {
			$sql      = (string) $query;
			$locks    = (bool) preg_match('/FOR UPDATE/i', $sql) && str_contains($sql, 'post_id = ' . $vehicle);
			$confirms = (bool) preg_match('/^\s*(UPDATE|INSERT)/i', $sql) && str_contains($sql, $wpdb->postmeta) && str_contains($sql, '_mhmrentiva_status') && str_contains($sql, (string) $booking);
			if ($fired || ( ! $locks && ! $confirms )) {
				return $query;
			}
			$fired = true;

			// The deadline runs out, and B books meanwhile.
			update_post_meta($booking, '_mhmrentiva_payment_deadline', gmdate('Y-m-d H:i:s', time() - 1));
			wp_cache_delete($booking, 'post_meta');
			$b = $this->pending_order();
			try {
				$this->store_api($b);
				$other = (int) wc_get_order($b->get_id())->get_meta('_mhmrentiva_booking_id');
			} catch (RouteException $e) {
				$other = 0;
			}

			return $query;
		};
		add_filter('query', $race);

		$a->set_status($arm);
		$a->save();
		WooCommerceBridge::handle_order_status_change($a->get_id(), 'pending', $arm, wc_get_order($a->get_id()));
		remove_filter('query', $race);

		$this->assertTrue($fired, 'The race ran.');
		wp_cache_delete($booking, 'post_meta');
		$a_holds = in_array(get_post_meta($booking, '_mhmrentiva_status', true), array( 'confirmed', 'completed' ), true);
		$b_holds = $other > 0 && 'pending' === get_post_meta($other, '_mhmrentiva_status', true);
		$this->assertFalse($a_holds && $b_holds, 'Never both: A=' . get_post_meta($booking, '_mhmrentiva_status', true) . ', B=' . ( $other > 0 ? get_post_meta($other, '_mhmrentiva_status', true) : 'refused' ));
		$this->assertTrue($a_holds || $b_holds, 'One of them holds the dates.');
		if (! $a_holds) {
			$this->assertNotEmpty(array_filter($this->notes(wc_get_order($a->get_id())), static fn($n) => str_contains($n, 'Refund the payment in WooCommerce')));
		}
	}

	// ----------------------------- Codex delta H2: unreadable is not free

	/**
	 * Make the locked COUNT, or the vehicle's row lock, fail.
	 */
	private function failing(string $what): callable
	{
		global $wpdb;
		$vehicle = $this->vehicle_id;
		$wpdb->suppress_errors(true);

		return static function ($query) use ($what, $vehicle, $wpdb) {
			$sql = (string) $query;
			if ('count' === $what && preg_match('/^\s*SELECT COUNT\(\*\)/i', $sql) && str_contains($sql, 'pm_vid')) {
				return 'SELECT * FROM mhmrentiva_no_such_table';
			}
			if ('lock' === $what && preg_match('/FOR UPDATE/i', $sql) && str_contains($sql, $wpdb->posts) && str_contains($sql, 'ID = ' . $vehicle)) {
				return 'SELECT * FROM mhmrentiva_no_such_table';
			}

			return $query;
		};
	}

	public function test_an_unreadable_count_reads_as_taken(): void
	{
		$fail = $this->failing('count');
		add_filter('query', $fail);
		$taken = \MHMRentiva\Admin\Booking\Helpers\Util::has_overlap_locked($this->vehicle_id, time() + DAY_IN_SECONDS, time() + 2 * DAY_IN_SECONDS);
		remove_filter('query', $fail);

		$this->assertTrue($taken, 'Util.php: a null count is never "available".');
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function failures(): array
	{
		return array(
			'count' => array( 'count' ),
			'lock'  => array( 'lock' ),
		);
	}

	/**
	 * @dataProvider failures
	 */
	public function test_a_failure_to_check_refuses_every_payment_path(string $what): void
	{
		list($order, $booking_id) = $this->failed_order();
		$fail                     = $this->failing($what);
		add_filter('query', $fail);

		// Order-pay: an error notice, so WC_Form_Handler::pay_action() never calls the gateway.
		WooCommerceBridge::claim_before_pay($order);
		$notices = wc_get_notices('error');

		// Store API: the request stops before the gateway.
		$stopped = false;
		try {
			$this->store_api(wc_get_order($order->get_id()));
		} catch (RouteException $e) {
			$stopped = true;
		}

		// A payment landing confirms nothing.
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'failed', 'processing', wc_get_order($order->get_id()));
		remove_filter('query', $fail);

		$this->assertCount(1, $notices);
		$this->assertStringContainsString('could not be checked', (string) $notices[0]['notice']);
		$this->assertTrue($stopped);
		wp_cache_delete($booking_id, 'post_meta');
		$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true), 'Stays cancelled.');
	}

	public function test_a_new_booking_is_not_made_on_an_unreadable_count(): void
	{
		$fail = $this->failing('count');
		add_filter('query', $fail);
		try {
			$this->store_api($this->pending_order());
			$this->fail('The checkout must stop.');
		} catch (RouteException $e) {
			$this->assertSame(400, $e->getCode());
		}
		remove_filter('query', $fail);

		$this->assertSame(0, $this->bookings_of_vehicle());
	}

	// ----------------------------------- Codex delta advice: all or nothing

	public function test_a_refused_later_booking_puts_the_earlier_revival_back(): void
	{
		$second_vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Renault Clio' ));

		// One order, two rentals; the payment failed and cancelled both.
		$order = wc_create_order();
		$this->pending_line($order, $this->snapshot());
		$this->pending_line($order, $this->snapshot(array( 'vehicle_id' => $second_vehicle )));
		$order->calculate_totals();
		$order->set_status('pending');
		$order->save();
		$this->store_api($order);
		$order = wc_get_order($order->get_id());
		$ids   = array();
		foreach ($order->get_items() as $item) {
			$ids[] = (int) $item->get_meta('_mhmrentiva_booking_id');
		}
		$order->update_status('failed');
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'pending', 'failed', $order);
		$deadline = (string) get_post_meta($ids[0], '_mhmrentiva_payment_deadline', true);

		// Someone takes the second vehicle's dates only.
		$other = wc_create_order();
		$this->pending_line($other, $this->snapshot(array( 'vehicle_id' => $second_vehicle )));
		$other->calculate_totals();
		$other->save();
		$this->store_api($other);

		WooCommerceBridge::claim_before_pay(wc_get_order($order->get_id()));

		$this->assertSame(1, wc_notice_count('error'));
		wp_cache_delete($ids[0], 'post_meta');
		$this->assertSame('cancelled', get_post_meta($ids[0], '_mhmrentiva_status', true), 'The first booking is not left revived.');
		$this->assertSame($deadline, (string) get_post_meta($ids[0], '_mhmrentiva_payment_deadline', true));
		$this->assertSame('cancelled', get_post_meta($ids[1], '_mhmrentiva_status', true));
	}

	// ------------------------------------ Fable delta M1: closed bookings

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function closed_bookings(): array
	{
		return array(
			'no-show, completed'  => array( 'no_show', 'completed' ),
			'no-show, processing' => array( 'no_show', 'processing' ),
			'refunded, completed' => array( 'refunded', 'completed' ),
		);
	}

	/**
	 * @dataProvider closed_bookings
	 */
	public function test_closing_the_order_of_a_closed_booking_tells_nobody_to_refund(string $status, string $arm): void
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');
		update_post_meta($booking_id, '_mhmrentiva_status', $status);

		WooCommerceBridge::handle_order_status_change($order->get_id(), 'on-hold', $arm, $order);

		$this->assertSame($status, get_post_meta($booking_id, '_mhmrentiva_status', true), 'The status table answers, as before.');
		$this->assertSame(array(), array_filter($this->notes(wc_get_order($order->get_id())), static fn($n) => str_contains($n, 'was not confirmed')), 'No refund instruction.');
	}

	public function test_an_on_hold_refusal_does_not_say_a_payment_was_recorded(): void
	{
		list($order, $booking_id) = $this->failed_order();
		$this->someone_else_books();

		WooCommerceBridge::handle_order_status_change($order->get_id(), 'failed', 'on-hold', $order);

		$notes = $this->notes(wc_get_order($order->get_id()));
		$this->assertNotEmpty(array_filter($notes, static fn($n) => str_contains($n, 'Order on hold, but booking #' . $booking_id)));
		$this->assertSame(array(), array_filter($notes, static fn($n) => str_contains($n, 'Payment recorded')));
	}

	// --------------------------- Codex delta2 H1: undo keeps a later payment

	/**
	 * One order, two rentals, both cancelled by a failed payment; the second
	 * vehicle's dates are then taken by someone else.
	 *
	 * @return array{0: \WC_Order, 1: list<int>, 2: int}
	 */
	private function two_rental_failed_order(): array
	{
		$second_vehicle = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Renault Clio' ));

		$order = wc_create_order();
		$this->pending_line($order, $this->snapshot());
		$this->pending_line($order, $this->snapshot(array( 'vehicle_id' => $second_vehicle )));
		$order->calculate_totals();
		$order->set_status('pending');
		$order->save();
		$this->store_api($order);
		$order = wc_get_order($order->get_id());
		$ids   = array();
		foreach ($order->get_items() as $item) {
			$ids[] = (int) $item->get_meta('_mhmrentiva_booking_id');
		}
		$order->update_status('failed');
		WooCommerceBridge::handle_order_status_change($order->get_id(), 'pending', 'failed', $order);

		$other = wc_create_order();
		$this->pending_line($other, $this->snapshot(array( 'vehicle_id' => $second_vehicle )));
		$other->calculate_totals();
		$other->save();
		$this->store_api($other);

		return array( wc_get_order($order->get_id()), $ids, $second_vehicle );
	}

	public function test_undoing_a_revival_keeps_a_payment_that_landed_meanwhile(): void
	{
		list($order, $ids, $second_vehicle) = $this->two_rental_failed_order();

		// While the second booking is being checked, a payment notification
		// confirms the first one (it was just revived).
		$fired  = false;
		$notify = static function ($query) use (&$fired, $second_vehicle, $ids) {
			$sql = (string) $query;
			if (! $fired && preg_match('/FOR UPDATE/i', $sql) && str_contains($sql, 'post_id = ' . $second_vehicle)) {
				$fired = true;
				update_post_meta($ids[0], '_mhmrentiva_status', 'confirmed');
				update_post_meta($ids[0], '_mhmrentiva_payment_status', 'paid');
			}
			return $query;
		};
		add_filter('query', $notify);
		WooCommerceBridge::claim_before_pay($order);
		remove_filter('query', $notify);

		$this->assertTrue($fired);
		$this->assertSame(1, wc_notice_count('error'), 'The second booking is refused.');
		wp_cache_delete($ids[0], 'post_meta');
		$this->assertSame('confirmed', get_post_meta($ids[0], '_mhmrentiva_status', true), 'The payment that landed is kept, not put back to cancelled.');
		$this->assertSame('paid', get_post_meta($ids[0], '_mhmrentiva_payment_status', true));
	}

	public function test_a_throw_while_checking_a_later_booking_puts_the_earlier_revival_back(): void
	{
		list($order, $ids, $second_vehicle) = $this->two_rental_failed_order();

		$boom = static function ($query) use ($second_vehicle) {
			if (preg_match('/FOR UPDATE/i', (string) $query) && str_contains((string) $query, 'post_id = ' . $second_vehicle)) {
				throw new \LogicException('mhmrentiva_test_boom');
			}
			return $query;
		};
		add_filter('query', $boom);
		try {
			WooCommerceBridge::claim_before_pay($order);
			$this->fail('The error must reach the caller.');
		} catch (\LogicException $e) {
			$this->assertSame('mhmrentiva_test_boom', $e->getMessage());
		}
		remove_filter('query', $boom);

		wp_cache_delete($ids[0], 'post_meta');
		$this->assertSame('cancelled', get_post_meta($ids[0], '_mhmrentiva_status', true), 'Fable delta2 m1: the first revival is put back.');
	}

	// ------------------------- Codex delta2 H2: writes that do not hold

	/**
	 * Make one meta write of one booking fail, as a database error would:
	 * nothing is written and update_post_meta() answers false.
	 */
	private function failing_write(int $booking_id, string $key, ?string $only_value = null): callable
	{
		return static function ($check, $object_id, $meta_key, $meta_value) use ($booking_id, $key, $only_value) {
			if ( (int) $object_id === $booking_id && $meta_key === $key && ( null === $only_value || (string) $meta_value === $only_value )) {
				return false;
			}
			return $check;
		};
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function claim_writes(): array
	{
		return array(
			'status'   => array( '_mhmrentiva_status' ),
			'deadline' => array( '_mhmrentiva_payment_deadline' ),
		);
	}

	/**
	 * @dataProvider claim_writes
	 */
	public function test_a_revival_write_that_does_not_hold_refuses_and_rolls_back(string $key): void
	{
		list($order, $booking_id) = $this->failed_order();
		// An older deadline than the fresh one the revival writes (made in the
		// same second, they would be the same string: "already that value").
		update_post_meta($booking_id, '_mhmrentiva_payment_deadline', gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS));
		$announced = 0;
		$count                    = static function () use (&$announced): void {
			++$announced;
		};
		add_action('mhmrentiva_booking_status_changed', $count);
		$fail = $this->failing_write($booking_id, $key);
		add_filter('update_post_metadata', $fail, 10, 4);
		$this->transactions = array();

		WooCommerceBridge::claim_before_pay($order);

		remove_filter('update_post_metadata', $fail, 10);
		remove_action('mhmrentiva_booking_status_changed', $count);

		$notices = wc_get_notices('error');
		$this->assertCount(1, $notices);
		$this->assertStringContainsString('could not be updated', (string) $notices[0]['notice']);
		$this->assertContains('ROLLBACK', $this->transactions, 'The transaction is rolled back, not committed.');
		$this->assertNotContains('COMMIT', $this->transactions);
		$this->assertSame(0, $announced, 'Nobody hears of a revival that did not hold.');
		if ('_mhmrentiva_status' === $key) {
			wp_cache_delete($booking_id, 'post_meta');
			$this->assertSame('cancelled', get_post_meta($booking_id, '_mhmrentiva_status', true));
		}
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function confirm_writes(): array
	{
		return array(
			'status'         => array( '_mhmrentiva_status' ),
			'payment status' => array( '_mhmrentiva_payment_status' ),
		);
	}

	/**
	 * @dataProvider confirm_writes
	 */
	public function test_a_confirm_write_that_does_not_hold_confirms_nothing(string $key): void
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');

		$announced = 0;
		$count     = static function () use (&$announced): void {
			++$announced;
		};
		add_action('mhmrentiva_booking_status_changed', $count);
		$fail = $this->failing_write($booking_id, $key);
		add_filter('update_post_metadata', $fail, 10, 4);
		$this->transactions = array();

		WooCommerceBridge::handle_order_status_change($order->get_id(), 'pending', 'processing', $order);

		remove_filter('update_post_metadata', $fail, 10);
		remove_action('mhmrentiva_booking_status_changed', $count);

		$this->assertSame(0, $announced, 'No confirmation is published.');
		$this->assertContains('ROLLBACK', $this->transactions);
		$this->assertNotContains('COMMIT', $this->transactions);
		wp_cache_delete($booking_id, 'post_meta');
		$this->assertSame('pending', get_post_meta($booking_id, '_mhmrentiva_status', true));
		$this->assertNotEmpty(array_filter($this->notes(wc_get_order($order->get_id())), static fn($n) => str_contains($n, 'could not be updated')));
	}

	public function test_a_write_that_is_already_that_value_is_not_a_failure(): void
	{
		$order = $this->pending_order();
		$this->store_api($order);
		$order      = wc_get_order($order->get_id());
		$booking_id = (int) $order->get_meta('_mhmrentiva_booking_id');
		update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid');

		// update_post_meta() answers false for an unchanged value too.
		$this->assertFalse(update_post_meta($booking_id, '_mhmrentiva_payment_status', 'paid'));

		WooCommerceBridge::handle_order_status_change($order->get_id(), 'pending', 'processing', $order);

		wp_cache_delete($booking_id, 'post_meta');
		$this->assertSame('confirmed', get_post_meta($booking_id, '_mhmrentiva_status', true));
	}

	public function test_an_undo_write_that_does_not_hold_is_logged_and_leaves_no_payment(): void
	{
		list($order, $ids) = $this->two_rental_failed_order();
		$fail              = $this->failing_write($ids[0], '_mhmrentiva_status', 'cancelled');
		add_filter('update_post_metadata', $fail, 10, 4);

		WooCommerceBridge::claim_before_pay($order);

		remove_filter('update_post_metadata', $fail, 10);

		$this->assertSame(1, wc_notice_count('error'), 'Still refused: no payment is taken.');
		$this->assertContains('ROLLBACK', $this->transactions, 'The failed undo is rolled back.');
	}
}
