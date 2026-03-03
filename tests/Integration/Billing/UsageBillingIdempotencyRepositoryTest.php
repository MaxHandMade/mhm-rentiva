<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Billing;

use MHMRentiva\Core\Billing\Usage\UsageBillingIdempotencyRepository;
use MHMRentiva\Core\Database\Migrations\UsageBillingMigration;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingIdempotencyRepositoryTest extends \WP_UnitTestCase
{
    private UsageBillingIdempotencyRepository $repository;

    private string $table;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_class(UsageBillingMigration::class);
        $this->require_class(UsageBillingIdempotencyRepository::class);

        global $wpdb;

        UsageBillingMigration::create_table();
        $this->repository = new UsageBillingIdempotencyRepository();
        $this->table = $wpdb->prefix . 'mhm_rentiva_usage_billing';

        $wpdb->query("TRUNCATE TABLE {$this->table}");
    }

    public function test_insert_pending_first_write_succeeds(): void
    {
        $row = $this->valid_row(1, 1001, '2026-03-01 00:00:00');

        $this->assertTrue($this->repository->insert_pending($row));
    }

    public function test_insert_pending_duplicate_key_returns_noop_false(): void
    {
        $row = $this->valid_row(1, 1001, '2026-03-01 00:00:00');

        $this->assertTrue($this->repository->insert_pending($row));
        $this->assertFalse($this->repository->insert_pending($row));
    }

    public function test_parallel_insert_contract_keeps_single_pending_row(): void
    {
        global $wpdb;

        $row = $this->valid_row(1, 1001, '2026-03-01 00:00:00');

        $first = $this->repository->insert_pending($row);
        $second = $this->repository->insert_pending($row);

        $this->assertTrue($first);
        $this->assertFalse($second);

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = %d AND idempotency_key = %s",
                1,
                $row['idempotency_key']
            )
        );

        $this->assertSame(1, $count);
    }

    public function test_cross_tenant_same_period_contract_allows_both(): void
    {
        global $wpdb;

        $row_a = $this->valid_row(1, 1001, '2026-03-01 00:00:00');
        $row_b = $this->valid_row(2, 1001, '2026-03-01 00:00:00');

        $this->assertTrue($this->repository->insert_pending($row_a));
        $this->assertTrue($this->repository->insert_pending($row_b));

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table} WHERE subscription_id = 1001 AND period_start_utc = '2026-03-01 00:00:00'"
        );

        $this->assertSame(2, $count);
    }

    public function test_invalid_idempotency_key_rejected(): void
    {
        $row = $this->valid_row(1, 1001, '2026-03-01 00:00:00');
        $row['idempotency_key'] = 'invalid';

        $this->assertFalse($this->repository->insert_pending($row));
    }

    public function test_build_idempotency_key_is_deterministic_and_lowercase_hex(): void
    {
        $first = $this->repository->build_idempotency_key(1, 1001, '2026-03-01 00:00:00');
        $second = $this->repository->build_idempotency_key(1, 1001, '2026-03-01 00:00:00');

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
        $this->assertSame(1, preg_match('/^[a-f0-9]{64}$/', $first));
    }

    public function test_build_idempotency_key_rejects_invalid_period_start_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repository->build_idempotency_key(1, 1001, '2026-03-01T00:00:00Z');
    }

    public function test_mark_committed_transitions_pending_once_only(): void
    {
        global $wpdb;

        $row = $this->valid_row(1, 1001, '2026-03-01 00:00:00');
        $this->assertTrue($this->repository->insert_pending($row));

        $this->assertTrue($this->repository->mark_committed($row['idempotency_key'], 'usage_ledger_1001'));
        $this->assertFalse($this->repository->mark_committed($row['idempotency_key'], 'usage_ledger_1001_repeated'));

        $status = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT status FROM {$this->table} WHERE idempotency_key = %s",
                $row['idempotency_key']
            )
        );
        $uuid = (string) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ledger_transaction_uuid FROM {$this->table} WHERE idempotency_key = %s",
                $row['idempotency_key']
            )
        );

        $this->assertSame('committed', $status);
        $this->assertSame('usage_ledger_1001', $uuid);
    }

    public function test_mark_committed_rejects_empty_ledger_uuid(): void
    {
        $row = $this->valid_row(1, 1001, '2026-03-01 00:00:00');
        $this->assertTrue($this->repository->insert_pending($row));
        $this->assertFalse($this->repository->mark_committed($row['idempotency_key'], ''));
    }

    /**
     * @return array<string,mixed>
     */
    private function valid_row(int $tenant_id, int $subscription_id, string $period_start_utc): array
    {
        $key = hash('sha256', $tenant_id . '|' . $subscription_id . '|' . $period_start_utc);

        return array(
            'tenant_id' => $tenant_id,
            'subscription_id' => $subscription_id,
            'period_start_utc' => $period_start_utc,
            'period_end_utc' => '2026-04-01 00:00:00',
            'idempotency_key' => $key,
            'amount_cents' => 15900,
            'computation_hash' => hash('sha256', 'usage-snapshot-' . $tenant_id . '-' . $subscription_id),
            'status' => 'pending',
            'created_at_utc' => '2026-03-01 00:00:00',
            'updated_at_utc' => '2026-03-01 00:00:00',
        );
    }

    private function require_class(string $fqcn): void
    {
        if (! class_exists($fqcn)) {
            $this->fail('Missing class contract for RED phase: ' . $fqcn);
        }
    }
}
