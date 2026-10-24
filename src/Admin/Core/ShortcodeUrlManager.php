<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}



// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global Shortcode URL Manager
 *
 * Dynamic URL management for all shortcodes.
 * Provides a robust way to locate pages containing specific shortcodes.
 *
 * @since 4.0.0
 */
final class ShortcodeUrlManager {



	/**
	 * Cache group for shortcode mappings.
	 */
	private const CACHE_GROUP = 'mhmrentiva_shortcodes';

	/**
	 * Rentiva Elementor widget names (get_name()) per shortcode. A page built with
	 * Elementor keeps these in `_elementor_data`, not in post_content.
	 */
	private const ELEMENTOR_WIDGETS = array(
		'rentiva_availability_calendar' => array( 'rv-availability-calendar' ),
		'rentiva_booking_form'          => array( 'rv-booking-form' ),
		'rentiva_contact'               => array( 'rv-contact-form' ),
		'rentiva_featured_vehicles'     => array( 'mhmrentiva_featured_vehicles' ),
		'rentiva_my_bookings'           => array( 'rv-my-bookings' ),
		'rentiva_my_favorites'          => array( 'rv-my-favorites' ),
		'rentiva_payment_history'       => array( 'rv-payment-history' ),
		'rentiva_search_results'        => array( 'rv-search-results' ),
		'rentiva_testimonials'          => array( 'rv-testimonials' ),
		'rentiva_unified_search'        => array( 'rv-vehicle-search' ),
		'rentiva_user_dashboard'        => array( 'rv-user-dashboard' ),
		'rentiva_vehicle_comparison'    => array( 'rv-vehicle-comparison' ),
		'rentiva_vehicle_details'       => array( 'rv-vehicle-details' ),
		'rentiva_vehicle_rating_form'   => array( 'rv-vehicle-rating' ),
		'rentiva_vehicles_grid'         => array( 'mhmrentiva_vehicles_grid' ),
		'rentiva_vehicles_list'         => array( 'mhmrentiva_vehicles_list' ),
	);

	/**
	 * Static cache for the current request.
	 *
	 * @var array<string, string>
	 */
	private static array $runtime_cache = array();

	/**
	 * Missing shortcodes to be notified in admin.
	 *
	 * @var array<string>
	 */
	private static array $missing_notices = array();

	/**
	 * Get page URL for a specific shortcode.
	 *
	 * @param string $shortcode Shortcode name (e.g. 'rentiva_my_account')
	 * @return string Validated Page URL
	 */
	public static function get_page_url( string $shortcode ): string {
		// 1. Check runtime cache
		if ( isset( self::$runtime_cache[ $shortcode ] ) ) {
			return self::$runtime_cache[ $shortcode ];
		}

		// 2. PRIORITY: Check Plugin Settings for manual URL override
		$settings    = get_option( 'mhmrentiva_settings', array() );
		$setting_key = self::get_setting_key_for_shortcode( $shortcode );

		if ( $setting_key && ! empty( $settings[ $setting_key ] ) ) {
			$url                               = esc_url( (string) $settings[ $setting_key ] );
			self::$runtime_cache[ $shortcode ] = $url;
			return $url;
		}

		// 3. PHYSICAL PAGE (High Priority): Find page containing the shortcode/block in DB
		// This ensures translated pages (e.g. /favorilerim/) take precedence over dynamic endpoints.
		$page_id = self::get_page_id( $shortcode );

		if ( $page_id ) {
			$url                               = (string) get_permalink( $page_id );
			self::$runtime_cache[ $shortcode ] = $url;
			return $url;
		}

		// 4. DYNAMIC ENDPOINT: Allow external modules (e.g., WooCommerceIntegration)
		/**
		 * Filter the URL for a specific shortcode.
		 *
		 * @since 4.0.0
		 * @param string|null $dynamic_url The URL provided by external modules. Default null.
		 * @param string      $shortcode   The shortcode tag being requested.
		 */
		$dynamic_url = apply_filters( 'mhmrentiva_shortcode_url', null, $shortcode );

		if ( ! empty( $dynamic_url ) ) {
			// Ensure it's a valid URL
			$url                               = esc_url( (string) $dynamic_url );
			self::$runtime_cache[ $shortcode ] = $url;
			return $url;
		}

		// 5. FINAL FALLBACK: Register missing notice and return home
		self::register_missing_notice( $shortcode );

		$fallback                          = home_url( '/' );
		self::$runtime_cache[ $shortcode ] = $fallback;
		return $fallback;
	}

