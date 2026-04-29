<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\VendorReport;

use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportRepository;
use MHMRentiva\Admin\VendorReport\Core\VendorReportStatus;

/**
 * @group vendor-report
 * @group vendor-report-repository
 */
final class VendorReportRepositoryTest extends \WP_UnitTestCase
{
    private string $table = '';
    private int $vendor_id = 0;
    private int $other_vendor_id = 0;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $this->table = $wpdb->prefix . 'mhm_rentiva_vendor_reports';

        // Clean any leftover rows in our test ID space.
        $wpdb->query("DELETE FROM {$this->table} WHERE vendor_id >= 99000");

        $this->vendor_id       = 99001;
        $this->other_vendor_id = 99002;
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$this->table} WHERE vendor_id >= 99000");
        parent::tearDown();
    }

    public function test_create_persists_a_new_report_with_open_status(): void
    {
        $report_id = VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::BOOKING,
            'context_id'   => '12345',
            'title'        => 'Customer never showed up',
            'description'  => 'The customer did not arrive at the agreed pickup time and is unreachable.',
        ]);

        $this->assertGreaterThan(0, $report_id, 'create() must return the inserted row id');

        $row = VendorReportRepository::find($report_id);
        $this->assertNotNull($row);
        $this->assertSame($this->vendor_id, (int) $row->vendor_id);
        $this->assertSame(VendorReportContext::BOOKING, $row->context_type);
        $this->assertSame('12345', (string) $row->context_id);
        $this->assertSame('Customer never showed up', $row->title);
        $this->assertSame(VendorReportStatus::OPEN, $row->status);
        $this->assertNotEmpty($row->created_at);
        $this->assertNotEmpty($row->updated_at);
        $this->assertNull($row->resolved_at);
    }

    public function test_create_accepts_uuid_string_context_id_for_penalty_context(): void
    {
        $uuid = 'withdrawal_penalty_42_99001_1761900000';

        $report_id = VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::PENALTY,
            'context_id'   => $uuid,
            'title'        => 'Unfair penalty appeal',
            'description'  => 'I withdrew because the vehicle was unsafe to drive — see attached report.',
        ]);

        $row = VendorReportRepository::find($report_id);
        $this->assertSame($uuid, (string) $row->context_id, 'context_id must accept full UUID string (VARCHAR(64))');
    }

    public function test_create_accepts_null_context_id_for_general_context(): void
    {
        $report_id = VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::GENERAL,
            'context_id'   => null,
            'title'        => 'General feedback',
            'description'  => 'I would like to suggest a feature for vendor accounting.',
        ]);

        $row = VendorReportRepository::find($report_id);
        $this->assertNull($row->context_id);
    }

    public function test_has_open_report_for_returns_true_when_open_report_exists(): void
    {
        VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::VEHICLE_ACTION,
            'context_id'   => '777',
            'title'        => 'Withdrawal reason',
            'description'  => 'Vehicle was damaged in a parking lot incident yesterday morning.',
        ]);

        $this->assertTrue(
            VendorReportRepository::has_open_report_for($this->vendor_id, VendorReportContext::VEHICLE_ACTION, '777')
        );
    }

    public function test_has_open_report_for_returns_false_for_different_vendor(): void
    {
        VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::VEHICLE_ACTION,
            'context_id'   => '888',
            'title'        => 'Withdrawal reason',
            'description'  => 'Vehicle has mechanical issues that need urgent service before further use.',
        ]);

        $this->assertFalse(
            VendorReportRepository::has_open_report_for($this->other_vendor_id, VendorReportContext::VEHICLE_ACTION, '888')
        );
    }

    public function test_has_open_report_for_returns_false_when_only_resolved_reports_exist(): void
    {
        $report_id = VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::VEHICLE_ACTION,
            'context_id'   => '999',
            'title'        => 'Withdrawal reason',
            'description'  => 'Vehicle removed from service for compliance reasons; documentation attached.',
        ]);

        VendorReportRepository::update_status($report_id, VendorReportStatus::RESOLVED, 'Valid reason — accepted.', 1);

        $this->assertFalse(
            VendorReportRepository::has_open_report_for($this->vendor_id, VendorReportContext::VEHICLE_ACTION, '999'),
            'Resolved reports must not count as open'
        );
    }

    public function test_has_open_report_for_in_review_status_still_counts_as_open(): void
    {
        $report_id = VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::PENALTY,
            'context_id'   => 'withdrawal_penalty_555',
            'title'        => 'Penalty appeal',
            'description'  => 'The penalty applied was incorrect because the vehicle was already paused at the time.',
        ]);

        VendorReportRepository::update_status($report_id, VendorReportStatus::IN_REVIEW, null, 1);

        $this->assertTrue(
            VendorReportRepository::has_open_report_for($this->vendor_id, VendorReportContext::PENALTY, 'withdrawal_penalty_555'),
            'in_review reports must count as open (penalty must stay suspended while admin reviews)'
        );
    }

    public function test_update_status_sets_resolved_at_for_terminal_statuses(): void
    {
        $report_id = VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::BOOKING,
            'context_id'   => '54321',
            'title'        => 'Damage report',
            'description'  => 'The customer returned the vehicle with significant exterior damage requiring repair.',
        ]);

        VendorReportRepository::update_status($report_id, VendorReportStatus::REJECTED, 'No supporting evidence.', 1);

        $row = VendorReportRepository::find($report_id);
        $this->assertSame(VendorReportStatus::REJECTED, $row->status);
        $this->assertNotNull($row->resolved_at, 'Terminal status must stamp resolved_at');
        $this->assertSame(1, (int) $row->admin_user_id);
        $this->assertSame('No supporting evidence.', $row->admin_note);
    }

    public function test_update_status_does_not_set_resolved_at_for_in_review(): void
    {
        $report_id = VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::BOOKING,
            'context_id'   => '54322',
            'title'        => 'Disputed cancellation fee',
            'description'  => 'Customer cancelled within the no-refund window but the platform refunded them.',
        ]);

        VendorReportRepository::update_status($report_id, VendorReportStatus::IN_REVIEW, null, 1);

        $row = VendorReportRepository::find($report_id);
        $this->assertSame(VendorReportStatus::IN_REVIEW, $row->status);
        $this->assertNull($row->resolved_at, 'in_review must not stamp resolved_at');
    }

    public function test_find_by_vendor_returns_only_that_vendors_reports(): void
    {
        VendorReportRepository::create([
            'vendor_id'    => $this->vendor_id,
            'context_type' => VendorReportContext::GENERAL,
            'context_id'   => null,
            'title'        => 'My report A',
            'description'  => 'A general message from vendor 99001 to the administrator team here.',
        ]);
        VendorReportRepository::create([
            'vendor_id'    => $this->other_vendor_id,
            'context_type' => VendorReportContext::GENERAL,
            'context_id'   => null,
            'title'        => 'Other report',
            'description'  => 'A general message from vendor 99002 to the administrator team here.',
        ]);

        $rows = VendorReportRepository::find_by_vendor($this->vendor_id);

        $this->assertCount(1, $rows);
        $this->assertSame($this->vendor_id, (int) $rows[0]->vendor_id);
    }
}
