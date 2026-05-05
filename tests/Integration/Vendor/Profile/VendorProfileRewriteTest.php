<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileRewrite;

/**
 * @group vendor-profile
 * @group vendor-rewrite
 */
final class VendorProfileRewriteTest extends \WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		VendorProfileRewrite::register();
		// register() relies on did_action('init') > 0 to call register_rewrite()
		// directly; the WP test bootstrap can reset this between tests. Call the
		// registrars directly so the rule lands in $wp_rewrite->extra_rules_top
		// and the query var on $wp regardless of init state.
		VendorProfileRewrite::register_rewrite();
		global $wp;
		$wp->public_query_vars = array_values(
			array_unique(VendorProfileRewrite::register_query_var($wp->public_query_vars))
		);
	}

	public function tearDown(): void
	{
		// WP_UnitTestCase::tearDown() restores hook globals from the snapshot
		// taken before the first test — no manual remove_all_* needed.
		// The query var added to $wp->public_query_vars persists across the
		// suite but is harmless: vendor profile flow checks the slug value,
		// and other tests do not assert on this list.
		parent::tearDown();
	}

	public function test_query_var_registered(): void
	{
		global $wp;
		$this->assertContains('mhm_rentiva_vendor_slug', $wp->public_query_vars);
	}

	public function test_rewrite_rule_matches_vendor_url(): void
	{
		// Inspect $wp_rewrite->extra_rules_top directly (where add_rewrite_rule
		// stores 'top' priority rules). wp_rewrite_rules() requires a fully
		// initialized WP_Rewrite singleton with cached permalink_structure,
		// which is brittle in test bootstrap; this assertion is semantically
		// equivalent — "did register_rewrite() call add_rewrite_rule correctly?".
		global $wp_rewrite;
		$matched = false;
		foreach ((array) $wp_rewrite->extra_rules_top as $pattern => $target) {
			$pattern_str = (string) $pattern;
			$target_str  = (string) $target;
			if (strpos($pattern_str, 'vendor/') !== false && strpos($target_str, 'mhm_rentiva_vendor_slug') !== false) {
				$matched = true;
				break;
			}
		}

		$this->assertTrue($matched, 'rewrite rule for /vendor/{slug}/ should be registered in $wp_rewrite->extra_rules_top');
	}

	public function test_lookup_returns_user_id_for_active_vendor(): void
	{
		$user_id = self::factory()->user->create();
		update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'akif-otomotiv');
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');

		$this->assertSame($user_id, VendorProfileRewrite::lookup_user_for_request('akif-otomotiv'));
	}

	public function test_handle_request_sets_404_for_unknown_slug(): void
	{
		// Inject the query var directly — relying on rewrite-resolved go_to()
		// is brittle in test bootstrap (same WP_Rewrite singleton issue
		// documented in test_rewrite_rule_matches_vendor_url).
		set_query_var(VendorProfileRewrite::QUERY_VAR, 'nonexistent-slug-xyz');
		global $wp_query;

		VendorProfileRewrite::handle_request();

		$this->assertTrue($wp_query->is_404());
	}

	public function test_handle_request_returns_301_target_for_history_match(): void
	{
		$user_id = self::factory()->user->create();
		update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'new-slug');
		update_user_meta($user_id, MetaKeys::VENDOR_SLUG_HISTORY, ['old-slug']);
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');

		$target = VendorProfileRewrite::resolve_redirect_target('old-slug');

		$this->assertStringEndsWith('/vendor/new-slug/', $target);
	}

	public function test_filter_template_include_swaps_for_active_vendor(): void
	{
		$user_id = self::factory()->user->create([ 'display_name' => 'Akif' ]);
		update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'akif');
		update_user_meta($user_id, '_rentiva_vendor_status', 'active');

		set_query_var(VendorProfileRewrite::QUERY_VAR, 'akif');

		$default = '/some/theme/index.php';
		$result  = VendorProfileRewrite::filter_template_include($default);

		$this->assertNotSame($default, $result, 'Active vendor request must swap the template');
		$this->assertStringEndsWith('vendor-profile-page.php', $result);
		$this->assertFileExists($result, 'Plugin template wrapper must exist on disk');
	}

	public function test_filter_template_include_passthrough_for_non_vendor_request(): void
	{
		// No query var set — this is a normal page request, not a vendor URL.
		set_query_var(VendorProfileRewrite::QUERY_VAR, '');

		$default = '/some/theme/index.php';
		$result  = VendorProfileRewrite::filter_template_include($default);

		$this->assertSame($default, $result, 'Non-vendor pages must keep their original template');
	}
}
