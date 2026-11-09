<?php
/**
 * Suggested privacy policy text for the site owner.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings → Privacy → Policy guide entry (5a spec T8). The site owner copies
 * what applies; the `privacy-policy-tutorial` paragraph is guidance for them
 * and is left out when copied.
 */
final class PolicyText {

	/**
	 * Hooked on admin_init (core: suggestions outside admin_init are rejected).
	 *
	 * @return void
	 */
	public static function add(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content( 'MHM Rentiva', wp_kses_post( wpautop( self::html(), false ) ) );
	}

	/**
	 * The suggested text.
	 *
	 * @return string
	 */
	public static function html(): string {
		$p = static fn( string $text ): string => '<p>' . $text . '</p>';

		return '<p class="privacy-policy-tutorial">' . esc_html__( 'MHM Rentiva stores the details below for vehicle rentals. Keep what applies to your site and adjust the retention periods to your own settings.', 'mhm-rentiva' ) . '</p>'
			. '<h2>' . esc_html__( 'Bookings', 'mhm-rentiva' ) . '</h2>'
			. $p( esc_html__( 'When you book a vehicle we store your name, e-mail address, phone number, the rental dates, the vehicle, any notes you add and your payment status. Payments are taken through our WooCommerce payment provider; we do not store card details.', 'mhm-rentiva' ) )
			. $p( esc_html__( 'Bookings are kept for our accounts. When you ask us to erase your data, your name, contact details and notes are removed from completed, cancelled and refunded bookings; dates, vehicle and amounts stay. Bookings that are still pending, confirmed or in progress are kept until they end.', 'mhm-rentiva' ) )
			. '<h2>' . esc_html__( 'Contact form', 'mhm-rentiva' ) . '</h2>'
			. $p( esc_html__( 'When you send us a message we store your name, e-mail address, phone number, company, the message, any file you attach, your IP address and your browser, to answer you and to keep out spam.', 'mhm-rentiva' ) )
			. '<h2>' . esc_html__( 'E-mails and logs', 'mhm-rentiva' ) . '</h2>'
			. $p( esc_html__( 'If e-mail logging is on, we keep a record of the e-mails sent about your booking, including your contact details. Our application log may record your account ID, IP address and browser. Both are deleted after the periods set on this site.', 'mhm-rentiva' ) )
			. '<h2>' . esc_html__( 'Your account', 'mhm-rentiva' ) . '</h2>'
			. $p( esc_html__( 'With an account we also store your phone number, address, favourite vehicles, your notification choices and when you last signed in.', 'mhm-rentiva' ) )
			. '<p class="privacy-policy-tutorial">' . esc_html__( 'Database backups made with the MHM Rentiva database tools contain this data and are not changed by an erasure request. Delete old backups yourself.', 'mhm-rentiva' ) . '</p>';
	}
}
