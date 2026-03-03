<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Stress\Billing;

use MHMRentiva\Core\Billing\Usage\BillingComputationResult;
use MHMRentiva\Core\Billing\Usage\UsageBillingExecutor;
use MHMRentiva\Core\Billing\Usage\UsageBillingIdempotencyRepository;
use MHMRentiva\Core\Billing\Usage\UsageBillingLedgerAdapterInterface;
use MHMRentiva\Core\Database\Migrations\UsageBillingMigration;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingExecutorStressTest extends \WP_UnitTestCase
{
    private string $table;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_contract(UsageBillingMigration::class);
        $this->require_contract(UsageBillingExecutor::class);
        $this->require_contract(UsageBillingIdempotencyRepository::class);
        $this->require_contract(UsageBillingLedgerAdapterInterface::class);

        global $wpdb;
        UsageBillingMigration::create_table();
        $this->table = $wpdb->prefix . 'mhm_rentiva_usage_billing';
        $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    public function test_parallel_simulation_keeps_single_committed_row_and_single_ledger_write(): void
    {
        global $wpdb;

        $ledger = new StressFakeLedgerAdapter();
        $repository = new UsageBillingIdempotencyRepository();
        $executor_a = new UsageBillingExecutor($repository, $ledger);
        $executor_b = new UsageBillingExecutor($repository, $ledger);

        $result = new BillingComputationResult(
            1,
            2001,
            '2026-03-01T00:00:00Z',
            '2026-04-01T00:00:00Z',
            19900,
            array(
                'api_calls' => array(
                    'usage_units' => 199,
                    'unit_price_cents' => 100,
                ),
            )
        );

        $first = $executor_a->execute($result, true, '2026-04-01 00:00:00');
        $second = $executor_b->execute($result, true, '2026-04-01 00:00:00');

        $this->assertNotSame($first->executed(), $second->executed());
        $this->assertSame(1, $ledger->count());

        $committed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'committed'");
        $this->assertSame(1, $committed);
    }

    public function test_parallel_insert_race_keeps_single_pending_row(): void
    {
        global $wpdb;

        $repository_a = new UsageBillingIdempotencyRepository();
        $repository_b = new UsageBillingIdempotencyRepository();

        $idempotency_key = $repository_a->build_idempotency_key(1, 2001, '2026-03-01 00:00:00');
        $row = array(
            'tenant_id' => 1,
            'subscription_id' => 2001,
            'period_start_utc' => '2026-03-01 00:00:00',
            'period_end_utc' => '2026-04-01 00:00:00',
            'idempotency_key' => $idempotency_key,
            'amount_cents' => 19900,
            'computation_hash' => hash('sha256', 'stress-race-snapshot'),
            'status' => UsageBillingIdempotencyRepository::STATUS_PENDING,
            'ledger_transaction_uuid' => null,
            'created_at_utc' => '2026-04-01 00:00:00',
            'updated_at_utc' => '2026-04-01 00:00:00',
        );

        $insert_a = $repository_a->insert_pending($row);
        $insert_b = $repository_b->insert_pending($row);

        $this->assertNotSame($insert_a, $insert_b);

        $pending_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = %d AND idempotency_key = %s AND status = %s",
                1,
                $idempotency_key,
                UsageBillingIdempotencyRepository::STATUS_PENDING
            )
        );

        $this->assertSame(1, $pending_count);
    }

    public function test_cross_tenant_same_window_isolation_keeps_independent_commits(): void
    {
        global $wpdb;

        $ledger = new StressFakeLedgerAdapter();
        $repository = new UsageBillingIdempotencyRepository();
        $executor = new UsageBillingExecutor($repository, $ledger);

        $result_tenant_a = $this->build_result(1, 3001, 21000);
        $result_tenant_b = $this->build_result(2, 3001, 24500);

        $outcome_a = $executor->execute($result_tenant_a, true, '2026-04-01 00:00:00');
        $outcome_b = $executor->execute($result_tenant_b, true, '2026-04-01 00:00:00');

        $this->assertTrue($outcome_a->executed());
        $this->assertTrue($outcome_b->executed());
        $this->assertSame(2, $ledger->count());

        $tenant_a_committed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = 1 AND status = 'committed'");
        $tenant_b_committed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = 2 AND status = 'committed'");
        $this->assertSame(1, $tenant_a_committed);
        $this->assertSame(1, $tenant_b_committed);
    }

    public function test_concurrent_drift_vs_commit_keeps_single_committed_and_returns_drift_outcome(): void
    {
        global $wpdb;

        $ledger = new StressFakeLedgerAdapter();
        $repository = new UsageBillingIdempotencyRepository();
        $executor_a = new UsageBillingExecutor($repository, $ledger);
        $executor_b = new UsageBillingExecutor($repository, $ledger);

        $committable = $this->build_result(1, 4444, 19900);
        $drifted = $this->build_result(1, 4444, 20700);

        $first = $executor_a->execute($committable, true, '2026-04-01 00:00:00');
        $second = $executor_b->execute($drifted, true, '2026-04-01 00:00:00');

        $this->assertTrue($first->executed());
        $this->assertFalse($second->executed());
        $this->assertSame('drift_mismatch', $second->reason());
        $this->assertSame(1, $ledger->count());

        $committed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'committed'");
        $this->assertSame(1, $committed);
    }

    private function build_result(int $tenant_id, int $subscription_id, int $amount_cents): BillingComputationResult
    {
        return new BillingComputationResult(
            $tenant_id,
            $subscription_id,
            '2026-03-01T00:00:00Z',
            '2026-04-01T00:00:00Z',
            $amount_cents,
            array(
                'api_calls' => array(
                    'usage_units' => intdiv($amount_cents, 100),
                    'unit_price_cents' => 100,
                ),
            )
        );
    }

    private function require_contract(string $fqcn): void
    {
        if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn)) {
            $this->fail('Missing contract: ' . $fqcn);
        }
    }
}

final class StressFakeLedgerAdapter implements UsageBillingLedgerAdapterInterface
{
    private int $count = 0;

    public function write(int $tenant_id, int $amount_cents): string
    {
        $this->count++;
        return 'stress_ledger_' . $tenant_id . '_' . $amount_cents . '_' . $this->count;
    }

    public function count(): int
    {
        return $this->count;
    }
}
