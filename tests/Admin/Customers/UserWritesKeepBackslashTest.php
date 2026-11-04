<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox;
use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Admin\Customers\AddCustomerPage;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersPage;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_Ajax_UnitTestCase;

/**
 * Every screen that writes a customer's name, phone or address keeps a
 * backslash the person typed.
 *
 * A real request arrives slashed; each handler unslashes once before
 * sanitising, and wp_update_user() / update_user_meta() unslash AGAIN, so a
 * value handed to them unslashed loses its backslash ("North\West" →
 * "NorthWest"). Found by the Codex review of #166 on the manual booking
 * screen; the class was then swept: the five Lite wp_update_user() writers and
 * the user meta writes beside them. (Pro's only writer, GDPR anonymisation,
 * writes constants.)
 */
final class UserWritesKeepBackslashTest extends WP_Ajax_UnitTestCase
{
	use UserManagementCapabilities;

	private const NAME = 'Ali\Veli';
	private const PHONE = '0555\1';
	private const ADDRESS = 'Blok A\3';

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
	}

	public function tearDown(): void
	{
		$this->forbid_site_admins_from_creating_users();
		$_POST = array();
		$_GET  = array();
		parent::tearDown();
	}

	private function ajax(string $action): void
	{
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$this->_last_response = '';
	}

	public function test_manual_booking_new_customer(): void
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
			'new_customer_first_name' => self::NAME,
			'new_customer_last_name'  => 'Kaya\Demir',
			'new_customer_email'      => 'bs-manual@example.com',
			'new_customer_phone'      => self::PHONE,
			'pickup_date'             => '2099-07-01',
			'pickup_time'             => '10:00',
			'dropoff_date'            => '2099-07-03',
			'dropoff_time'            => '10:00',
			'payment_type'            => 'full',
			'status'                  => 'confirmed',
		));
		$this->ajax('mhmrentiva_create_manual_booking');

		$user = get_user_by('email', 'bs-manual@example.com');
		$this->assertInstanceOf(\WP_User::class, $user);
		$this->assertSame(self::NAME, get_user_meta($user->ID, 'first_name', true));
		$this->assertSame('Kaya\Demir', get_user_meta($user->ID, 'last_name', true));
		$this->assertSame(self::NAME . ' Kaya\Demir', $user->display_name);
		$this->assertSame(self::PHONE, get_user_meta($user->ID, 'mhmrentiva_phone', true));
	}

	public function test_booking_portal_account(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		BookingPortalMetaBox::register();

		$_POST = wp_slash(array(
			'nonce'      => wp_create_nonce('mhmrentiva_create_customer_account'),
			'booking_id' => (string) $booking,
			'email'      => 'bs-portal@example.com',
			'name'       => self::NAME . ' Kaya',
		));
		$this->ajax('mhmrentiva_create_customer_account_manual');

		$user = get_user_by('email', 'bs-portal@example.com');
		$this->assertInstanceOf(\WP_User::class, $user);
		$this->assertSame(self::NAME, get_user_meta($user->ID, 'first_name', true));
		$this->assertSame(self::NAME . ' Kaya', $user->display_name);
	}

	public function test_add_customer_page(): void
	{
		$_POST = wp_slash(array(
			'mhmrentiva_add_customer_nonce' => wp_create_nonce('mhmrentiva_add_customer'),
			'customer_name'                 => self::NAME,
			'customer_email'                => 'bs-add@example.com',
			'customer_phone'                => self::PHONE,
			'customer_address'              => self::ADDRESS,
		));
		ob_start();
		AddCustomerPage::render();
		ob_end_clean();

		$user = get_user_by('email', 'bs-add@example.com');
		$this->assertInstanceOf(\WP_User::class, $user);
		$this->assertSame(self::NAME, $user->display_name);
		$this->assertSame(self::NAME, get_user_meta($user->ID, 'first_name', true));
		$this->assertSame(self::PHONE, get_user_meta($user->ID, 'mhmrentiva_phone', true));
		$this->assertSame(self::ADDRESS, get_user_meta($user->ID, 'mhmrentiva_address', true));
	}

	public function test_a_role_key_is_not_slashed_with_the_profile(): void
	{
		// wp_insert_user() hands `role` to set_role() as is (no unslash), so
		// slashing the whole update array would grant "fleet\\customer".
		add_role('fleet\customer', 'Fleet customer', array( 'read' => true ));
		$settings = (array) get_option(\MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME, array());
		$settings['mhmrentiva_customer_default_role'] = 'fleet\customer';
		update_option(\MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME, $settings);

		try {
			$_POST = wp_slash(array(
				'mhmrentiva_add_customer_nonce' => wp_create_nonce('mhmrentiva_add_customer'),
				'customer_name'                 => self::NAME,
				'customer_email'                => 'bs-role@example.com',
				'customer_phone'                => self::PHONE,
				'customer_address'              => self::ADDRESS,
			));
			ob_start();
			AddCustomerPage::render();
			ob_end_clean();
			$user = get_user_by('email', 'bs-role@example.com');
		} finally {
			delete_option(\MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME);
			remove_role('fleet\customer');
		}

		$this->assertInstanceOf(\WP_User::class, $user);
		$this->assertSame(array( 'fleet\customer' ), array_keys(array_filter((array) $user->caps)));
	}

	public function test_customer_edit_page(): void
	{
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer', 'user_email' => 'bs-edit@example.com' ));
		$_GET['customer_id'] = (string) $customer;
		$_POST               = wp_slash(array(
			'mhmrentiva_edit_customer_nonce' => wp_create_nonce('mhmrentiva_edit_customer'),
			'customer_name'                  => self::NAME,
			'customer_email'                 => 'bs-edit@example.com',
			'customer_phone'                 => self::PHONE,
			'customer_address'               => self::ADDRESS,
		));
		$method = new \ReflectionMethod(CustomersPage::class, 'render_customer_edit');
		$method->setAccessible(true);
		ob_start();
		$method->invoke(new CustomersPage());
		ob_end_clean();

		$user = get_userdata($customer);
		$this->assertSame(self::NAME, $user->display_name);
		$this->assertSame(self::NAME, get_user_meta($customer, 'first_name', true));
		$this->assertSame(self::PHONE, get_user_meta($customer, 'mhmrentiva_phone', true));
		$this->assertSame(self::ADDRESS, get_user_meta($customer, 'mhmrentiva_address', true));
	}

	public function test_my_account_profile(): void
	{
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_set_current_user($customer);
		AccountController::register();

		$_POST = wp_slash(array(
			'nonce'        => wp_create_nonce('mhmrentiva_account'),
			'display_name' => self::NAME,
			'first_name'   => self::NAME,
			'last_name'    => 'Kaya\Demir',
			'phone'        => self::PHONE,
			'address'      => self::ADDRESS,
		));
		$this->ajax('mhmrentiva_update_account');

		$user = get_userdata($customer);
		$this->assertSame(self::NAME, $user->display_name);
		$this->assertSame(self::NAME, get_user_meta($customer, 'first_name', true));
		$this->assertSame('Kaya\Demir', get_user_meta($customer, 'last_name', true));
		$this->assertSame(self::PHONE, get_user_meta($customer, 'mhmrentiva_phone', true));
		$this->assertSame(self::ADDRESS, get_user_meta($customer, 'mhmrentiva_address', true));
	}
}
