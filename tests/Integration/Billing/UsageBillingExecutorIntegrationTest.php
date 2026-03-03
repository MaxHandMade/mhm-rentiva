<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Billing;

use MHMRentiva\Core\Billing\Usage\BillingComputationResult;
use MHMRentiva\Core\Billing\Usage\UsageBillingExecutionOutcome;
use MHMRentiva\Core\Billing\Usage\UsageBillingIdempotencyRepository;
use MHMRentiva\Core\Billing\Usage\UsageBillingLedgerAdapterInterface;
use MHMRentiva\Core\Database\Migrations\UsageBillingMigration;
use MHMRentiva\Core\Monitoring\PaymentMetricsCollector;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingExecutorIntegrationTest extends \WP_UnitTestCase
{
    private string $table;

    /**
     * @var array<int,string>
     */
    private array $order_steps = array();

    public function setUp(): void
    {
        parent::setUp();

        $this->require_class(UsageBillingMigration::class);
        $this->require_class(UsageBillingIdempotencyRepository::class);
        $this->require_class(UsageBillingLedgerAdapterInterface::class);

        global $wpdb;

        UsageBillingMigration::create_table();
        $this->table = $wpdb->prefix . 'mhm_rentiva_usage_billing';
        $wpdb->query("TRUNCATE TABLE {$this->table}");
        $this->order_steps = array();

        if (class_exists(PaymentMetricsCollector::class)) {
            PaymentMetricsCollector::reset_buffer();
        }
    }

    public function test_double_execution_creates_single_ledger_entry(): void
    {
        $executor = $this->create_executor(new FakeUsageBillingLedgerAdapter());
        $result = $this->build_result();

        $first = $executor->execute($result, true, '2026-04-01 00:00:00');
        $second = $executor->execute($result, true, '2026-04-01 00:00:00');

        $this->assertInstanceOf(UsageBillingExecutionOutcome::class, $first);
        $this->assertInstanceOf(UsageBillingExecutionOutcome::class, $second);
        $this->assertTrue($first->executed());
        $this->assertFalse($second->executed());
        $this->assertSame('committed', $first->reason());
        $this->assertSame('duplicate_noop', $second->reason());
        $this->assertSame(1, $first->ledger_writes());
        $this->assertSame(0, $second->ledger_writes());
    }

    public function test_triple_execution_still_single_ledger_entry(): void
    {
        $executor = $this->create_executor(new FakeUsageBillingLedgerAdapter());
        $result = $this->build_result();

        $executor->execute($result, true, '2026-04-01 00:00:00');
        $executor->execute($result, true, '2026-04-01 00:00:00');
        $third = $executor->execute($result, true, '2026-04-01 00:00:00');

        $this->assertFalse($third->executed());
        $this->assertSame('duplicate_noop', $third->reason());
        $this->assertSame(0, $third->ledger_writes());
    }

    public function test_parallel_execution_is_idempotent(): void
    {
        $executor = $this->create_executor(new FakeUsageBillingLedgerAdapter());
        $result = $this->build_result();

        $run_a = $executor->execute($result, true, '2026-04-01 00:00:00');
        $run_b = $executor->execute($result, true, '2026-04-01 00:00:00');

        $this->assertNotSame($run_a->executed(), $run_b->executed());
        $this->assertSame(1, $run_a->ledger_writes() + $run_b->ledger_writes());
    }

    public function test_exception_in_ledger_write_rolls_back_and_rethrows(): void
    {
        $executor = $this->create_executor(new FakeUsageBillingLedgerAdapter(true));
        $result = $this->build_result();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('simulated_ledger_write_failure');

        $executor->execute($result, true, '2026-04-01 00:00:00');
    }

    public function test_partial_failure_rolls_back_without_orphan_writes(): void
    {
        $executor = $this->create_executor(new FakeUsageBillingLedgerAdapter(true));

        try {
            $executor->execute($this->build_result(), true, '2026-04-01 00:00:00');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $runtime_exception) {
            $this->assertSame('simulated_ledger_write_failure', $runtime_exception->getMessage());
        }

        global $wpdb;
        $rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'pending'");
        $committed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status = 'committed'");

        $this->assertSame(0, $rows);
        $this->assertSame(0, $pending);
        $this->assertSame(0, $committed);
    }

    public function test_ledger_write_happens_after_pending_insert_and_drift_guard(): void
    {
        $adapter = new FakeUsageBillingLedgerAdapter();
        $executor = $this->create_executor($adapter, function (string $step): void {
            $this->order_steps[] = $step;
        });

        $executor->execute($this->build_result(), true, '2026-04-01 00:00:00');

        $this->assertSame(
            array('insert_pending', 'drift_guard', 'ledger_write', 'mark_committed'),
            $this->order_steps
        );
    }

    public function test_feature_flag_off_uses_legacy_path(): void
    {
        $adapter = new FakeUsageBillingLedgerAdapter();
        $executor = $this->create_executor($adapter);

        $outcome = $executor->execute($this->build_result(), false, '2026-04-01 00:00:00');

        $this->assertSame('legacy_path', $outcome->reason());
        $this->assertFalse($outcome->executed());
        $this->assertSame(0, $adapter->write_count());

        global $wpdb;
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}");
        $this->assertSame(0, $count);
    }

    public function test_strict_hash_inequality_blocks_write_without_type_coercion(): void
    {
        $adapter = new FakeUsageBillingLedgerAdapter();
        $executor = $this->create_executor($adapter);
        $result = $this->build_result();
        $repository = new UsageBillingIdempotencyRepository();
        $idempotency_key = $repository->build_idempotency_key(1, 1001, '2026-03-01 00:00:00');

        $seeded = $repository->insert_pending(array(
            'tenant_id' => 1,
            'subscription_id' => 1001,
            'period_start_utc' => '2026-03-01 00:00:00',
            'period_end_utc' => '2026-04-01 00:00:00',
            'idempotency_key' => $idempotency_key,
            'amount_cents' => 15900,
            'computation_hash' => hash('sha256', 'different-computation'),
            'status' => UsageBillingIdempotencyRepository::STATUS_PENDING,
            'ledger_transaction_uuid' => null,
            'created_at_utc' => '2026-04-01 00:00:00',
            'updated_at_utc' => '2026-04-01 00:00:00',
        ));
        $this->assertTrue($seeded);

        $outcome = $executor->execute_with_existing_hash(
            $result,
            (string) (int) $result->hash(),
            true,
            '2026-04-01 00:00:00'
        );

        $this->assertFalse($outcome->executed());
        $this->assertSame('drift_mismatch', $outcome->reason());
        $this->assertSame(0, $adapter->write_count());
    }

    public function test_drift_mismatch_blocks_ledger_and_emits_metric(): void
    {
        $adapter = new FakeUsageBillingLedgerAdapter();
        $executor = $this->create_executor($adapter);
        $result = $this->build_result();
        $repository = new UsageBillingIdempotencyRepository();
        $idempotency_key = $repository->build_idempotency_key(1, 1001, '2026-03-01 00:00:00');

        $seeded = $repository->insert_pending(array(
            'tenant_id' => 1,
            'subscription_id' => 1001,
            'period_start_utc' => '2026-03-01 00:00:00',
            'period_end_utc' => '2026-04-01 00:00:00',
            'idempotency_key' => $idempotency_key,
            'amount_cents' => 15900,
            'computation_hash' => hash('sha256', 'different-computation-hash-for-drift'),
            'status' => UsageBillingIdempotencyRepository::STATUS_PENDING,
            'ledger_transaction_uuid' => null,
            'created_at_utc' => '2026-04-01 00:00:00',
            'updated_at_utc' => '2026-04-01 00:00:00',
        ));
        $this->assertTrue($seeded);

        $outcome = $executor->execute($result, true, '2026-04-01 00:00:00');
        $this->assertFalse($outcome->executed());
        $this->assertSame('drift_mismatch', $outcome->reason());
        $this->assertSame(0, $adapter->write_count());

        global $wpdb;
        $status = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$this->table} WHERE idempotency_key = %s",
                $idempotency_key
            )
        );
        $this->assertSame('skipped_drift', $status);

        $metrics = PaymentMetricsCollector::snapshot_buffer();
        $this->assertNotEmpty($metrics);
        $last = $metrics[count($metrics) - 1];
        $this->assertSame('usage_billing_drift_detected_count', $last['metric_name']);
        $this->assertSame(1, (int) $last['labels']['tenant_id']);
        $this->assertSame($idempotency_key, (string) $last['labels']['idempotency_key']);
    }

    /**
     * @param callable|null $order_probe
     */
    private function create_executor(UsageBillingLedgerAdapterInterface $adapter, $order_probe = null)
    {
        $this->require_class(\MHMRentiva\Core\Billing\Usage\UsageBillingExecutor::class);

        return new \MHMRentiva\Core\Billing\Usage\UsageBillingExecutor(
            new UsageBillingIdempotencyRepository(),
            $adapter,
            $order_probe
        );
    }

    private function build_result(): BillingComputationResult
    {
        return new BillingComputationResult(
            1,
            1001,
            '2026-03-01T00:00:00Z',
            '2026-04-01T00:00:00Z',
            15900,
            array(
                'api_calls' => array(
                    'usage_units' => 159,
                    'unit_price_cents' => 100,
                ),
            )
        );
    }

    private function require_class(string $fqcn): void
    {
        if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn)) {
            $this->fail('Missing class contract for RED phase: ' . $fqcn);
        }
    }
}

final class FakeUsageBillingLedgerAdapter implements UsageBillingLedgerAdapterInterface
{
    private bool $throw_on_write;

    private int $writes = 0;

    public function __construct(bool $throw_on_write = false)
    {
        $this->throw_on_write = $throw_on_write;
    }

    public function write(int $tenant_id, int $amount_cents): string
    {
        $this->writes++;

        if ($this->throw_on_write) {
            throw new \RuntimeException('simulated_ledger_write_failure');
        }

        return 'usage_ledger_' . $tenant_id . '_' . $amount_cents . '_' . $this->writes;
    }

    public function write_count(): int
    {
        return $this->writes;
    }
}
