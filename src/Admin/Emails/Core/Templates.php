<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

if (!defined('ABSPATH')) {
    exit;
}


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Settings\Groups\EmailSettings;

final class Templates {


	/**
	 * Template registry with translatable subjects.
	 *
	 * Each entry's 'subject' is translated once, via a literal __() call, at its
	 * definition site in self::registry() below — never re-translated later.
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
					'subject' => __( 'Booking #{{booking.order_id}} Confirmed - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-created-customer',
				),
				'booking_created_admin'           => array(
					'subject' => __( 'New Booking Request #{{booking.order_id}} - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-created-admin',
				),
				'booking_status_changed_customer' => array(
					'subject' => __( 'Booking #{{booking.order_id}} Status Updated - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-status-changed-customer',
				),
				'booking_status_changed_admin'    => array(
					'subject' => __( 'Booking #{{booking.order_id}} Status Updated - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-status-changed-admin',
				),
				'booking_reminder_customer'       => array(
					'subject' => __( 'Reminder: Your Booking #{{booking.order_id}} Starts Soon - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-reminder-customer',
				),
				'remaining_payment_link_customer' => array(
					'subject' => __( 'Complete Your Payment - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'remaining-payment-link-customer',
				),
				// Welcome Email (One-time)
				'welcome_customer'                => array(
					'subject' => __( 'Welcome to {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'welcome-customer',
				),

				// Manual Cancel
				'booking_cancelled'               => array(
					'subject' => __( 'Booking #{{booking.order_id}} Cancelled - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-cancelled',
				),

				// Auto Cancel
				'auto_cancel'                     => array(
					'subject' => __( 'Booking #{{booking.order_id}} Cancelled - {{site.name}}', 'mhm-rentiva' ),
					'file'    => 'booking-cancelled',
				),

				// Refund templates
				'refund_customer'                 => array(
					'subject' => __( 'Refund Processed for Booking #{{booking.order_id}}', 'mhm-rentiva' ),
					'file'    => 'refund-customer',
				),
				'refund_admin'                    => array(
					'subject' => __( 'Refund Alert: Booking #{{booking.order_id}}', 'mhm-rentiva' ),
					'file'    => 'refund-admin',
				),
			);
		}

		return apply_filters( 'mhmrentiva_email_registry', $registry );
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
		$plugin = MHMRENTIVA_PLUGIN_PATH . 'templates/emails/' . $slug . '.html.php';
		if ( file_exists( $plugin ) ) {
			return $plugin;
		}
		// Additional template directories registered by other extensions (e.g. an
		// active extension ships its own email templates and registers its path here).
		foreach ( apply_filters( 'mhmrentiva_email_template_dirs', array() ) as $dir ) {
			$candidate = trailingslashit( (string) $dir ) . $slug . '.html.php';
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
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
		// $tpl is already translated: each registry entry's 'subject' passes through a
		// literal __() call at definition time in self::registry() (or, if the key is
		// unknown, a dynamic 'Notification: {key}' fallback that was never
		// translatable). Re-wrapping either in __() here would be invalid i18n
		// (variable first argument) — pass the value through as-is.
		$tpl = $reg[ $key ]['subject'] ?? ( 'Notification: ' . $key );
		$sub = self::replace_placeholders( $tpl, $context );
		$sub = apply_filters( 'mhmrentiva_email_subject', $sub, $key, $context );
		$sub = apply_filters( 'mhmrentiva_email_subject_' . $key, $sub, $context );
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
			$html = apply_filters( 'mhmrentiva_email_body', $html, $key, $context );
			$html = apply_filters( 'mhmrentiva_email_body_' . $key, $html, $context );
			return $html;
		}

		$reg  = self::registry();
		$slug = $reg[ $key ]['file'] ?? $key;
		$path = self::locate_template( $slug );
		$ctx  = apply_filters( 'mhmrentiva_email_context', $context, $key );
		$ctx  = apply_filters( 'mhmrentiva_email_context_' . $key, $ctx );
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
		$html = apply_filters( 'mhmrentiva_email_body', $html, $key, $ctx );

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
		// This value is interpolated into the <style> block below. A colour that
		// lands inside a stylesheet cannot be made safe by esc_attr(): that leaves
		// `;` and `}` intact, so a malformed value would close the declaration and
		// open a rule of its own -- the same mismatch that applies to AssetManager's
		// inline :root block. Validate it to a hex colour,
		// which is the only thing this setting is ever meant to hold, and fall back
		// to the default rather than emitting an empty declaration.
		$baseColor   = sanitize_hex_color( (string) \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_base_color() ) ?: '#1e88e5';
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
		// Compute a darker gradient end-stop from the base color
		$gradientEnd = $baseColor;
		$hexVal      = ltrim( $baseColor, '#' );
		if ( ctype_xdigit( $hexVal ) ) {
			if ( strlen( $hexVal ) === 3 ) {
				$hexVal = $hexVal[0] . $hexVal[0] . $hexVal[1] . $hexVal[1] . $hexVal[2] . $hexVal[2];
			}
			if ( strlen( $hexVal ) === 6 ) {
				$r           = max( 0, hexdec( substr( $hexVal, 0, 2 ) ) - 38 );
				$g           = max( 0, hexdec( substr( $hexVal, 2, 2 ) ) - 38 );
				$b           = max( 0, hexdec( substr( $hexVal, 4, 2 ) ) - 38 );
				$gradientEnd = sprintf( '#%02x%02x%02x', $r, $g, $b );
			}
		}

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
					color: #1a1a1a;
					margin: 0;
					padding: 24px 16px;
					background: #eef2f7;
				}

				.container {
					max-width: 600px;
					margin: 0 auto;
					background: #ffffff;
					border-radius: 10px;
					overflow: hidden;
					box-shadow: 0 4px 24px rgba(0, 0, 0, 0.10);
				}

				.header {
					background: <?php echo sanitize_hex_color( $baseColor ); ?>;
					background: linear-gradient(135deg, <?php echo sanitize_hex_color( $baseColor ); ?> 0%, <?php echo sanitize_hex_color( $gradientEnd ); ?> 100%);
					color: #ffffff;
					padding: 32px 36px;
					text-align: center;
				}

				.header h1 {
					margin: 0;
					font-size: 21px;
					font-weight: 700;
					letter-spacing: -0.2px;
					text-shadow: 0 1px 2px rgba(0,0,0,0.15);
				}

				.header-logo {
					max-height: 72px;
					max-width: 180px;
					margin-bottom: 14px;
					display: block;
					margin-left: auto;
					margin-right: auto;
				}

				.content {
					padding: 32px 36px;
					text-align: left;
				}

				.content p {
					margin: 0 0 16px 0;
					font-size: 15px;
					line-height: 1.7;
					color: #374151;
				}

				.content p:last-child {
					margin-bottom: 0;
				}

				.content strong {
					color: #111827;
				}

				.content table {
					width: 100%;
					border-collapse: collapse;
					margin: 16px 0;
					font-size: 14px;
				}

				.content table td {
					padding: 10px 12px;
					border: 1px solid #e5e7eb;
					vertical-align: top;
				}

				.content table tr:nth-child(even) td {
					background: #f9fafb;
				}

				.cta-button {
					display: inline-block;
					padding: 13px 28px;
					text-decoration: none;
					border-radius: 7px;
					font-weight: 700;
					font-size: 15px;
					letter-spacing: 0.2px;
					line-height: 1;
				}

				.cta-button:hover {
					opacity: 0.88;
				}

				.footer {
					background: #f3f4f6;
					padding: 20px 36px;
					text-align: center;
					font-size: 12px;
					color: #9ca3af;
					border-top: 1px solid #e5e7eb;
				}

				.footer p {
					margin: 4px 0;
					line-height: 1.5;
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
					// Filtered on the line that prints it. This used to be assigned to
					// $content 150 lines up, which left the output site looking like a
					// raw echo to PHPCS and to anyone reading it.
					echo wp_kses( $innerHtml, $allowed );
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
		// Special cases where option keys differ
		switch ( $key ) {

			case 'refund_customer':
				$opt = 'mhmrentiva_refund_customer_subject';
				break;
			case 'refund_admin':
				$opt = 'mhmrentiva_refund_admin_subject';
				break;
			case 'booking_created_admin':
				$opt = 'mhmrentiva_booking_admin_subject';
				break;
			case 'booking_created_customer':
				$opt = 'mhmrentiva_booking_created_subject';
				break;
			case 'booking_status_changed_customer':
				$opt = 'mhmrentiva_booking_status_subject';
				break;
			case 'booking_status_changed_admin':
				$opt = 'mhmrentiva_booking_status_admin_subject';
				break;
			case 'booking_reminder_customer':
				$opt = 'mhmrentiva_booking_reminder_subject';
				break;
			case 'welcome_customer':
				$opt = 'mhmrentiva_welcome_email_subject';
				break;
			case 'auto_cancel':
				$opt = 'mhmrentiva_auto_cancel_email_subject';
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
		// $raw is an admin-entered value stored in a WP option (a custom subject
		// override typed into the settings UI) — dynamic content that was never
		// translatable via gettext. Use it as-is rather than wrapping it in __().
		return self::replace_placeholders( $raw, $context );
	}

	/**
	 * Try to pull a body override (HTML) from settings for the given key
	 */
	private static function getBodyOverride( string $key, array $context ): ?string {
		switch ( $key ) {

			case 'refund_customer':
				$opt = 'mhmrentiva_refund_customer_body';
				break;
			case 'refund_admin':
				$opt = 'mhmrentiva_refund_admin_body';
				break;
			case 'booking_created_admin':
				$opt = 'mhmrentiva_booking_admin_body';
				break;
			case 'booking_created_customer':
				$opt = 'mhmrentiva_booking_created_body';
				break;
			case 'booking_status_changed_customer':
				$opt = 'mhmrentiva_booking_status_body';
				break;
			case 'booking_status_changed_admin':
				$opt = 'mhmrentiva_booking_status_admin_body';
				break;
			case 'booking_reminder_customer':
				$opt = 'mhmrentiva_booking_reminder_body';
				break;
			case 'welcome_customer':
				$opt = 'mhmrentiva_welcome_email_body';
				break;
			case 'auto_cancel':
				$opt = 'mhmrentiva_auto_cancel_email_content';
				break;
			case 'booking_cancelled':
				$opt = 'mhmrentiva_booking_cancelled_body';
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
			case 'booking_cancelled':
				return EmailSettings::get_default_booking_cancelled_body();
			case 'welcome_customer':
				return EmailSettings::get_default_welcome_email_body();
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
				if ( self::is_money_path( $path ) && self::is_renderable_amount( $val ) ) {
					return self::format_price( \MHMRentiva\Admin\Core\CurrencyHelper::to_amount( $val ) );
				}
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
			'booking_id'     => 'booking.order_id', // Shows WooCommerce order ID (customer-facing)
			'order_id'       => 'booking.order_id', // WooCommerce order ID
			'vehicle_title'  => 'vehicle.title',
			'pickup_date'    => 'booking.pickup_date',
			'dropoff_date'   => 'booking.return_date',
			'return_date'    => 'booking.return_date',
			'total_price'    => 'booking.total_price',
			'status'         => 'booking.status',
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

				// Special formatting for total_price - add currency.
				// `is_numeric()` alone used to be the whole gate here, so a
				// producer that handed over a already-formatted string ("1.500,00")
				// fell straight through and the email printed the amount with NO
				// currency symbol at all. Coerce instead of skipping.
				if ( $token === 'total_price' && self::is_renderable_amount( $val ) ) {
					return self::format_price( \MHMRentiva\Admin\Core\CurrencyHelper::to_amount( $val ) );
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
	 * Context paths whose value is money and must be rendered with a currency.
	 *
	 * @param string $path Dot path from the template token.
	 * @return bool
	 */
	private static function is_money_path( string $path ): bool {
		return in_array(
			$path,
			array( 'booking.total_price', 'booking.deposit_amount', 'booking.remaining_amount' ),
			true
		);
	}

	/**
	 * Is this context value an amount worth rendering as money?
	 *
	 * Accepts numbers AND strings a producer already formatted, so a formatted
	 * value is repaired rather than silently printed without a symbol. An absent
	 * or empty value stays empty — an email must not invent a `0,00`.
	 *
	 * @param mixed $val Raw context value.
	 * @return bool
	 */
	private static function is_renderable_amount( $val ): bool {
		if ( is_int( $val ) || is_float( $val ) ) {
			return true;
		}

		// A string only counts when it actually carries digits, so a non-amount
		// placeholder value ("N/A", "") is never turned into a bogus `0,00`.
		return is_string( $val ) && 1 === preg_match( '/\d/', $val );
	}

	/**
	 * Format price with currency symbol
	 */
	private static function format_price( float $amount ): string {
		// Canonical currency formatting. The old WooCommerce-inactive fallback
		// hardcoded both the symbol (`₺`) and a left placement, so an email could
		// contradict every other surface — and the plugin's own currency setting.
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price( $amount, 2 );
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
