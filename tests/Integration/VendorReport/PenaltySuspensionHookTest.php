<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\VendorReport;

use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportRepository;
use MHMRentiva\Admin\VendorReport\Core\VendorReportService;
use MHMRentiva\Admin\VendorReport\Core\VendorReportStatus;
use MHMRentiva\Admin\VendorReport\Hooks\PenaltySuspensionHook;

/**
 * @group vendor-report
 * @group vendor-report-hook
 */
final class PenaltySuspensionHookTest extends \WP_UnitTestCase
{
    private string $reports_table = '';
    private int $vendor_id = 0;
    private int $vehicle_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->reports_table = $wpdb->prefix . 'mhm_rentiva_vendor_reports';
        $wpdb->query("DELETE FROM {$this->reports_table} WHERE vendor_id >= 99000");

        $this->vendor_id  = 99030;
        $this->vehicle_id = 88030;

        VendorReportRepository::reset_has_open_cache();
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->reports_table} WHERE vendor_id >= 99000");
        VendorReportRepository::reset_has_open_cache();
        parent::tearDown();
    }

    public function test_filter_returns_true_when_no_open_report_exists(): void
    {
        $apply = PenaltySuspensionHook::maybe_suspend(
            true,
            $this->vehicle_id,
            $this->vendor_id,
            'withdrawal',
            500.0
        );

        $this->assertTrue($apply, 'No report = penalty must apply normally');
    }

    public function test_filter_returns_false_when_open_vehicle_action_report_exists(): void
    {
        $service = new VendorReportService();
        $service->create_report(
            $this->vendor_id,
            VendorReportContext::VEHICLE_ACTION,
            $this->vehicle_id,
            'Withdrawal reason',
            'Vehicle was damaged in a parking lot incident yesterday morning, undriveable.'
        );

        $apply = PenaltySuspensionHook::maybe_suspend(
            true,
            $this->vehicle_id,
            $this->vendor_id,
            'withdrawal',
            500.0
        );

        $this->assertFalse($apply, 'Open vehicle_action report must suspend the penalty');
    }

    public function test_filter_does_not_re_enable_a_previously_blocked_decision(): void
    {
        // Some other filter callback already returned false. Suspension hook
        // must not flip that back to true even when no report exists.
        $apply = PenaltySuspensionHook::maybe_suspend(
            false,
            $this->vehicle_id,
            $this->vendor_id,
            'withdrawal',
            500.0
        );

        $this->assertFalse($apply);
    }

    public function test_filter_ignores_other_context_types(): void
    {
        $service = new VendorReportService();
        $service->create_report(
            $this->vendor_id,
            VendorReportContext::BOOKING,
            (string) $this->vehicle_id,
            'A booking complaint',
            'Customer never showed up at the agreed pickup point and is unreachable now.'
        );

        $apply = PenaltySuspensionHook::maybe_suspend(
            true,
            $this->vehicle_id,
            $this->vendor_id,
            'withdrawal',
            500.0
        );

        $this->assertTrue(
            $apply,
            'Booking context (same numeric context_id) must not suspend a withdrawal penalty'
        );
    }

    public function test_filter_only_blocks_for_matching_vendor(): void
    {
        $service = new VendorReportService();
        $service->create_report(
            $this->vendor_id,
            VendorReportContext::VEHICLE_ACTION,
            $this->vehicle_id,
            'Withdrawal reason',
            'Vehicle was in an accident yesterday and is awaiting repair shop assessment.'
        );

        // Different vendor (someone else) — penalty should still apply.
        $other_vendor_id = $this->vendor_id + 1;
        $apply = PenaltySuspensionHook::maybe_suspend(
            true,
            $this->vehicle_id,
            $other_vendor_id,
            'withdrawal',
            500.0
        );

        $this->assertTrue($apply, 'Open report for vendor A must not suspend penalty for vendor B');
    }

    public function test_filter_releases_penalty_after_report_resolved(): void
    {
        $service = new VendorReportService();
        $report_id = $service->create_report(
            $this->vendor_id,
            VendorReportContext::VEHICLE_ACTION,
            $this->vehicle_id,
            'Withdrawal reason',
            'Vehicle was damaged and needs to leave the platform until repairs are complete.'
        );

        VendorReportRepository::update_status((int) $report_id, VendorReportStatus::RESOLVED, 'Valid.', 1);

        $apply = PenaltySuspensionHook::maybe_suspend(
            true,
            $this->vehicle_id,
            $this->vendor_id,
            'withdrawal',
            500.0
        );

        $this->assertTrue(
            $apply,
            'Once the report is closed, the suspension is lifted (next withdrawal would penalize again)'
        );
    }
}
