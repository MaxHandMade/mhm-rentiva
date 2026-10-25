<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Admin\Core\MetaKeys;
use WP_Ajax_UnitTestCase;

/**
 * Aşama 2 PR G (K12): the manual booking screen prices and stores add-on
 * quantities. It also stored no detail rows at all before -- the creation
 * hook received the post array, which has no `selected_addons`.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox
 */
final class ManualBookingAddonQuantityTest extends WP_Ajax_UnitTestCase
{
	/** @var string */
	protected $_last_response;

	private int $vehicle_id;

	private int $seat;

	public function setUp(): void
	{
		parent::setUp();

		$this->vehicle_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Manual Quantity Vehicle',
			)
		);
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

		$this->seat = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish', 'post_title' => 'Child seat' ));
		update_post_meta($this->seat, 'mhmrentiva_addon_price', '10');
		update_post_meta($this->seat, '_mhmrentiva_addon_pricing_type', 'per_day');
		update_post_meta($this->seat, 'mhmrentiva_addon_enabled', '1');
		update_post_meta($this->seat, MetaKeys::ADDON_MAX_QUANTITY, '3');

		// Through a filter: booking creation runs DDL, which commits the test
		// transaction (see ManualBookingDepositRateTest).
		add_filter('pre_option_mhmrentiva_settings', static fn () => array( 'mhmrentiva_rental_deposit_type' => 'full_payment' ));

		ManualBookingMetaBox::register();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function call(string $action): array
	{
		$_POST = array(
			'nonce'           => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'      => (string) $this->vehicle_id,
			'customer_id'     => (string) self::factory()->user->create(array( 'role' => 'customer' )),
			'pickup_date'     => '2099-04-01',
			'pickup_time'     => '10:00',
			'dropoff_date'    => '2099-04-03',
			'dropoff_time'    => '10:00',
			'payment_type'    => 'full',
			'status'          => 'confirmed',
			'selected_addons' => array( (string) $this->seat ),
			'addon_qty'       => array( $this->seat => '2' ),
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

	public function test_manual_price_counts_quantities(): void
	{
		$data = $this->call('mhmrentiva_calculate_manual_booking');

		$this->assertSame(40.0, (float) $data['addon_total'], '10 x 2 days x 2.');
		$this->assertSame(240.0, (float) $data['total_amount']);
	}

	public function test_manual_booking_stores_details_total_and_quantities(): void
	{
		$booking_id = (int) $this->call('mhmrentiva_create_manual_booking')['booking_id'];

		$this->assertSame(array( $this->seat ), array_map('intval', get_post_meta($booking_id, MetaKeys::BOOKING_SELECTED_ADDONS, true)));
		$this->assertSame(array( $this->seat => 2 ), get_post_meta($booking_id, MetaKeys::BOOKING_ADDON_QUANTITIES, true));
		$details = get_post_meta($booking_id, '_mhmrentiva_addon_details', true);
		$this->assertIsArray($details, 'The manual screen writes detail rows like every other path.');
		$this->assertSame(40.0, (float) $details[0]['line_total']);
		$this->assertSame(2, $details[0]['quantity']);
		$this->assertSame(40.0, (float) get_post_meta($booking_id, '_mhmrentiva_addon_total', true));
		$this->assertSame(240.0, (float) get_post_meta($booking_id, '_mhmrentiva_total_price', true));
	}
}
