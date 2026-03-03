<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

final class UsagePricingEngine
{
    public function compute(UsageSnapshotDTO $snapshot, string $now_utc): BillingComputationResult
    {
        if (! self::is_valid_utc_datetime($now_utc)) {
            throw new \InvalidArgumentException('now_utc must be ISO-8601 UTC (Y-m-d\\TH:i:s\\Z).');
        }

        if (strcmp($snapshot->period_end_utc(), $now_utc) > 0) {
            throw new \DomainException('Billing window is not closed yet.');
        }

        if (strcmp($snapshot->recomputed_at_utc(), $snapshot->period_end_utc()) < 0) {
            throw new \DomainException('Snapshot recomputed_at_utc must be greater than or equal to period_end_utc.');
        }

        $amount_cents = 0;
        foreach ($snapshot->metrics() as $metric_data) {
            $usage_units = $metric_data['usage_units'];
            $unit_price_cents = $metric_data['unit_price_cents'];

            if (! is_int($usage_units) || ! is_int($unit_price_cents)) {
                throw new \InvalidArgumentException('Metric pricing inputs must be strict integers.');
            }

            if ($usage_units < 0 || $unit_price_cents < 0) {
                throw new \InvalidArgumentException('Metric pricing inputs cannot be negative.');
            }

            $line_total = $usage_units * $unit_price_cents;
            if (! is_int($line_total)) {
                throw new \OverflowException('Integer overflow detected during line total multiplication.');
            }

            if ($usage_units !== 0 && intdiv($line_total, $usage_units) !== $unit_price_cents) {
                throw new \OverflowException('Deterministic multiplication overflow guard triggered.');
            }

            $next_total = $amount_cents + $line_total;
            if (! is_int($next_total) || $next_total < $amount_cents) {
                throw new \OverflowException('Integer overflow detected during amount aggregation.');
            }

            $amount_cents = $next_total;
        }

        if (! is_int($amount_cents) || $amount_cents < 0) {
            throw new \OverflowException('Invalid deterministic amount_cents result.');
        }

        return new BillingComputationResult(
            $snapshot->tenant_id(),
            $snapshot->subscription_id(),
            $snapshot->period_start_utc(),
            $snapshot->period_end_utc(),
            $amount_cents,
            $snapshot->metrics()
        );
    }

    private static function is_valid_utc_datetime(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            return false;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:s\\Z', $value, new \DateTimeZone('UTC'));
        if (! $dt instanceof \DateTimeImmutable) {
            return false;
        }

        return $dt->format('Y-m-d\\TH:i:s\\Z') === $value;
    }
}
