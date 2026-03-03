<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Monitoring;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Sequence-1 payment metrics collector.
 * Emits deterministic, tenant-safe metric payloads without persistence.
 */
final class PaymentMetricsCollector {
    /**
     * In-memory metric buffer for deterministic test-safe collection.
     *
     * @var array<int,array{metric_name:string,labels:array<string,mixed>}>
     */
    private static array $buffer = array();

    /**
     * @var array<string,string[]>
     */
    private const METRIC_LABEL_ALLOWLIST = array(
        'payment.capture.success'    => array(
            'tenant_id',
            'provider',
            'status',
            'order_id',
            'plan_id',
            'amount_cents',
            'currency',
            'idempotency_key',
            'provider_event_id',
            'provider_transaction_id',
            'ts_utc',
        ),
        'payment.refund.success'     => array(
            'tenant_id',
            'provider',
            'status',
            'order_id',
            'amount_cents',
            'currency',
            'idempotency_key',
            'provider_event_id',
            'provider_transaction_id',
            'ts_utc',
        ),
        'payment.duplicate.hit'      => array(
            'tenant_id',
            'provider',
            'status',
            'order_id',
            'duplicate_kind',
            'existing_status',
            'provider_event_id',
            'provider_transaction_id',
            'idempotency_key',
            'ts_utc',
        ),
        'payment.lock_wait.conflict' => array(
            'tenant_id',
            'provider',
            'status',
            'order_id',
            'provider_event_id',
            'provider_transaction_id',
            'lock_window_seconds',
            'http_status',
            'retryable',
            'ts_utc',
        ),
        'payment.replay.reject'      => array(
            'tenant_id',
            'provider',
            'reason',
            'http_status',
            'ts_utc',
        ),
        'usage_billing_drift_detected_count' => array(
            'tenant_id',
            'idempotency_key',
            'ts_utc',
        ),
        'usage_billing_flag_enabled_count' => array(
            'tenant_id',
            'ts_utc',
        ),
        'usage_billing_flag_disabled_count' => array(
            'tenant_id',
            'ts_utc',
        ),
    );

    /**
     * @var string[]
     */
    private const PII_KEYS = array(
        'email',
        'phone',
        'name',
        'full_name',
        'customer_name',
        'customer_email',
        'address',
    );

    /**
     * @return string[]
     */
    public static function supported_metrics(): array
    {
        return array_keys(self::METRIC_LABEL_ALLOWLIST);
    }

    /**
     * Emit one metric with deterministic payload shaping.
     */
    public static function emit(string $metricName, array $labels): void
    {
        try {
            if (! isset(self::METRIC_LABEL_ALLOWLIST[ $metricName ])) {
                return;
            }

            $normalized     = self::normalize_labels($metricName, $labels);
            self::$buffer[] = array(
                'metric_name' => $metricName,
                'labels'      => $normalized,
            );
        } catch (\Throwable) {
            // Monitoring must never affect payment execution flow.
            return;
        }
    }

    /**
     * Returns a snapshot of buffered metrics.
     *
     * @return array<int,array{metric_name:string,labels:array<string,mixed>}>
     */
    public static function snapshot_buffer(): array
    {
        return self::$buffer;
    }

    /**
     * Returns and clears the buffered metrics atomically.
     *
     * @return array<int,array{metric_name:string,labels:array<string,mixed>}>
     */
    public static function flush_buffer(): array
    {
        $snapshot     = self::$buffer;
        self::$buffer = array();

        return $snapshot;
    }

    /**
     * Clears all buffered metrics.
     */
    public static function reset_buffer(): void
    {
        self::$buffer = array();
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalize_labels(string $metricName, array $labels): array
    {
        $allowlist = self::METRIC_LABEL_ALLOWLIST[ $metricName ];

        $normalized = array(
            'tenant_id' => max(0, (int) ( $labels['tenant_id'] ?? 0 )),
            'ts_utc'    => self::normalize_timestamp( (string) ( $labels['ts_utc'] ?? '' )),
        );

        foreach ($allowlist as $key) {
            if ($key === 'tenant_id' || $key === 'ts_utc') {
                continue;
            }

            if (! array_key_exists($key, $labels)) {
                continue;
            }

            if (in_array($key, self::PII_KEYS, true)) {
                continue;
            }

            $value = self::normalize_scalar($labels[ $key ]);
            if ($value === null) {
                continue;
            }

            $normalized[ $key ] = $value;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return bool|int|string|null
     */
    private static function normalize_scalar($value)
    {
        if (is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $stringValue = sanitize_text_field( (string) $value);
        if ($stringValue === '') {
            return '';
        }

        if (preg_match('/^-?[0-9]+$/', $stringValue) === 1) {
            return (int) $stringValue;
        }

        return $stringValue;
    }

    private static function normalize_timestamp(string $timestamp): string
    {
        if ($timestamp !== '') {
            try {
                $date = new \DateTimeImmutable($timestamp, new \DateTimeZone('UTC'));
                return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
            } catch (\Throwable) {
                return gmdate('Y-m-d\\TH:i:s\\Z');
            }
        }

        return gmdate('Y-m-d\\TH:i:s\\Z');
    }
}
