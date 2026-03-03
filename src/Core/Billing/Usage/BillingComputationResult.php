<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

final class BillingComputationResult
{
    private int $tenant_id;

    private int $subscription_id;

    private string $period_start_utc;

    private string $period_end_utc;

    private int $amount_cents;

    /**
     * @var array<string,array{usage_units:int,unit_price_cents:int}>
     */
    private array $metrics;

    private string $metrics_hash;

    private string $computation_hash;

    /**
     * @param array<string,array{usage_units:int,unit_price_cents:int}> $metrics
     */
    public function __construct(
        int $tenant_id,
        int $subscription_id,
        string $period_start_utc,
        string $period_end_utc,
        int $amount_cents,
        array $metrics
    ) {
        if ($tenant_id <= 0 || $subscription_id <= 0 || $amount_cents < 0) {
            throw new \InvalidArgumentException('Invalid computation result primitives.');
        }

        $this->tenant_id = $tenant_id;
        $this->subscription_id = $subscription_id;
        $this->period_start_utc = $period_start_utc;
        $this->period_end_utc = $period_end_utc;
        $this->amount_cents = $amount_cents;
        $this->metrics = self::normalize_metrics($metrics);

        $this->metrics_hash = self::hash_payload(array('metrics' => $this->metrics));
        $this->computation_hash = self::hash_payload(array(
            'amount_cents' => $this->amount_cents,
            'metrics' => $this->metrics,
            'period_end_utc' => $this->period_end_utc,
            'period_start_utc' => $this->period_start_utc,
            'subscription_id' => $this->subscription_id,
            'tenant_id' => $this->tenant_id,
        ));
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

    public function amount_cents(): int
    {
        return $this->amount_cents;
    }

    public function metrics_hash(): string
    {
        return $this->metrics_hash;
    }

    public function hash(): string
    {
        return $this->computation_hash;
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
     * @param array<string,array{usage_units:int,unit_price_cents:int}> $metrics
     * @return array<string,array{usage_units:int,unit_price_cents:int}>
     */
    private static function normalize_metrics(array $metrics): array
    {
        $normalized = array();

        foreach ($metrics as $metric_key => $metric_data) {
            if (! is_string($metric_key) || $metric_key === '' || ! is_array($metric_data)) {
                throw new \InvalidArgumentException('Invalid metric structure in computation result.');
            }

            if (! array_key_exists('usage_units', $metric_data) || ! array_key_exists('unit_price_cents', $metric_data)) {
                throw new \InvalidArgumentException('Computation metric payload missing required keys.');
            }

            $usage_units = $metric_data['usage_units'];
            $unit_price_cents = $metric_data['unit_price_cents'];
            if (! is_int($usage_units) || ! is_int($unit_price_cents)) {
                throw new \InvalidArgumentException('Computation metric payload must be integers.');
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
     * @param array<string,mixed> $payload
     */
    private static function hash_payload(array $payload): string
    {
        $canonical = self::canonicalize($payload);
        $json = wp_json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            $json = '{}';
        }

        return strtolower(hash('sha256', $json));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function canonicalize($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        if (self::is_associative($value)) {
            $keys = array_keys($value);
            sort($keys, SORT_STRING);
            $normalized = array();
            foreach ($keys as $key) {
                $normalized[(string) $key] = self::canonicalize($value[$key]);
            }

            return $normalized;
        }

        $normalized = array();
        foreach ($value as $entry) {
            $normalized[] = self::canonicalize($entry);
        }

        return $normalized;
    }

    /**
     * @param array<mixed> $value
     */
    private static function is_associative(array $value): bool
    {
        if ($value === array()) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
