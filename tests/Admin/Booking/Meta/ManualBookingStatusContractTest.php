<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * The manual booking screen writes _mhmrentiva_status straight from $_POST,
 * and until this test nothing checked what arrived there.
 *
 * The defect that produced these tests was reproduced against the running dev
 * site before a line was changed: the browser script assembles its AJAX payload
 * by reading `#mhmrentiva_manual_status`, an id the markup never prints (the
 * select carries `id="mhmrentiva_manual_booking_status"`; that spelling is its
 * `name`). jQuery.param() encodes a missing value as an empty string rather
 * than dropping the key -- `value == null ? "" : value` in the bundled source
 * -- so `isset($_POST['status'])` is true, the method's 'confirmed' default
 * never runs, and the booking is stored with an empty status.
 *
 * That is not cosmetic. Util::has_overlap_locked() counts only the four live
 * statuses, so a booking stored with '' is invisible to the conflict check and
 * the same vehicle can be booked again over the same dates. Measured on the dev
 * site: with '' the locked check answered NO, and the same row set to
 * 'confirmed' answered YES.
 *
 * Two guarantees are fenced here:
 *
 * 1. The handler refuses a status the form does not offer, loudly, instead of
 *    coercing it to a default -- a silent coercion hides a broken client, which
 *    is exactly how this survived.
 * 2. The set the handler accepts is the set the form renders. Fixing only the
 *    handler would leave the two free to drift apart again.
 */
final class ManualBookingStatusContractTest extends WP_Ajax_UnitTestCase
{
	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Manual Booking Status Contract Vehicle',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

		// Registered at bootstrap only when is_admin() was already true, and
		// WP_UnitTestCase restores $wp_filter between tests -- so re-register.
		ManualBookingMetaBox::register();

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	private function dispatch_ajax(): void
	{
		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			// Expected path for WP_Ajax_UnitTestCase.
		}
	}

	private function decode_response(): array
	{
		$decoded = json_decode((string) $this->_last_response, true);
		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * @param array<string, string> $overrides
	 */
	private function post_a_booking(array $overrides = array()): void
	{
		$customer_id = self::factory()->user->create(array( 'role' => 'customer' ));

		$_POST = array_merge(
			array(
				'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
				'vehicle_id'   => (string) $this->vehicle_id,
				'customer_id'  => (string) $customer_id,
				'pickup_date'  => '2099-04-01',
				'pickup_time'  => '10:00',
				'dropoff_date' => '2099-04-03',
				'dropoff_time' => '10:00',
				'payment_type' => 'full',
			),
			$overrides
		);
	}

	private function stored_statuses(): array
	{
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_mhmrentiva_status'
			)
		);
	}

	/**
	 * The reproduction, as a fence: this is byte-for-byte what the browser
	 * sends today.
	 */
	public function test_empty_status_is_refused_and_no_booking_is_written(): void
	{
		$this->post_a_booking(array( 'status' => '' ));

		$this->dispatch_ajax();
		$response = $this->decode_response();

		$this->assertFalse(
			$response['success'] ?? true,
			'An empty status must be refused, not stored. Response: ' . (string) $this->_last_response
		);
		$this->assertNotContains(
			'',
			$this->stored_statuses(),
			'No booking may carry an empty _mhmrentiva_status: Util::has_overlap_locked() cannot see one, so the vehicle stays bookable over the same dates.'
		);
	}

	/**
	 * 'completed' is a real status, and a valid one elsewhere in the plugin --
	 * it is simply not one this screen offers, so this boundary must not take
	 * it from the request.
	 */
	public function test_status_the_form_does_not_offer_is_refused(): void
	{
		$this->post_a_booking(array( 'status' => Status::COMPLETED ));

		$this->dispatch_ajax();
		$response = $this->decode_response();

		$this->assertFalse(
			$response['success'] ?? true,
			'A status outside the form offering must be refused. Response: ' . (string) $this->_last_response
		);
		$this->assertNotContains(
			Status::COMPLETED,
			$this->stored_statuses(),
			'A refused request must leave nothing behind.'
		);
	}

	/**
	 * The negative control for the two refusals above: a status the form does
	 * offer still creates the booking, and lands in the meta unchanged. Without
	 * this, a guard that refused everything would pass the two tests above.
	 */
	public function test_offered_status_is_accepted_and_stored_verbatim(): void
	{
		$this->post_a_booking(array( 'status' => Status::PENDING ));

		$this->dispatch_ajax();
		$response = $this->decode_response();

		$this->assertTrue(
			$response['success'] ?? false,
			'A status the form offers must still create the booking. Response: ' . (string) $this->_last_response
		);

		$booking_id = (int) ( $response['data']['booking_id'] ?? 0 );
		$this->assertGreaterThan(0, $booking_id);
		$this->assertSame(
			Status::PENDING,
			get_post_meta($booking_id, '_mhmrentiva_status', true),
			'The accepted status must be stored as sent, not normalised to the default.'
		);
	}

	/**
	 * The drift lock. The rendered <select> and the handler's guard must read
	 * one list; two hand-maintained copies is how the screen and its boundary
	 * come apart.
	 */
	public function test_accepted_statuses_are_exactly_the_statuses_the_form_renders(): void
	{
		$booking_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

		ob_start();
		ManualBookingMetaBox::render(get_post($booking_id));
		$markup = (string) ob_get_clean();

		$this->assertSame(
			1,
			preg_match(
				'#<select id="mhmrentiva_manual_booking_status".*?</select>#s',
				$markup,
				$select
			),
			'The manual booking screen must render the status select with the id its script reads.'
		);

		preg_match_all('#<option value="([^"]*)"#', $select[0], $options);
		$rendered = $options[1];
		sort($rendered);

		$accepted = ManualBookingMetaBox::initial_statuses();
		sort($accepted);

		$this->assertSame(
			$rendered,
			$accepted,
			'The statuses the handler accepts and the statuses the form offers must be one list.'
		);
	}
}
