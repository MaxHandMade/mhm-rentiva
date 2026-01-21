<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mhm-rentiva-customer-report">
    <div class="overview-header">
        <h2><?php echo esc_html__('Customer Report', 'mhm-rentiva'); ?></h2>
        <p class="overview-description"><?php printf(
                                            /* translators: 1: %s; 2: %s. */
                                            esc_html__('Customer analysis and spending patterns for %1$s to %2$s', 'mhm-rentiva'),
                                            wp_date('d.m.Y', strtotime($start_date)),
                                            wp_date('d.m.Y', strtotime($end_date))
                                        ); ?></p>
    </div>

    <div class="overview-cards-grid">
        <!-- Customer Summary Card -->
        <div class="analytics-card customers-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Customer Summary', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">👥</span>
            </div>
            <div class="card-content">
                <div class="analytics-metrics">
                    <div class="metric-row">
                        <span class="metric-label">Total Customers</span>
                        <span class="metric-value"><?php echo number_format((int)$customer_segments['total']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Repeat Customers</span>
                        <span class="metric-value"><?php echo number_format((int)$customer_segments['returning']); ?></span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Average Spending</span>
                        <?php $avg_spending = !empty($real_customers) ? array_sum(array_column($real_customers, 'total_spent')) / count($real_customers) : 0; ?>
                        <span class="metric-value"><?php echo number_format((float)$avg_spending, 2, ',', '.') . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Lifecycle Card -->
        <div class="analytics-card customers-analytics">
            <div class="card-header">
                <h3><?php echo esc_html__('Customer Lifecycle', 'mhm-rentiva'); ?></h3>
                <span class="card-icon">📈</span>
            </div>
            <div class="card-content">
                <div class="analytics-chart">
                    <div class="chart-bars">
                        <?php
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
            </div>
        </div>
    </div>

    <!-- Detail table -->
    <div class="data-table-container">
        <h3><?php echo esc_html__('Top Spending Customers', 'mhm-rentiva'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Customer', 'mhm-rentiva'); ?></th>
                    <th><?php echo esc_html__('Booking Count', 'mhm-rentiva'); ?></th>
                    <th><?php echo esc_html__('Total Spending', 'mhm-rentiva'); ?></th>
                    <th><?php echo esc_html__('Last Booking', 'mhm-rentiva'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($real_customers)) {
                    foreach ($real_customers as $customer) {
                ?>
                        <tr>
                            <td><?php echo esc_html($customer->customer_name); ?><br><small><?php echo esc_html($customer->customer_email); ?></small></td>
                            <td><?php echo number_format((int)$customer->booking_count); ?></td>
                            <td><?php echo number_format((float)$customer->total_spent, 2, ',', '.') . \MHMRentiva\Admin\Reports\Reports::get_currency_symbol(); ?></td>
                            <td><?php echo esc_html(wp_date('d.m.Y', strtotime($customer->last_booking))); ?></td>
                        </tr>
                <?php
                    }
                } else {
                    echo '<tr><td colspan="4">' . esc_html__('No customer data found.', 'mhm-rentiva') . '</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>