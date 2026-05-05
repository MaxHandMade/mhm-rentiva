<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vehicle\ReliabilityScoreCalculator;

/**
 * @group vendor-profile
 * @group vendor-badge
 */
final class VendorBookingCountFilterWiringTest extends \WP_UnitTestCase
{
    public function test_filter_helper_returns_completed_bookings_count_for_vendor(): void
    {
        // Simulate production wiring (VendorProfileExtension::register()).
        add_filter(
            'mhm_rentiva_vendor_completed_bookings_count',
            [ReliabilityScoreCalculator::class, 'count_completed_bookings_for_filter'],
            10,
            2
        );

        $vendor_id  = self::factory()->user->create();
        $vehicle_id = wp_insert_post([
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_author' => $vendor_id,
            'post_title'  => 'Wiring Test Vehicle',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $booking_id = wp_insert_post([
                'post_type'   => 'vehicle_booking',
                'post_status' => 'publish',
                'post_title'  => 'Wiring Test Booking ' . $i,
            ]);
            update_post_meta($booking_id, '_mhm_vehicle_id', $vehicle_id);
            update_post_meta($booking_id, '_mhm_status', 'completed');
        }

        $count = apply_filters('mhm_rentiva_vendor_completed_bookings_count', 0, $vendor_id);

        $this->assertSame(3, $count);
    }
}
