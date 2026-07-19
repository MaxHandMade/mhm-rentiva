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
}
