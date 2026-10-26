<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\BookingForm;
use WP_Ajax_UnitTestCase;

/**
 * PR S -- the booking form's availability AJAX refuses a window
 * that reaches a blocked day, with the message it gives for any taken
 * window. The customer is not told the day is closed.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::ajax_check_availability
 */
final class BookingFormBlockedDayAjaxTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		BookingForm::register();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Quiet Hatchback',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_blocked_dates', wp_json_encode(array( $this->date(11) )));

		wp_set_current_user(0);
	}

	public function test_availability_ajax_refuses_a_blocked_day(): void
	{
		$response = $this->dispatch($this->date(10), $this->date(12));

		$this->assertFalse($response['success'] ?? true, wp_json_encode($response));
		$this->assertStringNotContainsStringIgnoringCase('closed', (string) wp_json_encode($response));
	}

	public function test_availability_ajax_accepts_a_window_clear_of_it(): void
	{
		$response = $this->dispatch($this->date(12), $this->date(14));

		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));
	}

	private function date(int $days_ahead): string
	{
		return wp_date('Y-m-d', (int) strtotime('+' . $days_ahead . ' days'));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function dispatch(string $pickup, string $dropoff): array
	{
		$_POST    = array(
			'action'       => 'mhmrentiva_check_availability',
			'nonce'        => wp_create_nonce('mhmrentiva_booking_form_nonce'),
			'vehicle_id'   => (string) $this->vehicle_id,
			'pickup_date'  => $pickup,
			'pickup_time'  => '10:00',
			'dropoff_date' => $dropoff,
			'dropoff_time' => '10:00',
		);
		$_REQUEST = $_POST;

		// priv + nopriv both fire for user 0, so the buffer level can drift.
		$ob_level = ob_get_level();

		try {
			$this->_handleAjax('mhmrentiva_check_availability');
		} catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- The payload, not the die, is what is under test.
			unset($e);
		} finally {
			while (ob_get_level() > $ob_level) {
				ob_end_clean();
			}
			while (ob_get_level() < $ob_level) {
				ob_start();
			}
		}

		$buffer               = (string) $this->_last_response;
		$this->_last_response = '';

		return $this->decode_first_json($buffer);
	}

	/**
	 * The first complete JSON object in the buffer (the handler may answer twice).
	 *
	 * @return array<string, mixed>
	 */
	private function decode_first_json(string $buffer): array
	{
		$depth     = 0;
		$in_string = false;
		$escaped   = false;

		for ($i = 0, $len = strlen($buffer); $i < $len; $i++) {
			$char = $buffer[ $i ];

			if ($in_string) {
				if ($escaped) {
					$escaped = false;
				} elseif ('\\' === $char) {
					$escaped = true;
				} elseif ('"' === $char) {
					$in_string = false;
				}
				continue;
			}

			if ('"' === $char) {
				$in_string = true;
			} elseif ('{' === $char) {
				$depth++;
			} elseif ('}' === $char) {
				$depth--;
				if (0 === $depth) {
					$decoded = json_decode(substr($buffer, 0, $i + 1), true);
					return is_array($decoded) ? $decoded : array( 'success' => false );
				}
			}
		}

		return array( 'success' => false );
	}
}
