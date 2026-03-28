<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Vehicle lifecycle status constants and transition rules.
 *
 * States: active, paused, expired, withdrawn, pending_review.
 * Mirrors the Booking\Core\Status pattern for consistent FSM design.
 *
 * @since 4.24.0
 */
final class VehicleLifecycleStatus
{
    public const PENDING_REVIEW = 'pending_review';
    public const ACTIVE         = 'active';
    public const PAUSED         = 'paused';
    public const EXPIRED        = 'expired';
    public const WITHDRAWN      = 'withdrawn';

    /** Listing duration in days before expiry. */
    public const LISTING_DURATION_DAYS = 90;

    /** Days before expiry to send first warning. */
    public const EXPIRY_WARNING_DAYS_FIRST = 10;

    /** Days before expiry to send second warning. */
    public const EXPIRY_WARNING_DAYS_SECOND = 3;

    /** Grace period after expiry before auto-withdrawal (days). */
    public const EXPIRY_GRACE_DAYS = 7;

    /** Cooldown period after withdrawal before relisting (days). */
    public const WITHDRAWAL_COOLDOWN_DAYS = 7;

    /** Max pauses per vehicle per calendar month. */
    public const MAX_PAUSES_PER_MONTH = 2;

    /** Max pause duration in days. */
    public const MAX_PAUSE_DURATION_DAYS = 30;

    /**
     * Get listing duration days (settings-aware).
     */
    public static function listing_duration_days(): int {
        return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_listing_duration_days', self::LISTING_DURATION_DAYS );
    }

    /**
     * Get first expiry warning days before expiry (settings-aware).
     */
    public static function expiry_warning_first(): int {
        return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_expiry_warning_first_days', self::EXPIRY_WARNING_DAYS_FIRST );
    }

    /**
     * Get second expiry warning days before expiry (settings-aware).
     */
    public static function expiry_warning_second(): int {
        return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_expiry_warning_second_days', self::EXPIRY_WARNING_DAYS_SECOND );
    }

    /**
     * Get expiry grace period in days (settings-aware).
     */
    public static function expiry_grace_days(): int {
        return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_expiry_grace_days', self::EXPIRY_GRACE_DAYS );
    }

    /**
     * Get withdrawal cooldown in days (settings-aware).
     */
    public static function withdrawal_cooldown_days(): int {
        return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_withdrawal_cooldown_days', self::WITHDRAWAL_COOLDOWN_DAYS );
    }

    /**
     * Get max pauses per month (settings-aware).
     */
    public static function max_pauses_per_month(): int {
        return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_max_pauses_per_month', self::MAX_PAUSES_PER_MONTH );
    }

    /**
     * Get max pause duration in days (settings-aware).
     */
    public static function max_pause_duration_days(): int {
        return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'vendor_max_pause_duration_days', self::MAX_PAUSE_DURATION_DAYS );
    }

    /**
     * All allowed lifecycle statuses.
     *
     * @return string[]
     */
    public static function allowed(): array
    {
        return array(
            self::PENDING_REVIEW,
            self::ACTIVE,
            self::PAUSED,
            self::EXPIRED,
            self::WITHDRAWN,
        );
    }

    /**
     * Allowed state transitions.
     *
     * @return array<string, string[]>
     */
    public static function transitions(): array
    {
        return array(
            self::PENDING_REVIEW => array(self::ACTIVE, self::WITHDRAWN),
            self::ACTIVE         => array(self::PAUSED, self::EXPIRED, self::WITHDRAWN),
            self::PAUSED         => array(self::ACTIVE, self::WITHDRAWN),
            self::EXPIRED        => array(self::ACTIVE, self::WITHDRAWN),
            self::WITHDRAWN      => array(self::PENDING_REVIEW),
        );
    }

    /**
     * Check if a transition is valid.
     */
    public static function can_transition(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }

        $allowed = self::transitions();
        return isset($allowed[$from]) && in_array($to, $allowed[$from], true);
    }

    /**
     * Get the current lifecycle status for a vehicle.
     * Returns 'active' as default if not set (backward compatibility with legacy vehicles).
     */
    public static function get(int $vehicle_id): string
    {
        $status = (string) get_post_meta($vehicle_id, MetaKeys::VEHICLE_LIFECYCLE_STATUS, true);

        if ($status === '' || ! in_array($status, self::allowed(), true)) {
            return self::ACTIVE;
        }

        return $status;
    }

    /**
     * Get translatable label for a lifecycle status.
     */
    public static function get_label(string $status): string
    {
        $labels = array(
            self::PENDING_REVIEW => __('Pending Review', 'mhm-rentiva'),
            self::ACTIVE         => __('Active', 'mhm-rentiva'),
            self::PAUSED         => __('Paused', 'mhm-rentiva'),
            self::EXPIRED        => __('Expired', 'mhm-rentiva'),
            self::WITHDRAWN      => __('Withdrawn', 'mhm-rentiva'),
        );

        return $labels[$status] ?? $status;
    }

    /**
     * Get color code for a lifecycle status.
     */
    public static function get_color(string $status): string
    {
        $colors = array(
            self::PENDING_REVIEW => '#6c757d',
            self::ACTIVE         => '#28a745',
            self::PAUSED         => '#ffc107',
            self::EXPIRED        => '#fd7e14',
            self::WITHDRAWN      => '#dc3545',
        );

        return $colors[$status] ?? '#6c757d';
    }
}
