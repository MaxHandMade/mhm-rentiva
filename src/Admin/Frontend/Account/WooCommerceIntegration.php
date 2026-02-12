<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Integration
 *
 * Integrates MHM Rentiva with WooCommerce My Account system
 *
 * @since 4.0.0
 */
final class WooCommerceIntegration {

	use EndpointHelperTrait;


	public static function register(): void {
		// Don't run if WooCommerce is not installed
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Add tabs to WooCommerce My Account
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'add_menu_items' ), 20 );

		// Add endpoints (priority 5 to run before WooCommerce's default endpoints)
		add_action( 'init', array( self::class, 'add_endpoints' ), 5 );

		// Endpoint query var check
		add_filter( 'woocommerce_get_query_vars', array( self::class, 'add_query_vars' ) );

		// Filter shortcode URLs to provide WooCommerce endpoints
		add_filter( 'mhm_rentiva_shortcode_url', array( self::class, 'filter_shortcode_url' ), 10, 2 );

		// Filter WooCommerce endpoint URLs to use translated slugs if available
		add_filter( 'woocommerce_get_endpoint_url', array( self::class, 'filter_woocommerce_endpoint_url' ), 10, 4 );

		// Endpoint titles
		add_filter( 'the_title', array( self::class, 'endpoint_title' ), 10, 2 );

		// Flush rewrite rules on plugin activation/update (one-time)
		add_action( 'admin_init', array( self::class, 'maybe_flush_rewrite_rules' ) );
	}

	/**
	 * Add items to WooCommerce My Account menu
	 *
	 * @param array $items Existing menu items
	 * @return array Modified menu items
	 */
	public static function add_menu_items( array $items ): array {
		// Temporarily remove logout to add our items before it
		$logout = $items['customer-logout'] ?? null;
		unset( $items['customer-logout'] );

		$new_items   = array();
		$inserted    = false;
		$rentiva_map = self::get_rentiva_endpoints_map();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			// Insert Rentiva items after 'orders' or 'dashboard'
			if ( ! $inserted && ( $key === 'orders' || $key === 'dashboard' ) ) {
				foreach ( $rentiva_map as $e_key => $config ) {
					// Skip view_booking and messages if disabled
					if ( $e_key === 'view_booking' ) {
						continue;
					}
					if ( $e_key === 'messages' && ( ! class_exists( \MHMRentiva\Admin\Licensing\Mode::class ) || ! \MHMRentiva\Admin\Licensing\Mode::featureEnabled( \MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES ) ) ) {
						continue;
					}

					$slug               = self::get_endpoint_slug( $e_key );
					$new_items[ $slug ] = $config['label'];
				}
				$inserted = true;
			}
		}

		// If orders/dashboard not found, add Rentiva items at the beginning
		if ( ! $inserted ) {
			$rentiva_items = array();
			foreach ( $rentiva_map as $e_key => $config ) {
				if ( $e_key === 'view_booking' ) {
					continue;
				}
				if ( $e_key === 'messages' && ( ! class_exists( \MHMRentiva\Admin\Licensing\Mode::class ) || ! \MHMRentiva\Admin\Licensing\Mode::featureEnabled( \MHMRentiva\Admin\Licensing\Mode::FEATURE_MESSAGES ) ) ) {
					continue;
				}
				$slug                   = self::get_endpoint_slug( $e_key );
				$rentiva_items[ $slug ] = $config['label'];
			}
			$new_items = array_merge( $rentiva_items, $new_items );
		}

		// Restore logout at the end
		if ( $logout ) {
			$new_items['customer-logout'] = $logout;
		}

		return $new_items;
	}

	/**
	 * Add rewrite endpoints
	 * WooCommerce endpoints should use EP_PAGES only (not EP_ROOT)
	 */
	public static function add_endpoints(): void {
		$rentiva_map = self::get_rentiva_endpoints_map();

		foreach ( $rentiva_map as $key => $config ) {
			$slug = self::get_endpoint_slug( $key );
			add_rewrite_endpoint( $slug, EP_PAGES );

			// Map content rendering
			$callback = 'render_' . $key;
			if ( method_exists( self::class, $callback ) ) {
				add_action( 'woocommerce_account_' . $slug . '_endpoint', array( self::class, $callback ) );
			}
		}
	}

	/**
	 * Add to WooCommerce query vars
	 */
	public static function add_query_vars( array $vars ): array {
		$rentiva_map = self::get_rentiva_endpoints_map();
		foreach ( array_keys( $rentiva_map ) as $key ) {
			$slug          = self::get_endpoint_slug( $key );
			$vars[ $slug ] = $slug;
		}

		return $vars;
	}

	/**
	 * Bookings endpoint content
	 */
	public static function render_bookings(): void {
		// Simply render list
		echo wp_kses_post( AccountRenderer::render_bookings( array( 'hide_nav' => true ) ) );
	}

	/**
	 * View Booking Detail endpoint content
	 */
	public static function render_view_booking( $booking_id ): void {
		$id = $booking_id;
		if ( empty( $id ) ) {
			global $wp_query;
			$var = self::get_endpoint_slug( 'view_booking' );
			$id  = $wp_query->get( $var );
		}

		$id = (int) $id;

		// Security: Early Ownership Check
		if ( $id > 0 ) {
			$booking_owner_id = (int) get_post_meta( $id, '_mhm_customer_user_id', true );
			$current_user_id  = (int) get_current_user_id();

			if ( $booking_owner_id !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view this booking.', 'mhm-rentiva' ) . '</p></div>';
				return;
			}
		}

		echo wp_kses_post( AccountRenderer::render_booking_detail( $id, true ) );
	}

	/**
	 * Favorites endpoint content
	 */
	public static function render_favorites(): void {
		echo wp_kses_post( AccountRenderer::render_favorites( array( 'hide_nav' => true ) ) );
	}

	/**
	 * Payment History endpoint content
	 */
	public static function render_payment_history(): void {
		echo wp_kses_post( AccountRenderer::render_payment_history( array( 'hide_nav' => true ) ) );
	}

	/**
	 * Messages endpoint content
	 */
	public static function render_messages(): void {
		// ⭐ Directly call AccountRenderer instead of shortcode
		// Shortcode would redirect to WooCommerce page, causing infinite loop
		echo wp_kses_post( AccountRenderer::render_messages( array( 'hide_nav' => true ) ) );
	}

	/**
	 * Customize endpoint titles
	 */
	public static function endpoint_title( string $title, int $id = 0 ): string {
		global $wp_query;

		$rentiva_map = self::get_rentiva_endpoints_map();
		$active_key  = null;

		foreach ( $rentiva_map as $key => $config ) {
			$slug = self::get_endpoint_slug( $key );
			if ( isset( $wp_query->query_vars[ $slug ] ) ) {
				$active_key = $key;
				break;
			}
		}

		if ( ! $active_key || ! in_the_loop() ) {
			return $title;
		}

		return $rentiva_map[ $active_key ]['label'] ?? $title;
	}

	/**
	 * Flush rewrite rules (only on activation)
	 */
	public static function flush_rewrite_rules(): void {
		self::add_endpoints();
		flush_rewrite_rules();
	}

	/**
	 * Check if rewrite rules need to be flushed
	 * This runs once after plugin update/activation
	 */
	public static function maybe_flush_rewrite_rules(): void {
		// Check if we need to flush rewrite rules
		$flush_key   = 'mhm_rentiva_woocommerce_endpoints_flushed';
		$version_key = 'mhm_rentiva_woocommerce_endpoints_version';
		$hash_key    = 'mhm_rentiva_woocommerce_endpoints_hash';

		$current_version = '4.9.8'; // Forced flush version

		$rentiva_map   = self::get_rentiva_endpoints_map();
		$current_slugs = array();
		foreach ( array_keys( $rentiva_map ) as $key ) {
			$current_slugs[] = self::get_endpoint_slug( $key );
		}
		$current_hash = md5( serialize( $current_slugs ) );

		$flushed       = get_option( $flush_key, false );
		$saved_version = get_option( $version_key, '0' );
		$saved_hash    = get_option( $hash_key, '' );

		// Flush if:
		// 1. Not flushed before
		// 2. Version changed (code update)
		// 3. Hash changed (user changed settings/translation)
		if ( ! $flushed || version_compare( $saved_version, $current_version, '<' ) || $saved_hash !== $current_hash ) {
			self::add_endpoints();
			flush_rewrite_rules(); // Make sure to hard flush

			// Clear Shortcode Cache (to ensure menu links find new pages)
			if ( class_exists( \MHMRentiva\Admin\Core\ShortcodeUrlManager::class ) ) {
				\MHMRentiva\Admin\Core\ShortcodeUrlManager::clear_cache();
			}

			update_option( $flush_key, true );
			update_option( $version_key, $current_version );
			update_option( $hash_key, $current_hash );

			// Log flush event for debugging
			if ( class_exists( \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class ) ) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info(
					'WooCommerce endpoint rewrite rules flushed.',
					array( 'reason' => $saved_hash !== $current_hash ? 'slug_change' : 'version_update' )
				);
			}
		}
	}

	// Logic moved to EndpointHelperTrait

	/**
	 * Filter WooCommerce endpoint URLs to support translated slugs within My Account.
	 *
	 * @param string $url      Original URL.
	 * @param string $endpoint Endpoint slug.
	 * @param string $value    Endpoint value.
	 * @param string $permalink Permalink.
	 * @return string Modified URL.
	 */
	public static function filter_woocommerce_endpoint_url( string $url, string $endpoint, string $value, string $permalink ): string {
		$rentiva_map = self::get_rentiva_endpoints_map();
		$key         = null;

		foreach ( $rentiva_map as $e_key => $config ) {
			// Check standard defaults
			$defaults = array(
				$config['default'],
				str_replace( 'rentiva-', '', $config['default'] ), // e.g. bookings
			);

			if ( in_array( $endpoint, $defaults, true ) ) {
				$key = $e_key;
				break;
			}
		}

		// Double check against current custom slugs (translations or settings) if no match yet
		if ( ! $key ) {
			foreach ( array( 'bookings', 'favorites', 'payment_history', 'messages' ) as $test_key ) {
				if ( $endpoint === self::get_endpoint_slug( $test_key, '' ) ) {
					$key = $test_key;
					break;
				}
			}
		}

		if ( $key ) {
			// Integrated Dashboard Logic:
			// We stay within the "My Account" wrapper (Sidebar + Content).
			// We use wc_get_account_endpoint_url to ensure WooCommerce handles the load.

			// Get the potentially translated/customized slug for this key
			$custom_slug = self::get_endpoint_slug( $key, $endpoint );

			// Only modify if the slug is different from what was requested or currently used
			if ( $custom_slug && $custom_slug !== $endpoint && function_exists( 'wc_get_account_endpoint_url' ) ) {
				return \wc_get_account_endpoint_url( $custom_slug );
			}
		}

		return $url;
	}

	/**
	 * Provide dynamic URLs for shortcodes that map to WooCommerce endpoints.
	 *
	 * @param string|null $url       Current URL (or null).
	 * @param string      $shortcode Shortcode tag.
	 * @return string|null Modified URL or original.
	 */
	public static function filter_shortcode_url( ?string $url, string $shortcode ): ?string {
		if ( $url ) {
			return $url;
		}

		$rentiva_map = self::get_rentiva_endpoints_map();
		$endpoint    = null;

		if ( isset( $rentiva_map[ str_replace( 'rentiva_', '', $shortcode ) ] ) ) {
			$endpoint = self::get_endpoint_slug( str_replace( 'rentiva_', '', $shortcode ) );
		} elseif ( $shortcode === 'rentiva_my_bookings' ) { // Handle cases where naming doesn't match perfectly
			$endpoint = self::get_endpoint_slug( 'bookings' );
		} elseif ( $shortcode === 'rentiva_my_favorites' ) {
			$endpoint = self::get_endpoint_slug( 'favorites' );
		}

		if ( $endpoint && function_exists( 'wc_get_account_endpoint_url' ) ) {
			$url = \wc_get_account_endpoint_url( $endpoint );
			return $url;
		}

		return null;
	}
}
