<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vehicle\PenaltyCalculator;
use MHMRentiva\Admin\Vehicle\PenaltyRecorder;
use MHMRentiva\Admin\Vehicle\VehicleLifecycleStatus;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * End-to-end guard for the financial side of the withdrawal penalty.
 *
 * PenaltyCalculatorTest covers the rate math; this covers the part that was never
 * tested: PenaltyRecorder writing a ledger debit. This chain was silently failing at
 * runtime before the SaaS control-plane gate was removed from Ledger::add_entry
 * (2026-06-21): the gate threw "Tenant does not exist" (the single-site tenant is never
 * provisioned) and PenaltyRecorder's broad catch swallowed it, so no debit landed.
 *
 * record_penalty() is invoked directly rather than through do_action() so the assertions
 * do not depend on global hook/filter state left by other tests (the wiring itself is
 * asserted separately in test_register_hooks_recorder).
 *
 * @group vendor-lifecycle
 */
final class PenaltyRecorderTest extends WP_UnitTestCase {

	private int $vendor_id = 0;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		LedgerMigration::create_table();
	}

	public function setUp(): void {
		parent::setUp();
		$this->vendor_id = (int) self::factory()->user->create( array( 'role' => 'rentiva_vendor' ) );
		// Isolate from the Vendor-Report appeal-suspension feature (PenaltySuspensionHook),
		// which other tests may leave on this filter. This covers the recording chain, not
		// the suspension gate, so force the penalty to apply.
		add_filter( 'mhm_rentiva_before_apply_penalty', '__return_true', 99 );
	}

	public function tearDown(): void {
		global $wpdb;
		remove_filter( 'mhm_rentiva_before_apply_penalty', '__return_true', 99 );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tearDown();
	}

	private function seed_revenue( float $amount ): void {
		Ledger::add_entry( new LedgerEntry(
			'rev_' . $this->vendor_id, $this->vendor_id, null, null, 'commission_credit', $amount,
			null, null, null, 'TRY', 'booking', 'cleared', null, null, null
		) );
	}

	private function create_prior_withdrawal(): void {
		$v = (int) wp_insert_post( array( 'post_type' => 'vehicle', 'post_status' => 'draft', 'post_author' => $this->vendor_id, 'post_title' => 'Prior Withdrawn' ) );
		update_post_meta( $v, MetaKeys::VEHICLE_LIFECYCLE_STATUS, VehicleLifecycleStatus::WITHDRAWN );
		update_post_meta( $v, MetaKeys::VEHICLE_WITHDRAWN_AT, gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ) );
	}

	private function penalty_row_amount(): ?float {
		global $wpdb;
		$val = $wpdb->get_var( $wpdb->prepare(
			"SELECT amount FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE vendor_id = %d AND type = 'withdrawal_penalty' ORDER BY id DESC LIMIT 1",
			$this->vendor_id
		) );
		return null === $val ? null : (float) $val;
	}

	public function test_penalty_uuid_fits_ledger_column(): void {
		// transaction_uuid is CHAR(36); the UUID must never exceed it even for large IDs,
		// or the penalty debit silently fails to write (regression guard).
		$uuid = PenaltyRecorder::penalty_uuid( 999999, 888888 );
		$this->assertLessThanOrEqual( 36, strlen( $uuid ), 'Penalty UUID must fit transaction_uuid CHAR(36).' );
	}

	public function test_register_hooks_recorder(): void {
		PenaltyRecorder::register();
		$this->assertNotFalse(
			has_action( 'mhm_rentiva_vehicle_withdrawn', array( PenaltyRecorder::class, 'record_penalty' ) ),
			'PenaltyRecorder must hook record_penalty to the withdrawn action.'
		);
		remove_action( 'mhm_rentiva_vehicle_withdrawn', array( PenaltyRecorder::class, 'record_penalty' ), 20 );
	}

	public function test_second_withdrawal_debits_penalty_to_ledger(): void {
		$this->seed_revenue( 1000.0 );
		$this->create_prior_withdrawal(); // rolling count → 1, so the next is tier-2 (10%).

		$vehicle2 = (int) wp_insert_post( array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'post_author' => $this->vendor_id, 'post_title' => 'Second Vehicle' ) );
		$penalty  = PenaltyCalculator::calculate_withdrawal_penalty( $vehicle2, $this->vendor_id );
		$this->assertSame( 100.0, $penalty, '2nd withdrawal penalty must be 10% of the 1000 monthly average.' );

		$balance_before = Ledger::get_balance( $this->vendor_id );

		PenaltyRecorder::record_penalty( $vehicle2, $this->vendor_id, $penalty );

		$this->assertSame( -100.0, $this->penalty_row_amount(), 'A withdrawal_penalty debit of -100 must be written to the ledger.' );
		$this->assertSame( $balance_before - 100.0, Ledger::get_balance( $this->vendor_id ), 'Balance must drop by the penalty amount.' );
	}

	public function test_first_withdrawal_writes_no_penalty(): void {
		$this->seed_revenue( 1000.0 );
		// No prior withdrawal → rolling count 0 → tier-1 (free).

		$vehicle = (int) wp_insert_post( array( 'post_type' => 'vehicle', 'post_status' => 'publish', 'post_author' => $this->vendor_id, 'post_title' => 'First Vehicle' ) );
		$penalty = PenaltyCalculator::calculate_withdrawal_penalty( $vehicle, $this->vendor_id );
		$this->assertSame( 0.0, $penalty );

		PenaltyRecorder::record_penalty( $vehicle, $this->vendor_id, $penalty );

		$this->assertNull( $this->penalty_row_amount(), 'First withdrawal is free — no penalty row.' );
	}
}
