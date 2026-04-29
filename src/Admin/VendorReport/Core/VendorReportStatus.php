<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor report lifecycle states.
 *
 * Stored in the `status` column of `wp_mhm_rentiva_vendor_reports`.
 *
 * State transitions:
 *   open → in_review → resolved
 *                    → rejected
 *   open → resolved   (admin can skip in_review)
 *   open → rejected
 *
 * Once a report is `resolved` or `rejected` it is terminal — re-opening is
 * not modeled at this stage. If the same vendor needs to escalate again
 * they can file a fresh report.
 *
 * @since 4.35.0
 */
final class VendorReportStatus {

    public const OPEN      = 'open';
    public const IN_REVIEW = 'in_review';
    public const RESOLVED  = 'resolved';
    public const REJECTED  = 'rejected';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::OPEN,
            self::IN_REVIEW,
            self::RESOLVED,
            self::REJECTED,
        ];
    }

    /**
     * Open statuses are considered "active" — they suspend penalties via
     * the `mhm_rentiva_before_apply_penalty` filter and prevent the same
     * vendor from filing a duplicate report against the same context.
     *
     * @return list<string>
     */
    public static function open_statuses(): array
    {
        return [ self::OPEN, self::IN_REVIEW ];
    }

    /**
     * Terminal statuses — no further state transitions.
     *
     * @return list<string>
     */
    public static function terminal_statuses(): array
    {
        return [ self::RESOLVED, self::REJECTED ];
    }

    public static function is_valid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    public static function is_open(string $status): bool
    {
        return in_array($status, self::open_statuses(), true);
    }

    public static function is_terminal(string $status): bool
    {
        return in_array($status, self::terminal_statuses(), true);
    }
}
