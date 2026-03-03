<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

class UsageBillingFeatureFlagRepository
{
    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mhm_rentiva_usage_billing_feature_flags';
    }

    public function is_enabled(int $tenant_id): bool
    {
        if ($tenant_id <= 0) {
            return false;
        }

        global $wpdb;

        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT is_enabled FROM {$this->table_name} WHERE tenant_id = %d LIMIT 1",
                $tenant_id
            )
        );

        return (int) $value === 1;
    }

    public function set_enabled(int $tenant_id, bool $enabled, string $updated_at_utc): void
    {
        if ($tenant_id <= 0) {
            throw new \InvalidArgumentException('tenant_id must be a positive integer.');
        }

        if (! $this->is_valid_iso8601_utc($updated_at_utc)) {
            throw new \InvalidArgumentException('updated_at_utc must be ISO8601 UTC format.');
        }

        global $wpdb;

        $insert_sql = "
            INSERT INTO {$this->table_name} (tenant_id, is_enabled, updated_at_utc)
            VALUES (%d, %d, %s)
            ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                updated_at_utc = VALUES(updated_at_utc)
        ";

        $result = $wpdb->query(
            $wpdb->prepare(
                $insert_sql,
                $tenant_id,
                $enabled ? 1 : 0,
                $updated_at_utc
            )
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to persist usage billing feature flag.');
        }
    }

    private function is_valid_iso8601_utc(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d\TH:i:s\Z') === $value;
    }
}
