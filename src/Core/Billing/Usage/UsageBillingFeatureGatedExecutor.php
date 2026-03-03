<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * L3 rollout guard for usage billing execution.
 *
 * Applies immutable rollout snapshot to usage billing execution.
 */
final class UsageBillingFeatureGatedExecutor
{
    /**
     * @var object
     */
    private $executor;

    /**
     * @param object $executor must provide execute_snapshot(UsageSnapshotDTO,bool):UsageBillingExecutionOutcome
     */
    public function __construct($executor)
    {
        $this->executor = $executor;
    }

    public function execute_for_tenant(
        int $tenant_id,
        UsageSnapshotDTO $snapshot,
        bool $feature_enabled
    ): UsageBillingExecutionOutcome {
        if ($tenant_id <= 0) {
            throw new \InvalidArgumentException('tenant_id must be a positive integer.');
        }

        if ($snapshot->tenant_id() !== $tenant_id) {
            throw new \InvalidArgumentException('tenant_id must match usage snapshot tenant_id.');
        }

        $this->emit_flag_metric($tenant_id, $feature_enabled);

        if (! $feature_enabled) {
            return UsageBillingExecutionOutcome::legacy_path(
                $this->build_idempotency_key($snapshot)
            );
        }

        return $this->executor->execute_snapshot($snapshot, true);
    }

    private function emit_flag_metric(int $tenant_id, bool $feature_enabled): void
    {
        $metric_name = $feature_enabled
            ? 'usage_billing_flag_enabled_count'
            : 'usage_billing_flag_disabled_count';

        try {
            if (class_exists(\MHMRentiva\Core\Monitoring\PaymentMetricsCollector::class)) {
                \MHMRentiva\Core\Monitoring\PaymentMetricsCollector::emit(
                    $metric_name,
                    array(
                        'tenant_id' => $tenant_id,
                    )
                );
            }
        } catch (\Throwable $ignored) {
            // Telemetry cannot affect financial execution.
        }
    }

    private function build_idempotency_key(UsageSnapshotDTO $snapshot): string
    {
        $period_start = $this->normalize_iso8601_utc(
            $snapshot->period_start_utc(),
            'period_start_utc'
        );

        return strtolower(
            hash(
                'sha256',
                $snapshot->tenant_id() . '|' . $snapshot->subscription_id() . '|' . $period_start
            )
        );
    }

    private function normalize_iso8601_utc(string $value, string $field): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            throw new \InvalidArgumentException($field . ' must be ISO8601 UTC format.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        if (! $date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException($field . ' cannot be parsed as UTC datetime.');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
