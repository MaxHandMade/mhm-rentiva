<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_Ajax_UnitTestCase;

/**
 * Backslash class (2026-11-08): New booking inserts the booking with
 * wp_insert_post(), which unslashes the post fields and hands each meta_input
 * value to update_post_meta() (WP 7.1.4 post.php) -- one unslash too many for
 * values that were already unslashed. The notes (post content) and the
 * customer's name and phone on the booking, from either customer branch.
 */
final class ManualBookingKeepsBackslashTest extends WP_Ajax_UnitTestCase
{
	use UserManagementCapabilities;

	/** @var string */
	protected $_last_response;

	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		$this->allow_site_admins_to_create_users();
		CustomerIdentity::flush_memo();
		$actor = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$this->grant_user_management_privilege($actor);
		wp_set_current_user($actor);

		$this->vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($this->vehicle, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle, '_mhmrentiva_price_per_day', '100');
		add_filter('pre_option_mhmrentiva_settings', static fn () => array());
		ManualBookingMetaBox::register();
	}

	public function tearDown(): void
	{
		$this->forbid_site_admins_from_creating_users();
		$_POST = array();
		parent::tearDown();
	}

	private function create(array $fields): int
	{
		$_POST = wp_slash($fields + array(
			'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'   => (string) $this->vehicle,
			'payment_type' => 'full',
			'status'       => 'confirmed',
			'notes'        => 'Anahtar kasada C:\dolap\2',
		));
		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$response             = json_decode((string) $this->_last_response, true);
		$this->_last_response = '';
		$this->assertTrue($response['success'] ?? false, wp_json_encode($response));

		return (int) $response['data']['booking_id'];
	}

	public function test_a_new_customer_booking_keeps_its_backslashes(): void
	{
		$booking = $this->create(array(
			'customer_id'             => 'new_customer',
			'new_customer_first_name' => 'Ali\Veli',
			'new_customer_last_name'  => 'Kaya',
			'new_customer_email'      => 'bs-manual-booking@example.com',
			'new_customer_phone'      => '0555\1',
			'pickup_date'             => '2099-08-01',
			'pickup_time'             => '10:00',
			'dropoff_date'            => '2099-08-03',
			'dropoff_time'            => '10:00',
		));

		$this->assertSame('Anahtar kasada C:\dolap\2', get_post($booking)->post_content);
		$this->assertSame('Ali\Veli', get_post_meta($booking, '_mhmrentiva_customer_first_name', true));
		$this->assertSame('Ali\Veli Kaya', get_post_meta($booking, '_mhmrentiva_customer_name', true));
		$this->assertSame('0555\1', get_post_meta($booking, '_mhmrentiva_customer_phone', true));
	}

	public function test_an_existing_customer_booking_keeps_its_backslashes(): void
	{
		$customer = (int) self::factory()->user->create(array(
			'role'         => 'customer',
			'user_email'   => 'bs-existing@example.com',
			'last_name'    => 'Demir',
		));
		// The factory goes through wp_insert_user(), which unslashes: store the
		// name the way the fixed writers do.
		update_user_meta($customer, 'first_name', wp_slash('Ayşe\Nur'));
		$this->assertSame('Ayşe\Nur', get_user_meta($customer, 'first_name', true), 'Fixture: the stored name holds the backslash.');

		$booking = $this->create(array(
			'customer_id'  => (string) $customer,
			'pickup_date'  => '2099-08-05',
			'pickup_time'  => '10:00',
			'dropoff_date' => '2099-08-07',
			'dropoff_time' => '10:00',
		));

		$this->assertSame('Anahtar kasada C:\dolap\2', get_post($booking)->post_content);
		$this->assertSame('Ayşe\Nur', get_post_meta($booking, '_mhmrentiva_customer_first_name', true));
	}
}
