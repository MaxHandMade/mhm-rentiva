<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use WP_UnitTestCase;

/**
 * Task A9a seam inversion: Plugin.php (and mhm-rentiva.php) must no longer
 * register ANY Pro-feature class or the license subsystem itself.
 *
 * BEFORE this task, Lite's Plugin.php and Pro's own
 * \MHMRentiva\Pro\Bootstrap both registered the same Pro classes -- gated by
 * `\MHMRentiva\Admin\Licensing\Mode::canUse*()` in Lite and by
 * `\MHMRentiva\Pro\Edition::canUse*()` in Pro. With Pro installed, every one
 * of those ~36 sites in Plugin.php was a DOUBLE registration. Removing
 * Lite's copy both fixes the double-registration and makes Lite WP.org-
 * compliant (a Lite-only tree must never reference a Pro class by FQN
 * outside a class_exists() guard used purely for compatibility, and it must
 * never carry the license subsystem, which is a Pro concern now).
 *
 * This suite runs against the real Lite tree, where Pro is genuinely absent
 * (class_exists() for every Pro class is already false). A behavioural
 * assertion like "the retention cron is not scheduled" therefore proves very
 * little on its own -- it would pass just as happily whether or not Plugin.php
 * still had the (now-removed) gated registration, because the referenced
 * Pro classes never existed in this tree either way. The source-grep
 * assertions below are what actually pins the regression this task fixes:
 * that Plugin.php contains NO reference to Mode::, LicenseManager,
 * LicenseAdmin or VerifyEndpoint at all, not even inside a guarded block.
 * The behavioural assertions are still included because they document the
 * observable contract (and would catch a regression that reintroduced an
 * UNGATED registration of a class that happens to be autoloadable from
 * some other path).
 *
 * @covers \MHMRentiva\Plugin
 */
final class PluginNoProWiringTest extends WP_UnitTestCase
{
    private function plugin_php_source(): string
    {
        $path = defined('MHM_RENTIVA_PLUGIN_DIR')
            ? constant('MHM_RENTIVA_PLUGIN_DIR') . 'src/Plugin.php'
            : dirname(__DIR__, 3) . '/src/Plugin.php';

        $src = @file_get_contents($path);
        $this->assertNotFalse($src, "Could not read Plugin.php at {$path}");

        return (string) $src;
    }

    private function main_file_source(): string
    {
        $path = defined('MHM_RENTIVA_PLUGIN_DIR')
            ? constant('MHM_RENTIVA_PLUGIN_DIR') . 'mhm-rentiva.php'
            : dirname(__DIR__, 3) . '/mhm-rentiva.php';

        $src = @file_get_contents($path);
        $this->assertNotFalse($src, "Could not read mhm-rentiva.php at {$path}");

        return (string) $src;
    }

    // -- Source-structure proof (the real regression pin) --------------------

    public function test_plugin_php_names_no_licensing_mode(): void
    {
        $src = $this->plugin_php_source();

        $this->assertStringNotContainsString(
            'Mode::',
            $src,
            'Plugin.php must not reference \\MHMRentiva\\Admin\\Licensing\\Mode:: at all -- Pro-feature '
            . 'gating is Pro\'s own Bootstrap/Edition responsibility now.'
        );
    }

    public function test_plugin_php_names_no_license_subsystem_classes(): void
    {
        $src = $this->plugin_php_source();

        foreach (array( 'LicenseManager', 'LicenseAdmin', 'VerifyEndpoint' ) as $class) {
            $this->assertStringNotContainsString(
                $class,
                $src,
                "Plugin.php must not reference {$class} -- the license subsystem is registered "
                . 'exclusively by Pro\'s own Bootstrap::register_licensing() now.'
            );
        }
    }

