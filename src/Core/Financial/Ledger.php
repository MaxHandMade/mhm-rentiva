<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

use MHMRentiva\Core\Tenancy\TenantResolver;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Pure Domain Layer representing the financial ledger.
 * This class interacts exclusively with the immutable append-only mhm_rentiva_ledger table.
 * It strictly prohibits UPDATE or DELETE statements.
 */
final class Ledger
{
    /**
     * Insert a new entry into the ledger ensuring append-only constraints.
     * Entry is scoped to the currently resolved TenantContext.
     *
     * @return int Number of affected rows (1 on success, 0 on duplicate/idempotent skip)
     * @throws \RuntimeException If physical database insertion fails due to duplication constraints.
     * @throws \MHMRentiva\Core\Orchestration\Exceptions\QuotaExceededException If ledger quota is hit.
     */
    public static function add_entry(LedgerEntry $entry): int
    {
        global $wpdb;

        $table     = $wpdb->prefix . 'mhm_rentiva_ledger';
        $tenant_id = TenantResolver::resolve()->get_id();

        // 1. SaaS Control Plane Guard (Quota & Status)
        if (class_exists('\\MHMRentiva\\Core\\Orchestration\\ControlPlaneGuard')) {
            \MHMRentiva\Core\Orchestration\ControlPlaneGuard::assert_operational_and_quota($tenant_id, 'ledger_entries');
        }

        $data = array(
            'tenant_id'           => $tenant_id,
            'transaction_uuid'    => $entry->get_transaction_uuid(),
            'vendor_id'           => $entry->get_vendor_id(),
            'booking_id'          => $entry->get_booking_id(),
            'order_id'            => $entry->get_order_id(),
            'type'                => $entry->get_type(),
            'amount'              => $entry->get_amount(),
            'gross_amount'        => $entry->get_gross_amount(),
            'commission_amount'   => $entry->get_commission_amount(),
            'commission_rate'     => $entry->get_commission_rate(),
            'currency'            => $entry->get_currency(),
            'context'             => $entry->get_context(),
            'status'              => $entry->get_status(),
            'created_at'          => gmdate('Y-m-d H:i:s'), // UTC strict - no WP timezone.
            'policy_id'           => $entry->get_policy_id(),
            'policy_version_hash' => $entry->get_policy_version_hash(),
        );

        $formats = array(
            '%d', // tenant_id
            '%s', // transaction_uuid
            '%d', // vendor_id
            '%d', // booking_id
            '%d', // order_id
            '%s', // type
            '%f', // amount
            '%f', // gross_amount
            '%f', // commission_amount
            '%f', // commission_rate
            '%s', // currency
            '%s', // context
            '%s', // status
            '%s', // created_at
            '%d', // policy_id
            '%s', // policy_version_hash
        );

        // Suppress database errors to handle expected duplicate idempotency gracefully without polluting logs
        $suppress = $wpdb->suppress_errors(true);

        $inserted = $wpdb->insert($table, $data, $formats);

        $error = $wpdb->last_error;
        $wpdb->suppress_errors($suppress);

        if ($inserted === false) {
            // Idempotently ignore if duplicate key restriction triggered natively by InnoDB
            if (stripos($error, 'Duplicate entry') !== false) {
                return 0;
            }

            throw new \RuntimeException(sprintf(
                'Failed to write ledger transaction UUID: %s. DB Error: %s',
                $entry->get_transaction_uuid(),
                $error
            ));
        }

        // 2. SaaS Metering Capture (Post-Success)
        if (class_exists('\\MHMRentiva\\Core\\Orchestration\\MeteredUsageTracker')) {
            \MHMRentiva\Core\Orchestration\MeteredUsageTracker::increment($tenant_id, 'ledger_entries');
        }

        return 1;
    }

