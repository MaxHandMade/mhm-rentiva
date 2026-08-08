<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Build;

use PHPUnit\Framework\TestCase;

/**
 * WP.org T4 Phase B, Task B-A1 (the audit's "B1" finding).
 *
 * Lite must not physically ship the Pro-feature CSS/JS layer, and must not
 * reference it from source. Before this task, 15 asset files that exist only
 * to serve Pro-only surfaces (messaging, VIP transfer, vendor marketplace,
 * export) shipped inside Lite's own `assets/` tree, and four Lite classes
 * enqueued them via `MHMRENTIVA_PLUGIN_URL` -- even though Pro is the only
 * install that ever renders the screens that need them. That is a leftover
 * paid-tier shell in the free ZIP, exactly the class of finding that got Lite
 * rejected by WordPress.org twice already.
 *
 * This is a source scan on purpose, mirroring
 * tests/Tools/NoProMentionInLiteTest.php: it must catch the shell even on a
 * surface nobody thought to exercise at runtime.
 *
 * @package MHMRentiva\Tests\Unit\Build
 */
final class NoProFeatureAssetsTest extends TestCase
{

    /**
     * The 15 Pro-feature asset files carved out of Lite in this task. Paths
     * are relative to the plugin root.
     *
     * @return list<string>
     */
    private function moved_asset_paths(): array
    {
        return array(
            'assets/css/frontend/customer-messages.css',
            'assets/css/frontend/transfer-results.css',
            'assets/css/frontend/transfer.css',
            'assets/css/frontend/popular-routes.css',
            'assets/css/frontend/vendor-directory.css',
            'assets/css/frontend/vendor-forms.css',
            'assets/css/frontend/vendor-profile.css',
            'assets/css/admin/export.css',
            'assets/css/components/transfer-addon-modal.css',
            'assets/js/frontend/account-messages.js',
            'assets/js/admin/export.js',
            'assets/js/admin/message-list.js',
            'assets/js/admin/messages-settings.js',
            'assets/js/components/transfer-addon-modal.js',
            'assets/js/rentiva-transfer.js',
        );
    }

    /**
     * Lite classes that used to enqueue the moved assets directly (as opposed
     * to via the BlockRegistry base_url mechanism, which is covered by a
     * separate assertion below).
     *
     * @return list<string>
     */
    private function watched_lite_classes(): array
    {
        return array(
            'src/Admin/Frontend/Account/AccountController.php',
            'src/Admin/Frontend/Account/AccountRenderer.php',
            'src/Admin/Core/AssetManager.php',
            'src/Admin/Frontend/Shortcodes/Account/UserDashboard.php',
        );
    }

    private function plugin_root(): string
    {
        return rtrim((string) (defined('MHMRENTIVA_PLUGIN_DIR') ? MHMRENTIVA_PLUGIN_DIR : dirname(__DIR__, 3) . '/'), '/\\') . '/';
    }

