<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * PR S -- an operator may book a vehicle on a day the vehicle is closed.
 *
 * The customer is refused on a blocked day; the operator is not: the booking
 * is saved and a notice names the closed days. The operator's check counts
 * bookings only, under the lock, and never reads the cached customer answer
 * -- a customer's cached "booked" for the same window must not refuse the
 * operator (r3 Y1).
 *
 * START TRANSACTION / COMMIT are neutralised so the test's own transaction
 * survives Locker::withLock() (same approach as ManualBookingAtomicityTest).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox::ajax_create_booking
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::show_blocked_day_notice
 */
final class ManualBookingBlockedDayTest extends WP_Ajax_UnitTestCase
{
	protected $_last_response;

	private int $vehicle_id;

	private int $customer_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Closed Day Sedan',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', '["2027-10-11"]');

		ManualBookingMetaBox::register();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer' ));

		add_filter('query', static function ($query) {
			return preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query) ? 'SELECT 1' : $query;
		});
	}

	public function tearDown(): void
	{
		delete_transient($this->notice_key());
		$_POST = array();
		parent::tearDown();
	}

	private function notice_key(): string
	{
		return 'mhmrentiva_admin_blocked_day_notice_' . get_current_user_id();
	}

	/**
	 * @param array<string, string> $overrides
	 * @return array<string, mixed>
	 */
	private function create(array $overrides = array()): array
	{
		$_POST = array_merge(
			array(
				'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
				'vehicle_id'   => (string) $this->vehicle_id,
				'customer_id'  => (string) $this->customer_id,
				'pickup_date'  => '2027-10-10',
				'pickup_time'  => '10:00',
				'dropoff_date' => '2027-10-12',
				'dropoff_time' => '10:00',
				'payment_type' => 'full',
				'status'       => 'confirmed',
			),
			$overrides
		);

		$this->_last_response = '';
		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			// Expected exit path for WP_Ajax_UnitTestCase.
		}

		$decoded = json_decode((string) $this->_last_response, true);

		return is_array($decoded) ? $decoded : array();
	}

	private function notice_output(): string
	{
		ob_start();
		BookingMeta::show_blocked_day_notice();

		return (string) ob_get_clean();
	}

	public function test_admin_paths_save_and_warn_on_blocked_day(): void
	{
		$response = $this->create();

		$this->assertTrue($response['success'] ?? false, 'The operator is not refused: ' . (string) $this->_last_response);
		$this->assertSame(array( '2027-10-11' ), get_transient($this->notice_key()));

		$html = $this->notice_output();
		$this->assertStringContainsString(
			esc_html(sprintf(
				/* translators: %s: list of closed days. */
				__('This vehicle has closed days in this period: %s. The booking was saved anyway.', 'mhm-rentiva'),
				wp_date((string) get_option('date_format'), (int) \MHMRentiva\Admin\Booking\Helpers\SiteTime::instant('2027-10-11', '12:00'))
			)),
			$html
		);
		$this->assertStringContainsString('notice-warning', $html);
		$this->assertFalse(get_transient($this->notice_key()), 'Shown once, then gone.');
		$this->assertSame('', $this->notice_output());
	}

	public function test_no_notice_when_no_day_is_closed(): void
	{
		$response = $this->create(array( 'pickup_date' => '2027-10-20', 'dropoff_date' => '2027-10-22' ));

		$this->assertTrue($response['success'] ?? false);
		$this->assertFalse(get_transient($this->notice_key()));
	}

	public function test_customer_cached_blocked_answer_does_not_refuse_admin(): void
	{
		// The customer asks first; "booked" is cached for this window.
		$customer = Util::check_availability($this->vehicle_id, '2027-10-10', '10:00', '2027-10-12', '10:00');
		$this->assertFalse($customer['ok'], 'Precondition: the customer is refused and the answer is cached.');

		// The price quote and the create both answer the operator from the database.
		$_POST = array(
			'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'   => (string) $this->vehicle_id,
			'pickup_date'  => '2027-10-10',
			'pickup_time'  => '10:00',
			'dropoff_date' => '2027-10-12',
			'dropoff_time' => '10:00',
			'payment_type' => 'full',
		);
		try {
			$this->_handleAjax('mhmrentiva_calculate_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			// Expected exit path for WP_Ajax_UnitTestCase.
		}
		$quote = json_decode((string) $this->_last_response, true);
		$this->assertTrue($quote['success'] ?? false, 'The quote is not refused: ' . (string) $this->_last_response);

		$response = $this->create();
		$this->assertTrue($response['success'] ?? false, 'The booking is saved: ' . (string) $this->_last_response);
		$this->assertSame(array( '2027-10-11' ), get_transient($this->notice_key()), 'And the operator is warned.');
	}

	public function test_a_real_booking_conflict_still_refuses_the_operator(): void
	{
		$this->assertTrue($this->create()['success'] ?? false);
		delete_transient($this->notice_key());

		$second = $this->create();

		$this->assertFalse($second['success'] ?? true, 'Two bookings on one vehicle and window.');
		$this->assertFalse(get_transient($this->notice_key()), 'A refused booking leaves no "saved anyway" notice.');
	}
}
