<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Admin\Customers\AddCustomerPage;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_Ajax_UnitTestCase;

/**
 * Function scan H6, the rest of the class (Fable #175 I-1): every screen that
 * creates a customer account gives it a random password nobody knows, so the
 * customer gets core's own set-password e-mail -- and only the customer, no
 * copy to the site admin. The booking portal box was fixed in #175
 * (BookingPortalMetaBoxCapabilityTest); these are the other two creators
 * (`wp_create_user` has three callers in Lite, none in Pro).
 */
final class NewAccountPasswordEmailTest extends WP_Ajax_UnitTestCase
{
	use UserManagementCapabilities;

	/** @var string */
	protected $_last_response;

	public function setUp(): void
	{
		parent::setUp();
		$this->allow_site_admins_to_create_users();
		CustomerIdentity::flush_memo();
		$actor = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$this->grant_user_management_privilege($actor);
		wp_set_current_user($actor);
		reset_phpmailer_instance();
	}

	public function tearDown(): void
	{
		$this->forbid_site_admins_from_creating_users();
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Set-password mails, by recipient.
	 *
	 * @return list<string>
	 */
	private function password_mails_to(): array
	{
		$to = array();
		foreach (tests_retrieve_phpmailer_instance()->mock_sent as $mail) {
			if (str_contains((string) $mail['body'], 'action=rp')) {
				$to[] = (string) $mail['to'][0][0];
			}
		}

		return $to;
	}

	public function test_a_customer_created_with_a_manual_booking_gets_the_set_password_email(): void
	{
		$vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($vehicle, '_mhmrentiva_price_per_day', '100');
		add_filter('pre_option_mhmrentiva_settings', static fn () => array());
		ManualBookingMetaBox::register();

		$_POST = wp_slash(array(
			'nonce'                   => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'              => (string) $vehicle,
			'customer_id'             => 'new_customer',
			'new_customer_first_name' => 'Mehmet',
			'new_customer_last_name'  => 'Demir',
			'new_customer_email'      => 'h6-manual@example.com',
			'new_customer_phone'      => '05320000000',
			'pickup_date'             => '2099-07-01',
			'pickup_time'             => '10:00',
			'dropoff_date'            => '2099-07-03',
			'dropoff_time'            => '10:00',
			'payment_type'            => 'full',
			'status'                  => 'confirmed',
		));
		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$response = json_decode((string) $this->_last_response, true);

		$this->assertTrue($response['success'] ?? false, (string) $this->_last_response);
		$this->assertSame(array( 'h6-manual@example.com' ), $this->password_mails_to(), 'One set-password mail, to the customer only.');
		$this->assertStringContainsString('e-mail to set their password', $response['data']['message']);
	}

	public function test_a_manual_booking_for_an_existing_customer_sends_no_password_email(): void
	{
		$vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($vehicle, '_mhmrentiva_price_per_day', '100');
		add_filter('pre_option_mhmrentiva_settings', static fn () => array());
		ManualBookingMetaBox::register();
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer', 'user_email' => 'h6-known@example.com' ));
		reset_phpmailer_instance();

		$_POST = wp_slash(array(
			'nonce'        => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'   => (string) $vehicle,
			'customer_id'  => (string) $customer,
			'pickup_date'  => '2099-07-05',
			'pickup_time'  => '10:00',
			'dropoff_date' => '2099-07-07',
			'dropoff_time' => '10:00',
			'payment_type' => 'full',
			'status'       => 'confirmed',
		));
		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$response = json_decode((string) $this->_last_response, true);

		$this->assertTrue($response['success'] ?? false, (string) $this->_last_response);
		$this->assertSame(array(), $this->password_mails_to());
		$this->assertStringNotContainsString('password', $response['data']['message']);
	}

	public function test_a_customer_added_on_the_add_customer_screen_gets_the_set_password_email(): void
	{
		$_POST = wp_slash(array(
			'mhmrentiva_add_customer_nonce' => wp_create_nonce('mhmrentiva_add_customer'),
			'customer_name'                 => 'Ayşe Yılmaz',
			'customer_email'                => 'h6-add@example.com',
			'customer_phone'                => '05330000000',
			'customer_address'              => '',
		));
		ob_start();
		AddCustomerPage::render();
		$html = (string) ob_get_clean();

		$this->assertInstanceOf(\WP_User::class, get_user_by('email', 'h6-add@example.com'));
		$this->assertSame(array( 'h6-add@example.com' ), $this->password_mails_to(), 'One set-password mail, to the customer only.');
		$this->assertStringContainsString('e-mail to set their password', $html);
	}
}
