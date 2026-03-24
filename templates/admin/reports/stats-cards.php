<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="mhm-stats-cards">
	<div class="stats-grid">
		<!-- Total bookings -->
		<div class="stat-card stat-card-total-bookings">
			<div class="stat-icon">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( $stats['total_bookings'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Total Bookings', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( $stats['total_bookings'] ); ?> <?php esc_html_e( 'Total', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Monthly Revenue -->
		<div class="stat-card stat-card-monthly-revenue">
			<div class="stat-icon">
				<span class="dashicons dashicons-money-alt"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( $stats['monthly_revenue'] ); ?><?php echo esc_html( $currency_symbol ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'This Month Revenue', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( $stats['monthly_revenue'] ); ?><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'This month', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Active bookings -->
		<div class="stat-card stat-card-active-bookings">
			<div class="stat-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( $stats['active_bookings'] ); ?></div>
				<div class="stat-label"><?php esc_html_e( 'Active Reservations', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( $stats['active_bookings'] ); ?> <?php esc_html_e( 'Ongoing', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Occupancy Rate -->
		<div class="stat-card stat-card-occupancy-rate">
			<div class="stat-icon">
				<span class="dashicons dashicons-chart-pie"></span>
			</div>
			<div class="stat-content">
				<div class="stat-number"><?php echo esc_html( $stats['occupancy_rate'] ); ?>%</div>
				<div class="stat-label"><?php esc_html_e( 'Occupancy Rate', 'mhm-rentiva' ); ?></div>
				<div class="stat-trend">
					<span class="trend-text"><?php echo esc_html( $stats['occupancy_rate'] ); ?>% <?php esc_html_e( 'Capacity', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>
	</div>
</div>
