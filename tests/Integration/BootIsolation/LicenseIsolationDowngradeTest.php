<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\BootIsolation;

use MHMRentiva\Admin\Frontend\Shortcodes\Account\VendorLedger;
use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Api\REST\PayoutCallbackController;
use MHMRentiva\Core\Financial\Automation\MaturedPayoutJob;
use WP_UnitTestCase;

/**
 * Validates Pro -> Lite downgrade isolation across runtime surfaces.
 */
final class LicenseIsolationDowngradeTest extends WP_UnitTestCase
{
	private function forceProLicenseState(): void
	{
		update_option(
			LicenseManager::OPTION,
			array(
				'key'           => 'TEST-PRO-KEY-DOWNGRADE',
				'status'        => 'active',
				'plan'          => 'pro',
				'activation_id' => 'test-activation-downgrade',
				'expires_at'    => time() + DAY_IN_SECONDS,
			),
			false
		);
		update_option('mhm_rentiva_disable_dev_mode', 1, false);
		set_transient('mhm_rentiva_license_status_' . md5('TEST-PRO-KEY-DOWNGRADE' . 'test-activation-downgrade'), 1, 30);
	}

	private function forceLiteLicenseState(): void
	{
		update_option(LicenseManager::OPTION, array(), false);
		update_option('mhm_rentiva_disable_dev_mode', 1, false);
		delete_transient('mhm_rentiva_license_status_' . md5('TEST-PRO-KEY-DOWNGRADE' . 'test-activation-downgrade'));
	}

	private function resetVendorPayoutRuntimeSurface(): void
	{
		remove_action('init', array(PostType::class, 'register_post_type'));
		remove_action('rest_api_init', array(PayoutCallbackController::class, 'register_route'));
		remove_action('mhm_rentiva_process_matured_payouts', array(MaturedPayoutJob::class, 'run'));

		wp_clear_scheduled_hook('mhm_rentiva_process_matured_payouts');
		remove_shortcode('rentiva_vendor_ledger');

		if (post_type_exists('mhm_payout') && function_exists('unregister_post_type')) {
			unregister_post_type('mhm_payout');
		}
		unset($GLOBALS['wp_post_types']['mhm_payout']);
		$GLOBALS['wp_rest_server'] = null;
	}

	private function registerVendorPayoutSurfaceForCurrentLicense(): void
	{
		if (! Mode::canUseVendorPayout()) {
			return;
		}

		PostType::register_post_type();
		PayoutCallbackController::register();
		MaturedPayoutJob::register();
		VendorLedger::register();
		do_action('rest_api_init');
	}

	public function test_downgrade_removes_vendor_payout_surface(): void
	{
		// Start in Pro and verify module surfaces exist.
		$this->forceProLicenseState();
		$this->resetVendorPayoutRuntimeSurface();
		$this->registerVendorPayoutSurfaceForCurrentLicense();

		$this->assertTrue(post_type_exists('mhm_payout'));

		global $shortcode_tags;
		$this->assertArrayHasKey('rentiva_vendor_ledger', $shortcode_tags);

		$pro_routes = rest_get_server()->get_routes();
		$this->assertNotEmpty(
			array_filter(array_keys($pro_routes), static fn (string $route): bool => str_contains($route, 'payout'))
		);
		$this->assertNotFalse(wp_next_scheduled('mhm_rentiva_process_matured_payouts'));

		// Downgrade to Lite and re-run deterministic surface registration.
		$this->forceLiteLicenseState();
		$this->resetVendorPayoutRuntimeSurface();
		$this->registerVendorPayoutSurfaceForCurrentLicense();

		$this->assertFalse(
			post_type_exists('mhm_payout'),
			'Lite: Payout CPT should NOT be registered.'
		);

		$this->assertArrayNotHasKey(
			'rentiva_vendor_ledger',
			$shortcode_tags,
			'Lite: Vendor ledger shortcode should NOT be registered.'
		);

		$routes        = rest_get_server()->get_routes();
		$payout_routes = array_filter(
			array_keys($routes),
			static fn (string $route): bool => str_contains($route, 'payout')
		);

		$this->assertEmpty(
			$payout_routes,
			'Lite: Payout REST route should NOT exist.'
		);

		$timestamp = wp_next_scheduled('mhm_rentiva_process_matured_payouts');

		$this->assertFalse(
			$timestamp,
			'Lite: Payout cron should NOT be scheduled.'
		);
	}
}
