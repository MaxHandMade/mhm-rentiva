<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

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
			<div class="stat-item stat-item--monthly">
				<div class="stat-icon"><span class="dashicons dashicons-airplane"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $stats['monthly'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Monthly', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<div class="stat-item stat-item--count">
				<div class="stat-icon"><span class="dashicons dashicons-chart-line"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $stats['total'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Total', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<div class="stat-item stat-item--revenue">
				<div class="stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( number_format( (float) ( $stats['revenue'] ?? 0 ), 2 ) ); ?> <?php echo esc_html( $currency_symbol ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Total Revenue', 'mhm-rentiva' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Recent Routes -->
		<div class="transfer-routes-section">
			<h4 class="transfer-routes-title"><?php echo esc_html__( 'Recent Transfer Routes', 'mhm-rentiva' ); ?></h4>

			<?php if ( ! empty( $stats['recent_routes'] ) ) : ?>
				<div class="transfer-route-list">
					<?php
                    foreach ( $stats['recent_routes'] as $route ) :
						$pickup_ts     = ! empty( $route['pickup_date'] ) ? strtotime( $route['pickup_date'] ) : 0;
						$pickup_date   = $pickup_ts ? date_i18n( get_option( 'date_format' ), $pickup_ts ) : '-';
						$pickup_time   = ! empty( $route['pickup_time'] ) ? esc_html( $route['pickup_time'] ) : '';
						$origin        = $route['origin_name']   ?: __( 'Unknown', 'mhm-rentiva' );
						$dest          = $route['dest_name']     ?: __( 'Unknown', 'mhm-rentiva' );
						$status        = $route['status']        ?? '';
						$booking_url   = admin_url( 'post.php?post=' . (int) $route['ID'] . '&action=edit' );
						$vehicle_name  = $route['vehicle_title'] ?? '';
						$vehicle_plate = $route['vehicle_plate'] ?? '';
						?>
					<a href="<?php echo esc_url( $booking_url ); ?>" class="transfer-route-card status-<?php echo esc_attr( $status ); ?>">
						<div class="transfer-route-card__route">
							<span class="dashicons dashicons-airplane trc-type-icon"></span>
							<span class="trc-origin"><?php echo esc_html( $origin ); ?></span>
							<span class="trc-arrow"><span class="dashicons dashicons-arrow-right-alt"></span></span>
							<span class="trc-dest"><?php echo esc_html( $dest ); ?></span>
						</div>
						<?php if ( $vehicle_name ) : ?>
						<div class="transfer-route-card__vehicle">
							<span class="dashicons dashicons-car"></span>
							<?php echo esc_html( $vehicle_name ); ?>
							<?php if ( $vehicle_plate ) : ?>
								<span class="trc-plate"><?php echo esc_html( $vehicle_plate ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<div class="transfer-route-card__meta">
							<span class="trc-id">#<?php echo esc_html( mhm_rentiva_get_display_id( (int) $route['ID'] ) ); ?></span>
							<span class="trc-datetime">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php echo esc_html( $pickup_date ); ?>
								<?php if ( $pickup_time ) : ?>
									<span class="trc-time"><?php echo esc_html( $pickup_time ); ?></span>
								<?php endif; ?>
							</span>
							<?php if ( $status ) : ?>
								<span class="trc-status-badge trc-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
							<?php endif; ?>
						</div>
					</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="no-data"><?php echo esc_html__( 'No transfer bookings found.', 'mhm-rentiva' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=vehicle_booking&mhm_booking_type=transfer' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'View All Transfers', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>
