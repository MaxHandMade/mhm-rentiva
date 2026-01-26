<?php

/**
 * Receipt Status Email Template
 *
 * @package MHMRentiva
 * @since 4.3.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Template variables - sanitized and escaped
$status        = isset( $args['status'] ) ? mhm_rentiva_sanitize_text_field_safe( $args['status'] ) : 'approved';
$customer_name = isset( $args['customer_name'] ) ? mhm_rentiva_sanitize_text_field_safe( $args['customer_name'] ) : '';
$booking_title = isset( $args['booking_title'] ) ? mhm_rentiva_sanitize_text_field_safe( $args['booking_title'] ) : '';
$admin_note    = isset( $args['admin_note'] ) ? sanitize_textarea_field( $args['admin_note'] ) : '';
$account_url   = isset( $args['account_url'] ) ? esc_url_raw( $args['account_url'] ) : '';
$site_name     = get_bloginfo( 'name' );
$site_url      = home_url();

// Status-specific content - properly escaped
$status_text = ( $status === 'approved' )
	? esc_html__( 'Your payment receipt has been approved', 'mhm-rentiva' )
	: esc_html__( 'Your payment receipt has been rejected', 'mhm-rentiva' );

$status_color = ( $status === 'approved' ) ? '#28a745' : '#dc3545';
$status_icon  = ( $status === 'approved' ) ? '✓' : '✗';
?>

<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $status_text ); ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
			line-height: 1.6;
			color: #333;
			background-color: #f8f9fa;
			margin: 0;
			padding: 20px;
		}

		.email-container {
			max-width: 600px;
			margin: 0 auto;
			background: #ffffff;
			border-radius: 8px;
			box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
			overflow: hidden;
		}

		.email-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 30px 20px;
			text-align: center;
		}

		.email-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 600;
		}

		.status-badge {
			display: inline-block;
			background: <?php echo esc_attr( $status_color ); ?>;
			color: white;
			padding: 8px 16px;
			border-radius: 20px;
			font-size: 14px;
			font-weight: 600;
			margin-top: 10px;
		}

		.email-content {
			padding: 30px 20px;
		}

		.greeting {
			font-size: 18px;
			margin-bottom: 20px;
			color: #2c3e50;
		}

		.booking-info {
			background: #f8f9fa;
			border-left: 4px solid #667eea;
			padding: 20px;
			margin: 20px 0;
			border-radius: 0 4px 4px 0;
		}

		.booking-info h3 {
			margin: 0 0 15px 0;
			color: #2c3e50;
			font-size: 16px;
		}

		.booking-detail {
			display: flex;
			justify-content: space-between;
			margin-bottom: 8px;
			padding: 5px 0;
		}

		.booking-detail:last-child {
			margin-bottom: 0;
		}

		.booking-detail strong {
			color: #495057;
			min-width: 120px;
		}

		.admin-note {
			background: #fff3cd;
			border: 1px solid #ffeaa7;
			border-radius: 4px;
			padding: 15px;
			margin: 20px 0;
		}

		.admin-note h4 {
			margin: 0 0 10px 0;
			color: #856404;
			font-size: 14px;
		}

		.admin-note p {
			margin: 0;
			color: #856404;
		}

		.cta-button {
			display: inline-block;
			background: #667eea;
			color: white;
			text-decoration: none;
			padding: 12px 24px;
			border-radius: 6px;
			font-weight: 600;
			margin: 20px 0;
			transition: background-color 0.3s ease;
		}

		.cta-button:hover {
			background: #5a6fd8;
		}

		.email-footer {
			background: #f8f9fa;
			padding: 20px;
			text-align: center;
			border-top: 1px solid #e9ecef;
		}

		.email-footer p {
			margin: 0;
			color: #6c757d;
			font-size: 14px;
		}

		.social-links {
			margin-top: 15px;
		}

		.social-links a {
			color: #667eea;
			text-decoration: none;
			margin: 0 10px;
		}

		@media (max-width: 600px) {
			.email-container {
				margin: 0;
				border-radius: 0;
			}

			.email-header,
			.email-content,
			.email-footer {
				padding: 20px 15px;
			}

			.booking-detail {
				flex-direction: column;
			}

			.booking-detail strong {
				min-width: auto;
				margin-bottom: 5px;
			}
		}
	</style>
</head>

<body>
	<div class="email-container">
		<!-- Header -->
		<div class="email-header">
			<h1><?php echo esc_html( \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_brand_name', get_bloginfo( 'name' ) ) ); ?></h1>
			<div class="status-badge">
				<?php echo esc_html( $status_icon . ' ' . $status_text ); ?>
			</div>
		</div>

		<!-- Content -->
		<div class="email-content">
			<div class="greeting">
				<?php
				if ( ! empty( $customer_name ) ) {
					/* translators: %s: customer name */
					printf( esc_html__( 'Hello %s,', 'mhm-rentiva' ), esc_html( $customer_name ) );
				} else {
					echo esc_html__( 'Hello,', 'mhm-rentiva' );
				}
				?>
			</div>

			<p>
				<?php
				if ( $status === 'approved' ) {
					echo esc_html__( 'Great news! Your payment receipt has been reviewed and approved by our team.', 'mhm-rentiva' );
				} else {
					echo esc_html__( 'We have reviewed your payment receipt, but unfortunately it could not be approved at this time.', 'mhm-rentiva' );
				}
				?>
			</p>

			<!-- Booking Information -->
			<div class="booking-info">
				<h3><?php echo esc_html__( 'Booking Details', 'mhm-rentiva' ); ?></h3>
				<div class="booking-detail">
					<strong><?php echo esc_html__( 'Booking:', 'mhm-rentiva' ); ?></strong>
					<span><?php echo esc_html( $booking_title ); ?></span>
				</div>
				<div class="booking-detail">
					<strong><?php echo esc_html__( 'Status:', 'mhm-rentiva' ); ?></strong>
					<span style="color: <?php echo esc_attr( $status_color ); ?>; font-weight: 600;">
						<?php echo esc_html( ucfirst( $status ) ); ?>
					</span>
				</div>
			</div>

			<!-- Admin Note -->
			<?php if ( ! empty( $admin_note ) ) : ?>
				<div class="admin-note">
					<h4><?php echo esc_html__( 'Admin Note', 'mhm-rentiva' ); ?></h4>
					<p><?php echo esc_html( $admin_note ); ?></p>
				</div>
			<?php endif; ?>

			<!-- Next Steps -->
			<div>
				<?php if ( $status === 'approved' ) : ?>
					<p><?php echo esc_html__( 'Your booking is now confirmed and you can proceed with your rental plans.', 'mhm-rentiva' ); ?></p>
				<?php else : ?>
					<p><?php echo esc_html__( 'Please review the admin note above and upload a new receipt if needed.', 'mhm-rentiva' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Call to Action -->
			<div style="text-align: center;">
				<a href="<?php echo esc_url( $account_url ); ?>" class="cta-button">
					<?php echo esc_html__( 'View My Account', 'mhm-rentiva' ); ?>
				</a>
			</div>

			<p style="margin-top: 30px; color: #6c757d; font-size: 14px;">
				<?php echo esc_html__( 'If you have any questions or need assistance, please don\'t hesitate to contact us.', 'mhm-rentiva' ); ?>
			</p>
		</div>

		<!-- Footer -->
		<div class="email-footer">
			<p>
				<?php
				printf(
					/* translators: %s placeholder. */
					esc_html__( 'This email was sent from %s', 'mhm-rentiva' ),
					'<a href="' . esc_url( $site_url ) . '" style="color: #667eea;">' . esc_html( \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_brand_name', get_bloginfo( 'name' ) ) ) . '</a>'
				);
				?>
			</p>
			<div class="social-links">
				<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html__( 'Website', 'mhm-rentiva' ); ?></a>
				<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"><?php echo esc_html__( 'Contact', 'mhm-rentiva' ); ?></a>
			</div>
		</div>
	</div>
</body>

</html>
