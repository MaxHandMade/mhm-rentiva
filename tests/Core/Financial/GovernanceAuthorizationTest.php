<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\GovernanceService;
use WP_UnitTestCase;

/**
 * Tests Governance authorization layers.
 * 
 * @since 4.21.0
 */
class GovernanceAuthorizationTest extends WP_UnitTestCase
{
    private int $vendor_id;
    private int $manager_id;
    private int $admin_id;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LedgerMigration::create_table();

        // Ensure audit table exists
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_payout_audit_table();

        // Ensure capabilities exist on Administrator
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::register_governance_capabilities();
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = self::factory()->user->create(array('role' => 'customer'));

        $this->manager_id = self::factory()->user->create(array('role' => 'editor'));
        $manager = new \WP_User($this->manager_id);
        $manager->add_cap('manage_options'); // Has legacy dashboard access, but no governance cap

        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
    }

    /** @test */
    public function test_auth_denied_without_explicit_capability(): void
    {
        $payout_id = $this->create_pending_payout(500.0);

        wp_set_current_user($this->manager_id);

        $result = GovernanceService::process_approval($payout_id);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('governance_denied', $result->get_error_code());

        // Verify audit log recorded the denial
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $audit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE payout_id = %d AND action = %s", $payout_id, GovernanceService::ACTION_DENY));

        $this->assertNotNull($audit, 'Unauthorized attempts must be audited.');
        $this->assertEquals($this->manager_id, $audit->actor_user_id);
    }

    /** @test */
    public function test_auth_allowed_with_capability(): void
    {
        $payout_id = $this->create_pending_payout(500.0);

        wp_set_current_user($this->admin_id);

        // Provide enough credit so Atomic Payout succeeds
        $this->seed_credit($this->vendor_id, 1000.0);

        $result = GovernanceService::process_approval($payout_id);

        $this->assertTrue($result);

        // Verify audit log recorded the execution action (Low risk due to seeded ledger credit history)
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $audit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE payout_id = %d AND action = %s", $payout_id, GovernanceService::ACTION_EXECUTED));

        $this->assertNotNull($audit, 'Approval transitions must be audited.');
        $this->assertEquals($this->admin_id, $audit->actor_user_id);
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

    private function seed_credit(int $vendor_id, float $amount): void
    {
        \MHMRentiva\Core\Financial\Ledger::add_entry(new \MHMRentiva\Core\Financial\LedgerEntry(
            'seed_credit_' . wp_generate_password(8, false),
            $vendor_id,
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
}
