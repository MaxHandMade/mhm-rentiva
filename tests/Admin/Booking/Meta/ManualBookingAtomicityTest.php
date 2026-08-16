<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * Third independent review of the shipping package (findings H1, M1 and the
 * wp_insert_post() contract class the review sampled elsewhere).
 *
 * ajax_create_booking() is the admin's manual booking path. It checked
 * availability with Util::check_availability() and then called
 * wp_insert_post() as two bare statements: nothing held a lock between them,
 * so two admins submitting at the same moment could both read "free" and both
 * insert -- the exact defect closed on the WooCommerce checkout path in 6.0.3
 * (see BookingCreationAtomicityTest), left open on this one. The plugin owned
 * the right primitive the whole time; only one of its two booking-creation
 * paths called it.
 *
 * Two more defects live in the same method:
 *
 * - The new-customer branch ran wp_create_user() BEFORE the availability
 *   check, so a booking refused for a full vehicle still left a real WP
 *   account behind. Worse than litter: email_exists() then rejects the same
 *   address, so the operator who retries with corrected dates is told the
 *   customer "is already registered" and cannot complete the booking at all.
 *
 * - wp_insert_post() was called without $wp_error, and WordPress documents
 *   the failure return as "the value 0 or WP_Error" -- 0 unless you ask for
 *   the error object. The guard here only tested is_wp_error(), so a failed
 *   insert fell straight through to wp_send_json_success() and told the
 *   operator the booking was created, linking them to post 0.
 *
 * WHAT THESE TESTS CAN AND CANNOT PROVE (same caveat as the WC-path suite):
 * the transaction assertions prove the WIRING -- that the conflict check and
 * the INSERT are issued inside one transaction, in that order. They cannot
 * prove mutual exclusion under real concurrency; PHPUnit runs one connection.
 * Read them as a regression fence, not as proof double-booking is impossible.
 */
final class ManualBookingAtomicityTest extends WP_Ajax_UnitTestCase
{
	protected $_last_response;

	private int $vehicle_id;

	/** @var list<string> */
	private array $query_log = array();

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Manual Booking Atomicity Vehicle',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

		$this->query_log = array();

