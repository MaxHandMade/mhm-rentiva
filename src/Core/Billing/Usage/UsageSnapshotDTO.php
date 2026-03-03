<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageSnapshotDTO
{
    private int $tenant_id;

    private int $subscription_id;

    private string $period_start_utc;

    private string $period_end_utc;

    private string $recomputed_at_utc;

    /**
     * @var array<string,array{usage_units:int,unit_price_cents:int}>
     */
    private array $metrics;

    /**
     * @param array<int|string,array<string,mixed>> $metrics
     */
    public function __construct(
        int $tenant_id,
        int $subscription_id,
        string $period_start_utc,
        string $period_end_utc,
        string $recomputed_at_utc,
        array $metrics
    ) {
        if ($tenant_id <= 0 || $subscription_id <= 0) {
            throw new \InvalidArgumentException('tenant_id and subscription_id must be positive integers.');
        }

        if (! self::is_valid_utc_datetime($period_start_utc) || ! self::is_valid_utc_datetime($period_end_utc) || ! self::is_valid_utc_datetime($recomputed_at_utc)) {
            throw new \InvalidArgumentException('All snapshot timestamps must be ISO-8601 UTC (Y-m-d\\TH:i:s\\Z).');
        }

        if (strcmp($period_start_utc, $period_end_utc) > 0) {
            throw new \InvalidArgumentException('period_start_utc must be less than or equal to period_end_utc.');
        }

        $this->tenant_id = $tenant_id;
        $this->subscription_id = $subscription_id;
        $this->period_start_utc = $period_start_utc;
        $this->period_end_utc = $period_end_utc;
        $this->recomputed_at_utc = $recomputed_at_utc;
        $this->metrics = self::normalize_metrics($metrics);
    }

    public function tenant_id(): int
    {
        return $this->tenant_id;
    }

    public function subscription_id(): int
    {
        return $this->subscription_id;
    }

    public function period_start_utc(): string
    {
        return $this->period_start_utc;
    }

    public function period_end_utc(): string
    {
        return $this->period_end_utc;
    }

    public function recomputed_at_utc(): string
    {
        return $this->recomputed_at_utc;
    }

    /**
     * @return array<string,array{usage_units:int,unit_price_cents:int}>
     */
    public function metrics(): array
    {
        $copy = array();
        foreach ($this->metrics as $metric_key => $metric_data) {
            $copy[$metric_key] = array(
                'usage_units' => $metric_data['usage_units'],
                'unit_price_cents' => $metric_data['unit_price_cents'],
            );
        }

        return $copy;
    }

    /**
     * @param array<int|string,array<string,mixed>> $metrics
     * @return array<string,array{usage_units:int,unit_price_cents:int}>
     */
    private static function normalize_metrics(array $metrics): array
    {
        $normalized = array();

        foreach ($metrics as $key => $metric) {
            if (! is_array($metric)) {
                throw new \InvalidArgumentException('Each metric payload must be an array.');
            }

            $metric_key = self::resolve_metric_key($key, $metric);
            if ($metric_key === '') {
                throw new \InvalidArgumentException('metric_key must be a non-empty string.');
            }

            if (! array_key_exists('usage_units', $metric) || ! array_key_exists('unit_price_cents', $metric)) {
                throw new \InvalidArgumentException('Each metric must contain usage_units and unit_price_cents.');
            }

            $usage_units = $metric['usage_units'];
            $unit_price_cents = $metric['unit_price_cents'];

            if (! is_int($usage_units) || ! is_int($unit_price_cents)) {
                throw new \InvalidArgumentException('usage_units and unit_price_cents must be strict integers.');
            }

            if ($usage_units < 0 || $unit_price_cents < 0) {
                throw new \InvalidArgumentException('usage_units and unit_price_cents cannot be negative.');
            }

            $normalized[$metric_key] = array(
                'usage_units' => $usage_units,
                'unit_price_cents' => $unit_price_cents,
            );
        }

        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param int|string $key
     * @param array<string,mixed> $metric
     */
    private static function resolve_metric_key($key, array $metric): string
    {
        if (is_string($key) && $key !== '') {
            return $key;
        }

        $candidate = $metric['metric_key'] ?? '';
        if (! is_string($candidate)) {
            return '';
        }

        return $candidate;
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
