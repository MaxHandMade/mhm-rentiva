<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\GovernanceService;
use WP_UnitTestCase;

/**
 * Tests Governance freeze states.
 * 
 * @since 4.21.0
 */
class GovernanceFreezeTest extends WP_UnitTestCase
{
    private int $vendor_id;
    private int $admin_id;

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
        $this->vendor_id = self::factory()->user->create(array('role' => 'customer'));
        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
        wp_set_current_user($this->admin_id);
    }

    public function tearDown(): void
    {
        delete_option('mhm_rentiva_global_payout_freeze');
        delete_user_meta($this->vendor_id, '_mhm_vendor_payout_freeze');
        parent::tearDown();
    }

    /** @test */
    public function test_global_freeze_blocks_approval(): void
    {
        update_option('mhm_rentiva_global_payout_freeze', 'yes');
        $payout_id = $this->create_pending_payout(500.0);

        $result = GovernanceService::process_approval($payout_id);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('governance_frozen_global', $result->get_error_code());

        // Assert audit
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $audit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE payout_id = %d AND action = %s", $payout_id, GovernanceService::ACTION_DENY));
        $this->assertNotNull($audit);
    }

    /** @test */
    public function test_vendor_freeze_blocks_approval(): void
    {
        update_user_meta($this->vendor_id, '_mhm_vendor_payout_freeze', 'yes');
        $payout_id = $this->create_pending_payout(500.0);

        $result = GovernanceService::process_approval($payout_id);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('governance_frozen_vendor', $result->get_error_code());

        // Assert audit
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $audit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE payout_id = %d AND action = %s", $payout_id, GovernanceService::ACTION_DENY));
        $this->assertNotNull($audit);
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
