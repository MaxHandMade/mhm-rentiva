<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Recent Bookings Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $args */
$bookings      = $args['bookings']      ?? array();
$booking_stats = $args['booking_stats'] ?? array();
?>

<div class="mhm-dashboard-widget">
	<h3><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html__( 'Recent Bookings', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">

		<!-- Stats Cards -->
		<div class="message-stats-grid">
			<div class="stat-item stat-item--monthly">
				<div class="stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $booking_stats['monthly'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Monthly', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<div class="stat-item stat-item--count">
				<div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $booking_stats['confirmed'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Confirmed', 'mhm-rentiva' ); ?></div>
				</div>
			</div>

			<div class="stat-item stat-item--revenue">
				<div class="stat-icon"><span class="dashicons dashicons-clock"></span></div>
				<div class="stat-info">
					<div class="stat-number"><?php echo esc_html( $booking_stats['pending'] ?? 0 ); ?></div>
					<div class="stat-label"><?php echo esc_html__( 'Pending', 'mhm-rentiva' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Recent Bookings List -->
		<div class="transfer-routes-section">
			<h4 class="transfer-routes-title"><?php echo esc_html__( 'Recent Bookings', 'mhm-rentiva' ); ?></h4>

			<?php if ( ! empty( $bookings ) ) : ?>
				<div class="transfer-route-list">
					<?php
                    foreach ( $bookings as $booking ) :
						$status       = $booking['status']       ?? 'pending';
						$type         = $booking['booking_type'] ?? 'rental';
						$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label( $status );
						$status_class = 'status-' . $status;
						$type_icon    = ( 'transfer' === $type ) ? 'dashicons-airplane' : 'dashicons-car';
						$customer     = ! empty( $booking['customer_name'] ) ? $booking['customer_name'] : __( 'Guest', 'mhm-rentiva' );
						$phone        = $booking['customer_phone'] ?? '';
						$vehicle      = $booking['vehicle_title']  ?? '';
						$plate        = $booking['vehicle_plate']  ?? '';
						$pickup_ts    = ! empty( $booking['pickup_date'] ) ? strtotime( $booking['pickup_date'] ) : 0;
						$pickup_date  = $pickup_ts ? date_i18n( 'd M Y', $pickup_ts ) : '-';
						$pickup_time  = $booking['pickup_time'] ?? '';
						$booking_url  = admin_url( 'post.php?post=' . (int) $booking['id'] . '&action=edit' );
						?>
					<a href="<?php echo esc_url( $booking_url ); ?>" class="transfer-route-card <?php echo esc_attr( $status_class ); ?>">
						<div class="transfer-route-card__route">
							<span class="trc-origin">
								<span class="dashicons <?php echo esc_attr( $type_icon ); ?> trc-type-icon"></span>
								<?php echo esc_html( $customer ); ?>
								<?php if ( $phone ) : ?>
									<span class="trc-phone-inline"><?php echo esc_html( $phone ); ?></span>
								<?php endif; ?>
							</span>
							<span class="trc-booking-status">
								<span class="status-badge status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
							</span>
						</div>
						<?php if ( $vehicle ) : ?>
						<div class="transfer-route-card__vehicle">
							<span class="dashicons dashicons-car"></span>
							<?php echo esc_html( $vehicle ); ?>
							<?php if ( $plate ) : ?>
								<span class="trc-plate"><?php echo esc_html( $plate ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<div class="transfer-route-card__meta">
							<span class="trc-id">#<?php echo esc_html( mhm_rentiva_get_display_id( (int) $booking['id'] ) ); ?></span>
							<span class="trc-datetime">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php echo esc_html( $pickup_date ); ?>
								<?php if ( $pickup_time ) : ?>
									<span class="trc-time"><?php echo esc_html( $pickup_time ); ?></span>
								<?php endif; ?>
							</span>
						</div>
					</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="no-data"><?php echo esc_html__( 'No bookings found yet.', 'mhm-rentiva' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=vehicle_booking' ) ); ?>" class="button button-secondary">
				<?php echo esc_html__( 'View All Bookings', 'mhm-rentiva' ); ?>
			</a>
		</div>
	</div>
</div>
