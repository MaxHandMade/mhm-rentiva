<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * A manual booking stores the payment the calculator actually priced.
 *
 * DepositCalculator::calculate_booking_deposit() turns a "deposit" request into
 * a full payment when no deposit applies (rate 0: the global rental setting is
 * "Full payment" and the vehicle has no rate of its own). The manual booking
 * screen stored the operator's requested type next to full-payment amounts, so
 * the record said "deposit" while the deposit equalled the total.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox::ajax_create_booking
 */
final class ManualBookingDepositRateTest extends WP_Ajax_UnitTestCase
{
	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Manual Deposit Rate Vehicle',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

		ManualBookingMetaBox::register();

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	/**
	 * Settings come through a filter, not the option row: booking creation runs
	 * DDL somewhere down the line, and MySQL commits the open test transaction
	 * on DDL, so a written option would outlive this test and skew later ones.
	 *
	 * @param array<string, mixed> $settings
	 */
	private function settings(array $settings): void
	{
		add_filter('pre_option_mhmrentiva_settings', static fn () => $settings);
	}

	private function create_deposit_booking(): int
	{
		$_POST = array(
			'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'   => (string) $this->vehicle_id,
			'customer_id'  => (string) self::factory()->user->create(array( 'role' => 'customer' )),
			'pickup_date'  => '2099-04-01',
			'pickup_time'  => '10:00',
			'dropoff_date' => '2099-04-03',
			'dropoff_time' => '10:00',
			'payment_type' => 'deposit',
			'status'       => 'confirmed',
		);

		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}

		$response = json_decode((string) $this->_last_response, true);
		$this->assertTrue($response['success'] ?? false, (string) $this->_last_response);

		return (int) $response['data']['booking_id'];
	}

	public function test_deposit_request_under_full_payment_is_stored_as_full(): void
	{
		$this->settings(array( 'mhmrentiva_rental_deposit_type' => 'full_payment' ));

		$booking_id = $this->create_deposit_booking();

		$this->assertSame('full', get_post_meta($booking_id, '_mhmrentiva_payment_type', true));
		$this->assertEquals(
			(float) get_post_meta($booking_id, '_mhmrentiva_total_price', true),
			(float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true)
		);
	}

	public function test_deposit_request_with_a_rate_stays_a_deposit(): void
	{
		$this->settings(
			array(
				'mhmrentiva_rental_deposit_type' => 'percentage',
				'mhmrentiva_rental_deposit_rate' => 30,
			)
		);

		$booking_id = $this->create_deposit_booking();

		$this->assertSame('deposit', get_post_meta($booking_id, '_mhmrentiva_payment_type', true));
		$this->assertEqualsWithDelta(
			0.3 * (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true),
			(float) get_post_meta($booking_id, '_mhmrentiva_deposit_amount', true),
			0.01
		);
	}
}
