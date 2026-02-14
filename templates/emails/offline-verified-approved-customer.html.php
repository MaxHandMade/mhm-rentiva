<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Load plugin textdomain

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Payment Approved', 'mhm-rentiva' ); ?> - #<?php echo esc_html( $data['booking']['id'] ?? '' ); ?></title>
	<style>
		body {
			font-family: Arial, sans-serif;
			line-height: 1.6;
			color: #333;
			margin: 0;
			padding: 20px;
		}

		.container {
			max-width: 600px;
			margin: 0 auto;
			background: #fff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
		}

		.header {
			background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
			color: white;
			padding: 30px;
			text-align: center;
		}

		.header h1 {
			margin: 0;
			font-size: 24px;
		}

		.header .icon {
			font-size: 48px;
			margin-bottom: 10px;
		}

		.content {
			padding: 30px;
		}

		.success-message {
			background: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
			padding: 20px;
			border-radius: 6px;
			margin: 20px 0;
			text-align: center;
		}

		.booking-details {
			background: #f8f9fa;
			padding: 20px;
			border-radius: 8px;
			margin: 20px 0;
		}

		.detail-row {
			display: flex;
			justify-content: space-between;
			margin: 10px 0;
			padding: 8px 0;
			border-bottom: 1px solid #eee;
		}

		.detail-row:last-child {
			border-bottom: none;
		}

		.detail-label {
			font-weight: bold;
			color: #555;
		}

		.detail-value {
			color: #333;
		}

		.footer {
			background: #f8f9fa;
			padding: 20px;
			text-align: center;
			color: #666;
			font-size: 14px;
		}

		.cta-button {
			display: inline-block;
			background: #28a745;
			color: white;
			padding: 12px 24px;
			text-decoration: none;
			border-radius: 6px;
			margin: 20px 0;
		}
	</style>
</head>

<body>
	<div class="container">
		<div class="header">
			<div class="icon">&#10003;</div>
			<h1><?php esc_html_e( 'Your Payment Has Been Approved!', 'mhm-rentiva' ); ?></h1>
			<p>
				<?php
				/* translators: %s: booking ID. */
				printf( esc_html__( 'Your payment for Reservation #%s has been successfully verified.', 'mhm-rentiva' ), esc_html( $data['booking']['id'] ?? '' ) );
				?>
			</p>
			<p style="margin-top: 15px; font-size: 14px; opacity: 0.9;"><?php echo esc_html( \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_brand_name', get_bloginfo( 'name' ) ) ); ?></p>
		</div>

		<div class="content">
			<div class="success-message">
				<h3><?php esc_html_e( 'Congratulations!', 'mhm-rentiva' ); ?></h3>
				<p><?php esc_html_e( 'Your bank transfer has been approved and your reservation is now active.', 'mhm-rentiva' ); ?></p>
			</div>

			<p>
				<?php
				/* translators: %s: customer name HTML. */
				printf( esc_html__( 'Dear %s,', 'mhm-rentiva' ), '<strong>' . esc_html( $data['customer']['name'] ?? '' ) . '</strong>' );
				?>
			</p>

			<p><?php esc_html_e( 'Your bank transfer for your reservation has been successfully verified. Your reservation is now confirmed and we are ready for vehicle delivery on the specified date.', 'mhm-rentiva' ); ?></p>

			<h2><?php esc_html_e( 'Reservation Summary', 'mhm-rentiva' ); ?></h2>
			<div class="booking-details">
				<div class="detail-row">
					<span class="detail-label"><?php esc_html_e( 'Reservation No:', 'mhm-rentiva' ); ?></span>
					<span class="detail-value">#<?php echo esc_html( $data['booking']['id'] ?? '' ); ?></span>
				</div>
				<div class="detail-row">
					<span class="detail-label"><?php esc_html_e( 'Vehicle:', 'mhm-rentiva' ); ?></span>
					<span class="detail-value"><?php echo esc_html( $data['vehicle']['title'] ?? '' ); ?></span>
				</div>
				<div class="detail-row">
					<span class="detail-label"><?php esc_html_e( 'Pickup Date:', 'mhm-rentiva' ); ?></span>
					<span class="detail-value"><?php echo esc_html( $data['booking']['pickup_date'] ?? '' ); ?></span>
				</div>
				<div class="detail-row">
					<span class="detail-label"><?php esc_html_e( 'Return Date:', 'mhm-rentiva' ); ?></span>
					<span class="detail-value"><?php echo esc_html( $data['booking']['return_date'] ?? '' ); ?></span>
				</div>
				<div class="detail-row">
					<span class="detail-label"><?php esc_html_e( 'Amount Paid:', 'mhm-rentiva' ); ?></span>
					<span class="detail-value"><?php echo esc_html( apply_filters( 'mhm_rentiva/currency_symbol', '' ) ); ?><?php echo esc_html( number_format( $data['booking']['total_price'] ?? 0, 2 ) ); ?></span>
				</div>
			</div>

			<p><strong><?php esc_html_e( 'Important Notes:', 'mhm-rentiva' ); ?></strong></p>
			<ul>
				<li><?php esc_html_e( 'Please be ready for vehicle delivery on the specified date', 'mhm-rentiva' ); ?></li>
				<li><?php esc_html_e( 'Please bring your driver\'s license and ID with you', 'mhm-rentiva' ); ?></li>
				<li><?php esc_html_e( 'If you have any questions, please contact us', 'mhm-rentiva' ); ?></li>
			</ul>

			<div style="text-align: center;">
				<a href="<?php echo esc_url( $data['site']['url'] ?? '' ); ?>" class="cta-button"><?php esc_html_e( 'Visit Our Website', 'mhm-rentiva' ); ?></a>
			</div>

			<p><?php esc_html_e( 'We look forward to seeing you!', 'mhm-rentiva' ); ?></p>
		</div>

		<div class="footer">
			<p><strong><?php echo esc_html( \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_brand_name', get_bloginfo( 'name' ) ) ); ?></strong></p>
			<p><?php esc_html_e( 'This email was sent automatically. Please do not reply.', 'mhm-rentiva' ); ?></p>
		</div>
	</div>
</body>

</html>

