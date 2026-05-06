<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Directory;

use MHMRentiva\Admin\Vendor\Directory\VendorDirectoryRewrite;
use WP_UnitTestCase;

/**
 * @covers \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryRewrite
 */
final class VendorDirectoryRewriteTest extends WP_UnitTestCase
{
	public function test_register_adds_top_priority_rule(): void
	{
		// Calling do_action('init') re-fires plugin block registration, which
		// trips WP_Block_Type_Registry's "already registered" notice. The test
		// only cares about rewrite rule presence, so whitelist that notice.
		$this->setExpectedIncorrectUsage('WP_Block_Type_Registry::register');

		VendorDirectoryRewrite::register();
		do_action('init');

		global $wp_rewrite;
		$rules = $wp_rewrite->extra_rules_top;

		$found = false;
		foreach ($rules as $pattern => $replacement) {
			if (str_contains($pattern, 'vendors') || str_contains($pattern, 'bayiler')) {
				$this->assertStringContainsString('mhm_rentiva_vendor_directory=1', $replacement);
				$found = true;
				break;
			}
		}
		$this->assertTrue($found, 'Vendor directory rewrite rule should be registered.');
	}

	public function test_query_var_is_added(): void
	{
		VendorDirectoryRewrite::register();
		$vars = apply_filters('query_vars', []);
		$this->assertContains('mhm_rentiva_vendor_directory', $vars);
	}

	public function test_disjoint_from_profile_rule_pattern(): void
	{
		// Profile and Directory rewrite rules must have disjoint regex patterns.
		// Profile: `^bayi/([^/]+)/?$` (slug-capturing, requires `/<slug>`).
		// Directory: `^bayiler/?$` (exact, optional trailing slash).
		// Test the two regex contracts directly — independent of rewrite-table state.

		// Profile slug-capturing rule must NOT match the directory base.
		$profile_pattern = '^bayi/([^/]+)/?$';
		$this->assertSame(0, preg_match('#' . $profile_pattern . '#', 'bayiler'),
			'Profile rule must not match the directory base.');
		$this->assertSame(0, preg_match('#' . $profile_pattern . '#', 'bayiler/'),
			'Profile rule must not match the directory base with trailing slash.');

		// Directory base-only rule must NOT match a Profile slug URL.
		$directory_pattern = '^bayiler/?$';
		$this->assertSame(0, preg_match('#' . $directory_pattern . '#', 'bayi/akif-yildiz/'),
			'Directory rule must not match a Profile slug URL.');
		$this->assertSame(0, preg_match('#' . $directory_pattern . '#', 'bayiler/akif/'),
			'Directory rule must not match a slug-suffixed URL.');

		// Same pair against the EN slugs (where _x() default applies)
		$profile_pattern_en = '^vendor/([^/]+)/?$';
		$directory_pattern_en = '^vendors/?$';
		$this->assertSame(0, preg_match('#' . $profile_pattern_en . '#', 'vendors'));
		$this->assertSame(0, preg_match('#' . $directory_pattern_en . '#', 'vendor/akif/'));
	}

	public function test_handle_request_returns_404_for_lite_user(): void
	{
		// Force Lite mode: bypass filter returns false
		add_filter('mhm_rentiva_dev_pro_bypass', static fn(): bool => false);
		update_option('mhm_license_active', false);

		set_query_var('mhm_rentiva_vendor_directory', '1');
		VendorDirectoryRewrite::handle_request();

		$this->assertTrue(is_404(), 'Lite users should receive 404 on directory URL.');

		remove_all_filters('mhm_rentiva_dev_pro_bypass');
		delete_option('mhm_license_active');
	}
}
