<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Core;

use MHMRentiva\Admin\Booking\Core\Handler;
use WP_UnitTestCase;

/**
 * Regression test for finding H1 (whole-branch audit): Pro's GDPR
 * booking-consent enforcement (GDPRManager::check_booking_consent(), Pro
 * src/Admin/Privacy/GDPRManager.php:83) subscribes to the
 * `mhmrentiva_before_booking_creation` action, but nothing in Lite ever
 * fired that action — so the consent gate was permanently dead.
 *
 * Fix: Handler::handle() (the frontend customer booking-submission entry
 * point — registered for admin_post_mhmrentiva_booking and
 * admin_post_nopriv_mhmrentiva_booking, i.e. the public booking form, never
 * the wp-admin manual booking meta box) now fires
 * `do_action('mhmrentiva_before_booking_creation', $booking_data)` as soon
 * as the required fields are validated, before any booking record is
 * created.
 *
 * This is a neutral Lite extension point (no "pro"/"gdpr" in the name) —
 * Pro's GDPRManager enforces on it only when GDPR + consent-required are
 * both explicitly enabled by the site admin (both default OFF), so firing it
 * is a no-op for the default install. GDPRManager's own branch behaviour
 * (wp_die on missing consent, early-return otherwise) is unit-tested in
 * Pro's own test suite — this test only proves Lite now fires the hook on
 * the frontend path, with the correct data, at the correct time (before
 * validate_payment_method()'s WooCommerce-only gate, so a subscriber can
 * veto the booking regardless of payment configuration).
 */
final class HandlerBeforeBookingCreationHookTest extends WP_UnitTestCase
{
	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$_POST = array();
		$_GET  = array();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', 100);
	}

	private function fill_valid_post(): void
	{
		$_POST['mhmrentiva_booking_nonce'] = wp_create_nonce('mhmrentiva_booking_action');
		$_POST['vehicle_id']                = (string) $this->vehicle_id;
		$_POST['pickup_date']               = gmdate('Y-m-d', strtotime('+1 day'));
		$_POST['pickup_time']               = '10:00';
		$_POST['dropoff_date']              = gmdate('Y-m-d', strtotime('+3 days'));
		$_POST['dropoff_time']              = '10:00';
		$_POST['contact_name']              = 'Jane Customer';
		$_POST['contact_email']             = 'jane@example.com';
	}

	/**
	 * RED with the pre-fix code: `mhmrentiva_before_booking_creation` is
	 * never fired anywhere in Lite, so did_action() never increments no
	 * matter what a customer submits.
	 *
	 * GREEN with the fix: Handler::handle() fires the hook once the required
	 * fields pass validation, with the submitted booking context as the
	 * payload — reached even though this environment has no WooCommerce
	 * (validate_payment_method() will subsequently fail and redirect_error(),
	 * proving the hook fires strictly BEFORE that WooCommerce-only gate, not
	 * only on a fully successful booking).
	 */
	public function test_handle_fires_before_booking_creation_hook_on_valid_frontend_submission(): void
	{
		$this->fill_valid_post();

		$before = did_action('mhmrentiva_before_booking_creation');

		$captured = null;
		add_action('mhmrentiva_before_booking_creation', function ($booking_data) use (&$captured) {
			$captured = $booking_data;
		});

		add_filter('wp_redirect', function ($location) {
			throw new \RuntimeException('redirected:' . $location);
		});

		try {
			Handler::handle();
			$this->fail('Expected Handler::handle() to redirect (no WooCommerce in this test env) after firing the hook.');
		} catch (\RuntimeException $e) {
			// Expected: validate_payment_method() fails without WooCommerce and
			// redirects with an error — this happens AFTER the hook fires.
		}

		$this->assertSame(
			$before + 1,
			did_action('mhmrentiva_before_booking_creation'),
			'Handler::handle() must fire mhmrentiva_before_booking_creation exactly once on a valid frontend submission.'
		);

		$this->assertIsArray($captured, 'Subscriber must receive the booking data payload.');
		$this->assertSame($this->vehicle_id, $captured['vehicle_id']);
		$this->assertSame('jane@example.com', $captured['contact_email']);
	}

	/**
	 * Safe-by-default proof: with no subscriber attached at all (the real
	 * shipped state unless Pro's GDPR + consent-required settings are both
	 * explicitly turned on), firing the hook is inert — Handler::handle()
	 * proceeds to its normal next step (the WooCommerce payment-method
	 * validation error in this WC-less test env) instead of being halted.
	 */
	public function test_before_booking_creation_hook_is_a_no_op_with_no_subscribers(): void
	{
		$this->fill_valid_post();

		add_filter('wp_redirect', function ($location) {
			throw new \RuntimeException('redirected:' . $location);
		});

		try {
			Handler::handle();
			$this->fail('Expected redirect after normal (non-halted) processing.');
		} catch (\RuntimeException $e) {
			$this->assertStringContainsString('code=invalid_payment_method', $e->getMessage());
		}
	}

	/**
	 * Guard against future regression: nothing in this required-fields
	 * validation failure path should reach (and thus fire) the hook — it is
	 * gated on the same required-field validation as booking creation itself.
	 */
	public function test_handle_does_not_fire_hook_when_required_fields_missing(): void
	{
		// Missing pickup/dropoff fields entirely — only nonce + vehicle_id set.
		$_POST['mhmrentiva_booking_nonce'] = wp_create_nonce('mhmrentiva_booking_action');
		$_POST['vehicle_id']                = (string) $this->vehicle_id;

		$before = did_action('mhmrentiva_before_booking_creation');

		add_filter('wp_redirect', function ($location) {
			throw new \RuntimeException('redirected:' . $location);
		});

		try {
			Handler::handle();
			$this->fail('Expected redirect for missing required fields.');
		} catch (\RuntimeException $e) {
			$this->assertStringContainsString('code=invalid_input', $e->getMessage());
		}

		$this->assertSame(
			$before,
			did_action('mhmrentiva_before_booking_creation'),
			'Hook must not fire when required-field validation fails first.'
		);
	}
}