		// Registered at bootstrap only when is_admin() was already true, and
		// WP_UnitTestCase restores $wp_filter between tests -- so re-register.
		ManualBookingMetaBox::register();

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	/**
	 * Records every statement and neutralises transaction control, so the
	 * transaction WP_UnitTestCase wraps each test in is not implicitly
	 * committed by a nested START TRANSACTION (which would leak fixtures).
	 * The statement is recorded before being neutralised, so ordering is
	 * still observed exactly.
	 */
	private function start_recording(): void
	{
		add_filter('query', function ($query) {
			$this->query_log[] = (string) $query;

			if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query)) {
				return 'SELECT 1';
			}

			return $query;
		});
	}

	private function index_of(string $pattern): int
	{
		foreach ($this->query_log as $i => $sql) {
			if (preg_match($pattern, $sql)) {
				return $i;
			}
		}

		return -1;
	}

	/**
	 * @param array<string, string> $overrides
	 */
	private function seed_request(array $overrides = array()): void
	{
		$defaults = array(
			'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'   => (string) $this->vehicle_id,
			'pickup_date'  => gmdate('Y-m-d', strtotime('+10 days')),
			'pickup_time'  => '10:00',
			'dropoff_date' => gmdate('Y-m-d', strtotime('+12 days')),
			'dropoff_time' => '10:00',
			'payment_type' => 'full',
		);

		foreach (array_merge($defaults, $overrides) as $key => $value) {
			$_POST[ $key ] = $value;
		}
	}

	private function seed_new_customer_fields(string $email): void
	{
		$_POST['customer_id']             = 'new_customer';
		$_POST['new_customer_first_name'] = 'Jane';
		$_POST['new_customer_last_name']  = 'Atomicity';
		$_POST['new_customer_email']      = $email;
		$_POST['new_customer_phone']      = '5551234567';
	}

	private function dispatch(): void
	{
		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			// Expected exit path for WP_Ajax_UnitTestCase.
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decode_response(): array
	{
		$decoded = json_decode((string) $this->_last_response, true);
		return is_array($decoded) ? $decoded : array();
	}

	/**
	 * RED before the fix: no START TRANSACTION is issued on this path at all,
	 * so the first assertion fails with index -1.
	 */
	public function test_conflict_check_and_insert_run_inside_one_transaction(): void
	{
		$customer_id = self::factory()->user->create(array( 'role' => 'customer' ));
		$this->seed_request(array( 'customer_id' => (string) $customer_id ));

		$this->start_recording();
		$this->dispatch();

		$response = $this->decode_response();
		$this->assertTrue(
			$response['success'] ?? false,
			'Sanity check: the happy path must still create a booking. Response: ' . (string) $this->_last_response
		);

		$begin  = $this->index_of('/^\s*START TRANSACTION\b/i');
		$lock   = $this->index_of('/FOR UPDATE/i');
		$insert = $this->index_of('/INSERT INTO\s+\S*posts\b/i');
		$commit = $this->index_of('/^\s*COMMIT\b/i');

		$this->assertGreaterThan(
			-1,
			$begin,
			'Manual booking creation must open a transaction; a bare availability check under autocommit holds no lock at all.'
		);
		$this->assertGreaterThan(-1, $lock, 'The vehicle row lock must be taken.');
		$this->assertGreaterThan(-1, $insert, 'The booking post must be inserted.');
		$this->assertGreaterThan(-1, $commit, 'The transaction must be committed.');

		$this->assertGreaterThan($begin, $lock, 'The row lock must be taken AFTER the transaction opens, or it is released immediately.');
		$this->assertGreaterThan($lock, $insert, 'The booking must be inserted AFTER the conflict check holds its lock.');
		$this->assertGreaterThan($insert, $commit, 'The transaction must stay open until the booking row exists.');
	}

	/**
	 * The invariant the lock exists to protect, asserted sequentially so a
	 * regression that drops the authoritative check turns this red even
	 * though one connection cannot stage a real race.
	 */
	public function test_overlapping_second_manual_booking_is_refused(): void
	{
		$customer_id = self::factory()->user->create(array( 'role' => 'customer' ));

		$this->seed_request(array( 'customer_id' => (string) $customer_id ));
		$this->dispatch();
		$this->assertTrue(
			$this->decode_response()['success'] ?? false,
			'The first manual booking must succeed. Response: ' . (string) $this->_last_response
		);

		$this->_last_response = '';
		$this->seed_request(array( 'customer_id' => (string) $customer_id ));
		$this->dispatch();

		$this->assertFalse(
			$this->decode_response()['success'] ?? true,
			'An overlapping manual booking on the same vehicle must be refused. Response: ' . (string) $this->_last_response
		);
	}

	/**
	 * Negative control for the test above: the guard must not be satisfiable
	 * by refusing every second booking.
	 */
	public function test_non_overlapping_second_manual_booking_is_accepted(): void
	{
		$customer_id = self::factory()->user->create(array( 'role' => 'customer' ));

		$this->seed_request(array( 'customer_id' => (string) $customer_id ));
		$this->dispatch();
		$this->assertTrue(
			$this->decode_response()['success'] ?? false,
			'The first manual booking must succeed. Response: ' . (string) $this->_last_response
		);

		$this->_last_response = '';
		$this->seed_request(array(
			'customer_id'  => (string) $customer_id,
			'pickup_date'  => gmdate('Y-m-d', strtotime('+50 days')),
			'dropoff_date' => gmdate('Y-m-d', strtotime('+52 days')),
		));
		$this->dispatch();

		$this->assertTrue(
			$this->decode_response()['success'] ?? false,
			'A non-overlapping manual booking on the same vehicle must still be accepted. Response: ' . (string) $this->_last_response
		);
	}

	/**
	 * Finding M1. RED before the fix: the account exists even though the
	 * booking was refused, and -- because email_exists() guards the branch --
	 * the operator can never complete a booking for that customer again.
	 */
	public function test_new_customer_is_not_created_when_vehicle_is_unavailable(): void
	{
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'maintenance');

		$email = 'unavailable-vehicle-customer@example.com';
		$this->seed_request();
		$this->seed_new_customer_fields($email);

		$this->dispatch();

		$this->assertFalse(
			$this->decode_response()['success'] ?? true,
			'A booking on an unavailable vehicle must be refused. Response: ' . (string) $this->_last_response
		);
		$this->assertFalse(
			get_user_by('email', $email),
			'A refused booking must not leave a real WP account behind; email_exists() then blocks every retry for that customer.'
		);
	}

	/**
	 * The new-customer happy path must survive the reordering: moving user
	 * creation after the availability check must not stop it happening.
	 */
	public function test_new_customer_is_still_created_when_the_booking_succeeds(): void
	{
		$email = 'available-vehicle-customer@example.com';
		$this->seed_request();
		$this->seed_new_customer_fields($email);

		$this->dispatch();

		$this->assertTrue(
			$this->decode_response()['success'] ?? false,
			'The new-customer happy path must still create the booking. Response: ' . (string) $this->_last_response
		);
		$this->assertNotFalse(
			get_user_by('email', $email),
			'The customer account must still be created when the booking is accepted.'
		);
	}

	/**
	 * Finding H2's defect class, on the call site the review did not sample.
	 *
	 * wp_insert_post() returns 0 -- not WP_Error -- on failure unless
	 * $wp_error is passed, so an is_wp_error()-only guard lets a failed
	 * insert through. RED before the fix: the operator is told
	 * "Booking created successfully." and handed an edit link for post 0.
	 *
	 * wp_insert_post_empty_content is WordPress's own filter and is applied
	 * on every insert regardless of the post's contents, so forcing it true
	 * exercises the real failure return rather than a mock.
	 */
	public function test_failed_insert_is_reported_as_an_error_not_as_success(): void
	{
		$customer_id = self::factory()->user->create(array( 'role' => 'customer' ));
		$this->seed_request(array( 'customer_id' => (string) $customer_id ));

		add_filter('wp_insert_post_empty_content', '__return_true');
		$this->dispatch();
		remove_filter('wp_insert_post_empty_content', '__return_true');

		$response = $this->decode_response();

		$this->assertFalse(
			$response['success'] ?? true,
			'A failed insert must be reported as an error. Response: ' . (string) $this->_last_response
		);
		$this->assertArrayNotHasKey(
			'booking_id',
			(array) ($response['data'] ?? array()),
			'A failed insert must not hand the operator a booking id.'
		);
	}

	/**
	 * Negative control for the test above: the forced-failure filter must be
	 * what makes it fail, not something permanently broken in the fixture.
	 */
	public function test_insert_succeeds_when_the_failure_filter_is_absent(): void
	{
		$customer_id = self::factory()->user->create(array( 'role' => 'customer' ));
		$this->seed_request(array( 'customer_id' => (string) $customer_id ));

		$this->dispatch();

		$this->assertTrue(
			$this->decode_response()['success'] ?? false,
			'Without the forced-failure filter the same request must succeed. Response: ' . (string) $this->_last_response
		);
	}
}
