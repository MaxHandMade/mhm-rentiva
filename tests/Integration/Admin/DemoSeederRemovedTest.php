<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use WP_UnitTestCase;

/**
 * WP.org T4 Phase B, Task B-G1a: the Demo seeder must be gone from Lite.
 *
 * Two WP.org rejection root causes lived in `src/Admin/Testing/`:
 *   - #4-demo: `DemoImageImporter` did `require_once ABSPATH .
 *     'wp-admin/includes/file.php'` / `media.php` -- a plugin including
 *     WP-core admin files directly, which WP.org disallows.
 *   - #5-demo123: `DemoSeeder` called `wp_create_user()` with a hardcoded
 *     `demo123` password for every seeded demo customer/vendor account.
 *
 * The locked decision is deletion, not a fix-in-place -- the whole "Demo
 * seed 500 fake vehicles/customers/bookings" feature (DemoSeeder,
 * DemoImageImporter, DemoDataProvider, DemoAjaxHandler, DemoNoticeManager,
 * the wizard's "demo" step, and its dedicated JS) left Lite. It may return
 * to Pro later; that is out of scope here.
 *
 * This suite is the machine-checkable proof, mirroring
 * PluginNoProWiringTest's source-grep + behavioural-contract style.
 *
 * @covers \MHMRentiva\Admin\Setup\SetupWizard
 * @covers \MHMRentiva\Plugin
 */
final class DemoSeederRemovedTest extends WP_UnitTestCase
{
    private function plugin_dir(): string
    {
        return defined('MHM_RENTIVA_PLUGIN_DIR')
            ? constant('MHM_RENTIVA_PLUGIN_DIR')
            : dirname(__DIR__, 3) . '/';
    }

    private function read(string $relative_path): string
    {
        $path = $this->plugin_dir() . $relative_path;
        $src  = @file_get_contents($path);
        $this->assertNotFalse($src, "Could not read {$path}");

        return (string) $src;
    }

    // -- Class-existence proof (the classes are physically gone) -------------

    public function test_demo_seeder_class_does_not_exist(): void
    {
        $this->assertFalse(class_exists('\MHMRentiva\Admin\Testing\DemoSeeder'));
    }

    public function test_demo_image_importer_class_does_not_exist(): void
    {
        $this->assertFalse(class_exists('\MHMRentiva\Admin\Testing\DemoImageImporter'));
    }

    public function test_demo_data_provider_class_does_not_exist(): void
    {
        $this->assertFalse(class_exists('\MHMRentiva\Admin\Testing\DemoDataProvider'));
    }

    public function test_demo_ajax_handler_class_does_not_exist(): void
    {
        $this->assertFalse(class_exists('\MHMRentiva\Admin\Testing\DemoAjaxHandler'));
    }

    public function test_demo_notice_manager_class_does_not_exist(): void
    {
        $this->assertFalse(class_exists('\MHMRentiva\Admin\Testing\DemoNoticeManager'));
    }

    // -- Behavioural contract (nothing wires the removed feature) ------------

    public function test_no_demo_seed_ajax_action_is_registered(): void
    {
        $this->assertFalse(
            has_action('wp_ajax_mhm_rentiva_demo_seed'),
            'The demo-seed AJAX action must not be registered -- DemoAjaxHandler is gone.'
        );
    }

    public function test_no_demo_cleanup_ajax_action_is_registered(): void
    {
        $this->assertFalse(
            has_action('wp_ajax_mhm_rentiva_demo_cleanup'),
            'The demo-cleanup AJAX action must not be registered -- DemoAjaxHandler is gone.'
        );
    }

    public function test_no_demo_admin_notices_are_registered_by_the_removed_manager(): void
    {
        global $wp_filter;

        if (isset($wp_filter['admin_notices'])) {
            foreach ($wp_filter['admin_notices']->callbacks as $callbacks) {
                foreach ($callbacks as $callback) {
                    $fn = $callback['function'];
                    if (is_array($fn) && is_string($fn[0])) {
                        $this->assertNotSame(
                            'MHMRentiva\Admin\Testing\DemoNoticeManager',
                            ltrim($fn[0], '\\'),
                            'DemoNoticeManager must not be hooked into admin_notices -- it was deleted.'
                        );
                    }
                }
            }
        }

        $this->assertTrue(true); // No admin_notices hooks at all is also a pass.
    }

    public function test_setup_wizard_no_longer_has_a_demo_step(): void
    {
        $src = $this->read('src/Admin/Setup/SetupWizard.php');

        $this->assertStringNotContainsString(
            "'demo'",
            $src,
            "SetupWizard.php must not reference a 'demo' wizard step -- it was removed with the Demo seeder."
        );
        $this->assertStringNotContainsString('DemoAjaxHandler', $src);
        $this->assertStringNotContainsString('DemoNoticeManager', $src);
        $this->assertStringNotContainsString('render_step_demo', $src);

        // Positive control: the other wizard steps must survive.
        foreach (array( 'system', 'pages', 'email', 'frontend', 'summary' ) as $step) {
            $this->assertStringContainsString("'{$step}'", $src);
        }
    }

    public function test_plugin_php_no_longer_wires_the_demo_classes(): void
    {
        $src = $this->read('src/Plugin.php');

        foreach (array( 'DemoAjaxHandler', 'DemoNoticeManager', 'DemoSeeder', 'DemoImageImporter' ) as $class) {
            $this->assertStringNotContainsString(
                $class,
                $src,
                "Plugin.php must not reference {$class} -- the Demo seeder feature was deleted."
            );
        }
    }

    // -- Security root causes are gone ---------------------------------------

    public function test_no_hardcoded_demo_password_anywhere_in_src(): void
    {
        $this->assertSourceTreeDoesNotContain('src', 'demo123');
    }

    public function test_demo_image_importer_file_no_longer_exists_on_disk(): void
    {
        // #4-demo root cause: DemoImageImporter required WP-core admin
        // includes (file.php / media.php) directly to reach media-
        // sideloading helpers -- a plugin including WP-core admin files is
        // a WP.org guideline violation. The file itself must be gone (not
        // merely emptied), which also proves those requires are gone with it.
        $this->assertFileDoesNotExist($this->plugin_dir() . 'src/Admin/Testing/DemoImageImporter.php');
        $this->assertFileDoesNotExist($this->plugin_dir() . 'src/Admin/Testing/DemoSeeder.php');
    }

    private function assertSourceTreeDoesNotContain(string $relative_dir, string $needle): void
    {
        $root = $this->plugin_dir() . $relative_dir;
        $this->assertDirectoryExists($root);

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            $this->assertIsString($contents);
            $this->assertStringNotContainsString(
                $needle,
                $contents,
                "Found forbidden string '{$needle}' in {$file->getPathname()}"
            );
        }
    }
}
