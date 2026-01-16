<?php
if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Booking\Core\Status;
?>
<div class="mhm-rentiva-booking-report">
    <div class="overview-header">
        <h2><?php echo esc_html__('Booking Report', 'mhm-rentiva'); ?></h2>
        <p class="overview-description"><?php printf(
                                            /* translators: 1: %s; 2: %s. */
                                            esc_html__('Booking analysis and status distribution for %1$s to %2$s', 'mhm-rentiva'),
                                            date('d.m.Y', strtotime($start_date)),
                                            date('d.m.Y', strtotime($end_date))
                                        ); ?></p>
    </div>

    <div class="overview-cards-grid">
        <!-- Total Bookings Card -->
        <div class="analytics-card revenue-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Total Bookings', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">📅</span>
            </div>
            <div class="card-content">
                <div class="analytics-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Total Bookings</span>
                        <span class="metric-value"><?php echo number_format((int)($data['total_bookings'] ?? 0)); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Cancellation Rate</span>
                        <span class="metric-value">%<?php echo ($data['cancellation_rate'] ?? 0); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Status Distribution Card -->
        <div class="analytics-card bookings-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Status Distribution', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">📊</span>
            </div>
            <div class="card-content">
                <div class="analytics-chart">
                    <div class="chart-bars">
                        <?php
                        $status_distribution = $data['status_distribution'] ?? [];
                        $booking_statuses = [
                            'confirmed' => 0,
                            'pending' => 0,
                            'cancelled' => 0,
                            'completed' => 0
                        ];

                        if (!empty($status_distribution)) {
                            foreach ($status_distribution as $status) {
                                $status_name = strtolower($status->status ?? '');
                                if (isset($booking_statuses[$status_name])) {
                                    $booking_statuses[$status_name] = (int)$status->count;
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
            </div>
        </div>
    </div>

    <!-- Detail table -->
    <div class="data-table-container">
        <h3><?php echo esc_html__('Status Distribution Details', 'mhm-rentiva'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Status', 'mhm-rentiva'); ?></th>
                    <th><?php echo esc_html__('Count', 'mhm-rentiva'); ?></th>
                    <th><?php echo esc_html__('Percentage', 'mhm-rentiva'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['status_distribution'] as $status) :
                    $percentage = $data['total_bookings'] > 0 ? round(($status->count / $data['total_bookings']) * 100, 1) : 0;
                ?>
                    <tr>
                        <td><?php echo esc_html(Status::get_label($status->status)); ?></td>
                        <td><?php echo number_format((int)$status->count); ?></td>
                        <td>%<?php echo $percentage; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>