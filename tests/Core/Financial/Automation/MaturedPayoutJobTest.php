<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial\Automation;

use MHMRentiva\Admin\PostTypes\Payouts\PostType;
use MHMRentiva\Core\Financial\ApprovalStateMachine;
use MHMRentiva\Core\Financial\Automation\MaturedPayoutJob;
use WP_UnitTestCase;

/**
 * Regression: the matured-payout worker must process a matured time-locked payout
 * even when no `mhm_rentiva_tenants` registry row exists — the production single-site
 * state. Previously a ControlPlaneGuard::is_operational() gate returned false on the
 * empty registry and the worker silently skipped every payout.
 */
class MaturedPayoutJobTest extends WP_UnitTestCase
{
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
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

        // Simulate production single-site: no tenant row in the control-plane registry.
        // (Guarded so it works whether or not the table still exists.)
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_tenants';
        $like  = $wpdb->esc_like($table);
        if ((string) $wpdb->get_var("SHOW TABLES LIKE '{$like}'") === $table) { // phpcs:ignore WordPress.DB
            $wpdb->query("DELETE FROM {$table} WHERE tenant_id = 1"); // phpcs:ignore WordPress.DB
        }
    }

    public function tearDown(): void
    {
        wp_set_current_user(0);
        parent::tearDown();
    }

    /** @test */
    public function test_matured_payout_is_processed_without_tenant_registry_row(): void
    {
        $payout_id = (int) wp_insert_post(array(
            'post_type'   => PostType::POST_TYPE,
            'post_author' => $this->admin_id,
            'post_status' => 'pending',
            'post_title'  => 'Matured Payout',
        ));

        update_post_meta($payout_id, '_mhm_workflow_state', ApprovalStateMachine::STATE_TIME_LOCKED);
        update_post_meta($payout_id, '_mhm_release_after', gmdate('Y-m-d H:i:s', time() - 3600));
        update_post_meta($payout_id, '_mhm_lock_status', 'LOCKED');

        MaturedPayoutJob::run();

        $this->assertSame(
            'EXECUTED',
            get_post_meta($payout_id, '_mhm_lock_status', true),
            'Matured payout must be finalized (lock status EXECUTED), not skipped, when no tenant registry row exists.'
        );
    }
}
