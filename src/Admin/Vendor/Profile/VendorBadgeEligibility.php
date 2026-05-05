<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * Pure logic for vendor profile badge state.
 *
 * Returns 'verified' (Onaylı Bayi), 'new' (Yeni Bayi), or 'none'.
 *
 * Filter contracts:
 *   - mhm_rentiva_vendor_completed_bookings_count(int $count, int $user_id):
 *       Inject completed-booking count for this vendor. Default 0; production
 *       wiring delegates to ReliabilityScoreCalculator::count_completed_bookings().
 *   - mhm_rentiva_vendor_badge_eligibility(null|true|false $override, int $user_id, array $context):
 *       Force the result. Return true for STATUS_VERIFIED, false for STATUS_NONE,
 *       null (or any other value) to defer to natural threshold evaluation.
 *       $context contains 'age_days', 'score', 'completed' for inspection.
 *
 * @since 4.37.0
 */
final class VendorBadgeEligibility
{
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_NEW = 'new';
    public const STATUS_NONE = 'none';

    public static function evaluate(int $user_id): string
    {
        if ($user_id <= 0) {
            return self::STATUS_NONE;
        }

        $approved_at = (string) get_user_meta($user_id, '_rentiva_vendor_approved_at', true);
        if ($approved_at === '') {
            $user = get_userdata($user_id);
            $approved_at = $user ? (string) $user->user_registered : '';
        }
        $approved_ts = $approved_at !== '' ? strtotime($approved_at) : false;
        $age_days = $approved_ts !== false
            ? (int) floor((time() - $approved_ts) / DAY_IN_SECONDS)
            : 0;

        $score = (int) get_user_meta($user_id, '_rentiva_vendor_reliability_score', true);
        $completed = (int) apply_filters(
            'mhm_rentiva_vendor_completed_bookings_count',
            0,  // default; in production replaced by ReliabilityScoreCalculator helper
            $user_id
        );

        $min_age = (int) SettingsCore::get('vendor_badge_min_age_days', 180);
        $min_score = (int) SettingsCore::get('vendor_badge_min_score', 80);
        $min_bookings = (int) SettingsCore::get('vendor_badge_min_completed_bookings', 10);

        $verified = $age_days >= $min_age && $score >= $min_score && $completed >= $min_bookings;

        $override = apply_filters('mhm_rentiva_vendor_badge_eligibility', null, $user_id, [
            'age_days'  => $age_days,
            'score'     => $score,
            'completed' => $completed,
        ]);
        if ($override === true) {
            return self::STATUS_VERIFIED;
        }
        if ($override === false) {
            return self::STATUS_NONE;
        }

        return $verified ? self::STATUS_VERIFIED : self::STATUS_NEW;
    }
}