    /**
     * Retrieve the payout-ready cleared balance for a vendor within the current tenant.
     * Calculated exclusively by SUM() over entries exhibiting status = 'cleared'.
     *
     * @param int      $vendor_id
     * @param int|null $tenant_id Defaults to current tenant.
     * @return float
     * @throws \MHMRentiva\Core\Tenancy\Exceptions\TenantResolutionException
     */
    public static function get_balance(int $vendor_id, ?int $tenant_id = null): float
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'mhm_rentiva_ledger';
        $tenant_id = self::ensure_tenant_id($tenant_id);

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$table} WHERE tenant_id = %d AND vendor_id = %d AND status IN ('cleared', 'reserved')",
                $tenant_id,
                $vendor_id
            )
        );

        return (float) $sum;
    }

    /**
     * Retrieve the pending balance for a vendor holding funds until reservation completion.
     * Scoped to the current tenant.
     *
     * @param int      $vendor_id
     * @param int|null $tenant_id Defaults to current tenant.
     * @return float
     * @throws \MHMRentiva\Core\Tenancy\Exceptions\TenantResolutionException
     */
    public static function get_pending_balance(int $vendor_id, ?int $tenant_id = null): float
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'mhm_rentiva_ledger';
        $tenant_id = self::ensure_tenant_id($tenant_id);

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$table} WHERE tenant_id = %d AND vendor_id = %d AND status = %s AND type IN (%s, %s)",
                $tenant_id,
                $vendor_id,
                'pending',
                'commission_credit',
                'commission_refund'
            )
        );

        return (float) $sum;
    }

    /**
     * Retrieve the aggregate gross total a vendor has earned since inception.
     * Scoped to the current tenant.
     *
     * @param int      $vendor_id
     * @param int|null $tenant_id Defaults to current tenant.
     * @return float
     * @throws \MHMRentiva\Core\Tenancy\Exceptions\TenantResolutionException
     */
    public static function get_total_earned(int $vendor_id, ?int $tenant_id = null): float
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'mhm_rentiva_ledger';
        $tenant_id = self::ensure_tenant_id($tenant_id);

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$table} WHERE tenant_id = %d AND vendor_id = %d AND type != %s AND status IN ('cleared', 'pending')",
                $tenant_id,
                $vendor_id,
                'payout_debit'
            )
        );

        return (float) $sum;
    }

    /**
     * Fetch transaction ledger history with safe pagination, scoped to the current tenant.
     *
     * @param int      $vendor_id
     * @param array    $filters
     * @param int      $limit
     * @param int      $offset
     * @param int|null $tenant_id Defaults to current tenant.
     * @return array<int, \stdClass>
     * @throws \MHMRentiva\Core\Tenancy\Exceptions\TenantResolutionException
     */
    public static function get_entries(int $vendor_id, array $filters = array(), int $limit = 20, int $offset = 0, ?int $tenant_id = null): array
    {
        global $wpdb;
        $table     = $wpdb->prefix . 'mhm_rentiva_ledger';
        $tenant_id = self::ensure_tenant_id($tenant_id);

        $where_clauses = array('tenant_id = %d', 'vendor_id = %d');
        $args          = array($tenant_id, $vendor_id);

        if (! empty($filters['status']) && is_string($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $args[]          = sanitize_key($filters['status']);
        }

        if (! empty($filters['type']) && is_string($filters['type'])) {
            $where_clauses[] = 'type = %s';
            $args[]          = sanitize_text_field($filters['type']);
        }

        if (! empty($filters['date_from']) && is_string($filters['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $args[]          = gmdate('Y-m-d 00:00:00', strtotime($filters['date_from']));
        }

        if (! empty($filters['date_to']) && is_string($filters['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $args[]          = gmdate('Y-m-d 23:59:59', strtotime($filters['date_to']));
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Append pagination binding
        $args[] = absint($limit);
        $args[] = absint($offset);

        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

        $results = $wpdb->get_results($wpdb->prepare($query, ...$args));

        return is_array($results) ? $results : array();
    }

    /**
     * Ensures a valid tenant ID is provided or resolved.
     * Throws an exception if no isolation context can be established.
     *
     * @param int|null $tenant_id
     * @return int
     * @throws \MHMRentiva\Core\Tenancy\Exceptions\TenantResolutionException
     */
    private static function ensure_tenant_id(?int $tenant_id): int
    {
        if ($tenant_id !== null && $tenant_id > 0) {
            return $tenant_id;
        }

        return TenantResolver::resolve()->get_id();
    }
}
