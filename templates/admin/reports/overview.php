<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mhm-rentiva-overview">
    <div class="overview-header">
        <h2><?php echo esc_html__('Overview Dashboard', 'mhm-rentiva'); ?></h2>
        <p class="overview-description"><?php printf(
                                            /* translators: 1: %s; 2: %s. */
                                            esc_html__('Key metrics and trends for %1$s to %2$s', 'mhm-rentiva'),
                                            wp_date('d.m.Y', strtotime($start_date)),
                                            wp_date('d.m.Y', strtotime($end_date))
                                        ); ?></p>
    </div>

    <div class="overview-cards-grid">
        <!-- Revenue Analytics Card -->
        <div class="analytics-card revenue-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Revenue Analytics', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">📊</span>
            </div>
            <div class="card-content">
                <div class="analytics-chart">
                    <div class="chart-bars">
                        <?php
                        $daily_revenue = [];
                        $max_revenue = 1;

                        if (isset($revenue_data['daily'])) {
                            $raw_data = $revenue_data['daily'];
                            if (is_object($raw_data)) {
                                $daily_revenue = json_decode(json_encode($raw_data), true);
                            } elseif (is_array($raw_data)) {
                                $daily_revenue = $raw_data;
                            }

                            if (!empty($daily_revenue) && is_array($daily_revenue)) {
                                $revenues = [];
                                foreach ($daily_revenue as $item) {
                                    if (is_array($item) && isset($item['revenue'])) {
                                        $revenues[] = $item['revenue'];
                                    } elseif (is_object($item) && isset($item->revenue)) {
                                        $revenues[] = $item->revenue;
                                    }
                                }
                                if (!empty($revenues)) {
                                    $max_revenue = max($revenues);
                                }
                            }
                        }

                        for ($i = 0; $i < 7; $i++) {
                            $day_revenue = 0;
                            if (!empty($daily_revenue) && is_array($daily_revenue) && isset($daily_revenue[$i])) {
                                $item = $daily_revenue[$i];
                                if (is_array($item) && isset($item['revenue'])) {
                                    $day_revenue = $item['revenue'];
                                } elseif (is_object($item) && isset($item->revenue)) {
                                    $day_revenue = $item->revenue;
                                }
                            }

                            $bar_height = $max_revenue > 0 ? ($day_revenue / $max_revenue) * 100 : 0;
                            $min_height = $day_revenue > 0 ? 20 : 5;
                            $final_height = max($bar_height, $min_height);

                            echo '<div class="chart-bar" style="height: ' . $final_height . '%;">';
                            echo '<div class="bar-value">' . number_format((float)$day_revenue, 0, ',', '.') . '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div class="chart-labels">
                        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                    </div>
                </div>
                <div class="analytics-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Total Revenue</span>
                        <span class="metric-value"><?php echo number_format((float)($revenue_data['total'] ?? 0), 0, ',', '.') . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Daily Average</span>
                        <span class="metric-value"><?php echo number_format((float)($revenue_data['avg_daily'] ?? 0), 0, ',', '.') . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bookings Analytics Card -->
        <div class="analytics-card bookings-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Bookings Analytics', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">📈</span>
            </div>
            <div class="card-content">
                <div class="analytics-chart">
                    <div class="chart-bars">
                        <?php
                        $booking_statuses = [
                            'confirmed' => 0,
                            'pending' => 0,
                            'cancelled' => 0,
                            'completed' => 0
                        ];

                        if (is_array($booking_data) && isset($booking_data['status_distribution'])) {
                            foreach ($booking_data['status_distribution'] as $status_item) {
                                if (is_object($status_item) && isset($status_item->status) && isset($status_item->count)) {
                                    $status = $status_item->status;
                                    if (isset($booking_statuses[$status])) {
                                        $booking_statuses[$status] = (int)$status_item->count;
                                    }
                                }
                            }
                        }

                        $max_bookings = max($booking_statuses) ?: 1;

                        foreach ($booking_statuses as $status => $count) {
                            $bar_height = $max_bookings > 0 ? ($count / $max_bookings) * 100 : 0;
                            $min_height = $count > 0 ? 20 : 5;
                            $final_height = max($bar_height, $min_height);
                            echo '<div class="chart-bar ' . $status . '" style="height: ' . $final_height . '%;">';
                            echo '<div class="bar-value">' . $count . '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div class="chart-labels">
                        <span>Confirmed</span><span>Pending</span><span>Cancelled</span><span>Completed</span>
                    </div>
                </div>
                <div class="analytics-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Total Bookings</span>
                        <span class="metric-value"><?php echo number_format((int)($booking_data['total_bookings'] ?? 0)); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Success Rate</span>
                        <span class="metric-value">%<?php echo (100 - ((float)($booking_data['cancellation_rate'] ?? 0))); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Analytics Card -->
        <div class="analytics-card customers-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Customer Analytics', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">👥</span>
            </div>
            <div class="card-content">
                <div class="analytics-chart">
                    <div class="chart-bars">
                        <?php
                        $customer_segments = [
                            'new' => 0,
                            'returning' => 0,
                            'active' => 0,
                            'total' => 0
                        ];

                        if (!empty($real_customers)) {
                            $customer_segments['total'] = count($real_customers);
                            $customer_segments['returning'] = count(array_filter($real_customers, function ($customer) {
                                return $customer->booking_count > 1;
                            }));
                            $customer_segments['new'] = $customer_segments['total'] - $customer_segments['returning'];
                            $customer_segments['active'] = $customer_segments['total'];
                        }

                        $max_customers = max($customer_segments) ?: 1;

                        foreach ($customer_segments as $segment => $count) {
                            $bar_height = $max_customers > 0 ? ($count / $max_customers) * 100 : 0;
                            $min_height = $count > 0 ? 20 : 5;
                            $final_height = max($bar_height, $min_height);
                            echo '<div class="chart-bar ' . $segment . '" style="height: ' . $final_height . '%;">';
                            echo '<div class="bar-value">' . $count . '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div class="chart-labels">
                        <span>New</span><span>Returning</span><span>Active</span><span>Total</span>
                    </div>
                </div>
                <div class="analytics-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Total Customers</span>
                        <span class="metric-value"><?php echo number_format((int)($customer_data['summary']['total_customers'] ?? 0)); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Retention Rate</span>
                        <span class="metric-value">%<?php echo ((float)($customer_data['summary']['retention_rate'] ?? 0)); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehicle Analytics Card -->
        <div class="analytics-card vehicles-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Vehicle Analytics', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">🚗</span>
            </div>
            <div class="card-content">
                <div class="analytics-chart">
                    <div class="chart-bars">
                        <?php
                        $vehicle_performance = [
                            'hatchback' => 0,
                            'sedan' => 0,
                            'suv' => 0,
                        ];

                        if (!empty($vehicle_categories_data)) {
                            foreach ($vehicle_categories_data as $category) {
                                $category_name = strtolower($category->category_name);
                                $booking_count = (int)$category->booking_count;

                                switch ($category_name) {
                                    case 'hatchback':
                                        $vehicle_performance['hatchback'] = $booking_count;
                                        break;
                                    case 'sedan':
                                        $vehicle_performance['sedan'] = $booking_count;
                                        break;
                                    case 'suv':
                                        $vehicle_performance['suv'] = $booking_count;
                                        break;
                                    case 'coupe':
                                        $vehicle_performance['coupe'] = $booking_count; // Added coupe even though not in init array to match logic
                                        break;
                                }
                            }
                        }

                        $max_occupancy = max($vehicle_performance) ?: 1;

                        foreach ($vehicle_performance as $category => $vehicle_count) {
                            $bar_height = $max_occupancy > 0 ? ($vehicle_count / $max_occupancy) * 100 : 0;
                            $min_height = $vehicle_count > 0 ? 20 : 5;
                            $final_height = max($bar_height, $min_height);
                            echo '<div class="chart-bar ' . $category . '" style="height: ' . $final_height . '%;">';
                            echo '<div class="bar-value">' . $vehicle_count . '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <div class="chart-labels">
                        <span>Hatchback</span><span>Sedan</span><span>SUV</span><span>Coupe</span>
                    </div>
                </div>
                <div class="analytics-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Total Vehicles</span>
                        <span class="metric-value"><?php echo number_format((int)($vehicle_data['summary']['total_vehicles'] ?? 0)); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Avg Occupancy</span>
                        <span class="metric-value">%<?php echo ((float)($vehicle_data['summary']['avg_occupancy_rate'] ?? 0)); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>