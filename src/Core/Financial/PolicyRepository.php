<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Database access layer for Commission Policies.
 *
 * All queries use $wpdb->prepare() strictly. No raw interpolation.
 * NULL vendor_id rows represent platform-wide (global) policies.
 *
 * @since 4.21.0
 */
final class PolicyRepository {

    /**
     * Resolve the most specific active policy for a vendor at a given datetime.
     *
     * Resolution order:
     * 1. Vendor-specific policy active at $datetime
     * 2. Platform-wide policy (vendor_id IS NULL) active at $datetime
     *
     * Overlap Guard: If multiple policies overlap the same datetime (admin misconfiguration),
     * the MOST RECENTLY CREATED policy (highest ID) wins via ORDER BY id DESC LIMIT 1.
     * This is intentional — a newly created policy is assumed to supersede older ones.
     * Silent overlap is never permitted; the resolver is deterministic even under misconfiguration.
     *
     * @param int    $vendor_id    The vendor user ID to check for specific policy first.
     * @param string $datetime     UTC datetime string in 'Y-m-d H:i:s' format.
     * @return CommissionPolicy|null Returns NULL only when no platform-wide fallback exists.
     */
    public static function find_active_at(int $vendor_id, string $datetime): ?CommissionPolicy
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_commission_policy';

        // 1. Try vendor-specific policy first (highest ID wins if overlapping).
        $vendor_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
				WHERE vendor_id = %d
				AND effective_from <= %s
				AND (effective_to IS NULL OR effective_to >= %s)
				ORDER BY id DESC
				LIMIT 1",
                $vendor_id,
                $datetime,
                $datetime
            )
        );

        if ($vendor_row !== null) {
            return self::hydrate($vendor_row);
        }

        // 2. Fallback to platform-wide policy (vendor_id IS NULL), newest wins.
        $global_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
				WHERE vendor_id IS NULL
				AND effective_from <= %s
				AND (effective_to IS NULL OR effective_to >= %s)
				ORDER BY id DESC
				LIMIT 1",
                $datetime,
                $datetime
            )
        );

        if ($global_row !== null) {
            return self::hydrate($global_row);
        }

        return null;
    }

    /**
     * Read the current active global commission rate.
     * Returns NULL if no platform-wide policy is active.
     *
     * @return float|null Current rate as a percentage (e.g. 15.0 = 15%), or null if unset.
     */
    public static function get_current_global_rate(): ?float
    {
        $now    = current_time('mysql', true);
        $policy = self::find_active_at(0, $now);
        return $policy !== null ? $policy->get_global_rate() : null;
    }

    /**
     * Insert a new platform-wide commission policy effective from now (open-ended).
     * Previous global policies remain in history; the newest row wins via find_active_at().
     *
     * @param  float  $rate  Commission percentage (e.g. 15.0 for 15%).
     * @param  string $label Human-readable label for the audit log.
     * @return bool True on success.
     */
    public static function insert_global_policy(float $rate, string $label = ''): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mhm_rentiva_commission_policy';

        if ($rate < 0.0 || $rate > 100.0) {
            return false;
        }

        $now  = current_time('mysql', true);
        $hash = hash('sha256', (string) json_encode(array( null, $rate, $now, null )));

        $inserted = $wpdb->insert(
            $table,
            array(
                'label'          => $label !== '' ? $label : sprintf('Global rate %.2f%%', $rate),
                'global_rate'    => $rate,
                'vendor_id'      => null,
                'effective_from' => $now,
                'effective_to'   => null,
                'version_hash'   => $hash,
            ),
            array( '%s', '%f', null, '%s', null, '%s' )
        );

        return $inserted !== false;
    }

    /**
     * Hydrate a DB row object into a CommissionPolicy value object.
     *
     * @param \stdClass $row Raw database row.
     */
    private static function hydrate(\stdClass $row): CommissionPolicy
    {
        return new CommissionPolicy(
            (int) $row->id,
            (string) $row->label,
            (float) $row->global_rate,
            isset($row->vendor_id) ? (int) $row->vendor_id : null,
            (string) $row->effective_from,
            isset($row->effective_to) ? (string) $row->effective_to : null,
            (string) $row->version_hash
        );
    }
}
