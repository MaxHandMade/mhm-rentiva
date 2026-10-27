<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which asset class a My Account screen belongs to (PR D, r3 Y1).
 *
 * Three classes:
 *
 *   rvs     Lite's own redesigned screens: the dashboard, bookings, booking
 *           detail, payment history and favorites. They load the `rvs-` account
 *           layer and none of the old account stylesheets.
 *   wc      WooCommerce's own endpoints (orders, view-order, edit-account,
 *           edit-address, downloads, payment-methods ...) and any endpoint we
 *           do not know. Only the page shell and navigation layer loads, so the
 *           page keeps one layout while WooCommerce styles its own content.
 *   legacy  Endpoints an extension contributes through
 *           `mhmrentiva_account_endpoints` (the add-on's messages and vendor
 *           screens). Their templates still use the old account stylesheets,
 *           so those load on top of the navigation layer.
 *
 * @since 6.4.0
 */
final class AccountSurface {

	public const RVS    = 'rvs';
	public const WC     = 'wc';
	public const LEGACY = 'legacy';

	/**
	 * Lite endpoint keys (EndpointHelperTrait::get_rentiva_endpoints_map())
	 * rendered on the `rvs-` layer. The dashboard is `null`.
	 *
	 * @var array<int, string>
	 */
	private const RVS_KEYS = array( 'bookings', 'view_booking', 'payment_history', 'favorites' );

	/**
	 * Classify an endpoint key.
	 *
	 * @param string|null $endpoint_key A Lite endpoint key (`bookings`,
	 *                                  `view_booking` ...), `null` for the
	 *                                  dashboard, or the raw WooCommerce endpoint
	 *                                  slug of any other screen.
	 * @return string One of self::RVS, self::WC, self::LEGACY.
	 */
	public static function classify( ?string $endpoint_key ): string {
		if ( null === $endpoint_key || '' === $endpoint_key || 'dashboard' === $endpoint_key ) {
			return self::RVS;
		}

		if ( in_array( $endpoint_key, self::RVS_KEYS, true ) ) {
			return self::RVS;
		}

		if ( in_array( sanitize_title( $endpoint_key ), self::extension_endpoints(), true ) ) {
			return self::LEGACY;
		}

		return self::WC;
	}

	/**
	 * Asset class of an account shortcode, by tag. Lite's own account
	 * shortcodes are the redesigned screens; every other tag that reuses the
	 * account base class (the add-on's messages and vendor bookings) keeps the
	 * old stylesheets.
	 */
	public static function classify_shortcode( string $tag ): string {
		foreach ( WooCommerceIntegration::get_rentiva_endpoints_map() as $key => $config ) {
			if ( ! empty( $config['shortcode'] ) && $config['shortcode'] === $tag ) {
				return self::classify( (string) $key );
			}
		}

		return self::LEGACY;
	}

	/**
	 * The current My Account screen as an endpoint key: a Lite key for Lite's
	 * endpoints, `null` for the dashboard, the raw endpoint slug otherwise.
	 *
	 * On WooCommerce the answer comes from WooCommerce's own query
	 * (WC_Query::get_current_endpoint()); Lite's endpoint slugs are translated
	 * and configurable, so they are mapped back to their keys here. Without
	 * WooCommerce the standalone endpoints are read off the query vars.
	 */
	public static function current_endpoint_key(): ?string {
		$map = WooCommerceIntegration::get_rentiva_endpoints_map();

		if ( function_exists( 'WC' ) && WC()->query instanceof \WC_Query ) {
			$endpoint = (string) WC()->query->get_current_endpoint();
			if ( '' === $endpoint ) {
				return null;
			}

			foreach ( array_keys( $map ) as $key ) {
				if ( WooCommerceIntegration::get_endpoint_slug( (string) $key ) === $endpoint ) {
					return (string) $key;
				}
			}

			return $endpoint;
		}

		// An endpoint without a value sets its query var to '' -- present, so
		// isset() and not get_query_var(), which answers '' for an absent one too.
		global $wp_query;
		$vars = ( $wp_query instanceof \WP_Query ) ? (array) $wp_query->query_vars : array();

		foreach ( array_keys( $map ) as $key ) {
			if ( isset( $vars[ WooCommerceIntegration::get_endpoint_slug( (string) $key ) ] ) ) {
				return (string) $key;
			}
		}

		foreach ( self::extension_endpoints() as $slug ) {
			if ( isset( $vars[ $slug ] ) ) {
				return $slug;
			}
		}

		return null;
	}

	/**
	 * Endpoint slugs extensions contribute, sanitized the way
	 * WooCommerceIntegration registers them.
	 *
	 * @return array<int, string>
	 */
	private static function extension_endpoints(): array {
		$slugs = array();
		foreach ( (array) apply_filters( 'mhmrentiva_account_endpoints', array() ) as $slug ) {
			if ( is_string( $slug ) && '' !== $slug ) {
				$slugs[] = sanitize_title( $slug );
			}
		}
		return $slugs;
	}
}
