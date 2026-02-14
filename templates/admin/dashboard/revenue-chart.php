<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Revenue Chart Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$revenue_data    = $args['revenue_data'] ?? array();
$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
?>

<div class="mhm-dashboard-widget">
	<h3><span class="dashicons dashicons-chart-area"></span> <?php echo esc_html__( 'Revenue Trend (Last 14 Days)', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<div class="mhm-rentiva-chart-container">
			<canvas id="revenue-chart-canvas" width="400" height="200"></canvas>
		</div>

		<div class="revenue-summary">
			<div class="summary-item">
				<span class="summary-label"><?php echo esc_html__( 'This Week:', 'mhm-rentiva' ); ?></span>
				<span class="summary-value"><?php echo esc_html( number_format( (float) ( $revenue_data['weekly_total'] ?? 0 ), 2 ) ); ?> <?php echo esc_html( $currency_symbol ); ?></span>
			</div>
			<div class="summary-item">
				<span class="summary-label"><?php echo esc_html__( 'Last Week:', 'mhm-rentiva' ); ?></span>
				<span class="summary-value"><?php echo esc_html( number_format( (float) ( $revenue_data['last_weekly_total'] ?? 0 ), 2 ) ); ?> <?php echo esc_html( $currency_symbol ); ?></span>
			</div>
		</div>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mhm-rentiva-reports' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'Detailed Reports', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>