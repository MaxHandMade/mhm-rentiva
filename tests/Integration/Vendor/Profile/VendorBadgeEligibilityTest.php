<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vendor\Profile\VendorBadgeEligibility;
use MHMRentiva\Admin\Core\MetaKeys;

/**
 * @group vendor-profile
 * @group vendor-badge
 */
final class VendorBadgeEligibilityTest extends \WP_UnitTestCase
{
    private const STATUS_VERIFIED = 'verified';
    private const STATUS_NEW = 'new';
    private const STATUS_NONE = 'none';

    private function make_vendor(int $age_days, int $score, int $completed_bookings): int
    {
        $user_id = self::factory()->user->create();
        $registered = gmdate('Y-m-d H:i:s', strtotime('-' . $age_days . ' days'));
        update_user_meta($user_id, '_rentiva_vendor_approved_at', $registered);
        update_user_meta($user_id, '_rentiva_vendor_reliability_score', $score);
        // Mock completed bookings count via filter
        add_filter('mhm_rentiva_vendor_completed_bookings_count', function ($count, $vid) use ($user_id, $completed_bookings) {
            return $vid === $user_id ? $completed_bookings : $count;
        }, 10, 2);
        return $user_id;
    }

    public function test_verified_when_all_thresholds_met(): void
    {
        $user_id = $this->make_vendor(200, 85, 15);

        $status = VendorBadgeEligibility::evaluate($user_id);

        $this->assertSame(self::STATUS_VERIFIED, $status);
    }

    public function test_new_badge_when_age_below_threshold(): void
    {
        $user_id = $this->make_vendor(60, 85, 15);

        $this->assertSame(self::STATUS_NEW, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_new_badge_when_score_below_threshold(): void
    {
        $user_id = $this->make_vendor(200, 70, 15);

        $this->assertSame(self::STATUS_NEW, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_new_badge_when_bookings_below_threshold(): void
    {
        $user_id = $this->make_vendor(200, 85, 5);

        $this->assertSame(self::STATUS_NEW, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_settings_override_changes_threshold(): void
    {
        $existing = get_option('mhm_rentiva_settings', []);
        update_option(
            'mhm_rentiva_settings',
            array_merge(is_array($existing) ? $existing : [], ['vendor_badge_min_age_days' => 30])
        );
        $user_id = $this->make_vendor(45, 85, 15);

        $this->assertSame(self::STATUS_VERIFIED, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_at_exact_threshold_qualifies_as_verified(): void
    {
        $user_id = $this->make_vendor(180, 80, 10);  // exactly the defaults

        $this->assertSame(self::STATUS_VERIFIED, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_filter_override_can_force_verified(): void
    {
        $user_id = $this->make_vendor(10, 50, 0);  // would be NEW
        add_filter('mhm_rentiva_vendor_badge_eligibility', function ($value, $vid) use ($user_id) {
            return $vid === $user_id ? true : $value;
        }, 10, 2);

        $this->assertSame(self::STATUS_VERIFIED, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_new_badge_when_no_approved_at_meta(): void
    {
        $user_id = self::factory()->user->create(['user_registered' => gmdate('Y-m-d H:i:s', strtotime('-30 days'))]);
        update_user_meta($user_id, '_rentiva_vendor_reliability_score', 100);

        $this->assertSame(self::STATUS_NEW, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_corrupt_approved_at_does_not_falsely_verify(): void
    {
        $user_id = self::factory()->user->create();
        update_user_meta($user_id, '_rentiva_vendor_approved_at', 'not-a-valid-date');
        update_user_meta($user_id, '_rentiva_vendor_reliability_score', 100);
        add_filter('mhm_rentiva_vendor_completed_bookings_count', function ($count, $vid) use ($user_id) {
            return $vid === $user_id ? 99 : $count;
        }, 10, 2);

        $this->assertSame(self::STATUS_NEW, VendorBadgeEligibility::evaluate($user_id));
    }

    public function test_returns_none_for_zero_user_id(): void
    {
        $this->assertSame(self::STATUS_NONE, VendorBadgeEligibility::evaluate(0));
    }

    public function test_filter_receives_context_array_with_age_score_completed(): void
    {
        $user_id = $this->make_vendor(120, 75, 7);
        $captured = null;
        add_filter('mhm_rentiva_vendor_badge_eligibility', function ($value, $vid, $context) use ($user_id, &$captured) {
            if ($vid === $user_id) {
                $captured = $context;
            }
            return $value;
        }, 10, 3);

        VendorBadgeEligibility::evaluate($user_id);

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('age_days', $captured);
        $this->assertArrayHasKey('score', $captured);
        $this->assertArrayHasKey('completed', $captured);
        $this->assertSame(75, $captured['score']);
        $this->assertSame(7, $captured['completed']);
        $this->assertGreaterThanOrEqual(119, $captured['age_days']);
        $this->assertLessThanOrEqual(120, $captured['age_days']);
    }
}
