<?php

/**
 * Dashboard Customer Statistics Template
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

/** @var array $args */
$stats           = $args['customer_stats'] ?? array();
$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
?>

<div class="mhm-dashboard-widget">
	<h3><span class="dashicons dashicons-admin-users"></span> <?php echo esc_html__('Customer Statistics', 'mhm-rentiva'); ?></h3>
	<div class="widget-content">
		<div class="customer-stats-grid">
			<!-- Total Customers -->
			<div class="stat-item">
				<div class="stat-icon"><span class="dashicons dashicons-admin-users"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html($stats['total'] ?? 0); ?></div>
					<div class="stat-label"><?php echo esc_html__('Total Customers', 'mhm-rentiva'); ?></div>
				</div>
			</div>

			<!-- New Customers (This Month) -->
			<div class="stat-item">
				<div class="stat-icon"><span class="dashicons dashicons-plus-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html($stats['new_this_month'] ?? 0); ?></div>
					<div class="stat-label"><?php echo esc_html__('New This Month', 'mhm-rentiva'); ?></div>
				</div>
			</div>

			<!-- Active Customers -->
			<div class="stat-item">
				<div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html($stats['active'] ?? 0); ?></div>
					<div class="stat-label"><?php echo esc_html__('Active Customers', 'mhm-rentiva'); ?></div>
				</div>
			</div>

			<!-- Average Spending -->
			<div class="stat-item">
				<div class="stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html($stats['avg_spending'] ?? '0.00'); ?> <?php echo esc_html($currency_symbol); ?></div>
					<div class="stat-label"><?php echo esc_html__('Avg. Spending', 'mhm-rentiva'); ?></div>
				</div>
			</div>
		</div>

		<div class="widget-footer">
			<a href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-customers')); ?>" class="button button-secondary"><?php echo esc_html__('All Customers', 'mhm-rentiva'); ?></a>
		</div>
	</div>
</div>