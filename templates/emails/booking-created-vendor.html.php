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

$service_type_label = ( ( $booking['service_type'] ?? '' ) === 'transfer' )
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

		<div style="text-align: center;">
			<a href="<?php echo esc_url( $panel_url ); ?>" style="display: inline-block; background: #1e6bf5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: 600;"><?php esc_html_e( 'Open Vendor Panel', 'mhm-rentiva' ); ?></a>
		</div>
	</div>
</div>
