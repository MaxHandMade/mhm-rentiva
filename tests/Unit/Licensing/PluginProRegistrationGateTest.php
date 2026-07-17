<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use WP_UnitTestCase;

/**
 * Every Pro-only class registration in Lite's Plugin.php (and the UserDashboard
 * shortcode) must be bound to the licence gate, not to class_exists() alone.
 *
 * WHY THIS IS A SOURCE-STRUCTURE TEST, NOT A BEHAVIOURAL ONE.
 *
 * The classes these lines register (ListingExpiryJob, VehicleLifecycleManager,
 * PayoutStatementController, ...) live ONLY in Pro. In the Lite test tree they are
 * genuinely absent, so class_exists() is already false and nothing registers. A
 * behavioural assertion ("the listing-expiry cron is not scheduled") therefore
 * passes just as happily with the Mode gate REVERTED -- the exact tautology called
 * out in UnlicensedProSeamGateTest. The only shape that fails when the gate is
 * removed is "class present, licence absent", which cannot be reproduced here
 * because the class is not present.
 *
 * The gap that let F1 ship was a MISSING gate in source: two plugins register the
 * same Pro classes, the effective gate is the weaker door, and Lite's door asked
 * only class_exists(). So the regression this must pin is precisely a source
 * regression -- a registration whose licence gate was dropped. Each assertion below
 * requires the gate call and the class_exists() for the SAME class to appear as one
 * `Mode::canUse...() && ... class_exists('...Class')` clause. Delete the gate term
 * and the clause no longer matches: the test fails. A positive control asserts the
 * class is still referenced at all, so a rename fails loudly instead of masquerading
 * as "gated".
 *
 * @coversNothing
 */
final class PluginProRegistrationGateTest extends WP_UnitTestCase
{
    /** @return array<string, array{0:string,1:string}> feature-method => class basename */
    private function marketplace_gated_registrations(): array
    {
        return array(
            // Twice-daily cron that irreversibly expires/withdraws vendor listings.
            'ListingExpiryJob'              => array( 'canUseVendorMarketplace', 'ListingExpiryJob' ),
            'ListingExpiryWarningJob'       => array( 'canUseVendorMarketplace', 'ListingExpiryWarningJob' ),
            'VehicleLifecycleManager'       => array( 'canUseVendorMarketplace', 'VehicleLifecycleManager' ),
            'ListingFeeManager'             => array( 'canUseVendorMarketplace', 'ListingFeeManager' ),
            // wp_ajax_mhm_vehicle_lifecycle_{pause,resume,withdraw,renew,relist}.
            'VehicleLifecycleAjaxController' => array( 'canUseVendorMarketplace', 'VehicleLifecycleAjaxController' ),
            'LifecycleMetaBox'              => array( 'canUseVendorMarketplace', 'LifecycleMetaBox' ),
            'VendorReliabilityColumn'       => array( 'canUseVendorMarketplace', 'VendorReliabilityColumn' ),
            // Hourly commission clearing.
            'CommissionClearingJob'         => array( 'canUseVendorMarketplace', 'CommissionClearingJob' ),
        );
    }

    private function plugin_php(): string
    {
        $path = defined('MHM_RENTIVA_PLUGIN_DIR')
            ? constant('MHM_RENTIVA_PLUGIN_DIR') . 'src/Plugin.php'
            : dirname(__DIR__, 3) . '/src/Plugin.php';

        $src = @file_get_contents($path);
        $this->assertNotFalse($src, "Could not read Plugin.php at {$path}");

        return (string) $src;
    }

    private function user_dashboard_php(): string
    {
        $path = defined('MHM_RENTIVA_PLUGIN_DIR')
            ? constant('MHM_RENTIVA_PLUGIN_DIR') . 'src/Admin/Frontend/Shortcodes/Account/UserDashboard.php'
            : dirname(__DIR__, 3) . '/src/Admin/Frontend/Shortcodes/Account/UserDashboard.php';

        $src = @file_get_contents($path);
        $this->assertNotFalse($src, "Could not read UserDashboard.php at {$path}");

        return (string) $src;
    }

    /**
     * Asserts `Mode::<method>() && ... class_exists('...<basename>')` appears as one
     * clause. `[^;{}]*?` spans the newline+whitespace the real code uses but stops at
     * a statement boundary, so it cannot straddle two unrelated `if` heads.
     */
    private function assertGateBindsClass(string $src, string $method, string $basename, string $where): void
    {
        // Positive control: the class must still be referenced, or the gate
        // assertion below could pass vacuously after a rename/removal.
        $this->assertMatchesRegularExpression(
            '/class_exists\(\s*\'[^\']*' . preg_quote($basename, '/') . '\'/',
            $src,
            "Positive control failed: {$basename} is no longer registered in {$where} at all."
        );

        $pattern = '/Mode::' . preg_quote($method, '/')
            . '\(\)\s*&&\s*[^;{}]*?class_exists\(\s*\'[^\']*' . preg_quote($basename, '/') . '\'/s';

        $this->assertMatchesRegularExpression(
            $pattern,
            $src,
            "{$basename} registration in {$where} is not gated by Mode::{$method}(). "
            . 'A Pro-only registration behind class_exists() alone is defeated by Pro\'s '
            . 'weaker door on an unlicensed site (F1).'
        );
    }

    public function test_marketplace_pro_registrations_are_licence_gated_in_plugin_php(): void
    {
        $src = $this->plugin_php();

        foreach ($this->marketplace_gated_registrations() as $case) {
            [ $method, $basename ] = $case;
            $this->assertGateBindsClass($src, $method, $basename, 'Plugin.php');
        }
    }

    public function test_payout_statement_controller_is_gated_on_vendor_payout(): void
    {
        $this->assertGateBindsClass(
            $this->plugin_php(),
            'canUseVendorPayout',
            'PayoutStatementController',
            'Plugin.php'
        );
    }

    public function test_vendor_stats_analytics_ajax_is_marketplace_gated_in_user_dashboard(): void
    {
        $this->assertGateBindsClass(
            $this->user_dashboard_php(),
            'canUseVendorMarketplace',
            'AnalyticsController',
            'UserDashboard.php'
        );
    }

    /**
     * The lifecycle + commission-clearing crons scheduled by a prior licensed run
     * must be actively cleared while the marketplace gate is closed, or
     * wp_next_scheduled() keeps returning a timestamp on a lapsed site. Gating the
     * registration alone does not remove an already-scheduled event.
     *
     * Mutation proof: delete any wp_clear_scheduled_hook() line for these events, or
     * drop the `! Mode::canUseVendorMarketplace()` guard, and this fails.
     */
    public function test_orphaned_pro_crons_are_cleared_when_marketplace_gate_is_closed(): void
    {
        $src = $this->plugin_php();

        $this->assertMatchesRegularExpression(
            '/!\s*\\\\?[\w\\\\]*Mode::canUseVendorMarketplace\(\)/',
            $src,
            'cleanup_pro_only_schedules() must clear the lifecycle crons under a closed marketplace gate.'
        );

        foreach (
            array(
                'mhm_rentiva_listing_expiry_event',
                'mhm_rentiva_listing_expiry_warning_event',
                'mhm_rentiva_process_commission_clearing',
            ) as $event
        ) {
            $this->assertMatchesRegularExpression(
                '/wp_clear_scheduled_hook\(\s*\'' . preg_quote($event, '/') . '\'/',
                $src,
                "Orphaned cron '{$event}' is never cleared; wp_next_scheduled() stays truthy on a lapsed site."
            );
        }
    }
}
