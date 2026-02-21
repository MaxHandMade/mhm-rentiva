<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Legacy/extensible email hook names are kept stable.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Settings\Groups\EmailSettings;

final class Templates {


	/**
	 * Template registry - subjects are in English for i18n
	 * Translation is applied at runtime in compile_subject()
	 */
	/**
	 * Template registry with translatable subjects
	 */
	public static function register(): void {
		// No hooks yet; exists for consistency and future extensions
	}

	/**
	 * Template registry with translatable subjects
	 */
	public static function registry(): array {
		static $registry = null;
		if ( $registry === null ) {
			$registry = array(
				// Booking notifications
				'booking_created_customer'        => array(
					'subject' => __( 'Booking #{{booking.id}} Confirmed - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-created-customer',
				),
				'booking_created_admin'           => array(
					'subject' => __( 'New Booking Request #{{booking.id}} - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-created-admin',
				),
				'booking_status_changed_customer' => array(
					'subject' => __( 'Booking #{{booking.id}} Status Updated - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-status-changed-customer',
				),
				'booking_status_changed_admin'    => array(
					'subject' => __( 'Booking #{{booking.id}} Status Updated - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-status-changed-admin',
				),
				'booking_reminder_customer'       => array(
					'subject' => __( 'Reminder: Your Booking #{{booking.id}} Starts Soon - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-reminder-customer',
				),
				// Welcome Email (One-time)
				'welcome_customer'                => array(
					'subject' => __( 'Welcome to {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'welcome-customer',
				),

				// Manual Cancel
				'booking_cancelled'               => array(
					'subject' => __( 'Booking #{{booking.id}} Cancelled - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-cancelled',
				),

				// Auto Cancel
				'auto_cancel'                     => array(
					'subject' => __( 'Booking #{{booking.id}} Cancelled - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-cancelled',
				),

				// Refund templates
				'refund_customer'                 => array(
					'subject' => __( 'Refund Processed for Booking #{{booking.id}}', 'mhm-rentiva' ),
					'file'    => 'refund-customer',
				),
				'refund_admin'                    => array(
					'subject' => __( 'Refund Alert: Booking #{{booking.id}}', 'mhm-rentiva' ),
					'file'    => 'refund-admin',
				),

				// Message notifications
				'message_received_admin'          => array(
					'subject' => __( 'New Message - {{message.subject}} - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'message-received-admin',
				),
				'message_replied_customer'        => array(
					'subject' => __( 'Reply to Your Message - {{message.subject}} - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'message-replied-customer',
				),
				'message_auto_reply'              => array(
					'subject' => __( 'We received your message - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'message-auto-reply',
				),
			);
		}

		return apply_filters( 'mhm_rentiva_email_registry', $registry );
	}

	public static function locate_template( string $slug ): ?string {
		// Get template path from settings
		$template_path = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_template_path();
		$rel           = $template_path . $slug . '.html.php';

		$themePath = trailingslashit( get_stylesheet_directory() ) . $rel;
		if ( file_exists( $themePath ) ) {
			return $themePath;
		}
		$parentPath = trailingslashit( get_template_directory() ) . $rel;
		if ( file_exists( $parentPath ) ) {
			return $parentPath;
		}
		$plugin = MHM_RENTIVA_PLUGIN_PATH . 'templates/emails/' . $slug . '.html.php';
		if ( file_exists( $plugin ) ) {
			return $plugin;
		}
		return null;
	}

	public static function compile_subject( string $key, array $context ): string {
		// Subject override from settings (if defined and non-empty)
		$subject = self::getSubjectOverride( $key, $context );
		if ( $subject !== null ) {
			return $subject;
		}

		$reg = self::registry();
		$tpl = $reg[ $key ]['subject'] ?? ( 'Notification: ' . $key );
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		$tpl = __( $tpl, 'mhm-rentiva' );
		$sub = self::replace_placeholders( $tpl, $context );
		$sub = apply_filters( 'mhm_rentiva_email_subject', $sub, $key, $context );
		$sub = apply_filters( 'mhm_rentiva_email_subject_' . $key, $sub, $context );
		return $sub;
	}

	public static function render_body( string $key, array $context ): string {
		// Body override from settings if available (HTML)
		$override = self::getBodyOverride( $key, $context );
		if ( $override !== null && $override !== '' ) {
			$html = (string) $override;
			// If override is only a fragment (no full HTML), wrap with standard layout
			if ( stripos( $html, '<html' ) === false && stripos( $html, '<body' ) === false ) {
				$subject = self::compile_subject( $key, $context );
				$html    = self::wrapWithLayout( $context, $subject, $html );
			}
			$html = apply_filters( 'mhm_rentiva_email_body', $html, $key, $context );
			$html = apply_filters( 'mhm_rentiva_email_body_' . $key, $html, $context );
			return $html;
		}

		$reg  = self::registry();
		$slug = $reg[ $key ]['file'] ?? $key;
		$path = self::locate_template( $slug );
		$ctx  = apply_filters( 'mhm_rentiva_email_context', $context, $key );
		$ctx  = apply_filters( 'mhm_rentiva_email_context_' . $key, $ctx );
		if ( $path ) {
			ob_start();
			$data = $ctx;
			include $path;
			$html = ob_get_clean();
		} else {
			// Fallback: render a simple message if no file found (or use override body)
			$html     = '';
			$override = self::getBodyOverride( $key, $ctx );
			if ( $override ) {
				$html = $override;
			}
			if ( ! $html ) {
				// Default fallback
				$html = '<p>' . esc_html__( 'No content available for this email.', 'mhm-rentiva' ) . '</p>';
			}
		}

		// Filter valid HTML
		$html = apply_filters( 'mhm_rentiva_email_body', $html, $key, $ctx );

		// Check if allow partials or strict full HTML
		// If the template does NOT start with <!DOCTYPE or <html, we wrap it.
		if ( stripos( $html, '<html' ) === false ) {
			$subject = self::compile_subject( $key, $ctx ); // Re-compile subject ensuring context usage
			$html    = self::wrapWithLayout( $ctx, $subject, $html );
		}

		return $html;
	}

	/**
	 * Wrap inner HTML with the standard modern email layout
	 */
	/**
	 * Wrap inner HTML with the standard modern email layout
	 */
	public static function wrapWithLayout( array $context, string $subject, string $innerHtml ): string {
		$siteName = (string) ( $context['site']['name'] ?? get_bloginfo( 'name' ) );
		$title    = esc_html( $subject );
		$brand    = esc_html( $siteName );
		// Branding settings
		$baseColor   = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_base_color();
		$headerImage = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_header_image();
		$footerText  = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_footer_text();

		// Ensure some contrast logic if needed, but for now just use base color

		// Basic sanitized inner HTML (allow common tags)
		$allowed = array(
			'a'      => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'b'      => array(),
			'strong' => array(),
			'em'     => array(),
			'i'      => array(),
			'u'      => array(),
			'p'      => array( 'style' => array() ),
			'br'     => array(),
			'span'   => array( 'style' => array() ),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'h1'     => array(),
			'h2'     => array(),
			'h3'     => array(),
			'table'  => array(
				'border'      => array(),
				'cellpadding' => array(),
				'cellspacing' => array(),
				'width'       => array(),
				'style'       => array(),
			),
			'tr'     => array(),
			'td'     => array( 'style' => array() ),
			'th'     => array( 'style' => array() ),
			'img'    => array(
				'src'    => array(),
				'alt'    => array(),
				'width'  => array(),
				'height' => array(),
				'style'  => array(),
			),
		);
		$content = wp_kses( $innerHtml, $allowed );

		ob_start();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>

		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
					line-height: 1.6;
					color: #333;
					margin: 0;
					padding: 20px;
					background: #f5f5f5;
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
					background: <?php echo esc_attr( $baseColor ); ?>;
					background: linear-gradient(135deg, <?php echo esc_attr( $baseColor ); ?> 0%, <?php echo esc_attr( $baseColor ); ?> 100%);
					color: white;
					padding: 30px;
					text-align: center;
				}

				.header h1 {
					margin: 0;
					font-size: 22px;
				}

				.header-logo {
					max-height: 80px;
					max-width: 200px;
					margin-bottom: 15px;
				}

				.content {
					padding: 30px;
					text-align: left;
				}

				.footer {
					background: #f8f9fa;
					padding: 20px;
					text-align: center;
					font-size: 12px;
					color: #888;
				}
			</style>
		</head>

		<body>
			<div class="container">
				<div class="header">
					<?php if ( ! empty( $headerImage ) ) : ?>
						<img src="<?php echo esc_url( $headerImage ); ?>" alt="<?php echo esc_attr( $brand ); ?>" class="header-logo">
					<?php endif; ?>
					<h1><?php echo esc_html( $title ); ?></h1>
				</div>
				<div class="content">
					<?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $content; // already kses-filtered
					?>
				</div>
				<div class="footer">
					<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $brand ); ?>. <?php esc_html_e( 'All rights reserved.', 'mhm-rentiva' ); ?></p>
					<?php if ( ! empty( $footerText ) ) : ?>
						<p><?php echo wp_kses_post( $footerText ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</body>

		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Try to pull a subject override from settings for the given key
	 */
	private static function getSubjectOverride( string $key, array $context ): ?string {
		$base = self::$overrideMap[ $key ] ?? '';
		if ( $base === '' ) {
			// Check direct new keys if not in overrideMap
		}

		// Special cases where option keys differ
		switch ( $key ) {

			case 'refund_customer':
				$opt = 'mhm_rentiva_refund_customer_subject';
				break;
			case 'refund_admin':
				$opt = 'mhm_rentiva_refund_admin_subject';
				break;
			case 'booking_created_admin':
				$opt = 'mhm_rentiva_booking_admin_subject';
				break;
			case 'booking_created_customer':
				$opt = 'mhm_rentiva_booking_created_subject';
				break;
			case 'booking_status_changed_customer':
				$opt = 'mhm_rentiva_booking_status_subject';
				break;
			case 'booking_status_changed_admin':
				$opt = 'mhm_rentiva_booking_status_admin_subject';
				break;
			case 'booking_reminder_customer':
				$opt = 'mhm_rentiva_booking_reminder_subject';
				break;
			case 'welcome_customer':
				$opt = 'mhm_rentiva_welcome_email_subject';
				break;
			case 'auto_cancel':
				$opt = 'mhm_rentiva_auto_cancel_email_subject';
				break;
			case 'message_received_admin':
				$opt = 'mhm_rentiva_message_received_admin_subject';
				break;
			case 'message_replied_customer':
				$opt = 'mhm_rentiva_message_replied_customer_subject';
				break;
			case 'message_auto_reply':
				$opt = 'mhm_rentiva_message_auto_reply_subject';
				break;
			default:
				$opt = '';
		}
		if ( $opt === '' ) {
			return null;
		}
		$raw = get_option( $opt, '' );
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( $raw === '' ) {
			return null;
		}
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		$tpl = __( $raw, 'mhm-rentiva' );
		return self::replace_placeholders( $tpl, $context );
	}

	/**
	 * Try to pull a body override (HTML) from settings for the given key
	 */
	private static function getBodyOverride( string $key, array $context ): ?string {
		switch ( $key ) {

			case 'refund_customer':
				$opt = 'mhm_rentiva_refund_customer_body';
				break;
			case 'refund_admin':
				$opt = 'mhm_rentiva_refund_admin_body';
				break;
			case 'booking_created_admin':
				$opt = 'mhm_rentiva_booking_admin_body';
				break;
			case 'booking_created_customer':
				$opt = 'mhm_rentiva_booking_created_body';
				break;
			case 'booking_status_changed_customer':
				$opt = 'mhm_rentiva_booking_status_body';
				break;
			case 'booking_status_changed_admin':
				$opt = 'mhm_rentiva_booking_status_admin_body';
				break;
			case 'booking_reminder_customer':
				$opt = 'mhm_rentiva_booking_reminder_body';
				break;
			case 'welcome_customer':
				$opt = 'mhm_rentiva_welcome_email_body';
				break;
			case 'auto_cancel':
				$opt = 'mhm_rentiva_auto_cancel_email_content';
				break;
			case 'message_received_admin':
				$opt = 'mhm_rentiva_message_received_admin_body';
				break;
			case 'message_replied_customer':
				$opt = 'mhm_rentiva_message_replied_customer_body';
				break;
			case 'message_auto_reply':
				$opt = 'mhm_rentiva_message_auto_reply_body';
				break;
			case 'booking_cancelled':
				$opt = 'mhm_rentiva_booking_cancelled_body';
				break;
			default:
				$opt = '';
		}

		// First try DB value
		if ( $opt !== '' ) {
			$raw = get_option( $opt, '' );
			if ( is_string( $raw ) && trim( $raw ) !== '' ) {
				$html = self::replace_placeholders( trim( $raw ), $context );
				return $html;
			}
		}

		// Fallback to EmailSettings centralized defaults
		$default = self::get_default_body_for_key( $key );
		if ( $default !== null ) {
			$html = self::replace_placeholders( $default, $context );
			return $html;
		}

		return null;
	}

	/**
	 * Get default body from EmailSettings for a given template key
	 */
	private static function get_default_body_for_key( string $key ): ?string {
		switch ( $key ) {
			case 'booking_created_admin':
				return EmailSettings::get_default_admin_notification_body();
			case 'booking_created_customer':
				return EmailSettings::get_default_customer_confirmation_body();
			case 'auto_cancel':
				return EmailSettings::get_default_auto_cancel_body();
			case 'refund_customer':
				return EmailSettings::get_default_refund_customer_body();
			case 'refund_admin':
				return EmailSettings::get_default_refund_admin_body();
			case 'message_received_admin':
				return EmailSettings::get_default_message_admin_body();
			case 'message_replied_customer':
				return EmailSettings::get_default_message_customer_body();
			case 'message_auto_reply':
				return EmailSettings::get_default_message_auto_reply_body();
			case 'booking_cancelled':
				return EmailSettings::get_default_booking_cancelled_body();
			default:
				return null;
		}
	}

	public static function replace_placeholders( string $tpl, array $context ): string {
		// Pass 1: {{dot.path}} format
		$out = preg_replace_callback(
			'/\{\{\s*([a-zA-Z0-9_\.\-]+)\s*\}\}/',
			function ( $m ) use ( $context ) {
				$path = (string) $m[1];
				$val  = self::get_context_value( $context, $path );
				if ( is_scalar( $val ) ) {
					return (string) $val;
				}
				if ( is_object( $val ) && method_exists( $val, '__toString' ) ) {
					return (string) $val;
				}
				return '';
			},
			$tpl
		);

		// Pass 2: {snake_case_or_dot} format (admin UI uses single braces)
		$map = array(
			'site_name'      => 'site.name',
			'site_url'       => 'site.url',
			'my_account_url' => '_special.my_account_url', // Special handler
			'contact_name'   => 'customer.name',
			'contact_email'  => 'customer.email',
			'booking_id'     => 'booking.id',
			'order_id'       => 'booking.id', // Alias for backward compatibility
			'vehicle_title'  => 'vehicle.title',
			'pickup_date'    => 'booking.pickup_date',
			'dropoff_date'   => 'booking.return_date',
			'return_date'    => 'booking.return_date',
			'total_price'    => 'booking.total_price',
			'status'         => 'booking.status',
			'message_body'   => 'message.content',
			'reply_body'     => 'message.content',
			'customer_name'  => 'customer.name',
		);

		$out = preg_replace_callback(
			'/\{\s*([a-zA-Z0-9_\.\-]+)\s*\}/',
			function ( $m ) use ( $context, $map ) {
				$token = (string) $m[1];
				$path  = $map[ $token ] ?? str_replace( '_', '.', $token );

				// Special handling for my_account_url
				if ( $token === 'my_account_url' ) {
					return self::get_my_account_url();
				}

				$val = self::get_context_value( $context, $path );

				// Special formatting for total_price - add currency
				if ( $token === 'total_price' && is_numeric( $val ) ) {
					return self::format_price( (float) $val );
				}

				if ( is_scalar( $val ) ) {
					return (string) $val;
				}
				if ( is_object( $val ) && method_exists( $val, '__toString' ) ) {
					return (string) $val;
				}
				return '';
			},
			$out
		);

		return $out;
	}

	/**
	 * Format price with currency symbol
	 */
	private static function format_price( float $amount ): string {
		// Use WooCommerce price formatting if available
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( \wc_price( $amount ) );
		}

		// Fallback: Use plugin currency settings
		$currency_symbol = apply_filters( 'mhm_rentiva/currency_symbol', '₺' );
		$decimals        = 2;
		$dec_sep         = ',';
		$thousands_sep   = '.';

		return $currency_symbol . number_format( $amount, $decimals, $dec_sep, $thousands_sep );
	}

	/**
	 * Get WooCommerce My Account page URL
	 */
	private static function get_my_account_url(): string {
		// Try WooCommerce function first
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = \wc_get_page_permalink( 'myaccount' );
			if ( $url ) {
				return $url;
			}
		}

		// Fallback: Get from WooCommerce option
		$my_account_id = get_option( 'woocommerce_myaccount_page_id' );
		if ( $my_account_id && $my_account_id > 0 ) {
			$url = get_permalink( $my_account_id );
			if ( $url ) {
				return $url;
			}
		}

		// Final fallback
		return home_url( '/my-account/' );
	}



	private static function get_context_value( array $ctx, string $path ) {
		$parts = explode( '.', $path );
		$cur   = $ctx;
		foreach ( $parts as $p ) {
			if ( is_array( $cur ) && array_key_exists( $p, $cur ) ) {
				$cur = $cur[ $p ];
			} else {
				return null;
			}
		}
		return $cur;
	}
}

