<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\VendorReport;

use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportRepository;
use MHMRentiva\Admin\VendorReport\Core\VendorReportService;
use MHMRentiva\Admin\VendorReport\Core\VendorReportStatus;

/**
 * @group vendor-report
 * @group vendor-report-service
 */
final class VendorReportServiceTest extends \WP_UnitTestCase
{
    private string $reports_table = '';
    private int $vendor_id = 0;
    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->reports_table = $wpdb->prefix . 'mhm_rentiva_vendor_reports';
        $wpdb->query("DELETE FROM {$this->reports_table} WHERE vendor_id >= 99000");

        $this->vendor_id = 99010;
        $this->admin_id  = 99020;

        VendorReportRepository::reset_has_open_cache();
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->reports_table} WHERE vendor_id >= 99000");
        VendorReportRepository::reset_has_open_cache();
        parent::tearDown();
    }

    public function test_create_report_persists_open_report_with_minimum_validation(): void
    {
        $service = new VendorReportService();

        $result = $service->create_report(
            $this->vendor_id,
            VendorReportContext::BOOKING,
            '12345',
            'Damage report',
            'The vehicle was returned with serious damage to the front bumper and side panel.'
        );

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);

        $row = VendorReportRepository::find($result);
        $this->assertNotNull($row);
        $this->assertSame(VendorReportStatus::OPEN, $row->status);
    }

    public function test_create_report_rejects_short_description(): void
    {
        $service = new VendorReportService();

        $result = $service->create_report(
            $this->vendor_id,
            VendorReportContext::BOOKING,
            '12345',
            'Damage',
            'Short.'  // < 20 chars
        );

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('description_too_short', $result->get_error_code());
    }

    public function test_create_report_rejects_duplicate_open_report_for_same_context(): void
    {
        $service = new VendorReportService();

        $first = $service->create_report(
            $this->vendor_id,
            VendorReportContext::VEHICLE_ACTION,
            '777',
            'Withdrawal reason',
            'Vehicle was damaged in a parking lot accident two days ago, undriveable.'
        );
        $this->assertIsInt($first);

        $second = $service->create_report(
            $this->vendor_id,
            VendorReportContext::VEHICLE_ACTION,
            '777',
            'Another withdrawal reason',
            'Trying to file a duplicate while the first one is still under review.'
        );

        $this->assertInstanceOf(\WP_Error::class, $second);
        $this->assertSame('duplicate_open_report', $second->get_error_code());
    }

    public function test_create_report_allows_duplicate_after_first_is_resolved(): void
    {
        $service = new VendorReportService();

        $first = $service->create_report(
            $this->vendor_id,
            VendorReportContext::BOOKING,
            '12345',
            'Damage report',
            'The vehicle was returned with serious damage to the front bumper.'
        );
        $this->assertIsInt($first);

        VendorReportRepository::update_status($first, VendorReportStatus::RESOLVED, 'Accepted.', $this->admin_id);

        $second = $service->create_report(
            $this->vendor_id,
            VendorReportContext::BOOKING,
            '12345',
            'A different damage on a later booking',
            'A separate incident on a later booking with the same vehicle and a different customer.'
        );

        $this->assertIsInt($second);
        $this->assertNotSame($first, $second);
    }

    public function test_resolve_report_marks_status_resolved_with_admin_note(): void
    {
        $service = new VendorReportService();

        $report_id = $service->create_report(
            $this->vendor_id,
            VendorReportContext::BOOKING,
            '54321',
            'Cancellation dispute',
            'Customer cancelled within the no-refund window but they were refunded anyway.'
        );

        $service->resolve_report((int) $report_id, 'Refund will be reversed by the platform.', $this->admin_id);

        $row = VendorReportRepository::find((int) $report_id);
        $this->assertSame(VendorReportStatus::RESOLVED, $row->status);
        $this->assertSame('Refund will be reversed by the platform.', $row->admin_note);
        $this->assertSame($this->admin_id, (int) $row->admin_user_id);
        $this->assertNotNull($row->resolved_at);
    }

    public function test_reject_report_marks_status_rejected(): void
    {
        $service = new VendorReportService();

        $report_id = $service->create_report(
            $this->vendor_id,
            VendorReportContext::GENERAL,
            null,
            'Generic comment',
            'I would like to suggest adding a feature to the platform for vendor accounting.'
        );

        $service->reject_report((int) $report_id, 'Suggestion noted, but not actionable.', $this->admin_id);

        $row = VendorReportRepository::find((int) $report_id);
        $this->assertSame(VendorReportStatus::REJECTED, $row->status);
    }

    public function test_resolve_report_rejects_invalid_id(): void
    {
        $service = new VendorReportService();
        $result = $service->resolve_report(999999, 'note', $this->admin_id);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('report_not_found', $result->get_error_code());
    }

    public function test_resolve_report_rejects_already_terminal_report(): void
    {
        $service = new VendorReportService();

        $report_id = $service->create_report(
            $this->vendor_id,
            VendorReportContext::GENERAL,
            null,
            'A note',
            'Just a general feedback message that contains enough characters to pass validation.'
        );

        $service->resolve_report((int) $report_id, 'First resolve', $this->admin_id);

        $second = $service->resolve_report((int) $report_id, 'Trying to resolve again', $this->admin_id);

        $this->assertInstanceOf(\WP_Error::class, $second);
        $this->assertSame('already_terminal', $second->get_error_code());
    }
}
