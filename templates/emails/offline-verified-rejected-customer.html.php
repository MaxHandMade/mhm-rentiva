<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; } ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>
		<?php
		/* translators: %s: booking ID. */
		echo esc_html( sprintf( __( 'Payment Verification Failed · Booking #%s', 'mhm-rentiva' ), (string) ( $data['booking']['id'] ?? '' ) ) );
		?>
	</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background: #f5f5f5; }
		.container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
		.header { background: linear-gradient(135deg, #af4448 0%, #d32f2f 100%); color: white; padding: 24px; text-align:center; }
		.header h1 { margin: 0; font-size: 20px; }
		.content { padding: 24px; }
		.alert { background:#fff3cd; border-left:4px solid #ffc107; padding:16px; border-radius:6px; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">
			<h1><?php esc_html_e( 'Payment Could Not Be Verified', 'mhm-rentiva' ); ?></h1>
		</div>
		<div class="content">
			<p><?php esc_html_e( 'We could not verify your payment receipt for the booking below.', 'mhm-rentiva' ); ?></p>
			<p class="alert"><?php esc_html_e( 'Please reply to this email with a clear receipt image or contact us for assistance.', 'mhm-rentiva' ); ?></p>
			<p><strong><?php esc_html_e( 'Booking No:', 'mhm-rentiva' ); ?></strong> #<?php echo esc_html( (string) ( $data['booking']['id'] ?? '' ) ); ?></p>
		</div>
	</div>
</body>
</html>
