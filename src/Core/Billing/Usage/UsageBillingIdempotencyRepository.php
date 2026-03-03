<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Persistence boundary for usage billing idempotency rows.
 *
 * Notes:
 * - Insert path is race-safe (direct INSERT, no pre-SELECT).
 * - State transition is guarded (`pending` -> `committed` only).
 */
final class UsageBillingIdempotencyRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_SKIPPED_DRIFT = 'skipped_drift';

    private string $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'mhm_rentiva_usage_billing';
    }

    public function build_idempotency_key(int $tenant_id, int $subscription_id, string $period_start_utc): string
    {
        if ($tenant_id <= 0 || $subscription_id <= 0) {
            throw new \InvalidArgumentException('tenant_id and subscription_id must be positive integers.');
        }

        if (! $this->is_valid_utc_datetime($period_start_utc)) {
            throw new \InvalidArgumentException('period_start_utc must be in UTC DATETIME format: Y-m-d H:i:s');
        }

        $key = strtolower(hash('sha256', $tenant_id . '|' . $subscription_id . '|' . $period_start_utc));

        if (! $this->is_valid_hash($key)) {
            throw new \RuntimeException('Failed to generate a valid idempotency key.');
        }

        return $key;
    }

    /**
     * Race-safe pending insert path.
     * Duplicate key is a deterministic no-op and returns false.
     *
     * @param array<string,mixed> $row
     */
    public function insert_pending(array $row): bool
    {
        global $wpdb;

        if (! $this->validate_pending_row($row)) {
            return false;
        }

        $created_at = (string) ($row['created_at_utc'] ?? gmdate('Y-m-d H:i:s'));
        $updated_at = (string) ($row['updated_at_utc'] ?? gmdate('Y-m-d H:i:s'));

        if (! $this->is_valid_utc_datetime($created_at) || ! $this->is_valid_utc_datetime($updated_at)) {
            return false;
        }

        $data = array(
            'tenant_id' => (int) $row['tenant_id'],
            'subscription_id' => (int) $row['subscription_id'],
            'period_start_utc' => (string) $row['period_start_utc'],
            'period_end_utc' => (string) $row['period_end_utc'],
            'idempotency_key' => strtolower((string) $row['idempotency_key']),
            'amount_cents' => (int) $row['amount_cents'],
            'computation_hash' => strtolower((string) $row['computation_hash']),
            'ledger_transaction_uuid' => null,
            'status' => self::STATUS_PENDING,
            'created_at_utc' => $created_at,
            'updated_at_utc' => $updated_at,
        );

        $formats = array(
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        );

        // Duplicate insert is expected under concurrency; suppress query noise for that branch.
        $suppress = $wpdb->suppress_errors(true);
        $inserted = $wpdb->insert($this->table_name, $data, $formats);
        $last_error = (string) $wpdb->last_error;
        $wpdb->suppress_errors($suppress);

        if ($inserted === false) {
            if (stripos($last_error, 'Duplicate entry') !== false) {
                return false;
            }

            throw new \RuntimeException('Failed to insert usage billing pending row: ' . $last_error);
        }

        return true;
    }

    public function mark_committed(string $idempotency_key, string $ledger_transaction_uuid): bool
    {
        global $wpdb;

        $key = strtolower(trim($idempotency_key));
        $uuid = trim($ledger_transaction_uuid);

        if (! $this->is_valid_hash($key)) {
            return false;
        }

        if ($uuid === '') {
            return false;
        }

        $affected = $wpdb->update(
            $this->table_name,
            array(
                'status' => self::STATUS_COMMITTED,
                'ledger_transaction_uuid' => $uuid,
                'updated_at_utc' => gmdate('Y-m-d H:i:s'),
            ),
            array(
                'idempotency_key' => $key,
                'status' => self::STATUS_PENDING,
                'ledger_transaction_uuid' => null,
            ),
            array('%s', '%s', '%s'),
            array('%s', '%s', '%s')
        );

        return $affected === 1;
    }

    public function mark_skipped_drift(string $idempotency_key): bool
    {
        global $wpdb;

        $key = strtolower(trim($idempotency_key));
        if (! $this->is_valid_hash($key)) {
            return false;
        }

        $affected = $wpdb->update(
            $this->table_name,
            array(
                'status' => self::STATUS_SKIPPED_DRIFT,
                'updated_at_utc' => gmdate('Y-m-d H:i:s'),
            ),
            array(
                'idempotency_key' => $key,
                'status' => self::STATUS_PENDING,
                'ledger_transaction_uuid' => null,
            ),
            array('%s', '%s'),
            array('%s', '%s', '%s')
        );

        return $affected === 1;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find_by_key(string $idempotency_key): ?array
    {
        global $wpdb;

        $key = strtolower(trim($idempotency_key));
        if (! $this->is_valid_hash($key)) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE idempotency_key = %s LIMIT 1",
                $key
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function validate_pending_row(array $row): bool
    {
        $tenant_id = (int) ($row['tenant_id'] ?? 0);
        $subscription_id = (int) ($row['subscription_id'] ?? 0);
        $period_start_utc = (string) ($row['period_start_utc'] ?? '');
        $period_end_utc = (string) ($row['period_end_utc'] ?? '');
        $idempotency_key = strtolower((string) ($row['idempotency_key'] ?? ''));
        $amount_cents_raw = $row['amount_cents'] ?? null;
        $computation_hash = strtolower((string) ($row['computation_hash'] ?? ''));
        $status = (string) ($row['status'] ?? self::STATUS_PENDING);
        $ledger_uuid = isset($row['ledger_transaction_uuid']) ? trim((string) $row['ledger_transaction_uuid']) : '';

        if ($tenant_id <= 0 || $subscription_id <= 0) {
            return false;
        }

        if (! $this->is_valid_utc_datetime($period_start_utc) || ! $this->is_valid_utc_datetime($period_end_utc)) {
            return false;
        }

        if (! $this->is_valid_hash($idempotency_key) || ! $this->is_valid_hash($computation_hash)) {
            return false;
        }

        if ($status !== self::STATUS_PENDING) {
            return false;
        }

        if ($ledger_uuid !== '') {
            return false;
        }

        if (! is_int($amount_cents_raw)) {
            return false;
        }

        if ($amount_cents_raw < 0) {
            return false;
        }

        return true;
    }

    private function is_valid_hash(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $value);
    }

    private function is_valid_utc_datetime(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d H:i:s') === $value;
    }
}
