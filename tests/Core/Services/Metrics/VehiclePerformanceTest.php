<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Services\Metrics;

use MHMRentiva\Core\Financial\AnalyticsService;

final class VehiclePerformanceTest extends \WP_UnitTestCase
{
    private int $vendor_id;
    private int $vehicle_1_id;
    private int $vehicle_2_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->vendor_id = $this->factory->user->create(array('role' => 'rentiva_vendor'));

        $this->vehicle_1_id = $this->factory->post->create(
            array(
                'post_type'   => 'vehicle',
                'post_author' => $this->vendor_id,
            )
        );

        $this->vehicle_2_id = $this->factory->post->create(
            array(
                'post_type'   => 'vehicle',
                'post_author' => $this->vendor_id,
            )
        );
    }

    public function test_get_vehicle_performance_calculates_revenue()
    {
        $now = time();
        $from = $now - (7 * DAY_IN_SECONDS);

        $this->insert_ledger_entry($this->vendor_id, $this->vehicle_1_id, 1000.0, 'commission_credit', 'cleared', $now - DAY_IN_SECONDS);
        $this->insert_ledger_entry($this->vendor_id, $this->vehicle_1_id, 500.0, 'commission_credit', 'cleared', $now - (2 * DAY_IN_SECONDS));
        $this->insert_ledger_entry($this->vendor_id, $this->vehicle_1_id, -200.0, 'commission_refund', 'cleared', $now - (3 * DAY_IN_SECONDS));

        $this->insert_ledger_entry($this->vendor_id, $this->vehicle_2_id, 2000.0, 'commission_credit', 'cleared', $now - DAY_IN_SECONDS);

        $performance = AnalyticsService::get_vehicle_performance($this->vehicle_1_id, $from, $now);

        $this->assertIsArray($performance);
        $this->assertEquals(1300.0, $performance['revenue_period']);
    }

    public function test_get_vehicle_performance_calculates_occupancy_and_cancellation()
    {
        $now = time();
        // Set a fixed 10-day window for easy percentages
        $window_days = 10;
        $from = $now - ($window_days * DAY_IN_SECONDS);

        // Booking 1: 5 days within the window (Completed) -> Occupancy 50%
        $this->insert_booking_entry($this->vehicle_1_id, 'completed', $from, $from + (5 * DAY_IN_SECONDS));

        // Booking 2: Cancelled within the window -> Cancellation 1
        $this->insert_booking_entry($this->vehicle_1_id, 'cancelled', $from + (6 * DAY_IN_SECONDS), $from + (8 * DAY_IN_SECONDS));

        $performance = AnalyticsService::get_vehicle_performance($this->vehicle_1_id, $from, $now);

        $this->assertEquals(50.0, $performance['occupancy_rate']);
        $this->assertEquals(1, $performance['cancellation_count']);

        // 2 total reservations bounded by the window. 1 is cancelled. 1/2 = 50%
        $this->assertEquals(50.0, $performance['cancellation_rate']);
    }

    public function test_get_vendor_operational_metrics()
    {
        $now = time();
        // Set a fixed 10-day window
        $window_days = 10;
        $from = $now - ($window_days * DAY_IN_SECONDS);

        // Vendor has 2 vehicles, total available days = 20
        // Vehicle 1 has 5 days booked
        $this->insert_booking_entry($this->vehicle_1_id, 'completed', $from, $from + (5 * DAY_IN_SECONDS));

        // Vehicle 2 has 3 days booked and 1 cancellation
        $this->insert_booking_entry($this->vehicle_2_id, 'completed', $from + DAY_IN_SECONDS, $from + (4 * DAY_IN_SECONDS));
        $this->insert_booking_entry($this->vehicle_2_id, 'cancelled', $from + (5 * DAY_IN_SECONDS), $from + (8 * DAY_IN_SECONDS));

        $metrics = AnalyticsService::get_vendor_operational_metrics($this->vendor_id, $from, $now);

        // Total Booked Days = 8. Total Available = 20. Occupancy = 40%
        $this->assertEquals(40.0, $metrics['occupancy_rate']);

        // Total reservations = 3. Cancellations = 1. Rate = 33.33%
        $this->assertEquals(33.33, $metrics['cancellation_rate']);
    }

    private function insert_ledger_entry(int $vendor_id, int $vehicle_id, float $amount, string $type, string $status, int $created_at_ts): void
    {
        global $wpdb;
        $table_ledger = $wpdb->prefix . 'mhm_rentiva_ledger';

        $booking_id = $this->factory->post->create(array('post_type' => 'vehicle_booking'));
        update_post_meta($booking_id, '_mhm_vehicle_id', $vehicle_id);

        $wpdb->insert(
            $table_ledger,
            array(
                'transaction_uuid' => bin2hex(random_bytes(18)),
                'vendor_id'  => $vendor_id,
                'booking_id' => $booking_id,
                'amount'     => $amount,
                'currency'   => 'TRY',
                'context'    => 'checkout',
                'type'       => $type,
                'status'     => $status,
                'created_at' => gmdate('Y-m-d H:i:s', $created_at_ts),
            )
        );
    }

    private function insert_booking_entry(int $vehicle_id, string $status, int $pickup_ts, int $return_ts): void
    {
        $booking_id = $this->factory->post->create(array('post_type' => 'vehicle_booking'));

        update_post_meta($booking_id, '_mhm_vehicle_id', $vehicle_id);
        update_post_meta($booking_id, '_mhm_status', $status);
        update_post_meta($booking_id, '_mhm_pickup_date', gmdate('Y-m-d H:i:s', $pickup_ts));
        update_post_meta($booking_id, '_mhm_return_date', gmdate('Y-m-d H:i:s', $return_ts));
    }
}
