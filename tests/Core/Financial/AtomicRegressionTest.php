<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\GovernanceService;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

/**
 * Tests Governance layer does not break AtomicPayoutService logic.
 * 
 * @since 4.21.0
 * @method void set_up()
 * @method void tear_down()
 * @mixin \WP_UnitTestCase
 */
class AtomicRegressionTest extends WP_UnitTestCase
{
    private int $vendor_id;
    private int $admin_id;
    private const CREDIT = 5000;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LedgerMigration::create_table();
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::create_payout_audit_table();
        \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::register_governance_capabilities();
    }

    public function set_up()
    {
        /** @noinspection PhpUndefinedMethodInspection */
        parent::set_up();

        $this->vendor_id = $this->factory->user->create(array(
            'role' => 'customer',
            'user_registered' => wp_date('Y-m-d H:i:s', strtotime('-100 days')),
        ));
        $this->admin_id = $this->factory->user->create(array('role' => 'administrator'));

        // Grant super admin context for core tearDown delete_post capability checks
        grant_super_admin($this->admin_id);
        wp_set_current_user($this->admin_id);

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

    public function tear_down()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_ledger"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query("DELETE FROM {$wpdb->prefix}mhm_rentiva_key_registry"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        wp_set_current_user(0);
        /** @noinspection PhpUndefinedMethodInspection */
        parent::tear_down();
    }

    /** @test */
    public function test_governance_wrap_succeeds_atomic_payout(): void
    {
        $payout_id = $this->create_pending_payout(1000.0);

        $result = GovernanceService::process_approval($payout_id);

        $this->assertTrue($result);

        // Verify CPT status changed to publish
        $post = get_post($payout_id);
        $this->assertSame('publish', $post->post_status);

        // Verify ledger balance was correctly debited
        $balance = Ledger::get_balance($this->vendor_id);
        $this->assertEqualsWithDelta(
            (float) self::CREDIT - 1000.0,
            $balance,
            0.01,
            'Vendor balance must be reduced by payout amount after Governance approval.'
        );

        // Verify Governance audit logged the TRUE execution, not just the check
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_payout_audit';
        $audit = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE payout_id = %d AND action = %s", $payout_id, GovernanceService::ACTION_FINALIZED));

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
}
