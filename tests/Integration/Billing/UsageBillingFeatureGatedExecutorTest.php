<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Billing;

use MHMRentiva\Core\Billing\Usage\UsageBillingExecutionOutcome;
use MHMRentiva\Core\Billing\Usage\UsageBillingFeatureFlagRepository;
use MHMRentiva\Core\Billing\Usage\UsageBillingFeatureGatedExecutor;
use MHMRentiva\Core\Billing\Usage\UsageBillingFeatureResolver;
use MHMRentiva\Core\Billing\Usage\UsageBillingFeatureRolloutCoordinator;
use MHMRentiva\Core\Billing\Usage\UsageSnapshotDTO;
use MHMRentiva\Core\Database\Migrations\UsageBillingFeatureFlagMigration;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingFeatureGatedExecutorTest extends \WP_UnitTestCase
{
    public function test_feature_disabled_short_circuits_before_usage_execution(): void
    {
        $this->require_class(UsageBillingFeatureGatedExecutor::class);

        $executor = new SnapshotUsageExecutorSpy();
        $gated_executor = new UsageBillingFeatureGatedExecutor($executor);

        $outcome = $gated_executor->execute_for_tenant(
            5001,
            $this->build_snapshot(5001, 9001, 15900),
            false
        );

        $this->assertSame('legacy_path', $outcome->reason());
        $this->assertSame(0, $executor->execute_calls());
    }

    public function test_feature_enabled_executes_usage_path_once(): void
    {
        $this->require_class(UsageBillingFeatureGatedExecutor::class);

        $executor = new SnapshotUsageExecutorSpy();
        $gated_executor = new UsageBillingFeatureGatedExecutor($executor);

        $outcome = $gated_executor->execute_for_tenant(
            5001,
            $this->build_snapshot(5001, 9001, 15900),
            true
        );

        $this->assertSame('committed', $outcome->reason());
        $this->assertSame(1, $executor->execute_calls());
    }

    public function test_executor_reads_flag_once_via_rollout_coordinator(): void
    {
        $this->require_class(UsageBillingFeatureRolloutCoordinator::class);
        $this->require_class(UsageBillingFeatureFlagRepository::class);
        $this->require_class(UsageBillingFeatureResolver::class);
        $this->require_class(UsageBillingFeatureGatedExecutor::class);
        $this->require_class(UsageBillingFeatureFlagMigration::class);

        UsageBillingFeatureFlagMigration::create_table();

        $repository = new CountingFeatureFlagRepository();
        $resolver = new CountingFeatureResolver();
        $executor = new SnapshotUsageExecutorSpy();
        $gated_executor = new UsageBillingFeatureGatedExecutor($executor);

        $coordinator = new UsageBillingFeatureRolloutCoordinator(
            $repository,
            $resolver,
            $gated_executor
        );

        $outcome = $coordinator->execute(
            5001,
            $this->build_snapshot(5001, 9001, 15900)
        );

        $this->assertSame('committed', $outcome->reason());
        $this->assertSame(1, $repository->is_enabled_calls());
        $this->assertSame(1, $resolver->resolve_calls());
        $this->assertSame(1, $executor->execute_calls());
    }

    public function test_flag_toggle_mid_execution_does_not_affect_current_run(): void
    {
        $this->require_class(UsageBillingFeatureRolloutCoordinator::class);

        $repository = new CountingFeatureFlagRepository();
        $repository->set_next_values(array(true, false));
        $resolver = new CountingFeatureResolver();
        $executor = new SnapshotUsageExecutorSpy();
        $gated_executor = new UsageBillingFeatureGatedExecutor($executor);

        $coordinator = new UsageBillingFeatureRolloutCoordinator(
            $repository,
            $resolver,
            $gated_executor
        );

        $outcome = $coordinator->execute(
            5001,
            $this->build_snapshot(5001, 9001, 15900)
        );

        $this->assertSame('committed', $outcome->reason());
        $this->assertSame(1, $repository->is_enabled_calls());
        $this->assertSame(1, $executor->execute_calls());
    }

    private function build_snapshot(int $tenant_id, int $subscription_id, int $amount_cents): UsageSnapshotDTO
    {
        return new UsageSnapshotDTO(
            $tenant_id,
            $subscription_id,
            '2026-03-01T00:00:00Z',
            '2026-04-01T00:00:00Z',
            '2026-04-01T00:00:00Z',
            array(
                'api_calls' => array(
                    'usage_units' => intdiv($amount_cents, 100),
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

final class SnapshotUsageExecutorSpy
{
    private int $calls = 0;

    public function execute_snapshot(UsageSnapshotDTO $snapshot, bool $feature_enabled): UsageBillingExecutionOutcome
    {
        $this->calls++;

        if (! $feature_enabled) {
            return UsageBillingExecutionOutcome::legacy_path('legacy_' . $snapshot->tenant_id());
        }

        return UsageBillingExecutionOutcome::committed(
            'idempotency_' . $snapshot->tenant_id(),
            'ledger_uuid_' . $snapshot->tenant_id()
        );
    }

    public function execute_calls(): int
    {
        return $this->calls;
    }
}

class CountingFeatureFlagRepository extends UsageBillingFeatureFlagRepository
{
    private int $calls = 0;

    /**
     * @var bool[]
     */
    private array $next_values = array(true);

    /**
     * @param bool[] $values
     */
    public function set_next_values(array $values): void
    {
        $this->next_values = $values;
    }

    public function is_enabled(int $tenant_id): bool
    {
        $this->calls++;

        if ($this->next_values === array()) {
            return false;
        }

        return (bool) array_shift($this->next_values);
    }

    public function is_enabled_calls(): int
    {
        return $this->calls;
    }
}

class CountingFeatureResolver extends UsageBillingFeatureResolver
{
    private int $calls = 0;

    public function resolve(bool $flag_from_repository): bool
    {
        $this->calls++;
        return $flag_from_repository;
    }

    public function resolve_calls(): int
    {
        return $this->calls;
    }
}

