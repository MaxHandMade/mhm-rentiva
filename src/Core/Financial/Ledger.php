<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

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
     *
     * @throws \RuntimeException If physical database insertion fails due to duplication constraints.
     */
    public static function add_entry(LedgerEntry $entry): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

        $data = array(
            'transaction_uuid' => $entry->get_transaction_uuid(),
            'vendor_id'        => $entry->get_vendor_id(),
            'booking_id'       => $entry->get_booking_id(),
            'order_id'         => $entry->get_order_id(),
            'type'             => $entry->get_type(),
            'amount'           => $entry->get_amount(),
            'gross_amount'     => $entry->get_gross_amount(),
            'commission_amount' => $entry->get_commission_amount(),
            'commission_rate'  => $entry->get_commission_rate(),
            'currency'         => $entry->get_currency(),
            'context'          => $entry->get_context(),
            'status'           => $entry->get_status(),
            'created_at'       => current_time('mysql', true), // Always enforce UTC for audit logs
        );

        $formats = array(
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
        );

        // Suppress database errors to handle expected duplicate idempotency gracefully without polluting logs
        $suppress = $wpdb->suppress_errors(true);

        $inserted = $wpdb->insert($table, $data, $formats);

        $error = $wpdb->last_error;
        $wpdb->suppress_errors($suppress);

        if ($inserted === false) {
            // Idempotently ignore if duplicate key restriction triggered natively by InnoDB
            if (stripos($error, 'Duplicate entry') !== false) {
                return;
            }

            throw new \RuntimeException(sprintf(
                'Failed to write ledger transaction UUID: %s. DB Error: %s',
                $entry->get_transaction_uuid(),
                $error
            ));
        }
    }

    /**
     * Retrieve the payout-ready cleared balance for a vendor.
     * Calculated exclusively by SUM() over entries exhibiting status = 'cleared'.
     */
    public static function get_balance(int $vendor_id): float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$table} WHERE vendor_id = %d AND status = %s",
                $vendor_id,
                'cleared'
            )
        );

        return (float) $sum;
    }

    /**
     * Retrieve the pending balance for a vendor holding funds until reservation completion.
     */
    public static function get_pending_balance(int $vendor_id): float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$table} WHERE vendor_id = %d AND status = %s AND type IN (%s, %s)",
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
     * Ignores payout debit deductions strictly resolving the historical earnings scope.
     */
    public static function get_total_earned(int $vendor_id): float
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

        // Historical earning is technically credits + refunds strictly excluding payout debits and spanning cleared/pending states optionally.
        // Assuming 'total earned' applies strictly to non-payout transactions that represent vendor profit injections.
        $sum = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$table} WHERE vendor_id = %d AND type != %s AND status IN ('cleared', 'pending')",
                $vendor_id,
                'payout_debit'
            )
        );

        return (float) $sum;
    }

    /**
     * Fetch transaction ledger history mapping safe pagination ranges scaling dynamically.
     *
     * @return array<int, \stdClass> Array of database row objects representing the ledger table matches
     */
    public static function get_entries(int $vendor_id, array $filters = array(), int $limit = 20, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_ledger';

        $where_clauses = array('vendor_id = %d');
        $args          = array($vendor_id);

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
}
