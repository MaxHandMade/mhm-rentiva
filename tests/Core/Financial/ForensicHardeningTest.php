<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Core\Financial\AtomicPayoutService;
use MHMRentiva\Core\Financial\GovernanceService;
use MHMRentiva\Core\Database\Ledger;
use MHMRentiva\Core\Financial\Events\DomainEventDispatcher;
use MHMRentiva\Core\Financial\Events\PayoutApprovedEvent;
use WP_UnitTestCase;

/**
 * @group financial
 * @group audit
 * @group forensic
 */
class ForensicHardeningTest extends WP_UnitTestCase
{
    private int $vendor_id;
    private int $payout_id;

    public function setUp(): void
    {
        parent::setUp();

        global $wpdb;

        // Drop the table to force dbDelta to recreate it with the full new schema including tx_uuid
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mhm_rentiva_payout_audit");

        // Force dbDelta to ensure tx_uuid column exists in the test database
        require_once dirname(__DIR__, 3) . '/src/Admin/Core/Utilities/DatabaseMigrator.php';
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_payout_audit_table();

        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_payout_audit");

        // 1. Create a Vendor older than 90 days to bypass the New Vendor Risk Penalty
        $this->vendor_id = $this->factory->user->create(array(
            'role' => 'mhm_vendor',
            'user_registered' => wp_date('Y-m-d H:i:s', strtotime('-100 days')),
        ));

        // 2. Insert absolute minimum manual ledger setup instead of WP_Mock
        // Not strictly needed for logic test, but good isolation.
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_payout_audit");

        parent::tearDown();
    }

    private function create_pending_payout(float $amount = 1000.00): int
    {
        $payout_id = wp_insert_post(array(
            'post_type'   => 'mhm_payout',
            'post_status' => 'pending',
            'post_author' => $this->vendor_id,
            'post_title'  => 'Test Payout',
        ));

        update_post_meta($payout_id, '_mhm_payout_amount', $amount);

        return $payout_id;
    }

    public function test_forensic_commit_success(): void
    {
        global $wpdb;
        $payout_id = $this->create_pending_payout();

        // Admin capable of approving payouts
        $admin_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);
        $user = get_user_by('id', $admin_id);
        $user->add_cap('mhm_rentiva_finalize_payout');

        $result = GovernanceService::process_approval($payout_id);

        $this->assertTrue($result, 'Process approval should succeed.');

        // Verify the audit log was physically written
        $audit_table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$audit_table} WHERE payout_id = %d AND action = %s", $payout_id, GovernanceService::ACTION_FINALIZED));

        $this->assertNotNull($log, 'Audit log must be written on successful commit.');
        $this->assertSame((string) $admin_id, $log->actor_user_id);
        $this->assertNotEmpty($log->tx_uuid, 'Transaction UUID must be present.');
    }

    public function test_forensic_rollback_guarantee(): void
    {
        global $wpdb;
        $payout_id = $this->create_pending_payout();

        $admin_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);
        $user = get_user_by('id', $admin_id);
        $user->add_cap('mhm_rentiva_finalize_payout');

        $force_error = function ($query) {
            if (strpos($query, 'INSERT INTO') !== false && strpos($query, 'mhm_rentiva_ledger') !== false) {
                return 'SYNTAX ERROR INTENTIONALLY INDUCED BY TEST';
            }
            return $query;
        };
        add_filter('query', $force_error);

        // Suppress expected DB error output in tests
        $wpdb->suppress_errors(true);

        $result = GovernanceService::process_approval($payout_id);

        $wpdb->suppress_errors(false);
        remove_filter('query', $force_error);

        $this->assertWPError($result, 'Process approval should fail and rollback.');

        // Crucial Check: Ensure the memory dispatcher was emptied and NO 'approve_payout' audit log was written
        $audit_table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $log_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$audit_table} WHERE payout_id = %d AND action = %s", $payout_id, GovernanceService::ACTION_FINALIZED));

        $this->assertEquals(0, (int) $log_count, 'Forensic buffer MUST NOT persist an approval if transaction rolls back.');
    }

    public function test_forensic_duplicate_guard(): void
    {
        global $wpdb;
        $payout_id = $this->create_pending_payout();
        $actor_id  = 123;
        $tx_uuid   = 'baba-yaga-tx-uuid-constant';

        $audit_table = $wpdb->prefix . 'mhm_rentiva_payout_audit';

        // Insert first
        GovernanceService::log_approval_event($payout_id, $actor_id, GovernanceService::ACTION_FINALIZED, $tx_uuid, 'Test');

        // Assert 1 row
        $count1 = $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table}");
        $this->assertEquals(1, (int) $count1);

        $wpdb->suppress_errors(true);
        // Try to insert exact identical tx_uuid
        GovernanceService::log_approval_event($payout_id, $actor_id, GovernanceService::ACTION_FINALIZED, $tx_uuid, 'Test Again');
        $wpdb->suppress_errors(false);

        // Assert still 1 row (UNIQUE constraint kicked in)
        $count2 = $wpdb->get_var("SELECT COUNT(*) FROM {$audit_table}");
        $this->assertEquals(1, (int) $count2, 'Unique constraint (payout_id, action, tx_uuid) must prevent duplicate logs.');
    }

    public function test_forensic_event_not_duplicated_on_retry(): void
    {
        global $wpdb;
        $payout_id = $this->create_pending_payout();

        $admin_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($admin_id);
        $user = get_user_by('id', $admin_id);
        $user->add_cap('mhm_rentiva_finalize_payout');

        // First attempt - SUCCESS
        $result1 = GovernanceService::process_approval($payout_id);
        $this->assertTrue($result1);

        // Second attempt on SAME payout - Will hit `governance_frozen_risk` limit because it's 'executed' already
        $result2 = GovernanceService::process_approval($payout_id);
        $this->assertWPError($result2);
        $this->assertEquals('governance_frozen_risk', $result2->get_error_code());

        // Verify only ONE 'execute_payout' audit log exists for this payout
        $audit_table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $log_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$audit_table} WHERE action = %s AND payout_id = %d", GovernanceService::ACTION_FINALIZED, $payout_id));

        $this->assertEquals(1, (int) $log_count, 'Only one successful approve audit log must exist despite concurrent retry attempts.');
    }
}
