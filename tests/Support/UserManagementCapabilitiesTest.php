<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use WP_UnitTestCase;

/**
 * The probe for UserManagementCapabilities.
 *
 * A helper that quietly does nothing would turn 39 red tests green by making
 * them assert less, which is the failure mode this whole task exists to avoid.
 * So the helper is pinned from BOTH sides in BOTH modes:
 *
 * - the negative control proves the capability really is denied before the
 *   helper runs (on a network), so a passing "allowed" test cannot be passing
 *   for the wrong reason;
 * - the positive control proves the helper actually grants it.
 *
 * On a single site the same file proves the helper is a no-op and that role
 * capabilities were the whole contract all along.
 */
final class UserManagementCapabilitiesTest extends WP_UnitTestCase
{
	use UserManagementCapabilities;

	/**
	 * A role carrying the three user-management capabilities outright. On a
	 * single site this is a complete grant; on a network core overrides it.
	 */
	private function makeCapableUser(): int
	{
		remove_role('mhmrentiva_probe_user_manager');
		add_role(
			'mhmrentiva_probe_user_manager',
			'MHM Probe User Manager',
			array(
				'read'         => true,
				'create_users' => true,
				'edit_users'   => true,
				'delete_users' => true,
			)
		);

		return self::factory()->user->create(array('role' => 'mhmrentiva_probe_user_manager'));
	}

	public function tearDown(): void
	{
		remove_role('mhmrentiva_probe_user_manager');
		$this->forbid_site_admins_from_creating_users();
		wp_set_current_user(0);
		parent::tearDown();
	}

	// --- Negative control: the network really does override role caps -------

	public function test_on_a_network_role_capabilities_alone_do_not_grant_user_management(): void
	{
		$this->requireMultisite();

		wp_set_current_user($this->makeCapableUser());

		$this->assertFalse(
			current_user_can('edit_users'),
			'Core rewrites edit_users to do_not_allow without manage_network_users. If this passes, the suite is not really running as multisite and every "allowed" assertion below proves nothing.'
		);
		$this->assertFalse(current_user_can('delete_users'), 'delete_users is super-admin-only on a network.');
		$this->assertFalse(current_user_can('create_users'), 'create_users needs a super admin or the add_new_users network option.');
	}

	// --- Positive control: the helper grants what the mode requires ---------

	public function test_the_helper_grants_user_management_on_a_network(): void
	{
		$this->requireMultisite();

		$user_id = $this->makeCapableUser();
		wp_set_current_user($user_id);
		$this->grant_user_management_privilege($user_id);

		$this->assertTrue(current_user_can('edit_users'), 'The helper must make edit_users hold.');
		$this->assertTrue(current_user_can('delete_users'), 'The helper must make delete_users hold.');
		$this->assertTrue(current_user_can('create_users'), 'A super admin also clears create_users.');
	}

	// --- The narrow grant keeps the route-gate tests meaningful -------------

	public function test_manage_network_users_clears_edit_users_without_super_admin(): void
	{
		$this->requireMultisite();

		$user_id = $this->makeCapableUser();
		wp_set_current_user($user_id);
		$this->assertFalse(current_user_can('edit_users'), 'Precondition: denied before the grant.');

		$this->grant_network_user_editing($user_id);

		$this->assertTrue(current_user_can('edit_users'), 'manage_network_users satisfies core edit_users branch.');
		$this->assertFalse(
			is_super_admin($user_id),
			'The whole point of this grant is that it is NOT super admin -- otherwise the route-gate tests would pass for the wrong reason.'
		);
		$this->assertFalse(
			current_user_can('manage_options'),
			'The actor must still lack manage_options, or "edit_users alone is sufficient" proves nothing.'
		);
		$this->assertFalse(current_user_can('list_users'), 'And still lack list_users.');
		$this->assertFalse(
			current_user_can('delete_users'),
			'delete_users has no capability path on a network; only a super admin clears it.'
		);
	}


	// --- create_users has a SECOND path, and it is the realistic one --------

	public function test_the_network_option_lets_a_plain_site_admin_create_users(): void
	{
		$this->requireMultisite();

		wp_set_current_user($this->makeCapableUser());
		$this->assertFalse(current_user_can('create_users'), 'Precondition: denied while the option is off.');

		$this->allow_site_admins_to_create_users();

		$this->assertTrue(
			current_user_can('create_users'),
			'add_new_users is create_users second multisite path -- a site admin may add users when the network allows it.'
		);
		$this->assertFalse(
			current_user_can('edit_users'),
			'The option is scoped to creation only; it must not leak into edit_users.'
		);
		$this->assertFalse(current_user_can('delete_users'), 'Nor into delete_users.');
	}

	public function test_forbidding_puts_the_network_option_back(): void
	{
		$this->requireMultisite();

		wp_set_current_user($this->makeCapableUser());
		$this->allow_site_admins_to_create_users();
		$this->assertTrue(current_user_can('create_users'), 'Precondition: the option is on.');

		$this->forbid_site_admins_from_creating_users();

		$this->assertFalse(
			current_user_can('create_users'),
			'Network options survive the per-test transaction rollback, so the teardown has to actually reverse this one.'
		);
	}

	// --- Single site: role caps were the whole contract ---------------------

	public function test_on_a_single_site_role_capabilities_are_the_whole_contract(): void
	{
		$this->requireSingleSite();

		wp_set_current_user($this->makeCapableUser());

		$this->assertTrue(current_user_can('edit_users'));
		$this->assertTrue(current_user_can('delete_users'));
		$this->assertTrue(current_user_can('create_users'));
	}

	public function test_on_a_single_site_the_helper_changes_nothing(): void
	{
		$this->requireSingleSite();

		$user_id = self::factory()->user->create(array('role' => 'subscriber'));
		wp_set_current_user($user_id);

		$this->grant_user_management_privilege($user_id);
		$this->allow_site_admins_to_create_users();

		$this->assertFalse(
			current_user_can('edit_users'),
			'The helper must not hand out capabilities on a single site -- it exists to match the mode, not to escalate.'
		);
		$this->assertFalse(current_user_can('create_users'));
	}
}
