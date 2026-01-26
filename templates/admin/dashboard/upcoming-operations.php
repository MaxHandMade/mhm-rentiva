<?php

/**
 * Dashboard Upcoming Operations Template
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$operations = \MHMRentiva\Admin\Reports\Repository\ReportRepository::get_upcoming_operations( 5 );
?>

<div class="mhm-dashboard-widget full-width" style="width:100%; margin-top: 20px;">
	<h3><?php echo esc_html__( 'Upcoming Operations', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<?php if ( ! empty( $operations ) ) : ?>
			<table class="wp-list-table widefat fixed striped operations-table">
				<thead>
					<tr>
						<th style="width: 50px;"><?php echo esc_html__( 'Type', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Time', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Vehicle / Customer', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Detail', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'mhm-rentiva' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $operations as $op ) :
						$icon           = ( $op['type'] === 'transfer' ) ? 'dashicons-airplane' : 'dashicons-car';
						$date_time      = strtotime( $op['start_date'] );
						$formatted_date = date_i18n( 'd M Y', $date_time );
						$formatted_time = wp_date( 'H:i', $date_time );

						$today    = strtotime( 'today' );
						$tomorrow = strtotime( 'tomorrow' );
						$op_day   = strtotime( wp_date( 'Y-m-d', $date_time ) );

						if ( $op_day === $today ) {
							$day_label = '<strong>' . __( 'Today', 'mhm-rentiva' ) . '</strong>';
						} elseif ( $op_day === $tomorrow ) {
							$day_label = '<strong>' . __( 'Tomorrow', 'mhm-rentiva' ) . '</strong>';
						} else {
							$day_label = $formatted_date;
						}

						$detail = '-';
						if ( $op['type'] === 'rental' && ! empty( $op['end_date'] ) ) {
							$start  = new \DateTime( $op['start_date'] );
							$end    = new \DateTime( $op['end_date'] );
							$diff   = $start->diff( $end );
							$detail = $diff->days . ' ' . __( 'Days', 'mhm-rentiva' );
						} elseif ( $op['type'] === 'transfer' ) {
							$detail = esc_html( $op['origin'] ?? '' ) . ' &rarr; ' . esc_html( $op['destination'] ?? '' );
						}

						$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label( $op['status'] );
						$status_class = 'status-' . esc_attr( $op['status'] );
						?>
						<tr>
							<td class="op-icon" style="text-align:center;"><span class="dashicons <?php echo esc_attr( $icon ); ?>" style="font-size:24px; color:#666; margin-top:5px;"></span></td>
							<td><?php echo wp_kses_post( $day_label ); ?><br><small style="color:#888;"><?php echo esc_html( $formatted_time ); ?></small></td>
							<td>
								<strong><?php echo esc_html( $op['vehicle_title'] ?? __( 'VIP Transfer', 'mhm-rentiva' ) ); ?></strong><br>
								<small><?php echo esc_html( $op['customer_name'] ); ?></small>
							</td>
							<td><?php echo wp_kses_post( $detail ); ?></td>
							<td><span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="no-data"><?php echo esc_html__( 'No upcoming operations found.', 'mhm-rentiva' ); ?></p>
		<?php endif; ?>
	</div>
</div>