<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Build;

use PHPUnit\Framework\TestCase;

/**
 * The WordPress.org Lite artifact must not contain reachable paid-feature UI.
 */
final class LitePaidSurfaceRemovalTest extends TestCase
{
    private function plugin_root(): string
    {
        return dirname(__DIR__, 3) . '/';
    }

    public function test_paid_feature_implementations_are_not_part_of_lite(): void
    {
        $root = $this->plugin_root();

        foreach (
            array(
                'assets/css/frontend/search-premium.css',
                'assets/js/admin/addon-context.js',
                'src/Admin/Addons/AddonContextMetaBox.php',
                'src/Admin/Addons/AddonContextMigration.php',
                'src/Admin/Addons/AddonContextTaxonomy.php',
                'src/Admin/Addons/AddonContextValidator.php',
                'src/Admin/Vehicle/Meta/VehicleCommissionRateMetaBox.php',
                'src/Admin/Vehicle/PenaltyCalculator.php',
                'src/Admin/Vehicle/ReliabilityScoreCalculator.php',
                'templates/account/partials/vendor-bookings.php',
                'templates/account/partials/vendor-listings.php',
                'templates/account/partials/vendor-reliability.php',
                'templates/account/partials/vendor-report-modal.php',
            ) as $relative
        ) {
            $this->assertFileDoesNotExist($root . $relative, "Paid-feature implementation ships in Lite: {$relative}");
        }
    }

    public function test_unified_search_contract_is_rental_only(): void
    {
        $root = $this->plugin_root();

        $watched = array(
            'src/Admin/Frontend/Widgets/Elementor/UnifiedSearchWidget.php',
            'src/Admin/Frontend/Shortcodes/UnifiedSearch.php',
            'assets/blocks/unified-search/index.js',
            'assets/blocks/unified-search/block.json',
            'templates/shortcodes/unified-search.php',
        );

        foreach ($watched as $relative) {
            $source = strtolower((string) file_get_contents($root . $relative));
            foreach (array( 'transfer', 'service_type', 'servicetype', 'default_tab', 'defaulttab', 'passengers', 'luggage', 'search-premium' ) as $token) {
                $this->assertStringNotContainsString($token, $source, "{$relative} retains paid search contract token {$token}");
            }
        }
    }

    public function test_featured_vehicle_block_does_not_expose_paid_service_types(): void
    {
        $root = $this->plugin_root();

        foreach (array( 'assets/blocks/featured-vehicles/block.json', 'assets/blocks/featured-vehicles/index.js' ) as $relative) {
            $source = strtolower((string) file_get_contents($root . $relative));
            foreach (array( 'servicetype', 'service_type', 'transfer' ) as $token) {
                $this->assertStringNotContainsString($token, $source, "{$relative} retains paid vehicle block token {$token}");
            }
        }
    }

    public function test_lite_boot_and_dashboard_do_not_implement_vendor_mode(): void
    {
        $root = $this->plugin_root();

        foreach (
            array(
                'src/Plugin.php',
                'src/Core/Dashboard/DashboardContext.php',
                'src/Core/Dashboard/DashboardNavigation.php',
                'src/Admin/Frontend/Shortcodes/Account/UserDashboard.php',
                'templates/account/user-dashboard.php',
                'assets/js/frontend/user-dashboard.js',
            ) as $relative
        ) {
            $source = strtolower((string) file_get_contents($root . $relative));
            foreach (array( 'rentiva_vendor', 'vendor_panel', 'vendor_report', 'vendor_nonce', 'payout' ) as $token) {
                $this->assertStringNotContainsString($token, $source, "{$relative} retains reachable vendor contract token {$token}");
            }
        }
    }

    public function test_lite_vehicle_surfaces_do_not_render_transfer_or_vendor_badges(): void
    {
        $root = $this->plugin_root();

        foreach (
            array(
                'templates/partials/vehicle-card-base.php',
                'templates/partials/vehicle-card.php',
                'templates/shortcodes/vehicle-details.php',
                'src/Admin/Frontend/Shortcodes/VehiclesGrid.php',
                'src/Admin/Frontend/Shortcodes/SearchResults.php',
                'src/Admin/Frontend/Shortcodes/FeaturedVehicles.php',
                'src/Admin/Vehicle/ListTable/VehicleColumns.php',
            ) as $relative
        ) {
            $source = strtolower((string) file_get_contents($root . $relative));
            foreach (array( 'vehicle_service_type', 'transfer only', 'vip transfer', 'vendor badge', 'rentiva_vendor' ) as $token) {
                $this->assertStringNotContainsString($token, $source, "{$relative} retains paid vehicle surface token {$token}");
            }
        }
    }

    public function test_lite_catalogs_and_styles_do_not_advertise_paid_surfaces(): void
    {
        $root = $this->plugin_root();
        $files = array(
            'src/Admin/Settings/ShortcodePages/ShortcodePageActions.php',
            'src/Admin/Core/ShortcodeUrlManager.php',
            'src/Admin/Frontend/Widgets/Base/ElementorWidgetBase.php',
            'src/Core/Attribute/AllowlistRegistry.php',
            'assets/css/core/golden-ratio-contract.css',
            'assets/css/core/vehicle-card.css',
            'assets/css/frontend/user-dashboard.css',
        );
        $tokens = array(
            'rentiva_transfer_search',
            'rentiva_transfer_results',
            'rentiva_vendor_apply',
            'rentiva_vendor_profile',
            'rentiva_vendor_directory',
            'rentiva_vendor_bookings',
            'rentiva_vendor_ledger',
            'rentiva_messages',
            'mhm-transfer-',
            'mhm-vendor-',
            'search-premium',
        );

        foreach ($files as $relative) {
            $source = strtolower((string) file_get_contents($root . $relative));
            foreach ($tokens as $token) {
                $this->assertStringNotContainsString($token, $source, "{$relative} retains paid catalog/style token {$token}");
            }
        }
    }
}
