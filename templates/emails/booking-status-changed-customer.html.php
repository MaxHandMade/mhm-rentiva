<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="booking-status-email">
	<div class="intro" style="margin-bottom: 20px;">
		<p>
		<?php
			/* translators: %s: customer name */
			printf( esc_html__( 'Dear %s,', 'mhm-rentiva' ), esc_html( $data['customer']['name'] ?? '' ) );
		?>
		</p>
		<p><?php esc_html_e( 'Your booking status has been updated.', 'mhm-rentiva' ); ?></p>
	</div>

	<h2 style="color: #555; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Status Update', 'mhm-rentiva' ); ?></h2>

	<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background: #f8f9fa; border-radius: 8px;">
		<tr>
			<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555; width: 40%;"><strong><?php esc_html_e( 'Previous Status:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right; color: #6c757d;"><?php echo esc_html( $data['status_change']['old_status_label'] ?? ( $data['status_change']['old_status'] ?? '' ) ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e( 'New Status:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right; color: #28a745; font-weight: bold;"><?php echo esc_html( $data['status_change']['new_status_label'] ?? ( $data['status_change']['new_status'] ?? '' ) ); ?></td>
		</tr>
	</table>

	<h2 style="color: #555; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Booking Details', 'mhm-rentiva' ); ?></h2>

	<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777; width: 40%;"><strong><?php esc_html_e( 'Booking No:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;">#<?php echo esc_html( $data['booking']['id'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Vehicle:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;"><?php echo esc_html( $data['vehicle']['title'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Pickup Date:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;"><?php echo esc_html( $data['booking']['pickup_date'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; color: #777;"><strong><?php esc_html_e( 'Return Date:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; text-align: right;"><?php echo esc_html( $data['booking']['return_date'] ?? '' ); ?></td>
		</tr>
	</table>

	<p style="color: #666;"><?php esc_html_e( 'If you have any questions regarding this booking, please contact us.', 'mhm-rentiva' ); ?></p>
</div>
