<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>
<div class="remaining-payment-link-email">
	<p>
		<?php
		printf(
			/* translators: %s: customer name. */
			esc_html__( 'Dear %s,', 'mhm-rentiva' ),
			esc_html( $data['customer']['name'] ?? '' )
		);
		?>
	</p>
	<p>
		<?php
		printf(
			/* translators: %s: vehicle/service name. */
			esc_html__( 'There is a remaining balance due on your booking for %s.', 'mhm-rentiva' ),
			esc_html( $data['vehicle']['title'] ?? '' )
		);
		?>
	</p>

	<div class="booking-details" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
		<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
			<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Reservation No:', 'mhm-rentiva' ); ?></span>
			<span class="detail-value" style="color: #333;">#<?php echo esc_html( $data['booking']['order_id'] ?? $data['booking']['id'] ?? '' ); ?></span>
		</div>
		<div class="detail-row" style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee;">
			<span class="detail-label" style="font-weight: bold; color: #555;"><?php esc_html_e( 'Remaining Amount:', 'mhm-rentiva' ); ?></span>
			<span class="detail-value" style="color: #333;"><?php echo esc_html( apply_filters( 'mhm_rentiva/currency_symbol', '' ) ); ?><?php echo esc_html( number_format( (float) ( $data['booking']['remaining_amount'] ?? 0 ), 2 ) ); ?></span>
		</div>
	</div>

	<p><?php esc_html_e( 'You can pay this amount securely online by clicking the button below:', 'mhm-rentiva' ); ?></p>

	<div style="text-align: center; margin: 20px 0;">
		<a href="<?php echo esc_url( $data['payment']['url'] ?? '' ); ?>" class="cta-button" style="display: inline-block; background: #2196F3; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; font-size: 16px;">
			<?php esc_html_e( 'Complete Payment', 'mhm-rentiva' ); ?>
		</a>
	</div>

	<p style="font-size: 13px; color: #666;">
		<?php esc_html_e( 'If the button does not work, contact us and we will be happy to help.', 'mhm-rentiva' ); ?>
	</p>
</div>
