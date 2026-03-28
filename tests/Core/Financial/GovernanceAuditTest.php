<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\GovernanceService;
use WP_UnitTestCase;

/**
 * Tests Governance audit trail immutability.
 * 
 * @since 4.21.0
 */
class GovernanceAuditTest extends WP_UnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LedgerMigration::create_table();
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_payout_audit_table();
    }

    /** @test */
    public function test_audit_log_inserts_correctly(): void
    {
        global $wpdb;
        $table    = $wpdb->prefix . 'mhm_rentiva_payout_audit';

        $actor_id = self::factory()->user->create(array('role' => 'administrator'));
        $vendor_id = self::factory()->user->create(array('role' => 'customer'));

        $payout_id = wp_insert_post(array(
            'post_type'   => PostType::POST_TYPE,
            'post_author' => $vendor_id,
            'post_status' => 'pending',
            'post_title'  => 'Test Payout',
        ));

        GovernanceService::log_approval_event($payout_id, $actor_id, GovernanceService::ACTION_EXECUTED, '', 'Test context');

        $audit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE payout_id = %d", $payout_id));

        $this->assertNotNull($audit);
        $this->assertEquals($actor_id, $audit->actor_user_id);
        $this->assertEquals(GovernanceService::ACTION_EXECUTED, $audit->action);

        $metadata = json_decode($audit->metadata_json, true);
        $this->assertEquals('Test context', $metadata['context']);
    }
}
