<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Dashboard Upcoming Operations Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$per_page = 5;
$result   = \MHMRentiva\Admin\Reports\Repository\ReportRepository::get_upcoming_operations_paginated( 1, $per_page, 7 );
$ops      = $result['items'];
$total    = $result['total'];
$pages    = $result['total_pages'];
?>

<div class="mhm-dashboard-widget mhm-widget--full-width">
	<h3><?php echo esc_html__( 'Upcoming Operations', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<?php if ( $total > 0 ) : ?>
			<table class="wp-list-table widefat fixed striped operations-table" id="mhm-upcoming-ops-table">
				<thead>
					<tr>
						<th class="col-type"><?php echo esc_html__( 'Type', 'mhm-rentiva' ); ?></th>
						<th class="col-id"><?php echo esc_html__( '#ID', 'mhm-rentiva' ); ?></th>
						<th class="col-time"><?php echo esc_html__( 'Time', 'mhm-rentiva' ); ?></th>
						<th class="col-countdown"><?php echo esc_html__( 'Time Left', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Customer', 'mhm-rentiva' ); ?></th>
						<th class="col-phone"><?php echo esc_html__( 'Phone', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Vehicle', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Route / Location', 'mhm-rentiva' ); ?></th>
						<th class="col-status"><?php echo esc_html__( 'Status', 'mhm-rentiva' ); ?></th>
					</tr>
				</thead>
				<tbody id="mhm-upcoming-ops-body">
					<?php
					foreach ( $ops as $op ) :
						$icon      = ( 'transfer' === $op['type'] ) ? 'dashicons-airplane' : 'dashicons-car';
						$date_str  = ! empty( $op['start_time'] )
							? $op['start_date'] . ' ' . $op['start_time']
							: $op['start_date'];
						$date_time = strtotime( $date_str );

						$formatted_date = date_i18n( get_option( 'date_format' ), $date_time );
						$formatted_time = ! empty( $op['start_time'] ) ? esc_html( $op['start_time'] ) : wp_date( 'H:i', $date_time );

						$today    = strtotime( wp_date( 'Y-m-d' ) );
						$tomorrow = strtotime( wp_date( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp' ) ) ) );
						$op_day   = strtotime( wp_date( 'Y-m-d', $date_time ) );

						if ( $op_day === $today ) {
							$day_label = '<strong>' . esc_html__( 'Today', 'mhm-rentiva' ) . '</strong>';
						} elseif ( $op_day === $tomorrow ) {
							$day_label = '<strong>' . esc_html__( 'Tomorrow', 'mhm-rentiva' ) . '</strong>';
						} else {
							$day_label = esc_html( $formatted_date );
						}

						if ( 'transfer' === $op['type'] && ( ! empty( $op['origin'] ) || ! empty( $op['destination'] ) ) ) {
							$route = esc_html( $op['origin'] ?? '-' ) . ' &rarr; ' . esc_html( $op['destination'] ?? '-' );
						} elseif ( 'transfer' === $op['type'] ) {
							$route = '<em class="op-route-unknown">' . esc_html__( 'Transfer', 'mhm-rentiva' ) . '</em>';
						} elseif ( ! empty( $op['vehicle_location'] ) ) {
							$route = '<span class="dashicons dashicons-location op-location-icon"></span> ' . esc_html( $op['vehicle_location'] );
						} else {
							$route = '-';
						}

						$booking_id    = (int) ( $op['id'] ?? 0 );
						$booking_url   = $booking_id ? esc_url( admin_url( 'post.php?post=' . $booking_id . '&action=edit' ) ) : '';
						$vehicle_label = esc_html( $op['vehicle_title'] ?? __( 'VIP Transfer', 'mhm-rentiva' ) );
						if ( ! empty( $op['vehicle_plate'] ) ) {
							$vehicle_label .= ' <small class="op-vehicle-plate">(' . esc_html( $op['vehicle_plate'] ) . ')</small>';
						}

						$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label( $op['status'] );
						$status_class = 'status-' . esc_attr( $op['status'] );

						$countdown_html = '';
						if ( 'confirmed' === $op['status'] ) {
							$diff = $date_time - current_time( 'timestamp' );
							if ( $diff > 0 ) {
								$days    = (int) floor( $diff / DAY_IN_SECONDS );
								$hours   = (int) floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
								$minutes = (int) floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

								if ( $days >= 3 ) {
									$cd_class = 'countdown-green';
									$cd_text  = sprintf( __( '%1$dd %2$dh', 'mhm-rentiva' ), $days, $hours );
								} elseif ( $diff >= DAY_IN_SECONDS ) {
									$cd_class = 'countdown-orange';
									$cd_text  = sprintf( __( '%1$dd %2$dh', 'mhm-rentiva' ), $days, $hours );
								} elseif ( $diff >= HOUR_IN_SECONDS ) {
									$cd_class = 'countdown-red';
									$cd_text  = sprintf( __( '%1$dh %2$dm', 'mhm-rentiva' ), $hours, $minutes );
								} else {
									$cd_class = 'countdown-red';
									$cd_text  = $minutes > 0
										? sprintf( __( '%dm', 'mhm-rentiva' ), $minutes )
										: esc_html__( 'Almost there!', 'mhm-rentiva' );
								}

								$countdown_html = '<span class="op-countdown ' . esc_attr( $cd_class ) . '">' . esc_html( $cd_text ) . '</span>';
							}
						}
						?>
						<tr>
							<td class="op-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?> op-type-icon"></span></td>
							<td>
								<?php if ( $booking_url ) : ?>
									<a href="<?php echo esc_url( $booking_url ); ?>" class="op-booking-link">#<?php echo esc_html( mhm_rentiva_get_display_id( (int) $booking_id ) ); ?></a>
								<?php else : ?>
									-
								<?php endif; ?>
							</td>
							<td><?php echo wp_kses_post( $day_label ); ?><br><small class="op-time-sub"><?php echo esc_html( $formatted_time ); ?></small></td>
							<td><?php echo wp_kses_post( $countdown_html ); ?></td>
							<td><?php echo esc_html( $op['customer_name'] ?: '-' ); ?></td>
							<td><?php echo esc_html( $op['customer_phone'] ?? '-' ); ?></td>
							<td><?php echo wp_kses_post( $vehicle_label ); ?></td>
							<td><?php echo wp_kses_post( $route ); ?></td>
							<td><span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
			<div class="ops-pagination"
				data-current="1"
				data-total="<?php echo esc_attr( $pages ); ?>"
				data-total-items="<?php echo esc_attr( $total ); ?>">
				<button class="ops-page-btn" id="ops-prev" disabled>&#8592;</button>
				<span class="ops-page-info">
					<span id="ops-current-page">1</span> / <span id="ops-total-pages"><?php echo esc_html( $pages ); ?></span>
					<small class="ops-total-items">(<?php echo esc_html( $total ); ?> <?php echo esc_html__( 'operation', 'mhm-rentiva' ); ?>)</small>
				</span>
				<button class="ops-page-btn" id="ops-next" <?php echo $pages <= 1 ? 'disabled' : ''; ?>>&#8594;</button>
			</div>
			<?php endif; ?>

		<?php else : ?>
			<p class="no-data"><?php echo esc_html__( 'No upcoming operations found.', 'mhm-rentiva' ); ?></p>
		<?php endif; ?>
	</div>
</div>
