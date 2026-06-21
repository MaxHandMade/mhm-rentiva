<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Payouts;

use MHMRentiva\Admin\PostTypes\Payouts\PayoutListTable;
use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\AtomicPayoutService;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * Single-click payout finalize.
 *
 * The admin "Approve Selected" bulk action must finalize a pending payout in ONE
 * click: post_status → publish + ledger debit. Previously it routed through the
 * multi-stage maker-checker governance state machine, so a MEDIUM-risk payout only
 * advanced to 'under_review' (still pending) while the bulk handler reported success.
 *
 * @group payouts
 */
final class SingleClickApproveTest extends WP_UnitTestCase {

	private int $admin_id = 0;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		LedgerMigration::create_table();
	}

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $this->admin_id );
		wp_set_current_user( $this->admin_id );

		if ( ! post_type_exists( PostType::POST_TYPE ) ) {
			register_post_type(
				PostType::POST_TYPE,
				array(
					'public'   => false,
					'supports' => array( 'title', 'author', 'custom-fields' ),
				)
			);
		}
	}

	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		unset( $_POST['payout_ids'], $_POST['_wpnonce'], $_POST['action'] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function make_pending_payout( float $amount ): int {
		$vendor = (int) self::factory()->user->create( array( 'role' => 'rentiva_vendor' ) );
		// Seed enough cleared balance so the approval's funds check passes (mirrors a real
		// request, which only succeeds when the vendor has the balance).
		if ( $amount > 0 ) {
			Ledger::add_entry( new LedgerEntry(
				'seed_' . $vendor, $vendor, null, null, 'commission_credit', $amount,
				null, null, null, 'TRY', 'booking', 'cleared', null, null, null
			) );
		}
		$id = (int) self::factory()->post->create( array(
			'post_type'   => PostType::POST_TYPE,
			'post_status' => 'pending',
			'post_author' => $vendor,
		) );
		update_post_meta( $id, '_mhm_payout_amount', $amount );
		return $id;
	}

	public function test_approve_publishes_pending_payout(): void {
		$payout = $this->make_pending_payout( 250.00 );

		$result = AtomicPayoutService::approve( $payout );

		$this->assertTrue( $result, 'approve() must return true.' );
		$this->assertSame( 'publish', get_post_status( $payout ), 'Payout must be published (approved).' );
	}

	public function test_approve_rejects_zero_amount(): void {
		$payout = $this->make_pending_payout( 0.0 );

		$result = AtomicPayoutService::approve( $payout );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_amount', $result->get_error_code() );
		$this->assertSame( 'pending', get_post_status( $payout ) );
	}

	public function test_bulk_approve_single_click_publishes_medium_risk_payout(): void {
		$payout = $this->make_pending_payout( 120.00 );

		$_POST['payout_ids'] = array( (string) $payout );
		$_POST['_wpnonce']   = wp_create_nonce( PayoutListTable::BULK_ACTION_APPROVE );

		$result = PayoutListTable::process_bulk_approve();

		$this->assertSame( array(), $result['errors'], 'No governance errors expected.' );
		$this->assertSame( 1, $result['approved'], 'Exactly one payout approved.' );
		$this->assertSame( 'publish', get_post_status( $payout ), 'Single bulk approve must finalize (publish) the payout.' );
	}

	public function test_approve_rejects_when_balance_insufficient(): void {
		$vendor = (int) self::factory()->user->create( array( 'role' => 'rentiva_vendor' ) );
		// Vendor only has 100 cleared, but a 500 payout is pending — e.g. a withdrawal penalty
		// was applied after the request, dropping the balance below the requested amount.
		Ledger::add_entry( new LedgerEntry(
			'bal_' . $vendor, $vendor, null, null, 'commission_credit', 100.0,
			null, null, null, 'TRY', 'booking', 'cleared', null, null, null
		) );

		$payout = (int) self::factory()->post->create( array(
			'post_type'   => PostType::POST_TYPE,
			'post_status' => 'pending',
			'post_author' => $vendor,
		) );
		update_post_meta( $payout, '_mhm_payout_amount', 500.0 );

		$result = AtomicPayoutService::approve( $payout );

		$this->assertWPError( $result );
		$this->assertSame( 'insufficient_funds', $result->get_error_code() );
		$this->assertSame( 'pending', get_post_status( $payout ), 'Payout must stay pending when the balance is insufficient.' );
	}

	public function test_vendor_bio_url_targets_vendor_detail(): void {
		$url = PayoutListTable::vendor_bio_url( 42 );

		$this->assertStringContainsString( 'page=mhm-rentiva-vendors', $url );
		$this->assertStringContainsString( 'tab=vendors', $url );
		$this->assertStringContainsString( 'vendor=42', $url );
	}
}