	/**
	 * A published page built with Elementor that holds the shortcode's Rentiva
	 * widget, or the shortcode itself inside an Elementor shortcode/text widget.
	 * Asked only after post_content found nothing, so a classic page still wins.
	 * Only pages Elementor still renders (_elementor_edit_mode = builder) count: a
	 * page switched back to the classic editor keeps its stale _elementor_data.
	 *
	 * @param string $shortcode Shortcode name.
	 * @return int Page ID, 0 when none.
	 */
	private static function find_elementor_page( string $shortcode ): int {
		global $wpdb;

		/**
		 * Elementor widget names (get_name()) that render this shortcode, so an
		 * add-on can name its own widgets.
		 *
		 * @param string[] $names     Widget names.
		 * @param string   $shortcode Shortcode tag.
		 */
		$names = (array) apply_filters( 'mhmrentiva_elementor_widgets_for_shortcode', self::ELEMENTOR_WIDGETS[ $shortcode ] ?? array(), $shortcode );
		$names = array_values( array_filter( array_map( 'strval', $names ), static fn ( string $n ): bool => 1 === preg_match( '/^[a-z0-9_-]+$/', $n ) ) );

		$regex = '\[' . $shortcode . '(\]| |=)';
		if ( array() !== $names ) {
			$regex .= '|"widgetType":"(' . implode( '|', $names ) . ')"';
		}

		$page_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
                  INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
                  INNER JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = '_elementor_edit_mode' AND e.meta_value = 'builder'
                  WHERE p.post_type = 'page'
                  AND p.post_status = 'publish'
                  AND m.meta_value REGEXP %s
                  ORDER BY p.post_date DESC
                  LIMIT 1",
				$regex
			)
		);

		return $page_id ? (int) $page_id : 0;
	}

	/**
	 * Find page ID containing the shortcode OR Gutenberg block.
	 *
	 * @param string $shortcode Shortcode name.
	 * @return int|null Page ID or null if not found.
	 */
	public static function get_page_id( string $shortcode ): ?int {
		global $wpdb;

		$cache_key = 'page_id_' . $shortcode;
		$cached_id = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached_id ) {
			// Validate that the cached page still exists and is published
			if ( $cached_id > 0 && 'publish' === get_post_status( $cached_id ) ) {
				return (int) $cached_id;
			}
			// Cache is stale or invalid, clear it and force re-query
			wp_cache_delete( $cache_key, self::CACHE_GROUP );
		}

		// Calculate Block Slug (e.g. rentiva_vehicles_grid -> vehicles-grid)
		$block_slug = str_replace( '_', '-', str_replace( 'rentiva_', '', $shortcode ) );

		// Search for shortcode ([rentiva_...]) OR Gutenberg Block (<!-- wp:mhm-rentiva/... -->)
		// Regex explanation:
		// 1. \[shortcode(\]| |=)  -> Matches [shortcode] or [shortcode attr=...]
		// 2. <!-- wp:mhm-rentiva/slug + whitespace or / -> the whole block name, never a prefix
		//    (mhm-rentiva/search must not match mhm-rentiva/search-results)
		$regex = '\[' . $shortcode . '(\]| |=)|<!-- wp:mhm-rentiva/' . $block_slug . '[[:space:]/]';

		$page_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
                  WHERE post_type = 'page' 
                  AND post_status = 'publish' 
                  AND post_content REGEXP %s
                  ORDER BY post_date DESC 
                  LIMIT 1",
				$regex
			)
		);

		$page_id = $page_id ? (int) $page_id : self::find_elementor_page( $shortcode );
		wp_cache_set( $cache_key, $page_id, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $page_id > 0 ? $page_id : null;
	}

	/**
	 * Get all supported shortcodes.
	 *
	 * @return array<string>
	 */
	public static function get_all_shortcodes(): array {
		$shortcodes = array(
			'rentiva_my_bookings',
			'rentiva_my_favorites',
			'rentiva_payment_history',
			'rentiva_booking_form',
			'rentiva_availability_calendar',
			'rentiva_vehicle_details',
			'rentiva_vehicles_grid',
			'rentiva_vehicles_list',
			'rentiva_vehicle_comparison',
			'rentiva_unified_search',
			'rentiva_search_results',
			'rentiva_contact',
			'rentiva_testimonials',
			'rentiva_vehicle_rating_form',
			'rentiva_featured_vehicles',
		);

		return array_values( apply_filters( 'mhmrentiva_shortcode_url_tags', $shortcodes ) );
	}

	/**
	 * Check if a page exists for a specific shortcode.
	 *
	 * @param string $shortcode
	 * @return bool
	 */
	public static function page_exists( string $shortcode ): bool {
		return self::get_page_id( $shortcode ) !== null;
	}

	/**
	 * Get all pages containing supported shortcodes.
	 *
	 * @return array<string, array{id: int|null, url: string}>
	 */
	public function get_all_pages(): array {
		$pages = array();
		foreach ( self::get_all_shortcodes() as $shortcode ) {
			$page_id = self::get_page_id( $shortcode );
			$url     = self::get_page_url( $shortcode );

			// Check if URL is custom (not just the fallback home URL)
			$is_fallback = ( $url === home_url( '/' ) );
			$has_url     = ! empty( $url ) && ! $is_fallback;

			$pages[ $shortcode ] = array(
				'id'      => $page_id,
				'url'     => $url,
				'has_url' => $has_url, // Flag to indicate valid URL exists even if ID is null
			);
		}
		return $pages;
	}

	/**
	 * Map shortcodes to setting keys.
	 *
	 * @param string $shortcode
	 * @return string|null
	 */
	private static function get_setting_key_for_shortcode( string $shortcode ): ?string {
		$mapping = array(
			'rentiva_booking_form'          => 'mhmrentiva_booking_url',
			'rentiva_my_bookings'           => 'mhmrentiva_my_bookings_url',
			'rentiva_my_favorites'          => 'mhmrentiva_my_favorites_url',
			'rentiva_payment_history'       => 'mhmrentiva_payment_history_url',
			'rentiva_vehicles_list'         => 'mhmrentiva_vehicles_list_url',
			'rentiva_vehicles_grid'         => 'mhmrentiva_vehicles_grid_url',
			'rentiva_unified_search'        => 'mhmrentiva_unified_search_url',
			'rentiva_search_results'        => 'mhmrentiva_search_results_url',
			'rentiva_contact'               => 'mhmrentiva_contact_url',
			'rentiva_availability_calendar' => 'mhmrentiva_availability_calendar_url',
		);

		return $mapping[ $shortcode ] ?? null;
	}

	/**
	 * Queue a missing shortcode notice for admin display.
	 *
	 * @param string $shortcode
	 */
	private static function register_missing_notice( string $shortcode ): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$transient_key = 'mhmrentiva_miss_sc_' . md5( $shortcode );
		if ( get_transient( $transient_key ) ) {
			return;
		}

		self::$missing_notices[] = $shortcode;

		// Ensure the hook is added only once.
		if ( ! has_action( 'admin_notices', array( self::class, 'display_admin_notices' ) ) ) {
			add_action( 'admin_notices', array( self::class, 'display_admin_notices' ) );
		}

		set_transient( $transient_key, true, HOUR_IN_SECONDS );
	}

	/**
	 * Display collected admin notices.
	 *
	 * @internal Hooked to admin_notices
	 */
	public static function display_admin_notices(): void {
		foreach ( array_unique( self::$missing_notices ) as $shortcode ) {
			echo '<div class="notice notice-warning is-dismissible">';
			echo '<p><strong>' . esc_html__( 'MHM Rentiva:', 'mhm-rentiva' ) . '</strong> ';
			printf(
				/* translators: %s: shortcode name */
				esc_html__( 'No page found containing the [%s] shortcode. ', 'mhm-rentiva' ),
				'<code>' . esc_html( $shortcode ) . '</code>'
			);
			echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=page' ) ) . '">';
			echo esc_html__( 'Create a new page and add the shortcode.', 'mhm-rentiva' );
			echo '</a>';
			echo '</p>';
			echo '</div>';
		}
	}

	/**
	 * Clear all or specific shortcode cache.
	 *
	 * @param string|null $shortcode Specific shortcode or null for all.
	 */
	public static function clear_cache( ?string $shortcode = null ): void {
		$shortcodes = $shortcode ? array( $shortcode ) : self::get_all_shortcodes();

		foreach ( $shortcodes as $sc ) {
			wp_cache_delete( 'page_id_' . $sc, self::CACHE_GROUP );
			delete_transient( 'mhmrentiva_miss_sc_' . md5( $sc ) );
		}

		self::$runtime_cache = array();
	}

	/**
	 * Invalidate cache on page updates if shortcodes are detected.
	 *
	 * @param int $post_id
	 */
	public static function clear_cache_on_page_update( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || get_post_type( $post_id ) !== 'page' ) {
			return;
		}

		$content = get_post_field( 'post_content', $post_id );
		if ( empty( $content ) ) {
			return;
		}

		foreach ( self::get_all_shortcodes() as $shortcode ) {
			if ( has_shortcode( $content, $shortcode ) ) {
				self::clear_cache( $shortcode );
			}
		}
	}
}
