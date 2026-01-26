<?php

/**
 * Dashboard Transfer Summary Widget Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$stats           = $args['transfer_stats'] ?? array();
$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();
?>

<div class="mhm-dashboard-widget">
	<h3><span class="dashicons dashicons-airplane"></span> <?php echo esc_html__( 'Transfer Summary', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<!-- Quick Stats Row -->
		<div class="message-stats-grid">
			<div class="stat-item total" style="background: linear-gradient(135deg, #FF9966 0%, #FF5E62 100%);">
				<div class="stat-icon"><span class="dashicons dashicons-airplane"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $stats['monthly'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Monthly', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<div class="stat-item total" style="background: linear-gradient(135deg, #56CCF2 0%, #2F80ED 100%);">
				<div class="stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $stats['total'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Total', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<div class="stat-item total" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
				<div class="stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( number_format( (float) ( $stats['revenue'] ?? 0 ), 2 ) ); ?> <?php echo esc_html( $currency_symbol ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Revenue', 'mhm-rentiva' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Recent Routes List -->
		<div class="recent-messages">
			<h4><?php echo esc_html__( 'Recent Transfer Routes', 'mhm-rentiva' ); ?></h4>
			<?php if ( ! empty( $stats['recent_routes'] ) ) : ?>
				<ul class="message-list">
					<?php foreach ( $stats['recent_routes'] as $route ) : ?>
						<li class="message-item" style="border-left: 4px solid var(--mhm-primary);">
							<div class="message-header">
								<span class="customer-name">
									<?php echo esc_html( $route['origin_name'] ?: __( 'Unknown', 'mhm-rentiva' ) ); ?>
									&rarr;
									<?php echo esc_html( $route['dest_name'] ?: __( 'Unknown', 'mhm-rentiva' ) ); ?>
								</span>
								<span class="message-date"><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $route['post_date'] ) ) ); ?></span>
							</div>
							<div class="message-preview">
								<small>Booking ID: #<?php echo esc_html( $route['ID'] ); ?></small>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="no-data"><?php echo esc_html__( 'No transfer bookings found.', 'mhm-rentiva' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=vehicle_booking&mhm_booking_type=transfer' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'View All Transfers', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>