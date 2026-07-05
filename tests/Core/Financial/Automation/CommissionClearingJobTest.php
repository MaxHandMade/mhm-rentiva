<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Financial\Automation;

use MHMRentiva\Core\Database\Migrations\LedgerMigration;
use MHMRentiva\Core\Financial\Automation\CommissionClearingJob;
use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;
use WP_UnitTestCase;

class CommissionClearingJobTest extends WP_UnitTestCase
{
    private int $vendor_id;

    public function setUp(): void
    {
        parent::setUp();
        LedgerMigration::create_table();

        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}mhm_rentiva_ledger"); // phpcs:ignore WordPress.DB

        $this->vendor_id = self::factory()->user->create(array('role' => 'mhm_rentiva_vendor'));
    }

    private function insert_commission_credit(string $uuid, string $status, string $created_at): int
    {
        global $wpdb;

        Ledger::add_entry(new LedgerEntry(
            $uuid,
            $this->vendor_id,
            11,
            22,
            'commission_credit',
            85.0,
            100.0,
            15.0,
            15.0,
            'EUR',
            'vendor',
            $status
        ));

        $wpdb->update(
            $wpdb->prefix . 'mhm_rentiva_ledger',
            array( 'created_at' => $created_at ),
            array( 'transaction_uuid' => $uuid ),
            array( '%s' ),
            array( '%s' )
        );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE transaction_uuid = %s",
                $uuid
            )
        );
    }

    public function test_pending_entry_older_than_seven_days_is_cleared_and_fires_action(): void
    {
        $eight_days_ago = gmdate('Y-m-d H:i:s', time() - (8 * DAY_IN_SECONDS));
        $id             = $this->insert_commission_credit('old_pending', 'pending', $eight_days_ago);

        $captured = null;
        add_action('mhm_rentiva_commission_cleared', function ($vendor_id, $amount, $currency, $booking_id, $order_id) use (&$captured) {
            $captured = array( $vendor_id, $amount, $currency, $booking_id, $order_id );
        }, 10, 5);

        CommissionClearingJob::run();

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE id = %d",
            $id
        ));

        $this->assertSame('cleared', $status);
        $this->assertSame(array( $this->vendor_id, 85.0, 'EUR', 11, 22 ), $captured);
    }

    public function test_pending_entry_newer_than_seven_days_is_untouched(): void
    {
        $one_day_ago = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $id          = $this->insert_commission_credit('recent_pending', 'pending', $one_day_ago);

        CommissionClearingJob::run();

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE id = %d",
            $id
        ));

        $this->assertSame('pending', $status);
    }

    public function test_already_cleared_entry_is_not_reprocessed(): void
    {
        $eight_days_ago = gmdate('Y-m-d H:i:s', time() - (8 * DAY_IN_SECONDS));
        $this->insert_commission_credit('already_cleared', 'cleared', $eight_days_ago);

        $fired = false;
        add_action('mhm_rentiva_commission_cleared', function () use (&$fired) {
            $fired = true;
        });

        CommissionClearingJob::run();

        $this->assertFalse($fired, 'Already-cleared entries must not re-fire the clearing action.');
    }

    public function test_voided_entry_is_never_cleared(): void
    {
        $eight_days_ago = gmdate('Y-m-d H:i:s', time() - (8 * DAY_IN_SECONDS));
        $id             = $this->insert_commission_credit('voided_entry', 'voided', $eight_days_ago);

        CommissionClearingJob::run();

        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}mhm_rentiva_ledger WHERE id = %d",
            $id
        ));

        $this->assertSame('voided', $status);
    }
}