    /**
     * None of the 15 Pro-feature asset files may physically exist in Lite's
     * shipped tree any more -- they now live only in the Pro add-on.
     */
    public function test_moved_asset_files_do_not_exist_in_lite(): void
    {
        $root      = $this->plugin_root();
        $offenders = array();

        foreach ($this->moved_asset_paths() as $relative) {
            if (is_file($root . $relative)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            "These Pro-feature asset files must no longer ship inside Lite:\n" . implode("\n", $offenders)
        );
    }

    /**
     * The four Lite classes that used to enqueue these assets (by filename or
     * by the `MHMRENTIVA_PLUGIN_URL`-relative path) must no longer mention
     * any of them -- the screens that need them are Pro-only, so Pro is now
     * the sole class that enqueues its own copy.
     */
    public function test_watched_lite_classes_no_longer_reference_moved_assets(): void
    {
        $root      = $this->plugin_root();
        $filenames = array_map('basename', $this->moved_asset_paths());
        $offenders = array();

        foreach ($this->watched_lite_classes() as $relative_class) {
            $path = $root . $relative_class;
            $this->assertFileExists($path, "Expected watched Lite class to still exist: {$relative_class}");

            $code = (string) file_get_contents($path);
            foreach (preg_split('/\R/', $code) ?: array() as $index => $line) {
                foreach ($filenames as $filename) {
                    if (str_contains($line, $filename)) {
                        $offenders[] = sprintf('%s:%d references %s -> %s', $relative_class, $index + 1, $filename, trim($line));
                    }
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            "These Lite classes must no longer enqueue the moved Pro-feature assets:\n" . implode("\n", $offenders)
        );
    }

    /**
     * Belt-and-suspenders: no file anywhere under Lite's shipped `src/` tree
     * may reference the moved filenames by name, not just the four classes
     * named above. This is the same kind of full-surface scan
     * NoProMentionInLiteTest uses for upsell copy -- a leftover reference in
     * a fifth, not-yet-known class must fail loudly instead of shipping
     * silently.
     */
    public function test_no_lite_source_file_references_moved_assets(): void
    {
        $root      = $this->plugin_root();
        $filenames = array_map('basename', $this->moved_asset_paths());
        $offenders = array();

        foreach ($this->shipped_php_files($root) as $path) {
            $code = (string) file_get_contents($path);
            foreach (preg_split('/\R/', $code) ?: array() as $index => $line) {
                foreach ($filenames as $filename) {
                    if (str_contains($line, $filename)) {
                        $offenders[] = sprintf('%s:%d references %s -> %s', $this->relative($root, $path), $index + 1, $filename, trim($line));
                    }
                }
            }
        }

        $this->assertSame(
            array(),
            $offenders,
            "No Lite source file may reference the moved Pro-feature assets:\n" . implode("\n", $offenders)
        );
    }

    /**
     * Guards the scan itself: a glob/root mismatch that silently matched
     * nothing would make every assertion above pass while scanning no code.
     */
    public function test_the_scan_actually_reads_the_plugin_source(): void
    {
        $root  = $this->plugin_root();
        $files = iterator_to_array($this->shipped_php_files($root));

        $this->assertGreaterThan(250, count($files), 'The scan found implausibly few PHP files.');
    }

    /**
     * Hidden links are still a paid-feature shell even when a false capability
     * keeps them out of the rendered DOM. Lite's own component must not know
     * the carved add-on slugs or carry a `cap` gate for them.
     */
    public function test_dashboard_quick_actions_has_no_hidden_addon_actions(): void
    {
        $relative = 'src-react/admin/dashboard/components/QuickActions.jsx';
        $source   = (string) file_get_contents($this->plugin_root() . $relative);

        $this->assertStringContainsString('Add New Booking', $source, 'Positive control: the Lite quick-actions component was not read.');

        foreach (
            array(
                'mhm-rentiva-transfer-locations',
                'mhm-rentiva-reports',
                'mhm-rentiva-vendors',
                'mhm-rentiva-messages',
                'mhm-rentiva-export',
                'cap:',
                'caps[',
            ) as $forbidden
        ) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "{$relative} must not ship the hidden add-on surface '{$forbidden}'."
            );
        }
    }

    /**
     * The shared Lite API client is bundled into Lite admin builds. Endpoints
     * with no Lite consumer and no Lite route must not remain as paid-feature
     * implementation residue.
     */
    public function test_shared_api_has_no_unconsumed_addon_clients(): void
    {
        $relative = 'src-react/shared/api/rentiva.js';
        $source   = (string) file_get_contents($this->plugin_root() . $relative);

        $this->assertStringContainsString('customers:', $source, 'Positive control: the shared API module was not read.');

        foreach (array( 'reports', 'messages', 'vendorReports', 'export', 'vendorManagement' ) as $client) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*' . preg_quote($client, '/') . '\s*:/m',
                $source,
                "{$relative} must not expose the unconsumed {$client} add-on client."
            );
        }

        $this->assertStringNotContainsString(
            'getRecentTransfers',
            $source,
            "{$relative} must not ship the transfer dashboard client owned by the Pro add-on."
        );
    }

    /**
     * The transfer dashboard card is a paid-feature implementation. Supplying
     * data through an extension filter must not make Lite physically ship the
     * React component, its import, or its Pro-only localized-data contract.
     */
    public function test_lite_dashboard_has_no_transfer_widget_implementation(): void
    {
        $root      = $this->plugin_root();
        $relative  = 'src-react/admin/dashboard/DashboardPage.jsx';
        $source    = (string) file_get_contents($root . $relative);
        $component = 'src-react/admin/dashboard/components/TransferWidget.jsx';

        $this->assertStringContainsString('RecentBookings', $source, 'Positive control: the Lite dashboard component was not read.');
        $this->assertFileDoesNotExist($root . $component, 'The paid transfer widget implementation must not ship in Lite.');

        foreach (array( 'TransferWidget', 'transfer_stats', 'recent_transfers', 'recent_transfers_total_pages' ) as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "{$relative} must not ship the transfer dashboard contract '{$forbidden}'."
            );
        }
    }

    public function test_dashboard_styles_have_no_removed_reports_action_selector(): void
    {
        $relative = 'src-react/admin/dashboard/dashboard.css';
        $source   = (string) file_get_contents($this->plugin_root() . $relative);

        $this->assertStringContainsString('.rv-dash-header', $source, 'Positive control: the dashboard stylesheet was not read.');
        $this->assertStringNotContainsString(
            '.rv-dash-header__report',
            $source,
            'The removed reports link must not leave a paid-feature selector in Lite CSS.'
        );
    }

    /**
     * @return iterable<int, string>
     */
    private function shipped_php_files(string $root): iterable
    {
        foreach (array( 'src', 'templates' ) as $dir) {
            $dir_path = $root . $dir;
            if (! is_dir($dir_path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir_path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ('php' === $file->getExtension()) {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function relative(string $root, string $path): string
    {
        return str_replace(array( $root, '\\' ), array( '', '/' ), $path);
    }
}
