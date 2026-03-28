<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\AtomicPayoutService;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * PHPUnit test suite for AtomicPayoutService — Sprint 6 QA evidence.
 *
 * Covers 4 scenarios required by the governance model:
 *   1. Atomic success: ledger debit + CPT status = publish, both committed.
 *   2. rows_affected guard: duplicate UUID → rows_affected=0 → ROLLBACK → CPT pending.
 *   3. Concurrent guard: CPT already approved → pre-flight catches it → no double-debit.
 *   4. Invalid payout_id: pre-flight returns WP_Error before TX opens.
 *
 * @since 4.21.0
 */
class AtomicPayoutServiceTest extends WP_UnitTestCase
{
    private int $vendor_id;
    private const CREDIT = 5000;

    /**
     * Run dbDelta once per class — outside per-test lifecycle.
     *
     * dbDelta() may fire a WP deprecated notice. WP_UnitTestCase::assertPostConditions()
     * fails on unexpected caught_deprecated entries. Moving the migration here ensures
     * the notice fires before any test method runs, so assertPostConditions() never
     * sees it as part of a test's caught_deprecated list.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LedgerMigration::create_table();
    }

    public function setUp(): void
    {
        parent::setUp();

        // Administrator + super-admin required: WP_UnitTestCase tearDown calls delete_post
        // which checks the 'delete_post' capability. Without this, tearDown throws.
        $this->vendor_id = self::factory()->user->create(array('role' => 'administrator'));
        grant_super_admin($this->vendor_id);
        wp_set_current_user($this->vendor_id);

        // Register a minimal CPT stub for transaction testing.
        // map_meta_cap is intentionally omitted: WP 6.1+ fires _doing_it_wrong('map_meta_cap')
        // when current_user_can('delete_post') is called without a post ID via that code path.
        // These tests verify DB transaction behaviour, not capability enforcement.
        if (! post_type_exists(PostType::POST_TYPE)) {
            register_post_type(
                PostType::POST_TYPE,
                array(
                    'public'   => false,
                    'supports' => array('title', 'author', 'custom-fields'),
                )
            );
        }

        $this->seed_credit(self::CREDIT);
    }

    public function tearDown(): void
    {
        global $wpdb;
        // Truncate custom ledger table — WP_UnitTestCase does not clean custom tables.
        // Reset per-test so balance assertions start from a clean slate.
        $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        wp_set_current_user(0);
        parent::tearDown();
    }

	// ── Scenario 1: Happy path ───────────────────────────────────────────────

    /** @test */
    public function test_atomic_approve_success(): void
    {
        $payout_id = $this->create_pending_payout(1000.0);

        $result = AtomicPayoutService::approve($payout_id);

        $this->assertTrue($result, 'approve() must return true on success.');

        $post = get_post($payout_id);
        $this->assertSame('publish', $post->post_status, 'CPT must be publish after atomic approve.');

        $balance = Ledger::get_balance($this->vendor_id);
        $this->assertEqualsWithDelta(
            (float) self::CREDIT - 1000.0,
            $balance,
            0.01,
            'Vendor balance must be reduced by payout amount after COMMIT.'
        );
    }

	// ── Scenario 2: rows_affected guard ─────────────────────────────────────

    /** @test */
    public function test_rows_affected_zero_triggers_rollback(): void
    {
        $payout_id = $this->create_pending_payout(500.0);

        // Pre-seed the exact UUID AtomicPayoutService will generate ('payout_{id}').
        // Ledger::add_entry() silently ignores duplicate UUID (no exception) but
        // rows_affected will be 0 — AtomicPayoutService must detect and ROLLBACK.
        $this->seed_entry_with_uuid('payout_' . $payout_id, 0.0);

        $result = AtomicPayoutService::approve($payout_id);

        $this->assertInstanceOf(\WP_Error::class, $result, 'rows_affected=0 must return WP_Error.');
        $this->assertSame('atomic_approve_failed', $result->get_error_code());

        $post = get_post($payout_id);
        $this->assertSame('pending', $post->post_status, 'CPT must remain pending after ROLLBACK.');

        $balance = Ledger::get_balance($this->vendor_id);
        $this->assertEqualsWithDelta((float) self::CREDIT, $balance, 0.01, 'Balance must not change on ROLLBACK.');
    }

	// ── Scenario 3: Concurrent / deadlock guard ──────────────────────────────

    /** @test */
    public function test_concurrent_approve_guard(): void
    {
        $payout_id = $this->create_pending_payout(800.0);

        // Simulate concurrent approve: directly set CPT status to 'publish'.
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            array('post_status' => 'publish'),
            array('ID' => $payout_id),
            array('%s'),
            array('%d')
        );
        clean_post_cache($payout_id);

        $result = AtomicPayoutService::approve($payout_id);

        $this->assertInstanceOf(\WP_Error::class, $result, 'Already-approved payout must return WP_Error.');

        $balance = Ledger::get_balance($this->vendor_id);
        $this->assertEqualsWithDelta(
            (float) self::CREDIT,
            $balance,
            0.01,
            'Balance must not change on concurrent approve attempt.'
        );
    }

	// ── Scenario 4: Invalid payout_id ───────────────────────────────────────

    /** @test */
    public function test_invalid_payout_id_returns_wp_error(): void
    {
        $result = AtomicPayoutService::approve(9999999);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_payout', $result->get_error_code());
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function seed_credit(float $amount): void
    {
        Ledger::add_entry(new LedgerEntry(
            'seed_credit_' . wp_generate_password(8, false),
            $this->vendor_id,
            null,
            null,
            'commission_credit',
            $amount,
            $amount,
            0.0,
            0.0,
            'TRY',
            'vendor',
            'cleared'
        ));
    }

    private function seed_entry_with_uuid(string $uuid, float $amount): void
    {
        Ledger::add_entry(new LedgerEntry(
            $uuid,
            $this->vendor_id,
            null,
            null,
            'payout_debit',
            $amount * -1,
            null,
            null,
            null,
            'TRY',
            'payout',
            'cleared'
        ));
    }

    private function create_pending_payout(float $amount): int
    {
        $post_id = wp_insert_post(array(
            'post_type'   => PostType::POST_TYPE,
            'post_author' => $this->vendor_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Payout',
        ));
        update_post_meta((int) $post_id, '_mhm_payout_amount', $amount);
        return (int) $post_id;
    }
}
