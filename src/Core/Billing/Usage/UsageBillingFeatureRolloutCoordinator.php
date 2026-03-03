<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Tenant-scoped rollout orchestration for usage billing.
 *
 * Deterministic and stateless:
 * - reads tenant feature flag once for the current execution,
 * - resolves immutable feature snapshot,
 * - delegates execution to gated executor.
 */
final class UsageBillingFeatureRolloutCoordinator
{
    private UsageBillingFeatureFlagRepository $flag_repository;

    private UsageBillingFeatureResolver $resolver;

    private UsageBillingFeatureGatedExecutor $gated_executor;

    public function __construct(
        UsageBillingFeatureFlagRepository $flagRepository,
        UsageBillingFeatureResolver $resolver,
        UsageBillingFeatureGatedExecutor $gatedExecutor
    ) {
        $this->flag_repository = $flagRepository;
        $this->resolver = $resolver;
        $this->gated_executor = $gatedExecutor;
    }

    public function execute(int $tenantId, UsageSnapshotDTO $snapshot): UsageBillingExecutionOutcome
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('tenantId must be a positive integer.');
        }

        if ($snapshot->tenant_id() !== $tenantId) {
            throw new \InvalidArgumentException('tenantId must match UsageSnapshotDTO tenant_id.');
        }

        $flag_snapshot = $this->resolver->resolve(
            $this->flag_repository->is_enabled($tenantId)
        );

        return $this->gated_executor->execute_for_tenant(
            $tenantId,
            $snapshot,
            $flag_snapshot
        );
    }
}
