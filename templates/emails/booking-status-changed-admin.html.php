<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="booking-status-admin-email">
	<div class="intro" style="margin-bottom: 20px;">
		<p><?php esc_html_e( 'A booking status has been updated.', 'mhm-rentiva' ); ?></p>
	</div>

	<h2 style="color: #555; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Status Update', 'mhm-rentiva' ); ?></h2>

	<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777; width: 40%;"><strong><?php esc_html_e( 'Booking No:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;">#<?php echo esc_html( (string) ( $data['booking']['id'] ?? '' ) ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Customer:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;"><?php echo esc_html( $data['customer']['name'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Email:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right;"><?php echo esc_html( $data['customer']['email'] ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; color: #777;"><strong><?php esc_html_e( 'Old Status:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; border-bottom: 1px solid #eee; text-align: right; color: #6c757d;"><?php echo esc_html( $data['status_change']['old_status_label'] ?? ( $data['status_change']['old_status'] ?? '' ) ); ?></td>
		</tr>
		<tr>
			<td style="padding: 12px 0; color: #777;"><strong><?php esc_html_e( 'New Status:', 'mhm-rentiva' ); ?></strong></td>
			<td style="padding: 12px 0; text-align: right; color: #28a745; font-weight: bold;"><?php echo esc_html( $data['status_change']['new_status_label'] ?? ( $data['status_change']['new_status'] ?? '' ) ); ?></td>
		</tr>
	</table>

	<div style="text-align: center; margin-top: 20px;">
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=vehicle_booking' ) ); ?>" style="display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px;"><?php esc_html_e( 'View Bookings', 'mhm-rentiva' ); ?></a>
	</div>
</div>
