<?php
/**
 * One-shot maintenance script: revert prematurely auto-completed bookings.
 *
 * Background: prior to fix 6b51cb0, AutoComplete cron compared
 * _mhm_dropoff_date < NOW() with date-only granularity. Any confirmed
 * booking returning later TODAY was auto-completed at midnight — long
 * before the actual return time. has_overlap() then skipped these
 * wrongly-completed bookings, allowing double-booking.
 *
 * This script identifies such contaminated rows (status='completed' but
 * _mhm_end_ts > NOW()) and reverts them to 'in_progress' via the audited
 * Status::update_status() chain so that mhm_rentiva_booking_status_changed
 * fires for log/cache invalidation.
 *
 * Usage:
 *   wp eval-file plugins/mhm-rentiva/bin/cleanup-early-completed-bookings.php
 *     → DRY-RUN: lists rows, no DB writes
 *   wp eval-file plugins/mhm-rentiva/bin/cleanup-early-completed-bookings.php apply
 *     → APPLY: reverts each contaminated row to in_progress + invalidates cache
 *
 * Idempotent: re-running after apply finds zero rows because each fix
 * removes the row from the WHERE clause (status no longer 'completed').
 *
 * Note: declare(strict_types=1) is intentionally omitted — wp eval-file
 * runs this file via eval(), which forbids strict_types as a non-first
 * statement. Type safety relies on Status::update_status()'s own checks.
 */

if (! defined('WP_CLI') || ! WP_CLI) {
    fwrite(STDERR, "This script must be run via wp eval-file.\n");
    exit(1);
}

// WP-CLI eval-file passes positional arguments after the file path into $args.
// Flag-style (--apply) is unreliable across WP-CLI versions because the wp
// frontend may consume it as a global option. We use a positional keyword.
$args = isset($args) && is_array($args) ? $args : array();

WP_CLI::log('DEBUG: $args = ' . wp_json_encode($args));

$apply = in_array('apply', $args, true);
$mode  = $apply ? 'APPLY' : 'DRY-RUN';

WP_CLI::log("Mode: {$mode}");
WP_CLI::log('');

global $wpdb;
$now_ts = (int) current_time('timestamp');

// Identify contaminated rows: status='completed' AND end_ts > NOW()
// (cron marked them completed prematurely before actual dropoff time).
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-shot maintenance, no caching.
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT p.ID,
            ets.meta_value AS end_ts,
            dd.meta_value  AS dropoff_date,
            dt.meta_value  AS dropoff_time,
            v.meta_value   AS vehicle_id
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} s   ON s.post_id   = p.ID AND s.meta_key   = '_mhm_status'
     INNER JOIN {$wpdb->postmeta} ets ON ets.post_id = p.ID AND ets.meta_key = '_mhm_end_ts'
     LEFT  JOIN {$wpdb->postmeta} dd  ON dd.post_id  = p.ID AND dd.meta_key  = '_mhm_dropoff_date'
     LEFT  JOIN {$wpdb->postmeta} dt  ON dt.post_id  = p.ID AND dt.meta_key  = '_mhm_dropoff_time'
     LEFT  JOIN {$wpdb->postmeta} v   ON v.post_id   = p.ID AND v.meta_key   = '_mhm_vehicle_id'
     WHERE p.post_type     = 'vehicle_booking'
       AND s.meta_value    = 'completed'
       AND CAST(ets.meta_value AS UNSIGNED) > %d",
    $now_ts
));
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

if (empty($rows)) {
    WP_CLI::success('No contaminated bookings found. System is clean.');
    return;
}

WP_CLI::log("=== [{$mode}] Contaminated bookings (status=completed but end_ts > NOW()) ===");

$table = array();
foreach ($rows as $row) {
    $table[] = array(
        'booking_id'   => (int) $row->ID,
        'vehicle_id'   => (int) $row->vehicle_id,
        'end_ts'       => (int) $row->end_ts,
        'end_datetime' => gmdate('Y-m-d H:i:s', (int) $row->end_ts),
        'dropoff'      => trim(($row->dropoff_date ?? '') . ' ' . ($row->dropoff_time ?? '')),
        'action'       => $apply ? 'WILL FIX' : 'would fix',
    );
}
WP_CLI\Utils\format_items('table', $table, array('booking_id', 'vehicle_id', 'end_ts', 'end_datetime', 'dropoff', 'action'));

if (! $apply) {
    WP_CLI::log('');
    WP_CLI::warning(sprintf('DRY-RUN: %d booking(s) would be corrected. Re-run with positional "apply" to fix.', count($rows)));
    return;
}

// APPLY mode — perform corrections via audited Status::update_status().
$fixed   = 0;
$skipped = 0;
foreach ($rows as $row) {
    $bid = (int) $row->ID;
    try {
        $ok = \MHMRentiva\Admin\Booking\Core\Status::update_status($bid, 'in_progress', 0);
        if ($ok) {
            $fixed++;
            $vid = (int) $row->vehicle_id;
            if ($vid > 0 && class_exists('MHMRentiva\Admin\Booking\Helpers\Cache')) {
                \MHMRentiva\Admin\Booking\Helpers\Cache::invalidateVehicle($vid);
            }
            WP_CLI::log("  + #{$bid} completed -> in_progress");
        } else {
            $skipped++;
            WP_CLI::warning("  ! #{$bid} update_status returned false (transition blocked or meta unchanged?)");
        }
    } catch (\Throwable $e) {
        $skipped++;
        WP_CLI::warning("  ! #{$bid} exception: " . $e->getMessage());
    }
}

WP_CLI::log('');
WP_CLI::success(sprintf('Applied: %d fixed, %d skipped.', $fixed, $skipped));
