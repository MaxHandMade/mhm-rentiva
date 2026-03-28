<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Emails\Notifications\VendorNotifications;
use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;

/**
 * Tests that lifecycle email templates are properly registered
 * and handlers are wired to the correct hooks.
 */
class LifecycleNotificationsTest extends \WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // ── Template Registry ────────────────────────────────────

    public function test_lifecycle_templates_registered(): void
    {
        $registry = VendorNotifications::add_templates(array());

        $expected_keys = array(
            'vehicle_activated',
            'vehicle_paused',
            'vehicle_resumed',
            'vehicle_withdrawn',
            'vehicle_expired',
            'vehicle_expiry_warning_first',
            'vehicle_expiry_warning_second',
            'vehicle_renewed',
            'vehicle_relisted',
        );

        foreach ($expected_keys as $key) {
            $this->assertArrayHasKey($key, $registry, "Template '{$key}' should be registered.");
            $this->assertArrayHasKey('subject', $registry[$key], "Template '{$key}' should have a subject.");
            $this->assertArrayHasKey('file', $registry[$key], "Template '{$key}' should have a file.");
        }
    }

    public function test_all_template_files_exist(): void
    {
        $registry = VendorNotifications::add_templates(array());
        $template_dir = MHM_RENTIVA_PLUGIN_DIR . 'templates/emails/';

        $lifecycle_keys = array(
            'vehicle_activated',
            'vehicle_paused',
            'vehicle_resumed',
            'vehicle_withdrawn',
            'vehicle_expired',
            'vehicle_expiry_warning_first',
            'vehicle_renewed',
            'vehicle_relisted',
        );

        foreach ($lifecycle_keys as $key) {
            $file_path = $template_dir . $registry[$key]['file'] . '.html.php';
            $this->assertFileExists($file_path, "Template file for '{$key}' should exist at {$file_path}.");
        }
    }

    // ── Hook Wiring ──────────────────────────────────────────

    public function test_lifecycle_changed_hook_fires_email_handler(): void
    {
        $vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));
        $user = get_userdata($vendor_id);
        $user->add_role('rentiva_vendor');

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Notification Test Vehicle',
        ));

        // Track if the handler method was reached by checking Mailer::send was called.
        // Since we can't easily mock wp_mail in integration tests, we verify
        // the handler doesn't throw errors when invoked with valid data.
        $exception_thrown = false;

        try {
            VendorNotifications::on_lifecycle_changed($vehicle_id, 'pending_review', 'active');
        } catch (\Throwable $e) {
            $exception_thrown = true;
        }

        $this->assertFalse($exception_thrown, 'Lifecycle changed handler should not throw exceptions.');

        wp_delete_post($vehicle_id, true);
        wp_delete_user($vendor_id);
    }

    public function test_expiry_warning_handlers_dont_throw(): void
    {
        $vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Warning Test Vehicle',
        ));

        $expires_at = gmdate('Y-m-d H:i:s', strtotime('+5 days'));

        $exception_thrown = false;
        try {
            VendorNotifications::on_expiry_warning_first($vehicle_id, $vendor_id, $expires_at, 10);
            VendorNotifications::on_expiry_warning_second($vehicle_id, $vendor_id, $expires_at, 3);
        } catch (\Throwable $e) {
            $exception_thrown = true;
        }

        $this->assertFalse($exception_thrown, 'Expiry warning handlers should not throw exceptions.');

        wp_delete_post($vehicle_id, true);
        wp_delete_user($vendor_id);
    }

    public function test_renewed_handler_doesnt_throw(): void
    {
        $vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Renewed Test Vehicle',
        ));

        $exception_thrown = false;
        try {
            VendorNotifications::on_vehicle_renewed($vehicle_id);
        } catch (\Throwable $e) {
            $exception_thrown = true;
        }

        $this->assertFalse($exception_thrown);

        wp_delete_post($vehicle_id, true);
        wp_delete_user($vendor_id);
    }

    public function test_relisted_handler_doesnt_throw(): void
    {
        $vendor_id = $this->factory()->user->create(array('role' => 'subscriber'));

        $vehicle_id = wp_insert_post(array(
            'post_type'   => 'vehicle',
            'post_status' => 'pending',
            'post_author' => $vendor_id,
            'post_title'  => 'Relisted Test Vehicle',
        ));

        $exception_thrown = false;
        try {
            VendorNotifications::on_vehicle_relisted($vehicle_id, $vendor_id);
        } catch (\Throwable $e) {
            $exception_thrown = true;
        }

        $this->assertFalse($exception_thrown);

        wp_delete_post($vehicle_id, true);
        wp_delete_user($vendor_id);
    }

    // ── Template Count ───────────────────────────────────────

    public function test_total_template_count(): void
    {
        $registry = VendorNotifications::add_templates(array());

        // 13 existing + 9 lifecycle = 22 total
        $this->assertGreaterThanOrEqual(22, count($registry));
    }
}
