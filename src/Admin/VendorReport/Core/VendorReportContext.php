<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\VendorReport\Core;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vendor report context types.
 *
 * Stored in the `context_type` column. The matching `context_id` (VARCHAR(64))
 * carries either an integer ID, a UUID string, or NULL depending on the type.
 *
 * - BOOKING        → context_id = booking post ID (int)
 * - VEHICLE        → context_id = vehicle post ID (int)
 * - VEHICLE_ACTION → context_id = vehicle post ID (int) — Not 2 augment;
 *                    captures withdrawal/pause reason and suspends penalty
 *                    via `mhm_rentiva_before_apply_penalty` filter
 * - PENALTY        → context_id = ledger transaction UUID (CHAR(36))
 * - GENERAL        → context_id = NULL
 *
 * @since 4.35.0
 */
final class VendorReportContext {

    public const BOOKING        = 'booking';
    public const VEHICLE        = 'vehicle';
    public const VEHICLE_ACTION = 'vehicle_action';
    public const PENALTY        = 'penalty';
    public const GENERAL        = 'general';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::BOOKING,
            self::VEHICLE,
            self::VEHICLE_ACTION,
            self::PENALTY,
            self::GENERAL,
        ];
    }

    public static function is_valid(string $context_type): bool
    {
        return in_array($context_type, self::all(), true);
    }

    /**
     * Whether this context_type expects context_id to carry a value.
     * GENERAL is the only context that allows NULL/empty context_id.
     */
    public static function requires_context_id(string $context_type): bool
    {
        return $context_type !== self::GENERAL;
    }
}
