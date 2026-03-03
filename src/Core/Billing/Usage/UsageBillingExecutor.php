<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingExecutor
{
    private UsageBillingIdempotencyRepository $idempotency_repository;

    private UsageBillingLedgerAdapterInterface $ledger_adapter;

    private UsagePricingEngine $pricing_engine;

    /**
     * @var callable|null
     */
    private $order_probe;

    private bool $transaction_active = false;

    /**
     * @param callable|null $order_probe
     */
    public function __construct(
        UsageBillingIdempotencyRepository $idempotency_repository,
        UsageBillingLedgerAdapterInterface $ledger_adapter,
        $order_probe = null
    ) {
        $this->idempotency_repository = $idempotency_repository;
        $this->ledger_adapter = $ledger_adapter;
        $this->pricing_engine = new UsagePricingEngine();
        $this->order_probe = is_callable($order_probe) ? $order_probe : null;
    }

    public function execute_snapshot(
        UsageSnapshotDTO $snapshot,
        bool $feature_enabled
    ): UsageBillingExecutionOutcome {
        $result = $this->pricing_engine->compute($snapshot, $snapshot->period_end_utc());

        return $this->execute(
            $result,
            $feature_enabled,
            $this->normalize_iso8601_utc($snapshot->period_end_utc(), 'period_end_utc')
        );
    }

    public function execute(
        BillingComputationResult $result,
        bool $feature_enabled,
        string $now_utc
    ): UsageBillingExecutionOutcome {
        return $this->execute_internal($result, $feature_enabled, $now_utc, null);
    }

    public function execute_with_existing_hash(
        BillingComputationResult $result,
        string $existing_hash,
        bool $feature_enabled,
        string $now_utc
    ): UsageBillingExecutionOutcome {
        return $this->execute_internal($result, $feature_enabled, $now_utc, $existing_hash);
    }

    private function execute_internal(
        BillingComputationResult $result,
        bool $feature_enabled,
        string $now_utc,
        ?string $forced_existing_hash
    ): UsageBillingExecutionOutcome {
        $now_db = $this->normalize_db_datetime($now_utc, 'now_utc');
        $period_start_db = $this->normalize_iso8601_utc($result->period_start_utc(), 'period_start_utc');
        $period_end_db = $this->normalize_iso8601_utc($result->period_end_utc(), 'period_end_utc');

        $idempotency_key = $this->idempotency_repository->build_idempotency_key(
            $result->tenant_id(),
            $result->subscription_id(),
            $period_start_db
        );

        if (! $feature_enabled) {
            return UsageBillingExecutionOutcome::legacy_path($idempotency_key);
        }

        $amount_cents = $result->amount_cents();
        if (! is_int($amount_cents) || $amount_cents < 0) {
            throw new \InvalidArgumentException('amount_cents must be a non-negative integer.');
        }

        if ($this->transaction_active) {
            throw new \RuntimeException('Nested transactions are not allowed in UsageBillingExecutor.');
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');
        $this->transaction_active = true;

        try {
            $this->probe('insert_pending');
            $inserted = $this->idempotency_repository->insert_pending(array(
                'tenant_id' => $result->tenant_id(),
                'subscription_id' => $result->subscription_id(),
                'period_start_utc' => $period_start_db,
                'period_end_utc' => $period_end_db,
                'idempotency_key' => $idempotency_key,
                'amount_cents' => $amount_cents,
                'computation_hash' => $result->hash(),
                'status' => UsageBillingIdempotencyRepository::STATUS_PENDING,
                'ledger_transaction_uuid' => null,
                'created_at_utc' => $now_db,
                'updated_at_utc' => $now_db,
            ));

            $this->probe('drift_guard');
            if (! $inserted) {
                $existing = $this->idempotency_repository->find_by_key($idempotency_key);
                $existing_hash = $forced_existing_hash ?? (string) ($existing['computation_hash'] ?? '');

                if ($existing_hash !== $result->hash()) {
                    $this->probe('mark_skipped_drift');
                    $marked_drift = $this->idempotency_repository->mark_skipped_drift($idempotency_key);
                    if (! $marked_drift) {
                        $resolved_row = $this->idempotency_repository->find_by_key($idempotency_key);
                        $resolved_status = is_array($resolved_row) ? (string) ($resolved_row['status'] ?? '') : '';

                        if (
                            $resolved_status !== UsageBillingIdempotencyRepository::STATUS_COMMITTED
                            && $resolved_status !== UsageBillingIdempotencyRepository::STATUS_SKIPPED_DRIFT
                        ) {
                            throw new \RuntimeException('Unable to transition pending usage billing row to skipped_drift state.');
                        }
                    }

                    $this->emit_drift_metric($result->tenant_id(), $idempotency_key);
                    $wpdb->query('COMMIT');
                    $this->transaction_active = false;
                    return UsageBillingExecutionOutcome::drift_mismatch($idempotency_key);
                }

                $wpdb->query('COMMIT');
                $this->transaction_active = false;
                return UsageBillingExecutionOutcome::duplicate_noop($idempotency_key);
            }

            $ledger_uuid = $this->ledger_adapter->write($result->tenant_id(), $amount_cents);
            $this->probe('ledger_write');

            if ($ledger_uuid === '') {
                throw new \RuntimeException('Ledger adapter returned empty transaction UUID.');
            }

            $marked = $this->idempotency_repository->mark_committed($idempotency_key, $ledger_uuid);
            $this->probe('mark_committed');
            if (! $marked) {
                throw new \RuntimeException('Unable to transition pending usage billing row to committed state.');
            }

            $wpdb->query('COMMIT');
            $this->transaction_active = false;
            return UsageBillingExecutionOutcome::committed($idempotency_key, $ledger_uuid);
        } catch (\Throwable $throwable) {
            $wpdb->query('ROLLBACK');
            $this->transaction_active = false;
            throw $throwable;
        }
    }

    private function probe(string $step): void
    {
        if (is_callable($this->order_probe)) {
            call_user_func($this->order_probe, $step);
        }
    }

    private function emit_drift_metric(int $tenant_id, string $idempotency_key): void
    {
        if (! class_exists('\MHMRentiva\Core\Monitoring\PaymentMetricsCollector')) {
            return;
        }

        try {
            \MHMRentiva\Core\Monitoring\PaymentMetricsCollector::emit('usage_billing_drift_detected_count', array(
                'tenant_id' => $tenant_id,
                'idempotency_key' => $idempotency_key,
            ));
        } catch (\Throwable $ignored) {
            // Metrics failures must not affect financial execution.
        }
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

    private function normalize_db_datetime(string $value, string $field): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            throw new \InvalidArgumentException($field . ' must be UTC DATETIME format.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        if (! $date instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException($field . ' cannot be parsed as UTC datetime.');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
