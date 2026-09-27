<?php
/**
 * What else a customer account is, as add-ons report it.
 *
 * @package MHM_Rentiva
 */

declare( strict_types=1 );

namespace MHMRentiva\Admin\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Badges beside a customer's name on the Customers screen.
 *
 * Lite has no badge of its own; the seam is for add-ons. Pro badges a vendor
 * ("Vendor", linking to the vendor's page) -- the same person was on two
 * screens with nothing saying so. Lite does not know what a vendor is; it
 * only renders what it is handed, after checking the shape.
 *
 * Resolved per request, never cached with the list: a badge's link depends on
 * what the current viewer may open.
 */
final class CustomerBadges {

	/**
	 * Well-formed badges for the account.
	 *
	 * @param int $user_id Account.
	 * @return array<int, array{key: string, label: string, url: string}>
	 */
	public static function for_user( int $user_id ): array {
		/**
		 * Badges to show beside a customer on the Customers screen.
		 *
		 * Each badge: `key` (becomes a CSS class suffix), `label` (visible
		 * text), `url` (optional http(s) link; '' for none).
		 *
		 * @param array<int, array{key: string, label: string, url: string}> $badges  Default none.
		 * @param int                                                         $user_id Account.
		 */
		$raw = apply_filters( 'mhmrentiva_customer_badges', array(), $user_id );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$badges = array();
		foreach ( $raw as $badge ) {
			if ( ! is_array( $badge ) ) {
				continue;
			}
			$label = is_scalar( $badge['label'] ?? null ) ? trim( (string) $badge['label'] ) : '';
			$key   = is_scalar( $badge['key'] ?? null ) ? sanitize_html_class( (string) $badge['key'] ) : '';
			if ( '' === $label || '' === $key ) {
				continue;
			}
			$url = is_scalar( $badge['url'] ?? null ) ? (string) $badge['url'] : '';

			$badges[] = array(
				'key'   => $key,
				'label' => $label,
				// Rendered as an href by two clients; only http(s) may pass.
				'url'   => '' === $url ? '' : esc_url_raw( $url, array( 'http', 'https' ) ),
			);
		}

		return $badges;
	}
}
