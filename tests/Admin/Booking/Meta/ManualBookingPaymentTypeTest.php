<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Admin\Core\MetaKeys;
use WP_Ajax_UnitTestCase;

/**
 * The new-booking price preview: the screen only shows the Deposit and
 * Remaining rows when the response says the payment is a deposit, and the
 * response never said it. Also: with the guest field gone (K5), a booking
 * still stores one guest.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox
 */
final class ManualBookingPaymentTypeTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Payment Type Vehicle',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');
		update_post_meta($this->vehicle_id, MetaKeys::VEHICLE_DEPOSIT, '20');

		// Through a filter: booking creation runs DDL, which commits the test
		// transaction (see ManualBookingDepositRateTest).
		add_filter('pre_option_mhmrentiva_settings', static fn () => array());

		ManualBookingMetaBox::register();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	/**
	 * @param array<string, mixed> $extra
	 * @return array<string, mixed>
	 */
	private function call(string $action, array $extra = array()): array
	{
		$_POST = $extra + array(
			'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'   => (string) $this->vehicle_id,
			'customer_id'  => (string) self::factory()->user->create(array( 'role' => 'customer' )),
			'pickup_date'  => '2099-05-01',
			'pickup_time'  => '10:00',
			'dropoff_date' => '2099-05-03',
			'dropoff_time' => '10:00',
			'payment_type' => 'deposit',
			'status'       => 'confirmed',
		);

		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}

		$raw                  = (string) $this->_last_response;
		$this->_last_response = '';
		$response             = json_decode($raw, true);
		$this->assertTrue($response['success'] ?? false, $raw);

		return $response['data'];
	}

	public function test_price_response_carries_payment_type_deposit(): void
	{
		$data = $this->call('mhmrentiva_calculate_manual_booking');

		$this->assertSame('deposit', $data['payment_type'] ?? null);
		$this->assertGreaterThan(0, (float) $data['deposit_amount']);
	}

	public function test_price_response_carries_payment_type_full(): void
	{
		$data = $this->call('mhmrentiva_calculate_manual_booking', array( 'payment_type' => 'full' ));

		$this->assertSame('full', $data['payment_type'] ?? null);
	}

	public function test_create_without_guests_stores_one(): void
	{
		$booking_id = (int) $this->call('mhmrentiva_create_manual_booking')['booking_id'];

		$this->assertSame('1', (string) get_post_meta($booking_id, '_mhmrentiva_guests', true));
	}
}
