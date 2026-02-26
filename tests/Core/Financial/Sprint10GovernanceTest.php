<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\ApprovalStateMachine;
use MHMRentiva\Core\Financial\GovernanceService;
use WP_UnitTestCase;

/**
 * Validates the Multi-Actor Maker-Checker and Risk Engine flows.
 *
 * @since 4.21.0
 */
class Sprint10GovernanceTest extends WP_UnitTestCase
{
    private int $maker_id;
    private int $checker_id;
    private int $manager_id;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LedgerMigration::create_table();
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_payout_audit_table();
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::register_governance_capabilities();
    }

    public function setUp(): void
    {
        parent::setUp();

        // 1. Maker: The vendor or staff member who creates the payout
        $this->maker_id = self::factory()->user->create(array(
            'role' => 'editor',
        ));
        $maker = new \WP_User($this->maker_id);
        $maker->add_cap('mhm_rentiva_create_payout');
        $maker->add_cap('mhm_rentiva_review_payout');
        $maker->add_cap('mhm_rentiva_finalize_payout');

        // 2. Checker: The governance officer
        $this->checker_id = self::factory()->user->create(array(
            'role' => 'administrator',
        ));
        $checker = new \WP_User($this->checker_id);
        $checker->add_cap('mhm_rentiva_create_payout');
        $checker->add_cap('mhm_rentiva_review_payout');
        $checker->add_cap('mhm_rentiva_finalize_payout');

        $this->manager_id = self::factory()->user->create(array('role' => 'editor'));
    }

    public function tearDown(): void
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_payout_audit");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger");
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function create_payout(float $amount, string $date = 'now'): int
    {
        // To control Risk Engine, we adjust the vendor's age
        $vendor_id = self::factory()->user->create(array(
            'role' => 'customer',
            'user_registered' => wp_date('Y-m-d H:i:s', strtotime($date)),
        ));

        $payout_id = wp_insert_post(array(
            'post_type'   => PostType::POST_TYPE,
            'post_author' => $vendor_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Payout',
        ));

        update_post_meta((int) $payout_id, '_mhm_payout_amount', $amount);
        update_post_meta((int) $payout_id, '_mhm_payout_maker_id', $this->maker_id); // Assign maker explicitly
        update_post_meta((int) $payout_id, '_mhm_workflow_state', ApprovalStateMachine::STATE_PENDING);

        return (int) $payout_id;
    }

    public function test_maker_cannot_self_approve(): void
    {
        // New vendor = MEDIUM risk. Starts workflow.
        $payout_id = $this->create_payout(500.0, 'now');

        wp_set_current_user($this->maker_id);

        $result = GovernanceService::process_approval($payout_id);

        $this->assertWPError($result);
        $this->assertEquals('governance_maker_checker', $result->get_error_code());

        $expected_msg = 'Maker cannot be the Checker. Self-approval is explicitly prohibited.';
        $this->assertEquals($expected_msg, $result->get_error_message());
    }

    public function test_checker_can_approve_medium_risk(): void
    {
        // New vendor = MEDIUM risk. Requires Dual-Approval path.
        $payout_id = $this->create_payout(1000.0, 'now');

        // Checker takes action
        wp_set_current_user($this->checker_id);

        $result = GovernanceService::process_approval($payout_id);

        // Medium risk candidate should hit 'under_review'
        $this->assertTrue($result);

        $state = get_post_meta($payout_id, '_mhm_workflow_state', true);
        $this->assertEquals(ApprovalStateMachine::STATE_UNDER_REVIEW, $state);
    }

    public function test_high_risk_auto_freeze(): void
    {
        // Amount over 5000 + Bad Refund History = 30 + 40 = 70 score (HIGH risk)
        $payout_id = $this->create_payout(6000.0, 'now');
        $vendor_id = (int) get_post_meta($payout_id, '_mhm_payout_maker_id', true);

        // Seed terrible refund history to push score > 70
        global $wpdb;
        $ledger = $wpdb->prefix . 'mhm_rentiva_ledger';
        $vendor_id = get_post($payout_id)->post_author;

        $wpdb->insert($ledger, [
            'transaction_uuid' => wp_generate_password(16, false),
            'vendor_id' => $vendor_id,
            'type' => 'commission_credit',
            'amount' => 100,
            'currency' => 'TRY',
            'context' => 'test',
            'status' => 'cleared',
            'created_at' => current_time('mysql'),
        ]);
        $wpdb->insert($ledger, [
            'transaction_uuid' => wp_generate_password(16, false),
            'vendor_id' => $vendor_id,
            'type' => 'commission_refund',
            'amount' => 100,
            'currency' => 'TRY',
            'context' => 'test',
            'status' => 'cleared',
            'created_at' => current_time('mysql'),
        ]);

        wp_set_current_user($this->checker_id);

        $result = GovernanceService::process_approval($payout_id);

        $this->assertWPError($result);
        $this->assertEquals('governance_frozen_risk', $result->get_error_code());

        // State machine candidate resolver blocks High risk completely
        $expected_msg = 'Payout is auto-frozen due to HIGH risk scoring.';
        $this->assertEquals($expected_msg, $result->get_error_message());

        $state = get_post_meta($payout_id, '_mhm_workflow_state', true);
        $this->assertEquals(ApprovalStateMachine::STATE_PENDING, $state, 'State should remain untouched after auto-freeze.');
    }

    public function test_maker_can_bypass_with_override_capability(): void
    {
        // LOW risk = FAST TRACK
        $payout_id = $this->create_payout(500.0, '-100 days');

        // Give maker the explicit bypass capability
        $maker = new \WP_User($this->maker_id);
        $maker->add_cap('mhm_rentiva_override_maker_checker');

        wp_set_current_user($this->maker_id);

        $result = GovernanceService::process_approval($payout_id);

        // Should successfully bypass self-approval limit
        $this->assertTrue($result);

        // Verify override usage was forensically logged
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $log_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE payout_id = %d AND action = %s AND actor_user_id = %d", $payout_id, GovernanceService::ACTION_OVERRIDE_USED, $this->maker_id));

        $this->assertEquals(1, (int) $log_count, 'Override usage must trigger a specific ACTION_OVERRIDE_USED forensic audit log.');
    }
}
