<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait EndpointHelperTrait
 *
 * Centralizes endpoint slug resolution logic with static caching.
 */
trait EndpointHelperTrait {

	/**
	 * Static cache for resolved slugs.
	 */
	private static array $slug_cache = array();

	/**
	 * Get endpoint configuration mapping.
	 */
	public static function get_rentiva_endpoints_map(): array {
		return array(
			'bookings'        => array(
				'shortcode' => 'rentiva_my_bookings',
				'default'   => 'rentiva-bookings',
				'label'     => __( 'Vehicle Bookings', 'mhm-rentiva' ),
			),
			'favorites'       => array(
				'shortcode' => 'rentiva_my_favorites',
				'default'   => 'rentiva-favorites',
				'label'     => __( 'Favorite Vehicles', 'mhm-rentiva' ),
			),
			'payment_history' => array(
				'shortcode' => 'rentiva_payment_history',
				'default'   => 'rentiva-payment-history',
				'label'     => __( 'Vehicle Payments', 'mhm-rentiva' ),
			),
			'view_booking'    => array(
				'shortcode' => null, // No direct page for view detail
				'default'   => 'view-rentiva-booking',
				'label'     => __( 'View Booking', 'mhm-rentiva' ),
			),
		);
	}

	/**
	 * Get endpoint slug with priority logic and caching.
	 *
	 * Priority:
	 * 1. Physical page existence (via ShortcodeUrlManager)
	 * 2. Database option (mhmrentiva_settings)
	 * 3. Translation (_x context based)
	 * 4. Hardcoded fallback
	 */
	public static function get_endpoint_slug( string $key, ?string $fallback_default = null ): string {
		// 1. Check runtime cache
		if ( isset( self::$slug_cache[ $key ] ) ) {
			return self::$slug_cache[ $key ];
		}

		$map    = self::get_rentiva_endpoints_map();
		$config = $map[ $key ] ?? null;

		if ( ! $config ) {
			return sanitize_title( $fallback_default ?? $key );
		}

		$default = ! empty($fallback_default) ? $fallback_default : $config['default'];

		// 2. PRIORITY: Physical Page
		if ( ! empty( $config['shortcode'] ) && class_exists( \MHMRentiva\Admin\Core\ShortcodeUrlManager::class ) ) {
			$page_id = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id( $config['shortcode'] );
			if ( $page_id ) {
				$post = get_post( $page_id );
				if ( $post && ! empty( $post->post_name ) ) {
					$slug                     = sanitize_title( $post->post_name );
					self::$slug_cache[ $key ] = $slug;
					return $slug;
				}
			}
		}

		// 3. BACKUP: Database Option
		$settings   = get_option( 'mhmrentiva_settings', array() );
		$option_key = 'mhmrentiva_endpoint_' . $key;
		$user_slug  = $settings[ $option_key ] ?? '';

		if ( ! empty( $user_slug ) ) {
			$slug                     = sanitize_title( $user_slug );
			self::$slug_cache[ $key ] = $slug;
			return $slug;
		}

		// 4. FALLBACK: Translation
		//
		// Resolved in the SITE locale, not the asker's. A rewrite slug is an
		// identifier, and rewrite rules live in one global option with no
		// locale dimension -- so a slug that follows determine_locale() lets an
		// administrator whose profile language differs rewrite the URLs every
		// visitor sees (Trac #40298).
		$translated = \MHMRentiva\Admin\Core\Utilities\SiteLocaleString::resolve(
			static fn (): string => match ( $key ) {
				'bookings'        => _x( 'rentiva-bookings', 'endpoint slug', 'mhm-rentiva' ),
				'favorites'       => _x( 'rentiva-favorites', 'endpoint slug', 'mhm-rentiva' ),
				'payment_history' => _x( 'rentiva-payment-history', 'endpoint slug', 'mhm-rentiva' ),
				'view_booking'    => _x( 'view-rentiva-booking', 'endpoint slug', 'mhm-rentiva' ),
				default           => $default,
			}
		);

		$slug = sanitize_title( $translated );

		// Cached only once translations can be trusted. Before `init` a
		// translated string resolves to its untranslated source, and caching
		// that would hand the English answer to every later caller in the
		// request -- silently, for the whole request.
		if ( did_action( 'init' ) > 0 ) {
			self::$slug_cache[ $key ] = $slug;
		}

		return $slug;
	}

	/**
	 * Whether a slug is currently memoised. Test seam.
	 */
	public static function slug_cache_is_warm( string $key ): bool {
		return isset( self::$slug_cache[ $key ] );
	}

	/**
	 * Clear slug cache
	 */
	public static function clear_slug_cache(): void {
		self::$slug_cache = array();
	}
}
