<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * The user-management capabilities this plugin gates its Customers surface on
 * do not mean the same thing on a single site and on a network.
 *
 * Read from wp-includes/capabilities.php (WP 7.1), map_meta_cap():
 *
 * - `edit_users`   -- on multisite the caller must also pass
 *                     `user_can( $user, 'manage_network_users' )`, otherwise the
 *                     capability resolves to `do_not_allow`.
 * - `delete_users` -- on multisite `is_super_admin()` is the ONLY path; there is
 *                     no capability a site role can carry that substitutes.
 * - `create_users` -- on multisite there are TWO paths: `is_super_admin()`, or
 *                     the network option `add_new_users` being on, which is the
 *                     network admin's switch for "site admins may add users".
 *
 * So `add_role( ..., array( 'edit_users' => true ) )` is a complete grant on a
 * single site and a no-op on a network. Tests that assert the allowed side of
 * these gates have to ask for the privilege the CURRENT mode actually requires,
 * or they are not testing the product -- they are testing core's multisite
 * rewrite. Tests that assert the denied side must NOT use these helpers.
 */
trait UserManagementCapabilities
{
	/**
	 * Give $user_id the privilege that makes `edit_users` and `delete_users`
	 * hold in the current mode.
	 *
	 * Single site: role capabilities already are the whole contract -- no-op.
	 * Multisite: only a super admin clears both, so grant that.
	 *
	 * Call this AFTER `wp_set_current_user()`; it refreshes the current user so
	 * the new privilege is live for the rest of the test.
	 */
	/**
	 * Logins this test has made super admins, for the pre_site_option filter.
	 *
	 * @var string[]
	 */
	private array $mhm_super_admin_logins = array();

	/**
	 * User IDs this test has given manage_network_users, for the user_has_cap
	 * filter. Deliberately not written to the database -- see the note in
	 * grant_network_user_editing().
	 *
	 * @var int[]
	 */
	private array $mhm_network_user_editors = array();

	protected function grant_user_management_privilege(int $user_id): void
	{
		if (! is_multisite()) {
			return;
		}

		$user = get_userdata($user_id);
		if (! $user) {
			return;
		}

		// Filtered rather than granted through grant_super_admin(), for the same
		// reason allow_site_admins_to_create_users() is filtered: that function
		// writes the `site_admins` network option, and this suite commits the
		// PHPUnit transaction mid-test, so the write can outlive the test that
		// made it. The option stores user LOGINS, and the factory recycles
		// logins once the rollback frees the IDs -- so a leaked entry does not
		// merely linger, it silently promotes an unrelated later user to super
		// admin and turns real denials green. Hooks are restored after every
		// test; network options are not.
		$this->mhm_super_admin_logins[] = $user->user_login;

		if (! has_filter('pre_site_option_site_admins', array($this, 'mhm_filter_super_admins'))) {
			add_filter('pre_site_option_site_admins', array($this, 'mhm_filter_super_admins'));
		}

		// Capability results are cached on the WP_User object that
		// wp_set_current_user() built, so re-set the current user to pick the
		// new privilege up.
		if (get_current_user_id() === $user_id) {
			wp_set_current_user(0);
			wp_set_current_user($user_id);
		}
	}

	/**
	 * @return string[]
	 */
	public function mhm_filter_super_admins(): array
	{
		return $this->mhm_super_admin_logins;
	}

	/**
	 * Give $user_id exactly what core asks for on the `edit_users` branch --
	 * the `manage_network_users` capability -- WITHOUT making them a super
	 * admin.
	 *
	 * This matters for the route-gate tests. Those assert "edit_users ALONE is
	 * sufficient, manage_options is not", and a super admin holds every
	 * capability, so granting one would make them pass for a reason that has
	 * nothing to do with the gate under test. `manage_network_users` is the
	 * narrowest grant that satisfies core's multisite branch, so the assertion
	 * keeps its edge: the actor still has no `manage_options` and no
	 * `list_users`.
	 *
	 * Note this deliberately does NOT clear `delete_users`, which core gates on
	 * `is_super_admin()` with no capability path at all.
	 */
	protected function grant_network_user_editing(int $user_id): void
	{
		if (! is_multisite()) {
			return;
		}

		// Filtered rather than granted with WP_User::add_cap(), which writes the
		// wp_capabilities user meta. That write has the same escape route as the
		// network options above -- this suite commits the PHPUnit transaction
		// mid-test, so the meta can survive while the rollback frees the user ID
		// for the factory to hand to somebody else. An inherited
		// manage_network_users is exactly the kind of leak that turns a real
		// denial green somewhere far away, so nothing is written at all.
		$this->mhm_network_user_editors[] = $user_id;

		if (! has_filter('user_has_cap', array($this, 'mhm_filter_network_user_editing'))) {
			add_filter('user_has_cap', array($this, 'mhm_filter_network_user_editing'), 10, 4);
		}

		if (get_current_user_id() === $user_id) {
			wp_set_current_user(0);
			wp_set_current_user($user_id);
		}
	}

