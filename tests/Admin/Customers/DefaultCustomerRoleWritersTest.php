<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Admin\Customers\AddCustomerPage;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_Ajax_UnitTestCase;

/**
 * A resolver nothing calls is not a guard.
 *
 * DefaultCustomerRoleTest fences what CustomerIdentity::default_customer_role()
 * answers. These two drive the screens that actually create accounts, with the
 * option set to 'administrator', and look at the role the new user ends up
 * wearing. Both screens previously accepted the value on the strength of
 * get_role() alone, so both must be shown to refuse it now -- separately,
 * because they read the setting in two places and only one of them being
 * rewired would leave the other minting administrators.
 */
final class DefaultCustomerRoleWritersTest extends WP_Ajax_UnitTestCase
{
    use UserManagementCapabilities;

	protected $_last_response;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		// create_users is the capability this suite is about, and it is the one
		// capability with TWO multisite paths: super admin, or the network
		// option add_new_users. Turning the option on is the faithful choice
		// here -- it keeps the actor a plain administrator, so the DENIED
		// tests (roles without create_users) stay denied for exactly the
		// reason they always did, and it models the deployment where a site
		// owner really can add customers. No-op on a single site.
		$this->allow_site_admins_to_create_users();

		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Default Customer Role Vehicle',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

		ManualBookingMetaBox::register();

		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

		$settings = get_option(SettingsCore::OPTION_NAME, array());
		if (! is_array($settings)) {
			$settings = array();
		}
		$settings['mhmrentiva_customer_default_role'] = 'administrator';
		update_option(SettingsCore::OPTION_NAME, $settings);
	}

	public function tearDown(): void
	{
		// Defensive: allow_site_admins_to_create_users() works through a hook, and
		// WP_UnitTestCase restores hooks after each test, so this is belt and
		// braces rather than load-bearing. It is kept because a future edit that
		// reaches for update_site_option() again would reintroduce a leak this
		// suite has already been bitten by once.
		$this->forbid_site_admins_from_creating_users();
		delete_option(SettingsCore::OPTION_NAME);

		parent::tearDown();
	}

	public function test_manual_booking_new_customer_does_not_receive_a_privileged_role(): void
	{
		$_POST = array(
			'nonce'                   => wp_create_nonce('mhmrentiva_manual_booking_nonce'),
			'vehicle_id'              => (string) $this->vehicle_id,
			'customer_id'             => 'new_customer',
			'pickup_date'             => '2099-05-01',
			'pickup_time'             => '10:00',
			'dropoff_date'            => '2099-05-03',
			'dropoff_time'            => '10:00',
			'payment_type'            => 'full',
			'status'                  => 'confirmed',
			'new_customer_first_name' => 'Role',
			'new_customer_last_name'  => 'Probe',
			'new_customer_email'      => 'role-probe-manual@example.com',
			'new_customer_phone'      => '5551110000',
		);

		try {
			$this->_handleAjax('mhmrentiva_create_manual_booking');
		} catch (\WPAjaxDieContinueException $e) {
			// Expected path for WP_Ajax_UnitTestCase.
		}

		$created = get_user_by('email', 'role-probe-manual@example.com');
		$this->assertInstanceOf(
			\WP_User::class,
			$created,
			'The booking should still be created; only the role is in question. Response: ' . (string) $this->_last_response
		);
		$this->assertNotContains(
			'administrator',
			(array) $created->roles,
			'The manual booking screen must not hand out a privileged role because the option named one.'
		);
		$this->assertContains('customer', (array) $created->roles);
	}

	public function test_add_customer_page_does_not_receive_a_privileged_role(): void
	{
		$_POST = array(
			'mhmrentiva_add_customer_nonce' => wp_create_nonce('mhmrentiva_add_customer'),
			'customer_name'                 => 'Role Probe Page',
			'customer_email'                => 'role-probe-page@example.com',
			'customer_phone'                => '5552220000',
			'customer_address'              => 'Test',
		);

		ob_start();
		AddCustomerPage::render();
		ob_end_clean();

		$created = get_user_by('email', 'role-probe-page@example.com');
		$this->assertInstanceOf(
			\WP_User::class,
			$created,
			'The Add Customer screen should still create the account; only the role is in question.'
		);
		$this->assertNotContains(
			'administrator',
			(array) $created->roles,
			'The Add Customer screen must not hand out a privileged role because the option named one.'
		);
		$this->assertContains('customer', (array) $created->roles);
	}
}
