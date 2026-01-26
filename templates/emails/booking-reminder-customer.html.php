<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="booking-reminder-email">
	<div class="content">
		<p><?php esc_html_e( 'Just a friendly reminder for your upcoming booking. Here are the details:', 'mhm-rentiva' ); ?></p>
		<div class="booking-details" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Vehicle:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value"><?php echo esc_html( $data['vehicle']['title'] ?? '' ); ?></span>
			</div>
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Pickup Date:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value"><?php echo esc_html( $data['booking']['pickup_date'] ?? '' ); ?></span>
			</div>
			<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
				<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Return Date:', 'mhm-rentiva' ); ?></span>
				<span class="detail-value"><?php echo esc_html( $data['booking']['return_date'] ?? '' ); ?></span>
			</div>
		</div>
		<p><?php esc_html_e( 'Please arrive on time and bring your required documents.', 'mhm-rentiva' ); ?></p>
	</div>
</div>