	/**
	 * @param array<string,bool> $allcaps
	 * @param string[]           $caps
	 * @param array<int,mixed>   $args
	 * @return array<string,bool>
	 */
	public function mhm_filter_network_user_editing(array $allcaps, array $caps, array $args, \WP_User $user): array
	{
		if (in_array((int) $user->ID, $this->mhm_network_user_editors, true)) {
			$allcaps['manage_network_users'] = true;
		}

		return $allcaps;
	}

	/**
	 * Turn on the network switch that lets a plain site administrator create
	 * users, which is `create_users`' second multisite path.
	 *
	 * This is the deployment where the product's "add a customer" flow works
	 * for a site owner who is not a super admin, so it is worth covering on its
	 * own rather than folding every create_users test into super admin.
	 *
	 * Single site: no-op, the option has no meaning there.
	 */
	protected function allow_site_admins_to_create_users(): void
	{
		if (! is_multisite()) {
			return;
		}

		// Filtered, not written. The obvious spelling --
		// update_site_option( 'add_new_users', 1 ) in setUp and 0 in tearDown --
		// leaks, and it leaks in a way that only shows up in a full-suite run:
		// this suite exercises code that COMMITs the PHPUnit transaction
		// mid-test (Locker.php), so the setUp write survives while the tearDown
		// write is what the rollback undoes. Net effect: the option stays ON for
		// every later class, and genuine create_users denials quietly go green.
		// Measured, not guessed -- it turned two probe assertions red.
		//
		// A filter cannot leak: WP_UnitTestCase restores hooks after every test,
		// and nothing touches the database. get_network_option() returns the
		// pre_site_option_* value as soon as it is not false.
		add_filter('pre_site_option_add_new_users', '__return_true');
	}

	/**
	 * Undo allow_site_admins_to_create_users() within a test.
	 *
	 * Rarely needed -- the hook is restored automatically after each test -- but
	 * a test that wants to observe both sides of the switch needs it.
	 */
	protected function forbid_site_admins_from_creating_users(): void
	{
		if (! is_multisite()) {
			return;
		}

		remove_filter('pre_site_option_add_new_users', '__return_true');
	}

	/**
	 * Assert the account is gone as far as THIS SITE is concerned.
	 *
	 * `wp_delete_user()` does not mean the same thing in the two modes, read
	 * from wp-admin/includes/user.php (WP 7.1): on a single site it deletes the
	 * row from `wp_users`; on a network users are network objects, so it calls
	 * `remove_user_from_blog()` and the row survives on purpose.
	 *
	 * `assertFalse( get_user_by( 'id', ... ) )` therefore states the contract on
	 * one mode and a falsehood on the other.
	 */
	protected function assertAccountRemovedFromSite(int $user_id, string $message = ''): void
	{
		if (is_multisite()) {
			$this->assertFalse(
				is_user_member_of_blog($user_id, get_current_blog_id()),
				$message . ' (multisite: the account is removed from the site, not from the network -- wp_delete_user() calls remove_user_from_blog().)'
			);

			return;
		}

		$this->assertFalse(get_user_by('id', $user_id), $message);
	}

	/**
	 * The other direction, and the one that matters more.
	 *
	 * On a network `get_user_by( 'id', ... )` is true for every account that was
	 * ever created, so an untouched `assertNotFalse( get_user_by( ... ) )` is a
	 * free pass: it would keep reporting green with the guard it exists to
	 * protect deleted outright. Assert site membership, which is what the
	 * delete actually takes away.
	 */
	protected function assertAccountStillOnSite(int $user_id, string $message = ''): void
	{
		$this->assertNotFalse(get_user_by('id', $user_id), $message);

		if (is_multisite()) {
			$this->assertTrue(
				is_user_member_of_blog($user_id, get_current_blog_id()),
				$message . ' (multisite: the row survives for everyone, so site membership is the assertion with teeth.)'
			);
		}
	}

	/**
	 * Skip a test that only has meaning on a network.
	 */
	protected function requireMultisite(): void
	{
		if (! is_multisite()) {
			$this->markTestSkipped('Multisite-only: this asserts core\'s network capability rewrite.');
		}
	}

	/**
	 * Skip a test that only has meaning on a single site.
	 */
	protected function requireSingleSite(): void
	{
		if (is_multisite()) {
			$this->markTestSkipped('Single-site-only: on a network core rewrites these capabilities.');
		}
	}
}
