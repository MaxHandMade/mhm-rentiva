<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

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
	private const CACHE_GROUP = 'mhm_rentiva_shortcodes';

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
		$settings    = get_option( 'mhm_rentiva_settings', array() );
		$setting_key = self::get_setting_key_for_shortcode( $shortcode );

		if ( $setting_key && ! empty( $settings[ $setting_key ] ) ) {
			$url                               = esc_url( (string) $settings[ $setting_key ] );
			self::$runtime_cache[ $shortcode ] = $url;
			return $url;
		}

		// 3. FALLBACK: Find page containing the shortcode in DB
		$page_id = self::get_page_id( $shortcode );

		if ( $page_id ) {
			$url                               = (string) get_permalink( $page_id );
			self::$runtime_cache[ $shortcode ] = $url;
			return $url;
		}

		// 4. FINAL FALLBACK: Register missing notice and return home
		self::register_missing_notice( $shortcode );

		$fallback                          = home_url( '/' );
		self::$runtime_cache[ $shortcode ] = $fallback;
		return $fallback;
	}

	/**
	 * Find page ID containing the shortcode.
	 *
	 * @param string $shortcode Shortcode name.
	 * @return int|null Page ID or null if not found.
	 */
	public static function get_page_id( string $shortcode ): ?int {
		global $wpdb;

		$cache_key = 'page_id_' . $shortcode;
		$cached_id = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached_id ) {
			return $cached_id > 0 ? (int) $cached_id : null;
		}

		// Search specifically for shortcode patterns in published pages.
		// Using a refined LIKE query to minimize false positives.
		$query = "SELECT ID FROM {$wpdb->posts} 
                  WHERE post_type = 'page' 
                  AND post_status = 'publish' 
                  AND (post_content LIKE %s OR post_content LIKE %s)
                  ORDER BY post_date DESC 
                  LIMIT 1";

		$page_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} 
                  WHERE post_type = 'page' 
                  AND post_status = 'publish' 
                  AND (post_content LIKE %s OR post_content LIKE %s)
                  ORDER BY post_date DESC 
                  LIMIT 1",
				'%[' . $wpdb->esc_like( $shortcode ) . ']%',
				'%[' . $wpdb->esc_like( $shortcode ) . ' %]%'
			)
		);

		$page_id = $page_id ? (int) $page_id : 0;
		wp_cache_set( $cache_key, $page_id, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $page_id > 0 ? $page_id : null;
	}

	/**
	 * Get all supported shortcodes.
	 *
	 * @return array<string>
	 */
	public static function get_all_shortcodes(): array {
		return array(
			'rentiva_my_bookings',
			'rentiva_my_favorites',
			'rentiva_payment_history',
			'rentiva_messages',
			'rentiva_booking_form',
			'rentiva_availability_calendar',
			'rentiva_booking_confirmation',
			'rentiva_vehicle_details',
			'rentiva_vehicles_grid',
			'rentiva_vehicles_list',
			'rentiva_vehicle_comparison',
			'rentiva_search',
			'rentiva_search_results',
			'rentiva_contact',
			'rentiva_testimonials',
			'rentiva_vehicle_rating_form',
			'mhm_rentiva_transfer_search',
		);
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
			$page_id             = self::get_page_id( $shortcode );
			$pages[ $shortcode ] = array(
				'id'  => $page_id,
				'url' => $page_id ? (string) get_permalink( $page_id ) : '',
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
			'rentiva_booking_form'          => 'mhm_rentiva_booking_url',
			'rentiva_my_bookings'           => 'mhm_rentiva_my_bookings_url',
			'rentiva_my_favorites'          => 'mhm_rentiva_my_favorites_url',
			'rentiva_payment_history'       => 'mhm_rentiva_payment_history_url',
			'rentiva_messages'              => 'mhm_rentiva_messages_url',
			'rentiva_account_details'       => 'mhm_rentiva_account_details_url',
			'rentiva_vehicles_list'         => 'mhm_rentiva_vehicles_list_url',
			'rentiva_vehicles_grid'         => 'mhm_rentiva_vehicles_grid_url',
			'rentiva_search'                => 'mhm_rentiva_search_url',
			'rentiva_search_results'        => 'mhm_rentiva_search_results_url',
			'rentiva_contact'               => 'mhm_rentiva_contact_url',
			'rentiva_availability_calendar' => 'mhm_rentiva_availability_calendar_url',
			'rentiva_booking_confirmation'  => 'mhm_rentiva_booking_confirmation_url',
			'mhm_rentiva_transfer_search'   => 'mhm_rentiva_transfer_url',
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

		$transient_key = 'mhm_miss_sc_' . md5( $shortcode );
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
			delete_transient( 'mhm_miss_sc_' . md5( $sc ) );
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
