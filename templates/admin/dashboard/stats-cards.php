<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Stats Cards Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$metrics         = $args['metrics'] ?? array();
$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
?>

<div class="mhm-stats-cards">
	<div class="stats-grid">
		<!-- Monthly Bookings -->
		<div class="stat-card stat-card-total-bookings">
			<div class="stat-icon">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( $metrics['bookings_this_month'] ?? 0 ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Monthly Bookings', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( $metrics['total_bookings'] ?? 0 ); ?> <?php esc_html_e( 'total', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Monthly Revenue -->
		<div class="stat-card stat-card-total-revenue">
			<div class="stat-icon">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( number_format( (float) ( $metrics['monthly_revenue'] ?? 0 ), 2 ) ); ?> <?php echo esc_html( $currency_symbol ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Monthly Revenue', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( number_format( (float) ( $metrics['total_revenue'] ?? 0 ), 2 ) ); ?> <?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'total', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Total Vehicles -->
		<div class="stat-card stat-card-total-vehicles">
			<div class="stat-icon">
				<span class="dashicons dashicons-car"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( $metrics['total_vehicles'] ?? 0 ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Total Vehicles', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( $metrics['available_vehicles'] ?? 0 ); ?> <?php esc_html_e( 'available', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Monthly Customers -->
		<div class="stat-card stat-card-total-customers">
			<div class="stat-icon">
				<span class="dashicons dashicons-admin-users"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( $metrics['total_customers_this_month'] ?? 0 ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Monthly Customers', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( $metrics['total_customers_all_time'] ?? 0 ); ?> <?php esc_html_e( 'total', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>