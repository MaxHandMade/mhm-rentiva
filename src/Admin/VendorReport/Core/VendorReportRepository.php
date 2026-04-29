<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor Reports — DB layer.
 *
 * CRUD over the `wp_mhm_rentiva_vendor_reports` custom table. No business logic,
 * no email side-effects, no penalty pipeline interaction — those live in the
 * Service layer ({@see VendorReportService}).
 *
 * @since 4.35.0
 */
final class VendorReportRepository {


    /**
     * Per-request cache for `has_open_report_for()` lookups.
     *
     * Without this, the `mhm_rentiva_before_apply_penalty` filter callback
     * would issue one DB query per penalty evaluation. The cache key includes
     * vendor + context_type + context_id; entries are invalidated whenever
     * `create()` or `update_status()` writes a row.
     *
     * @var array<string, bool>
     */
    private static array $has_open_cache = [];

    /**
     * Insert a new report row. Status defaults to OPEN.
     *
     * @param array{
     *     vendor_id: int,
     *     context_type: string,
     *     context_id?: string|int|null,
     *     title: string,
     *     description: string,
     *     status?: string,
     * } $data
     *
     * @return int Inserted row id, or 0 on failure.
     */
    public static function create(array $data): int
    {
        global $wpdb;

        $vendor_id    = isset($data['vendor_id']) ? (int) $data['vendor_id'] : 0;
        $context_type = isset($data['context_type']) ? (string) $data['context_type'] : '';
        $title        = isset($data['title']) ? (string) $data['title'] : '';
        $description  = isset($data['description']) ? (string) $data['description'] : '';
        $status       = isset($data['status']) ? (string) $data['status'] : VendorReportStatus::OPEN;

        if ($vendor_id <= 0 || ! VendorReportContext::is_valid($context_type) || $title === '' || $description === '') {
            return 0;
        }

        if (! VendorReportStatus::is_valid($status)) {
            $status = VendorReportStatus::OPEN;
        }

        $context_id = self::normalize_context_id($data['context_id'] ?? null);

        $now = current_time('mysql', true);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table outside WP_Query; cache busted via static map below.
        $inserted = $wpdb->insert(
            self::table(),
            [
                'vendor_id'    => $vendor_id,
                'context_type' => $context_type,
                'context_id'   => $context_id,
                'title'        => $title,
                'description'  => $description,
                'status'       => $status,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ($inserted === false) {
            return 0;
        }

        // Invalidate the matching has_open cache key.
        self::invalidate_has_open_cache($vendor_id, $context_type, (string) ( $context_id ?? '' ));

        return (int) $wpdb->insert_id;
    }

    /**
     * Find a single report by id.
     *
     * @return object|null Row object with all columns, or null if not found.
     */
    public static function find(int $report_id): ?object
    {
        if ($report_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = self::table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $report_id));

        return is_object($row) ? $row : null;
    }

    /**
     * Update a report's status, admin note, and (for terminal statuses) resolved_at.
     */
    public static function update_status(int $report_id, string $status, ?string $admin_note, ?int $admin_user_id = null): bool
    {
        if ($report_id <= 0 || ! VendorReportStatus::is_valid($status)) {
            return false;
        }

        $now = current_time('mysql', true);

        $data   = [
            'status'     => $status,
            'admin_note' => $admin_note,
            'updated_at' => $now,
        ];
        $format = [ '%s', '%s', '%s' ];

        if ($admin_user_id !== null && $admin_user_id > 0) {
            $data['admin_user_id'] = $admin_user_id;
            $format[]              = '%d';
        }

        if (VendorReportStatus::is_terminal($status)) {
            $data['resolved_at'] = $now;
            $format[]            = '%s';
        }

        global $wpdb;

        // Read the row first so we can bust the cache for its (vendor, context) key.
        $row = self::find($report_id);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            self::table(),
            $data,
            [ 'id' => $report_id ],
            $format,
            [ '%d' ]
        );

        if ($updated === false) {
            return false;
        }

        if ($row !== null) {
            self::invalidate_has_open_cache(
                (int) $row->vendor_id,
                (string) $row->context_type,
                (string) ( $row->context_id ?? '' )
            );
        }

        return true;
    }

    /**
     * @return list<object>
     */
    public static function find_by_vendor(int $vendor_id, ?string $status = null): array
    {
        if ($vendor_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = self::table();

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Custom table outside WP_Query; $table is a sanitized identifier built from $wpdb->prefix + a hard-coded suffix.
        if ($status !== null && VendorReportStatus::is_valid($status)) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE vendor_id = %d AND status = %s ORDER BY created_at DESC",
                    $vendor_id,
                    $status
                )
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE vendor_id = %d ORDER BY created_at DESC",
                    $vendor_id
                )
            );
        }
        // phpcs:enable

        return is_array($rows) ? $rows : [];
    }

    /**
     * Whether a vendor has an open (non-terminal) report against the given context.
     *
     * Used by the `mhm_rentiva_before_apply_penalty` filter callback to decide
     * whether to suspend a penalty. Per-request cached so a withdrawal flow that
     * checks this filter twice (Manager + Recorder hook points) only hits the DB
     * once.
     */
    public static function has_open_report_for(int $vendor_id, string $context_type, ?string $context_id): bool
    {
        if ($vendor_id <= 0 || ! VendorReportContext::is_valid($context_type)) {
            return false;
        }

        $context_id = $context_id !== null ? (string) $context_id : '';

        $cache_key = self::has_open_cache_key($vendor_id, $context_type, $context_id);
        if (array_key_exists($cache_key, self::$has_open_cache)) {
            return self::$has_open_cache[ $cache_key ];
        }

        global $wpdb;
        $table         = self::table();
        $open_statuses = VendorReportStatus::open_statuses();
        $placeholders  = implode(',', array_fill(0, count($open_statuses), '%s'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- $table = sanitized prefix+suffix; $placeholders = '%s,%s,…' tokens; values bound via prepare(array).
        if ($context_id === '') {
            // GENERAL context only — context_id IS NULL.
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d AND context_type = %s AND context_id IS NULL AND status IN ({$placeholders})",
                    array_merge([ $vendor_id, $context_type ], $open_statuses)
                )
            );
        } else {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE vendor_id = %d AND context_type = %s AND context_id = %s AND status IN ({$placeholders})",
                    array_merge([ $vendor_id, $context_type, $context_id ], $open_statuses)
                )
            );
        }
        // phpcs:enable

        $has_open                           = $count > 0;
        self::$has_open_cache[ $cache_key ] = $has_open;
        return $has_open;
    }

    /**
     * Clear the per-request has_open cache. Used in tests + after cron/cli writes.
     */
    public static function reset_has_open_cache(): void
    {
        self::$has_open_cache = [];
    }

    /**
     * Resolved table name with the WP prefix.
     */
    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'mhm_rentiva_vendor_reports';
    }

    /**
     * Normalize the various context_id input shapes to a `?string`.
     *
     * @param mixed $raw
     */
    private static function normalize_context_id($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw)) {
            return (string) $raw;
        }
        if (is_string($raw)) {
            return $raw;
        }
        if (is_numeric($raw)) {
            return (string) $raw;
        }
        return null;
    }

    private static function has_open_cache_key(int $vendor_id, string $context_type, string $context_id): string
    {
        return $vendor_id . '|' . $context_type . '|' . $context_id;
    }

    private static function invalidate_has_open_cache(int $vendor_id, string $context_type, string $context_id): void
    {
        unset(self::$has_open_cache[ self::has_open_cache_key($vendor_id, $context_type, $context_id) ]);
    }
}
