<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\VendorReport;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\PenaltyCalculator;
use MHMRentiva\Admin\Vehicle\PenaltyRecorder;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleManager;
use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportService;
use MHMRentiva\Admin\VendorReport\Hooks\PenaltySuspensionHook;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Database\Migrations\VendorReportsMigration;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * End-to-end guard for the vendor appeal / penalty-waiver flow.
 *
 * Withdraw-with-reason opens a report and SUSPENDS the penalty; the admin then either rejects
 * the appeal (penalty applied) or resolves it (penalty waived). This whole flow was dead on
 * real installs until the vendor_reports table started being created (schema-version fix).
 *
 * @group vendor-report
 */
final class AppealPenaltyFlowTest extends WP_UnitTestCase {

	private int $vendor_id = 0;
	private int $admin_id  = 0;
	private VendorReportService $service;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		LedgerMigration::create_table();
		VendorReportsMigration::create_table();
	}

	public function setUp(): void {
		parent::setUp();
		$this->admin_id  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->vendor_id = (int) self::factory()->user->create( array( 'role' => 'rentiva_vendor' ) );
		$this->service   = new VendorReportService();

		PenaltyRecorder::register();
		PenaltySuspensionHook::register();

		// Revenue → monthly average 2000; one prior withdrawal → next is tier-2 (25% = 500).
		Ledger::add_entry( new LedgerEntry( 'flow_rev_' . $this->vendor_id, $this->vendor_id, null, null, 'commission_credit', 2000.00, null, null, null, 'TRY', 'booking', 'cleared', null, null, null ) );
		$prior = (int) wp_insert_post( array( 'post_type' => 'vehicle', 'post_status' => 'draft', 'post_author' => $this->vendor_id, 'post_title' => 'Prior' ) );
		update_post_meta( $prior, MetaKeys::VEHICLE_WITHDRAWN_AT, gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ) );
	}

	public function tearDown(): void {
		global $wpdb;
		remove_action( 'mhm_rentiva_vehicle_withdrawn', array( PenaltyRecorder::class, 'record_penalty' ), 20 );
		remove_filter( 'mhm_rentiva_before_apply_penalty', array( PenaltySuspensionHook::class, 'maybe_suspend' ), 10 );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_vendor_reports" ); // phpcs:ignore WordPress.DB
		parent::tearDown();
	}

	private function penalty_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE vendor_id=%d AND type='withdrawal_penalty'", $this->vendor_id ) );
	}

	private function withdraw_with_reason( string $reason ): int {
		$vehicle = (int) wp_insert_post( array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'post_author' => $this->vendor_id, 'post_title' => 'Active' ) );
		// Mirror VehicleLifecycleAjaxController: create the report BEFORE withdrawing.
		$report_id = $this->service->create_report( $this->vendor_id, VendorReportContext::VEHICLE_ACTION, $vehicle, 'Withdrawal reason', $reason );
		$this->assertIsInt( $report_id, 'create_report must succeed (table must exist).' );
		VehicleLifecycleManager::withdraw( $vehicle, $this->vendor_id );
		return (int) $report_id;
	}

	public function test_open_appeal_suspends_then_reject_applies_penalty(): void {
		$report_id = $this->withdraw_with_reason( 'Aracimi uzun sureli bakima aldim, listede kalmasin lutfen.' );

		$this->assertSame( 0, $this->penalty_count(), 'Penalty must be suspended while the appeal is open.' );

		$this->service->reject_report( $report_id, 'Sebep yetersiz.', $this->admin_id );

		$this->assertSame( 1, $this->penalty_count(), 'Rejecting the appeal must apply the deferred penalty.' );
		$this->assertSame( 1500.0, Ledger::get_balance( $this->vendor_id ), 'Balance must drop by the tier-2 penalty (25% of 2000).' );
	}

	public function test_open_appeal_then_resolve_waives_penalty(): void {
		$report_id = $this->withdraw_with_reason( 'Musteri talebiyle gecici cekiyorum, gecerli gerekce.' );

		$this->assertSame( 0, $this->penalty_count(), 'Penalty must be suspended while the appeal is open.' );

		$this->service->resolve_report( $report_id, 'Gerekce gecerli.', $this->admin_id );

		$this->assertSame( 0, $this->penalty_count(), 'Resolving (upholding) the appeal must waive the penalty.' );
		$this->assertSame( 2000.0, Ledger::get_balance( $this->vendor_id ), 'Balance must be unchanged when the penalty is waived.' );
	}

	public function test_resolve_penalty_appeal_refunds_an_already_applied_penalty(): void {
		// An already-applied penalty: a cleared withdrawal_penalty debit, referenced by its UUID.
		$penalty_uuid = 'wpen_appliedpenaltytest0001';
		Ledger::add_entry( new LedgerEntry(
			$penalty_uuid, $this->vendor_id, null, null, 'withdrawal_penalty', -250.0,
			null, null, null, 'TRY', 'platform', 'cleared', null, null, null
		) );
		$balance_before = Ledger::get_balance( $this->vendor_id ); // 2000 - 250 = 1750

		// Vendor appeals that specific penalty (PENALTY context, context_id = the ledger UUID).
		$report_id = $this->service->create_report( $this->vendor_id, VendorReportContext::PENALTY, $penalty_uuid, 'Penalty appeal', 'Bu ceza haksiz uygulandi, gecerli bir gerekcem vardi.' );
		$this->assertIsInt( $report_id );

		$this->service->resolve_report( (int) $report_id, 'Gerekce gecerli, ceza iade edildi.', $this->admin_id );

		$this->assertSame( $balance_before + 250.0, Ledger::get_balance( $this->vendor_id ), 'Resolving a penalty appeal must refund the applied penalty.' );

		global $wpdb;
		$reversals = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE vendor_id=%d AND type='withdrawal_penalty_reversal'",
			$this->vendor_id
		) );
		$this->assertSame( 1, $reversals, 'A compensating reversal ledger entry must be written.' );
	}

	public function test_resolving_penalty_appeal_restores_reliability_score(): void {
		// Withdraw with NO open appeal → the penalty applies immediately and PenaltyRecorder
		// stores the ledger UUID on the vehicle. The withdrawal also drags the reliability
		// score down (it is now counted in the rolling window).
		$vehicle = (int) wp_insert_post( array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'post_author' => $this->vendor_id, 'post_title' => 'Applied' ) );
		VehicleLifecycleManager::withdraw( $vehicle, $this->vendor_id );

		$this->assertSame( 1, $this->penalty_count(), 'Penalty must apply when no appeal is open.' );
		$uuid = (string) get_post_meta( $vehicle, MetaKeys::VEHICLE_PENALTY_UUID, true );
		$this->assertNotSame( '', $uuid, 'The applied penalty UUID must be stored on the vehicle.' );

		// 'Prior' (setUp) + this vehicle = 2 withdrawals counted against the score.
		$this->assertSame( 2, PenaltyCalculator::get_rolling_withdrawal_count( $this->vendor_id ), 'Both withdrawals must count before the appeal is upheld.' );

		// Vendor appeals the applied penalty and wins.
		$report_id = $this->service->create_report( $this->vendor_id, VendorReportContext::PENALTY, $uuid, 'Penalty appeal', 'Bu ceza haksiz uygulandi, gecerli bir gerekcem vardi.' );
		$this->assertIsInt( $report_id );
		$this->service->resolve_report( (int) $report_id, 'Kabul.', $this->admin_id );

		// Full exoneration: money refunded AND the withdrawal no longer counts against the score.
		$this->assertSame( '1', (string) get_post_meta( $vehicle, MetaKeys::VEHICLE_WITHDRAWAL_EXCUSED, true ), 'The vehicle must be flagged as excused.' );
		$this->assertSame( 1, PenaltyCalculator::get_rolling_withdrawal_count( $this->vendor_id ), 'The exonerated withdrawal must be excluded from the rolling count (score restored).' );
	}

	public function test_resolving_vehicle_action_appeal_excludes_withdrawal_from_score(): void {
		$report_id = $this->withdraw_with_reason( 'Gecerli gerekce ile cekiyorum, lutfen mazur gorun bu seferlik.' );

		$this->assertSame( 2, PenaltyCalculator::get_rolling_withdrawal_count( $this->vendor_id ), 'Both withdrawals count while the appeal is open.' );

		$this->service->resolve_report( $report_id, 'Gerekce gecerli.', $this->admin_id );

		$this->assertSame( 1, PenaltyCalculator::get_rolling_withdrawal_count( $this->vendor_id ), 'Upholding a vehicle-action appeal must exclude the withdrawal from the score count.' );
	}
}
