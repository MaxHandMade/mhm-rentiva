<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- template-scope render variables.

$customer = $data['customer'] ?? array();
$booking  = $data['booking']  ?? array();
$vehicle  = $data['vehicle']  ?? array();
$vendor   = $data['vendor']   ?? array();
$panel    = $data['panel']    ?? array();
$transfer = $data['transfer'] ?? array();

$is_transfer        = ( ( $booking['service_type'] ?? '' ) === 'transfer' );
$service_type_label = $is_transfer
	? __( 'Transfer', 'mhm-rentiva' )
	: __( 'Car Rental', 'mhm-rentiva' );

$payment_status = $booking['payment_status'] ?? 'pending';
$status_text    = array(
	'pending'   => esc_html__( 'Payment Pending', 'mhm-rentiva' ),
	'completed' => esc_html__( 'Completed', 'mhm-rentiva' ),
	'failed'    => esc_html__( 'Failed', 'mhm-rentiva' ),
	'cancelled' => esc_html__( 'Cancelled', 'mhm-rentiva' ),
	'refunded'  => esc_html__( 'Refunded', 'mhm-rentiva' ),
);

$currency_symbol = apply_filters( 'mhm_rentiva/currency_symbol', '₺' );
$panel_url       = $panel['url'] ?? home_url( '/panel/' );
?>
<div class="booking-created-vendor-email">
	<div class="intro">
		<p style="font-size: 15px; line-height: 1.6; color: #333;">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: vendor name */
					__( 'Hello %s,', 'mhm-rentiva' ),
					$vendor['name'] ?? ''
				)
			);
			?>
		</p>
		<div style="background: #e7f5ff; border: 1px solid #74c0fc; color: #0b5394; padding: 15px; border-radius: 6px; margin: 20px 0;">
			<strong><?php esc_html_e( 'New Reservation Request', 'mhm-rentiva' ); ?></strong><br>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: vehicle title */
					__( 'A customer has just reserved your vehicle "%s". Please review the details and confirm from your vendor panel.', 'mhm-rentiva' ),
					$vehicle['title'] ?? ''
				)
			);
			?>
		</div>
	</div>

	<div class="content">
		<h2 style="color: #555; border-bottom: 2px solid #e3f2fd; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Customer Information', 'mhm-rentiva' ); ?></h2>

		<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #e3f2fd; border-radius: 8px;">
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; color: #1565c0; width: 35%;"><strong><?php esc_html_e( 'Name:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; text-align: right;"><?php echo esc_html( $customer['name'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; color: #1565c0;"><strong><?php esc_html_e( 'Email:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #bbdefb; text-align: right;"><?php echo esc_html( $customer['email'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; color: #1565c0;"><strong><?php esc_html_e( 'Phone:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; text-align: right;"><?php echo esc_html( $customer['phone'] ?? '' ); ?></td>
			</tr>
		</table>

		<h2 style="color: #555; border-bottom: 2px solid #f8f9fa; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Reservation Details', 'mhm-rentiva' ); ?></h2>

		<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #f8f9fa; border-radius: 8px;">
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555; width: 35%;"><strong><?php esc_html_e( 'Reservation No:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;">#<?php echo esc_html( $booking['id'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e( 'Service Type:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html( $service_type_label ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e( 'Vehicle:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html( $vehicle['title'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e( 'Pickup Date:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html( $booking['pickup_date'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e( 'Return Date:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right;"><?php echo esc_html( $booking['return_date'] ?? '' ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; color: #555;"><strong><?php esc_html_e( 'Total Amount:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; border-bottom: 1px solid #e9ecef; text-align: right; color: #28a745; font-weight: bold;"><?php echo esc_html( $currency_symbol ); ?><?php echo esc_html( number_format( (float) ( $booking['total_price'] ?? 0 ), 2 ) ); ?></td>
			</tr>
			<tr>
				<td style="padding: 12px 15px; color: #555;"><strong><?php esc_html_e( 'Payment Status:', 'mhm-rentiva' ); ?></strong></td>
				<td style="padding: 12px 15px; text-align: right;"><?php echo esc_html( $status_text[ $payment_status ] ?? ucfirst( (string) $payment_status ) ); ?></td>
			</tr>
		</table>

		<?php if ( $is_transfer && ! empty( $transfer ) ) : ?>
			<h2 style="color: #555; border-bottom: 2px solid #eef2ff; padding-bottom: 10px; margin-bottom: 15px;"><?php esc_html_e( 'Transfer Route', 'mhm-rentiva' ); ?></h2>

			<table style="width: 100%; border-collapse: collapse; margin-bottom: 25px; background: #eef2ff; border-radius: 8px;">
				<tr>
					<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; color: #3730a3; width: 35%;"><strong><?php esc_html_e( 'From:', 'mhm-rentiva' ); ?></strong></td>
					<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; text-align: right;">
						<?php echo esc_html( $transfer['origin_name'] ?? '-' ); ?>
						<?php if ( ! empty( $transfer['origin_city'] ) ) : ?>
							<br><small style="color: #6366f1;"><?php echo esc_html( $transfer['origin_city'] ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; color: #3730a3;"><strong><?php esc_html_e( 'To:', 'mhm-rentiva' ); ?></strong></td>
					<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; text-align: right;">
						<?php echo esc_html( $transfer['destination_name'] ?? '-' ); ?>
						<?php if ( ! empty( $transfer['destination_city'] ) ) : ?>
							<br><small style="color: #6366f1;"><?php echo esc_html( $transfer['destination_city'] ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
				<?php if ( ! empty( $transfer['distance_km'] ) ) : ?>
					<tr>
						<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; color: #3730a3;"><strong><?php esc_html_e( 'Distance:', 'mhm-rentiva' ); ?></strong></td>
						<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; text-align: right;"><?php echo esc_html( sprintf( /* translators: %d km */ __( '%d km', 'mhm-rentiva' ), (int) $transfer['distance_km'] ) ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $transfer['duration_min'] ) ) : ?>
					<tr>
						<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; color: #3730a3;"><strong><?php esc_html_e( 'Duration:', 'mhm-rentiva' ); ?></strong></td>
						<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; text-align: right;"><?php echo esc_html( sprintf( /* translators: %d minutes */ __( '%d min', 'mhm-rentiva' ), (int) $transfer['duration_min'] ) ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $transfer['adults'] ) || ! empty( $transfer['children'] ) ) : ?>
					<tr>
						<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; color: #3730a3;"><strong><?php esc_html_e( 'Passengers:', 'mhm-rentiva' ); ?></strong></td>
						<td style="padding: 12px 15px; border-bottom: 1px solid #c7d2fe; text-align: right;">
							<?php
							$parts = array();
							if ( ! empty( $transfer['adults'] ) ) {
								$parts[] = sprintf( /* translators: %d adults */ _n( '%d adult', '%d adults', (int) $transfer['adults'], 'mhm-rentiva' ), (int) $transfer['adults'] );
							}
							if ( ! empty( $transfer['children'] ) ) {
								$parts[] = sprintf( /* translators: %d children */ _n( '%d child', '%d children', (int) $transfer['children'], 'mhm-rentiva' ), (int) $transfer['children'] );
							}
							echo esc_html( implode( ', ', $parts ) );
							?>
						</td>
					</tr>
				<?php endif; ?>
				<?php if ( ! empty( $transfer['luggage_big'] ) || ! empty( $transfer['luggage_small'] ) ) : ?>
					<tr>
						<td style="padding: 12px 15px; color: #3730a3;"><strong><?php esc_html_e( 'Luggage:', 'mhm-rentiva' ); ?></strong></td>
						<td style="padding: 12px 15px; text-align: right;">
							<?php
							$lug = array();
							if ( ! empty( $transfer['luggage_big'] ) ) {
								$lug[] = sprintf( /* translators: %d large bags */ __( '%d large', 'mhm-rentiva' ), (int) $transfer['luggage_big'] );
							}
							if ( ! empty( $transfer['luggage_small'] ) ) {
								$lug[] = sprintf( /* translators: %d small bags */ __( '%d small', 'mhm-rentiva' ), (int) $transfer['luggage_small'] );
							}
							echo esc_html( implode( ', ', $lug ) );
							?>
						</td>
					</tr>
				<?php endif; ?>
			</table>
		<?php endif; ?>

		<div style="text-align: center;">
			<a href="<?php echo esc_url( $panel_url ); ?>" style="display: inline-block; background: #1e6bf5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 600;"><?php esc_html_e( 'Open Vendor Panel', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>
