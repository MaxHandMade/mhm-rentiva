<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vendor\Profile\VendorBadgeEligibility;

/**
 * v4.37.1 regression: badge evaluator must compute completed bookings inline
 * when the filter callback is not registered.
 *
 * Background:
 *   Until v4.37.0, the badge defaulted the completed-bookings count to `0`
 *   when no filter callback was wired. The production wiring lived in
 *   {@see \MHMRentiva\Admin\Vendor\Profile\VendorProfileExtension::register()},
 *   which is only called from the admin-only bucket in
 *   {@see \MHMRentiva\Plugin::initialize_admin_services()}. On public
 *   profile renders (`/vendor/<slug>/`, `is_admin() === false`), the filter
 *   was never wired → completed defaulted to 0 → every vendor stuck on
 *   STATUS_NEW even when their score, age, and bookings all met the bar.
 *
 *   Smoke test on the live `/bayi/<slug>/` URL reproduced the bug for
 *   Akif Yıldız (200d / score 92 / 20 bookings) and Zeynep Demir
 *   (335d / score 88 / 11 bookings) — both showed "Yeni Bayi" instead of
 *   "Onaylı Bayi".
 *
 * Fix:
 *   `VendorBadgeEligibility::evaluate()` now seeds the apply_filters() default
 *   from `ReliabilityScoreCalculator::count_completed_bookings($id, 9999)` so
 *   the badge works out of the box. Filter callbacks may still override the
 *   count, but production no longer depends on the filter being wired.
 *
 * @group vendor-profile
 * @group vendor-badge
 */
final class VendorBadgeDefaultFallbackTest extends \WP_UnitTestCase
{
    public function test_badge_verified_without_any_filter_callback_when_thresholds_met(): void
    {
        // Note: we intentionally do NOT add the filter callback here.
        // This is the production scenario on a frontend request before the
        // v4.37.1 fix.

        $vendor_id  = self::factory()->user->create();
        update_user_meta(
            $vendor_id,
            '_rentiva_vendor_approved_at',
            gmdate('Y-m-d H:i:s', strtotime('-200 days'))
        );
        update_user_meta($vendor_id, '_rentiva_vendor_reliability_score', 92);

        $vehicle_id = wp_insert_post([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Default Fallback Test Vehicle',
        ]);

        for ($i = 0; $i < 12; $i++) {
            $booking_id = wp_insert_post([
                'post_type'   => 'vehicle_booking',
                'post_status' => 'publish',
                'post_title'  => 'Default Fallback Booking ' . $i,
                'post_date'   => gmdate('Y-m-d H:i:s', strtotime('-' . (10 + $i * 3) . ' days')),
            ]);
            update_post_meta($booking_id, '_mhm_vehicle_id', $vehicle_id);
            update_post_meta($booking_id, '_mhm_status', 'completed');
        }

        $status = VendorBadgeEligibility::evaluate($vendor_id);

        $this->assertSame(
            'verified',
            $status,
            'Badge must compute completed bookings inline when no filter callback is wired.'
        );
    }

    public function test_explicit_filter_override_still_wins_over_default_fallback(): void
    {
        $vendor_id  = self::factory()->user->create();
        update_user_meta(
            $vendor_id,
            '_rentiva_vendor_approved_at',
            gmdate('Y-m-d H:i:s', strtotime('-200 days'))
        );
        update_user_meta($vendor_id, '_rentiva_vendor_reliability_score', 92);

        $vehicle_id = wp_insert_post([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Override Test Vehicle',
        ]);
        for ($i = 0; $i < 12; $i++) {
            $booking_id = wp_insert_post([
                'post_type'   => 'vehicle_booking',
                'post_status' => 'publish',
                'post_title'  => 'Override Booking ' . $i,
            ]);
            update_post_meta($booking_id, '_mhm_vehicle_id', $vehicle_id);
            update_post_meta($booking_id, '_mhm_status', 'completed');
        }

        // Force completed count to 0 via filter, simulating an override that
        // disqualifies the vendor (e.g. a lifecycle penalty that should
        // suppress the badge regardless of raw booking count).
        add_filter(
            'mhm_rentiva_vendor_completed_bookings_count',
            static function () {
                return 0;
            },
            20,
            2
        );

        $status = VendorBadgeEligibility::evaluate($vendor_id);

        $this->assertSame(
            'new',
            $status,
            'Explicit filter override must win over the default inline calculation.'
        );
    }
}
