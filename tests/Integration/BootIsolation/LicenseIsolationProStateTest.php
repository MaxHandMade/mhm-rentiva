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
 * Validates Pro boot surface registrations for Vendor/Payout modules.
 */
final class LicenseIsolationProStateTest extends WP_UnitTestCase
{
	private function forceProLicenseState(): void
	{
		update_option(
			LicenseManager::OPTION,
			array(
				'key'           => 'TEST-PRO-KEY',
				'status'        => 'active',
				'plan'          => 'pro',
				'activation_id' => 'test-activation-id',
				'expires_at'    => time() + DAY_IN_SECONDS,
			),
			false
		);
		update_option('mhm_rentiva_disable_dev_mode', 1, false);
		set_transient('mhm_rentiva_license_status_' . md5('TEST-PRO-KEY' . 'test-activation-id'), 1, 30);
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

	public function setUp(): void
	{
		parent::setUp();

		$this->forceProLicenseState();
		$this->resetVendorPayoutRuntimeSurface();
		$this->registerVendorPayoutSurfaceForCurrentLicense();
	}

	public function test_pro_boot_registers_vendor_payout_surface(): void
	{
		$this->assertTrue(
			post_type_exists('mhm_payout'),
			'Pro: Payout CPT should be registered.'
		);

		global $shortcode_tags;
		$this->assertArrayHasKey(
			'rentiva_vendor_ledger',
			$shortcode_tags,
			'Pro: Vendor ledger shortcode should be registered.'
		);

		$routes        = rest_get_server()->get_routes();
		$payout_routes = array_filter(
			array_keys($routes),
			static fn (string $route): bool => str_contains($route, 'payout')
		);

		$this->assertNotEmpty(
			$payout_routes,
			'Pro: Payout REST route should be registered.'
		);

		$timestamp = wp_next_scheduled('mhm_rentiva_process_matured_payouts');

		$this->assertNotFalse(
			$timestamp,
			'Pro: Payout cron should be scheduled.'
		);
	}
}
