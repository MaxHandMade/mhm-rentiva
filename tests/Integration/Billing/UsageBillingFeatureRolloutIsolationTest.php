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

final class UsageBillingFeatureRolloutIsolationTest extends \WP_UnitTestCase
{
    private UsageBillingFeatureFlagRepository $flag_repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_class(UsageBillingFeatureFlagMigration::class);
        $this->require_class(UsageBillingFeatureFlagRepository::class);
        $this->require_class(UsageBillingFeatureResolver::class);
        $this->require_class(UsageBillingFeatureGatedExecutor::class);
        $this->require_class(UsageBillingFeatureRolloutCoordinator::class);
        $this->require_class(UsageSnapshotDTO::class);

        UsageBillingFeatureFlagMigration::create_table();

        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_usage_billing_feature_flags';
        $wpdb->query("TRUNCATE TABLE {$table}");

        $this->flag_repository = new UsageBillingFeatureFlagRepository();
    }

    public function test_tenant_on_and_off_isolated_execution(): void
    {
        $this->flag_repository->set_enabled(9101, true, '2026-05-01T00:00:00Z');
        $this->flag_repository->set_enabled(9102, false, '2026-05-01T00:00:00Z');

        $executor_spy = new RolloutUsageExecutorSpy();
        $coordinator = $this->build_coordinator($executor_spy);

        $outcome_on = $coordinator->execute(9101, $this->build_snapshot(9101, 31001, 15100));
        $outcome_off = $coordinator->execute(9102, $this->build_snapshot(9102, 31002, 9900));

        $this->assertSame('committed', $outcome_on->reason());
        $this->assertSame('legacy_path', $outcome_off->reason());
        $this->assertSame(array(9101), $executor_spy->executed_tenants());
    }

    public function test_default_flag_is_off(): void
    {
        $executor_spy = new RolloutUsageExecutorSpy();
        $coordinator = $this->build_coordinator($executor_spy);

        $outcome = $coordinator->execute(9201, $this->build_snapshot(9201, 32001, 11100));

        $this->assertSame('legacy_path', $outcome->reason());
        $this->assertSame(array(), $executor_spy->executed_tenants());
    }

    public function test_parallel_mixed_flags_is_deterministic(): void
    {
        $this->flag_repository->set_enabled(9301, true, '2026-05-01T00:00:00Z');
        $this->flag_repository->set_enabled(9302, false, '2026-05-01T00:00:00Z');
        $this->flag_repository->set_enabled(9303, true, '2026-05-01T00:00:00Z');

        $executor_spy = new RolloutUsageExecutorSpy();
        $coordinator = $this->build_coordinator($executor_spy);

        $first = array(
            9301 => $coordinator->execute(9301, $this->build_snapshot(9301, 33001, 13000)),
            9302 => $coordinator->execute(9302, $this->build_snapshot(9302, 33002, 14000)),
            9303 => $coordinator->execute(9303, $this->build_snapshot(9303, 33003, 15000)),
        );

        $second = array(
            9301 => $coordinator->execute(9301, $this->build_snapshot(9301, 33001, 13000)),
            9302 => $coordinator->execute(9302, $this->build_snapshot(9302, 33002, 14000)),
            9303 => $coordinator->execute(9303, $this->build_snapshot(9303, 33003, 15000)),
        );

        $this->assertSame($first[9301]->reason(), $second[9301]->reason());
        $this->assertSame($first[9302]->reason(), $second[9302]->reason());
        $this->assertSame($first[9303]->reason(), $second[9303]->reason());
        $this->assertSame(array(9301, 9301, 9303, 9303), $executor_spy->executed_tenants());
    }

    public function test_flag_change_does_not_affect_other_tenants(): void
    {
        $this->flag_repository->set_enabled(9401, true, '2026-05-01T00:00:00Z');
        $this->flag_repository->set_enabled(9402, false, '2026-05-01T00:00:00Z');

        $executor_spy = new RolloutUsageExecutorSpy();
        $coordinator = $this->build_coordinator($executor_spy);

        $first_9401 = $coordinator->execute(9401, $this->build_snapshot(9401, 34001, 10100));
        $first_9402 = $coordinator->execute(9402, $this->build_snapshot(9402, 34002, 10200));

        $this->flag_repository->set_enabled(9401, false, '2026-05-01T00:01:00Z');

        $second_9401 = $coordinator->execute(9401, $this->build_snapshot(9401, 34001, 10100));
        $second_9402 = $coordinator->execute(9402, $this->build_snapshot(9402, 34002, 10200));

        $this->assertSame('committed', $first_9401->reason());
        $this->assertSame('legacy_path', $first_9402->reason());
        $this->assertSame('legacy_path', $second_9401->reason());
        $this->assertSame('legacy_path', $second_9402->reason());
    }

    private function build_snapshot(int $tenant_id, int $subscription_id, int $amount_cents): UsageSnapshotDTO
    {
        return new UsageSnapshotDTO(
            $tenant_id,
            $subscription_id,
            '2026-04-01T00:00:00Z',
            '2026-05-01T00:00:00Z',
            '2026-05-01T00:00:00Z',
            array(
                'api_calls' => array(
                    'usage_units' => intdiv($amount_cents, 100),
                    'unit_price_cents' => 100,
                ),
            )
        );
    }

    private function build_coordinator(RolloutUsageExecutorSpy $executor_spy): UsageBillingFeatureRolloutCoordinator
    {
        $resolver = new UsageBillingFeatureResolver();
        $gated_executor = new UsageBillingFeatureGatedExecutor($executor_spy);

        return new UsageBillingFeatureRolloutCoordinator(
            $this->flag_repository,
            $resolver,
            $gated_executor
        );
    }

    private function require_class(string $fqcn): void
    {
        if (! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn)) {
            $this->fail('Missing class contract for RED phase: ' . $fqcn);
        }
    }
}

final class RolloutUsageExecutorSpy
{
    /**
     * @var int[]
     */
    private array $executed_tenants = array();

    public function execute_snapshot(\MHMRentiva\Core\Billing\Usage\UsageSnapshotDTO $snapshot, bool $feature_enabled): UsageBillingExecutionOutcome
    {
        if (! $feature_enabled) {
            return UsageBillingExecutionOutcome::legacy_path('legacy_' . $snapshot->tenant_id());
        }

        $this->executed_tenants[] = $snapshot->tenant_id();
        sort($this->executed_tenants);

        return UsageBillingExecutionOutcome::committed(
            'rollout_key_' . $snapshot->tenant_id(),
            'ledger_uuid_' . $snapshot->tenant_id()
        );
    }

    /**
     * @return int[]
     */
    public function executed_tenants(): array
    {
        return $this->executed_tenants;
    }
}