    public function test_plugin_php_no_longer_wires_the_pro_cli_commands(): void
    {
        $src = $this->plugin_php_source();

        foreach (
            array(
                'mhm audit:export',
                'mhm audit:verify',
                'mhm key:revoke',
                'mhm payout:execute-matured',
            ) as $command
        ) {
            $this->assertStringNotContainsString(
                $command,
                $src,
                "Plugin.php must not register the Pro CLI command '{$command}' -- moved to "
                . 'Pro\'s own Bootstrap::register_cli_commands().'
            );
        }

        // Positive control: Lite's own CLI commands must still be wired, or this
        // file's CLI-registration block could have been deleted wholesale rather
        // than surgically edited.
        $this->assertStringContainsString('mhm-rentiva repair-ratings', $src);
        $this->assertStringContainsString('mhm-rentiva layout', $src);
    }

    /**
     * H3 audit fix (inversion holes): Plugin.php used to be the ONLY
     * registrar of HealthController and IntegrityVerificationJob -- both
     * Pro classes, wired with a bare class_exists() guard and no licence
     * gate at all, absent from Pro's own Bootstrap. Removing Lite's blocks
     * (as the seam-inversion design orders) would have silently killed both
     * features had they not first been moved into
     * Bootstrap::register_operational().
     */
    public function test_plugin_php_no_longer_wires_health_and_integrity_job(): void
    {
        $src = $this->plugin_php_source();

        foreach (array( 'HealthController', 'IntegrityVerificationJob' ) as $class) {
            $this->assertStringNotContainsString(
                $class,
                $src,
                "Plugin.php must not reference {$class} -- moved to Pro's own "
                . 'Bootstrap::register_operational() (H3 audit fix).'
            );
        }
    }

    /**
     * Audit finding M2: Plugin.php carried ~12 `class_exists('<Pro vendor
     * class>')` init blocks (VendorApplication, VendorMediaIsolation,
     * VendorOwnershipEnforcer, VendorVehicleReviewManager,
     * VendorNotifications, the VendorReport trio, VendorCancellationDateBlocker,
     * AdminVendorApplicationsPage, VendorReportsController,
     * VendorManagementRestController) that duplicated Pro's own
     * Bootstrap::register_vendor()/register_vendor_reports() wiring. They were
     * runtime-safe (WP de-dups hooks; Pro classes self-gate on Edition) but
     * were dead Lite→Pro wiring left over from before the seam inversion --
     * the last of the kind after H3 removed HealthController/
     * IntegrityVerificationJob. Every one of these classes is registered
     * exclusively by Pro's Bootstrap now.
     */
    public function test_plugin_php_no_longer_wires_pro_vendor_classes(): void
    {
        $src = $this->plugin_php_source();

        foreach (
            array(
                'VendorApplication',
                'VendorMediaIsolation',
                'VendorOwnershipEnforcer',
                'VendorVehicleReviewManager',
                'VendorNotifications',
                'VendorReportAjaxHandler',
                'VendorReportAssets',
                'VendorReportsAdminPage',
                'VendorCancellationDateBlocker',
                'AdminVendorApplicationsPage',
                'VendorReportsController',
                'VendorManagementRestController',
            ) as $class
        ) {
            $this->assertStringNotContainsString(
                $class,
                $src,
                "Plugin.php must not reference {$class} -- moved to Pro's own "
                . 'Bootstrap::register_vendor()/register_vendor_reports() (audit finding M2).'
            );
        }

        // Positive control: Lite's own vendor ROLE registration (not the Pro
        // vendor FEATURE classes above) must survive, or the whole
        // initialize_post_types()/initialize_admin_services() block could have
        // been deleted wholesale rather than surgically edited.
        $this->assertStringContainsString('register_vendor_role', $src);
    }

    public function test_main_file_no_longer_calls_license_manager_deactivation(): void
    {
        $src = $this->main_file_source();

        $this->assertStringNotContainsString(
            'LicenseManager',
            $src,
            'mhm-rentiva.php must not reference LicenseManager -- Pro\'s own '
            . 'mhm-rentiva-pro.php deactivation hook unschedules every Pro cron '
            . '(including the license ones) already, and is a strict superset of '
            . 'LicenseManager::deactivatePluginHook().'
        );

        // Positive control: the rest of the deactivation hook (rewrite flush +
        // log-maintenance cron cleanup) must survive -- both are Lite-native.
        $this->assertStringContainsString('flush_rewrite_rules()', $src);
        $this->assertStringContainsString('LogMaintenanceScheduler', $src);
    }

