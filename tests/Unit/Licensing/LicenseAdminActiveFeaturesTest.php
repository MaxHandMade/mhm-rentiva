<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\LicenseAdmin;
use MHMRentiva\Admin\Licensing\LicenseManager;
use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * v4.33.0 — LicenseAdmin "Active Pro features" line must reflect the
 * actual gate decisions, not a static string. Bug B fix.
 *
 * Pre-v4.33.0: line 268 echoed "All Pro features active: ..." regardless
 * of which feature tokens were granted, misleading customers when their
 * token was empty or partial.
 */
final class LicenseAdminActiveFeaturesTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        update_option('mhm_rentiva_disable_dev_mode', true, false);
    }

    protected function tearDown(): void
    {
        delete_option(LicenseManager::OPTION);
        delete_option('mhm_rentiva_disable_dev_mode');
        remove_all_filters('mhm_rentiva_dev_pro_bypass');
        parent::tearDown();
    }

    /**
     * Capture echoed output from LicenseAdmin's active-features rendering.
     */
    private function captureActiveFeaturesOutput(): string
    {
        ob_start();
        ( new LicenseAdmin() )->render_active_features();
        return (string) ob_get_clean();
    }

    public function test_displays_only_granted_features(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-001',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a1',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $output = $this->captureActiveFeaturesOutput();

        $this->assertStringContainsString('Vendor & Payout', $output);
        $this->assertStringContainsString('Advanced Reports', $output);
        $this->assertStringContainsString('Messages', $output);
        $this->assertStringContainsString('Expanded Export', $output);
        $this->assertStringNotContainsString('All Pro features active', $output, 'Misleading static string must be gone');
    }

    public function test_displays_all_features_when_all_granted(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-002',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a2',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $output = $this->captureActiveFeaturesOutput();

        $vendor_pos  = strpos($output, 'Vendor & Payout');
        $reports_pos = strpos($output, 'Advanced Reports');
        $messages_pos = strpos($output, 'Messages');
        $export_pos  = strpos($output, 'Expanded Export');

        $this->assertNotFalse($vendor_pos);
        $this->assertNotFalse($reports_pos);
        $this->assertNotFalse($messages_pos);
        $this->assertNotFalse($export_pos);
        $this->assertLessThan($reports_pos, $vendor_pos, 'Vendor before Advanced Reports');
        $this->assertLessThan($messages_pos, $reports_pos, 'Advanced Reports before Messages');
        $this->assertLessThan($export_pos, $messages_pos, 'Messages before Export');
    }

    public function test_shows_warning_when_no_features_granted(): void
    {
        // License active but token empty AND no dev bypass → all gates false.
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-003',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a3',
        ], false);

        $output = $this->captureActiveFeaturesOutput();

        $this->assertStringContainsString('notice-warning', $output);
        $this->assertStringContainsString('Re-validate Now', $output);
        $this->assertStringNotContainsString('Active Pro features', $output);
    }

    public function test_does_not_render_misleading_text(): void
    {
        update_option(LicenseManager::OPTION, [
            'key'           => 'PRO-TEST-004',
            'status'        => 'active',
            'plan'          => 'monthly',
            'expires_at'    => time() + 86400,
            'activation_id' => 'a4',
        ], false);
        add_filter('mhm_rentiva_dev_pro_bypass', '__return_true');

        $output = $this->captureActiveFeaturesOutput();

        $this->assertStringNotContainsString(
            'All Pro features active: Unlimited vehicles/bookings, export, advanced reports, Vendor & Payout',
            $output,
            'v4.32.0 hardcoded misleading string must be removed'
        );
    }
}
