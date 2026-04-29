<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\Hooks;

use MHMRentiva\Admin\VendorReport\Core\VendorReportContext;
use MHMRentiva\Admin\VendorReport\Core\VendorReportRepository;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Penalty Suspension hook — bridges the Vendor Report system with the Vehicle
 * lifecycle penalty pipeline.
 *
 * When a vendor files a `vehicle_action` report (withdrawal/pause reason
 * capture, see plan §5.4), the report sits in `open` status until an admin
 * resolves it. While the report is open, this filter callback returns `false`
 * for the matching vehicle — both the score deduction in
 * {@see \MHMRentiva\Admin\Vehicle\VehicleLifecycleManager::withdraw()} and the
 * ledger entry in {@see \MHMRentiva\Admin\Vehicle\PenaltyRecorder::record_penalty()}
 * are skipped.
 *
 * Resolution side-effects (deferred apply on reject, reverse on resolve) are
 * handled by {@see \MHMRentiva\Admin\VendorReport\Core\VendorReportService}.
 *
 * @since 4.35.0
 */
final class PenaltySuspensionHook {


    public static function register(): void
    {
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- prefix `mhm_rentiva_` matches Text Domain.
        add_filter('mhm_rentiva_before_apply_penalty', [ self::class, 'maybe_suspend' ], 10, 5);
    }

    /**
     * Filter callback. Returns `false` when an open vehicle_action report
     * exists for the given vendor + vehicle pair, otherwise the previous value.
     *
     * @param bool   $apply       Whether to apply the penalty (filter chain default = true).
     * @param int    $vehicle_id  Vehicle post ID.
     * @param int    $vendor_id   Vendor user ID.
     * @param string $reason      Penalty reason (e.g. 'withdrawal', 'pause').
     * @param mixed  $penalty     Pre-calculated penalty amount (float or array, ignored here).
     */
    public static function maybe_suspend(bool $apply, int $vehicle_id, int $vendor_id, string $reason, $penalty): bool
    {
        unset($reason, $penalty); // not needed for the suspension check.

        if (! $apply) {
            return false;
        }

        if ($vehicle_id <= 0 || $vendor_id <= 0) {
            return $apply;
        }

        $has_open = VendorReportRepository::has_open_report_for(
            $vendor_id,
            VendorReportContext::VEHICLE_ACTION,
            (string) $vehicle_id
        );

        return $has_open ? false : $apply;
    }
}
