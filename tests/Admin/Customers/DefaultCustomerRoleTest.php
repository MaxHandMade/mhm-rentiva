<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * `mhmrentiva_customer_default_role` decides the role this plugin hands to
 * every account it creates. Two screens read it, and both checked only that
 * `get_role()` returned something -- which 'administrator' does.
 *
 * The setting has no UI, no entry in the defaults map and no sanitiser, so
 * nothing between a value and the accounts it creates ever looked at it. It is
 * reachable through update_option(), WP-CLI and any filter that touches the
 * settings array, which is thin cover for handing out a role by request.
 *
 * The sweep that produced this test also found the part the earlier record
 * missed: the same option answers "is this account a customer?" in
 * CustomerIdentity, once in PHP and once in SQL. A privileged value there does
 * not just mint privileged accounts -- it makes the Customers list, and the
 * delete guard built on it, treat every administrator as a customer. That is
 * why the resolver lives beside those two readers instead of in either screen.
 *
 * The rule is a deny list of privileged capabilities rather than "may hold
 * nothing but read": a site that added a harmless capability to its customer
 * role keeps working, and nothing that can edit users, manage options or
 * publish content gets handed out.
 */
final class DefaultCustomerRoleTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option(SettingsCore::OPTION_NAME);
		remove_role('mhmrentiva_test_roomy_customer');
		remove_role('mhmrentiva_test_publisher');
		CustomerIdentity::flush_memo();

		parent::tearDown();
	}

	private function configure_role(string $role): void
	{
		$settings = get_option(SettingsCore::OPTION_NAME, array());
		if (! is_array($settings)) {
			$settings = array();
		}
		$settings['mhmrentiva_customer_default_role'] = $role;
		update_option(SettingsCore::OPTION_NAME, $settings);
	}

	public function test_unset_option_resolves_to_customer(): void
	{
		$this->assertSame('customer', CustomerIdentity::default_customer_role());
	}

	public function test_administrator_is_refused_and_falls_back_to_customer(): void
	{
		$this->configure_role('administrator');

		$this->assertSame(
			'customer',
			CustomerIdentity::default_customer_role(),
			'A role holding manage_options must never be handed to an account this plugin creates.'
		);
	}

	public function test_editor_is_refused_for_holding_content_capabilities(): void
	{
		$this->configure_role('editor');

		$this->assertSame('customer', CustomerIdentity::default_customer_role());
	}

	public function test_role_that_can_promote_users_is_refused(): void
	{
		add_role(
			'mhmrentiva_test_publisher',
			'MHM Test Publisher',
			array(
				'read'          => true,
				'promote_users' => true,
			)
		);
		$this->configure_role('mhmrentiva_test_publisher');

		$this->assertSame('customer', CustomerIdentity::default_customer_role());
	}

	public function test_role_that_does_not_exist_falls_back_to_customer(): void
	{
		$this->configure_role('mhmrentiva_role_that_was_deleted');

		$this->assertSame('customer', CustomerIdentity::default_customer_role());
	}

	/**
	 * The negative control: the guard must not collapse to always answering
	 * 'customer'. A site is free to configure its own unprivileged role, and
	 * extra harmless capabilities do not disqualify it.
	 */
	public function test_unprivileged_configured_role_is_honoured(): void
	{
		add_role(
			'mhmrentiva_test_roomy_customer',
			'MHM Test Roomy Customer',
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);
		$this->configure_role('mhmrentiva_test_roomy_customer');

		$this->assertSame(
			'mhmrentiva_test_roomy_customer',
			CustomerIdentity::default_customer_role(),
			'A configured role with no privileged capability must be honoured, or the setting means nothing.'
		);
	}

	public function test_subscriber_is_honoured(): void
	{
		$this->configure_role('subscriber');

		$this->assertSame('subscriber', CustomerIdentity::default_customer_role());
	}
}