    // -- Behavioural contract (documents the runtime outcome) ----------------

    public function test_premise_this_tree_has_no_pro_classes(): void
    {
        $this->assertFalse(class_exists('\MHMRentiva\Admin\Licensing\LicenseManager'));
        $this->assertFalse(class_exists('\MHMRentiva\Admin\Privacy\DataRetentionManager'));
        $this->assertFalse(class_exists('\MHMRentiva\Admin\REST\Locations'));
    }

    public function test_no_gdpr_retention_cron_is_scheduled(): void
    {
        $this->assertFalse(
            wp_next_scheduled('mhm_data_retention_cleanup'),
            'A standalone Lite install must never schedule the Pro GDPR retention cron.'
        );
    }

    public function test_no_transfer_locations_rest_route_is_registered(): void
    {
        do_action('rest_api_init');
        $routes = rest_get_server()->get_routes();

        $this->assertArrayNotHasKey(
            '/mhm-rentiva/v1/locations',
            $routes,
            'A standalone Lite install must not expose the Pro transfer Locations REST route.'
        );
    }

    public function test_no_license_verify_rest_route_is_registered(): void
    {
        do_action('rest_api_init');
        $routes = rest_get_server()->get_routes();

        $this->assertArrayNotHasKey(
            '/mhm-rentiva-verify/v1/ping',
            $routes,
            'A standalone Lite install must not expose the licence reverse-validation REST route.'
        );
    }

    /**
     * Positive control for the two REST tests above: Lite's own, non-Pro REST
     * routes must still register, or "no Pro route" could be trivially true
     * because rest_api_init produced nothing at all.
     */
    public function test_own_rest_routes_still_register(): void
    {
        do_action('rest_api_init');
        $routes = rest_get_server()->get_routes();

        $this->assertArrayHasKey('/mhm-rentiva/v1/about', $routes);
    }

    /** H3 audit fix: a standalone Lite install must not expose the Pro health endpoint. */
    public function test_no_health_rest_route_is_registered(): void
    {
        do_action('rest_api_init');
        $routes = rest_get_server()->get_routes();

        $this->assertArrayNotHasKey(
            '/mhm-rentiva/v1/health',
            $routes,
            'A standalone Lite install must not expose the Pro HealthController REST route.'
        );
    }

    /** H3 audit fix: a standalone Lite install must not schedule the Pro integrity-check cron. */
    public function test_no_integrity_check_cron_is_scheduled(): void
    {
        $this->assertFalse(
            wp_next_scheduled('mhm_rentiva_daily_integrity_check'),
            'A standalone Lite install must never schedule the Pro IntegrityVerificationJob cron.'
        );
    }

    /**
     * Audit finding M2 behavioural half: the source-grep in
     * test_plugin_php_no_longer_wires_pro_vendor_classes() pins that Plugin.php
     * names none of the 12 vendor classes, but (per this file's own docblock,
     * ~:23-35) a string-grep alone cannot catch an ungated re-registration of
     * the same class reached from a DIFFERENT autoload path. This is the
     * runtime proxy: VendorReportsController::register_routes() (Pro's
     * REST_NAMESPACE 'mhm-rentiva/v1' + BASE '/vendor-reports') must not
     * appear on a standalone Lite boot regardless of which file tried to wire
     * it. If Plugin.php's deleted class_exists() block were reintroduced (or
     * reintroduced elsewhere) while the class were somehow autoloadable, this
     * would go from PASS to FAIL by picking up the extra route -- see the two
     * adjacent tests (`test_no_transfer_locations_rest_route_is_registered`,
     * `test_no_health_rest_route_is_registered`) for the same pattern.
     */
    public function test_no_vendor_reports_rest_route_is_registered(): void
    {
        do_action('rest_api_init');
        $routes = rest_get_server()->get_routes();

        $this->assertArrayNotHasKey(
            '/mhm-rentiva/v1/vendor-reports',
            $routes,
            'A standalone Lite install must not expose the Pro vendor-reports REST route '
            . '(audit finding M2).'
        );
    }
}
