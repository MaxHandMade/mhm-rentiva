<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\TestEnvironment;

use WP_UnitTestCase;

/**
 * WordPress's update checks must never reach the network during the suite.
 *
 * WP_Ajax_UnitTestCase runs admin_init, which can fire wp_version_check(),
 * wp_update_plugins() and wp_update_themes(). Where api.wordpress.org is
 * unreachable (CI runners at times) WordPress raises a warning, and the
 * warning text lands inside the AJAX JSON under test, failing an unrelated
 * test (seen twice on 2026-10-20: RefundEventContractTest and
 * BookingFormServerRulesTest, on one PHP version only).
 *
 * The bootstrap's "recently checked" transient lacked version_checked, which
 * wp_version_check() compares with the running version before it trusts the
 * transient, so the check ran anyway.
 */
final class UpdateChecksStayOfflineTest extends WP_UnitTestCase
{
	public function test_the_core_update_transient_is_trusted_by_wp_version_check(): void
	{
		$current = get_site_transient('update_core');

		$this->assertIsObject($current);
		$this->assertSame(wp_get_wp_version(), $current->version_checked ?? null);
	}

	public function test_requests_to_wordpress_org_never_leave_the_suite(): void
	{
		$response = wp_remote_get('https://api.wordpress.org/core/version-check/1.7/');

		$this->assertIsArray($response, 'The request must be answered locally, not fail on the network.');
		$this->assertSame('mhmrentiva-test-offline', wp_remote_retrieve_header($response, 'x-mhmrentiva-test'));
	}

	public function test_forced_update_checks_raise_no_warning(): void
	{
		// phpunit.xml does not turn warnings into exceptions, so collect them.
		$raised = array();
		set_error_handler(
			static function (int $errno, string $errstr, string $errfile, int $errline) use (&$raised): bool {
				$raised[] = $errstr . ' @ ' . basename($errfile) . ':' . $errline;
				return true;
			}
		);
		try {
			wp_version_check(array(), true);
			wp_update_plugins();
			wp_update_themes();
		} finally {
			restore_error_handler();
		}

		$this->assertSame(array(), $raised);
	}
}
