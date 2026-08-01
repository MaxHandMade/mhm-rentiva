<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Correction of finding H1 (whole-branch audit, second pass): the first fix
 * fired `mhmrentiva_before_booking_creation` only from
 * Handler::handle() (admin_post_mhmrentiva_booking), but a deeper trace
 * showed that entry point has NO live UI trigger — no template or JS ever
 * posts to admin-post.php?action=mhmrentiva_booking. The real customer
 * booking-creation flow is:
 *
 *   [rentiva_booking_form] shortcode -> AJAX mhmrentiva_booking_form
 *   -> BookingForm::ajax_booking_form() -> WooCommerceBridge::add_booking_to_payment()
 *   -> cart -> WC checkout -> woocommerce_checkout_order_processed
 *   -> WooCommerceBridge::create_booking_from_order()
 *   -> create_booking_from_data() -> wp_insert_post() (~line 1172)
 *
 * so the GDPR consent hook (Pro's GDPRManager::check_booking_consent(),
 * subscribed to this same action) was still dead for every real booking.
 *
 * Fix: create_booking_from_data() now fires
 * `do_action('mhmrentiva_before_booking_creation', $payload)` immediately
 * before the `wp_insert_post()` call that creates the booking post, using
 * the same neutral payload key shape as Handler.php:92 (vehicle_id,
 * pickup_date/time, dropoff_date/time, contact_name/email/phone,
 * selected_addons) plus order_id for checkout context.
 *
 * create_booking_from_data() is private and the test suite does not load
 * WooCommerce, so this test reaches it directly via reflection (same
 * pattern used elsewhere in this suite, e.g.
 * Integration/Admin/Services/CompareServiceTest.php) rather than going
 * through create_booking_from_order(), which short-circuits on
 * `! class_exists('WooCommerce')`. create_booking_from_data() itself has no
 * hard WooCommerce dependency on the non-overlap happy path exercised here.
 */
final class CreateBookingFromDataBeforeBookingCreationHookTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Test Vehicle',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', 100);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function valid_booking_data(): array
	{
		return array(
			'vehicle_id'             => $this->vehicle_id,
			'pickup_date'            => gmdate('Y-m-d', strtotime('+1 day')),
			'pickup_time'            => '10:00',
			'dropoff_date'           => gmdate('Y-m-d', strtotime('+3 days')),
			'dropoff_time'           => '10:00',
			'guests'                 => 2,
			'customer_user_id'       => 0,
			'customer_name'          => 'Jane Customer',
			'customer_first_name'    => 'Jane',
			'customer_last_name'     => 'Customer',
			'customer_email'         => 'jane@example.com',
			'customer_phone'         => '5551234567',
			'payment_type'           => 'deposit',
			'payment_method'         => 'woocommerce',
			'payment_gateway'        => '',
			'deposit_amount'         => 50,
			'remaining_amount'       => 150,
			'deposit_type'           => 'percentage',
			'payment_display'        => 'Deposit',
			'total_price'            => 200,
			'rental_days'            => 2,
			'selected_addons'        => array(),
			'cancellation_policy'    => 'flexible',
			'cancellation_deadline'  => gmdate('Y-m-d H:i:s', strtotime('+24 hours')),
			'payment_deadline'       => gmdate('Y-m-d H:i:s', strtotime('+48 hours')),
		);
	}

	private function invoke_create_booking_from_data(array $booking_data, int $order_id): ?int
	{
		$method = new ReflectionMethod(WooCommerceBridge::class, 'create_booking_from_data');
		$method->setAccessible(true);

		return $method->invoke(null, $booking_data, $order_id);
	}

	/**
	 * RED with the pre-correction code (hook only in Handler::handle()):
	 * create_booking_from_data() never fires the hook, so did_action() never
	 * increments no matter what real checkout data is supplied.
	 *
	 * GREEN with the fix: create_booking_from_data() fires the hook once,
	 * with a booking-context payload, strictly before wp_insert_post()
	 * creates the booking post.
	 */
	public function test_create_booking_from_data_fires_before_booking_creation_hook_before_insert(): void
	{
		$booking_data = $this->valid_booking_data();

		$before = did_action('mhmrentiva_before_booking_creation');

		$captured        = null;
		$post_existed_at_hook_time = null;
		add_action('mhmrentiva_before_booking_creation', function ($payload) use (&$captured, &$post_existed_at_hook_time) {
			$captured = $payload;
			// Prove ordering: no vehicle_booking post exists yet for this
			// customer email at the moment the hook fires.
			$post_existed_at_hook_time = get_posts(array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'any',
				'meta_key'    => '_mhmrentiva_customer_email',
				'meta_value'  => 'jane@example.com',
				'fields'      => 'ids',
			));
		});

		$booking_id = $this->invoke_create_booking_from_data($booking_data, 999);

		$this->assertNotNull($booking_id, 'Sanity check: booking creation must succeed in this happy-path scenario.');

		$this->assertSame(
			$before + 1,
			did_action('mhmrentiva_before_booking_creation'),
			'create_booking_from_data() must fire mhmrentiva_before_booking_creation exactly once on the live checkout path.'
		);

		$this->assertIsArray($captured, 'Subscriber must receive the booking context payload.');
		$this->assertSame($this->vehicle_id, $captured['vehicle_id']);
		$this->assertSame('jane@example.com', $captured['contact_email']);
		$this->assertSame('Jane Customer', $captured['contact_name']);
		$this->assertSame(999, $captured['order_id']);

		$this->assertSame(
			array(),
			$post_existed_at_hook_time,
			'The booking post must not exist yet at the moment the hook fires (hook runs before wp_insert_post()).'
		);
	}

	/**
	 * A subscriber that halts the request (wp_die()-equivalent here, a
	 * thrown exception used as the test-safe stand-in) must prevent the
	 * booking post from ever being written — proving the hook is truly
	 * "before booking creation", not merely "notified during".
	 */
	public function test_halting_subscriber_prevents_booking_post_from_being_created(): void
	{
		$booking_data = $this->valid_booking_data();

		add_action('mhmrentiva_before_booking_creation', function () {
			throw new \RuntimeException('halted:consent_required');
		});

		$before_count = wp_count_posts('mhmrentiva_booking')->publish ?? 0;

		try {
			$this->invoke_create_booking_from_data($booking_data, 999);
			$this->fail('Expected the subscriber to halt execution before booking creation.');
		} catch (\RuntimeException $e) {
			$this->assertSame('halted:consent_required', $e->getMessage());
		}

		$after_count = wp_count_posts('mhmrentiva_booking')->publish ?? 0;
		$this->assertSame($before_count, $after_count, 'No booking post may be created when a subscriber halts on mhmrentiva_before_booking_creation.');
	}

	/**
	 * Safe-by-default proof: with no subscriber attached (the real shipped
	 * state unless Pro's GDPR + consent-required settings are both
	 * explicitly turned on), firing the hook is inert and booking creation
	 * proceeds normally.
	 */
	public function test_before_booking_creation_hook_is_a_no_op_with_no_subscribers(): void
	{
		$booking_data = $this->valid_booking_data();

		$booking_id = $this->invoke_create_booking_from_data($booking_data, 999);

		$this->assertIsInt($booking_id);
		$this->assertGreaterThan(0, $booking_id);
		$this->assertSame('publish', get_post_status($booking_id));
	}
}
