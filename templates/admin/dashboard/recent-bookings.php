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
$bookings = $args['bookings'] ?? array();
?>

<div class="mhm-dashboard-widget">
	<h3><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html__( 'Recent Bookings', 'mhm-rentiva' ); ?></h3>
	<div class="widget-content">
		<?php if ( ! empty( $bookings ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'ID', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Customer', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Vehicle', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Date', 'mhm-rentiva' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'mhm-rentiva' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $bookings as $booking ) :
						$status = $booking['status'] ?? 'pending';
						// Get translated status label
						$status_label = \MHMRentiva\Admin\Booking\Core\Status::get_label( $status );
						$status_class = 'status-' . esc_attr( $status );
						?>
						<tr>
							<td><strong>#<?php echo esc_html( $booking['id'] ); ?></strong></td>
							<td><?php echo esc_html( $booking['customer_name'] ); ?></td>
							<td><?php echo esc_html( $booking['vehicle_title'] ); ?></td>
							<td><?php echo esc_html( $booking['pickup_date'] ); ?></td>
							<td><span class="status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="no-data"><?php echo esc_html__( 'No bookings found yet.', 'mhm-rentiva' ); ?></p>
		<?php endif; ?>

		<div class="widget-footer">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=vehicle_booking' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'View All Bookings', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>