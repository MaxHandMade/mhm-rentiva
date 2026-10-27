<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

if (!defined('ABSPATH')) {
    exit;
}

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

		// Counts on Lite's own items, after they are in (PR D, R2).
		add_filter( 'woocommerce_account_menu_items', array( self::class, 'add_menu_counts' ), 21 );

		// Snapshot the query vars that already belong to someone else, before
		// any endpoint registration adds to that list -- including ours.
		//
		// Taken here, synchronously, rather than from an init hook: register()
		// is itself called on init priority 2, and adding a callback to a
		// priority that has already passed on a hook that is currently running
		// never fires it. An earlier version hooked init/1 and silently never
		// ran, leaving the snapshot to whichever caller happened to ask first.
		self::snapshot_reserved_query_vars();

		// Add endpoints (priority 5 to run before WooCommerce's default endpoints)
		add_action( 'init', array( self::class, 'add_endpoints' ), 5 );

		// Endpoint query var check
		add_filter( 'woocommerce_get_query_vars', array( self::class, 'add_query_vars' ) );

		// Filter shortcode URLs to provide WooCommerce endpoints
		add_filter( 'mhmrentiva_shortcode_url', array( self::class, 'filter_shortcode_url' ), 10, 2 );

		// Filter WooCommerce endpoint URLs to use translated slugs if available
		add_filter( 'woocommerce_get_endpoint_url', array( self::class, 'filter_woocommerce_endpoint_url' ), 10, 4 );

		// Endpoint titles
		add_filter( 'the_title', array( self::class, 'endpoint_title' ), 10, 2 );

		// Flush rewrite rules on plugin activation/update (one-time)
		add_action( 'admin_init', array( self::class, 'maybe_flush_rewrite_rules' ) );

		// admin_init fires in wp-admin and admin-ajax only, but the endpoint set
		// is not static: a contribution can appear or disappear between requests,
		// on whatever request happens to be first. Without a front-end path the
		// cached rewrite rule keeps matching a URL nothing renders until someone
		// opens wp-admin.
		add_action( 'wp', array( self::class, 'maybe_flush_rewrite_rules' ) );

		// Override WooCommerce default dashboard with Rentiva dashboard
		add_action( 'woocommerce_account_dashboard', array( self::class, 'render_dashboard' ) );

		// One greeting: WooCommerce's dashboard template prints its own "Hello"
		// paragraphs above the Rentiva dashboard (PR D, R3).
		add_filter( 'woocommerce_locate_template', array( self::class, 'locate_dashboard_template' ), 10, 4 );

		// The booking detail belongs to "My bookings" in the menu.
		add_filter( 'woocommerce_account_menu_item_classes', array( self::class, 'menu_item_classes' ), 10, 2 );
	}

	/**
	 * WooCommerce template name of the account dashboard.
	 */
	private const DASHBOARD_TEMPLATE = 'myaccount/dashboard.php';

	/**
	 * `woocommerce_locate_template` callback: while the Rentiva dashboard is
	 * hooked, WooCommerce's own `myaccount/dashboard.php` is swapped for Lite's
	 * copy (templates/woocommerce/myaccount/dashboard.php), which fires the same
	 * three actions in the same order without the two greeting paragraphs.
	 *
	 * Only WooCommerce's default file is swapped. The filter runs after the
	 * theme lookup (wc_locate_template()), so a theme that overrides the
	 * dashboard template arrives here with its own path and keeps it.
	 *
	 * The answer is the same for every request on a site, so WooCommerce's
	 * template path cache (wc_get_template()) can hold it.
	 *
	 * @param string $template      Located file.
	 * @param string $template_name Template name, e.g. `myaccount/dashboard.php`.
	 * @param string $template_path Theme sub-directory WooCommerce looked in.
	 * @param string $default_path  WooCommerce's default template directory.
	 */
	public static function locate_dashboard_template( $template, $template_name, $template_path = '', $default_path = '' ): string {
		$template = (string) $template;

		if ( self::DASHBOARD_TEMPLATE !== $template_name ) {
			return $template;
		}

		if ( false === has_action( 'woocommerce_account_dashboard', array( self::class, 'render_dashboard' ) ) ) {
			return $template;
		}

		if ( '' === (string) $default_path && function_exists( 'WC' ) ) {
			$default_path = WC()->plugin_path() . '/templates/';
		}

		if ( $template !== trailingslashit( (string) $default_path ) . $template_name ) {
			return $template;
		}

		$ours = dirname( __DIR__, 4 ) . '/templates/woocommerce/' . self::DASHBOARD_TEMPLATE;

		return is_readable( $ours ) ? $ours : $template;
	}

	/**
	 * `woocommerce_account_menu_item_classes` callback: on the booking detail
	 * the "My bookings" item is the current one. Only the class is added --
	 * WooCommerce writes `aria-current` itself and offers no filter for it.
	 *
	 * @param array<int, string> $classes  Menu item classes.
	 * @param string             $endpoint Menu item endpoint.
	 * @return array<int, string>
	 */
	public static function menu_item_classes( $classes, $endpoint ): array {
		$classes = (array) $classes;

		if ( (string) $endpoint !== self::get_endpoint_slug( 'bookings' ) ) {
			return $classes;
		}

		if ( 'view_booking' !== AccountSurface::current_endpoint_key() || in_array( 'is-active', $classes, true ) ) {
			return $classes;
		}

		$classes[] = 'is-active';

		return $classes;
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

		$new_items     = array();
		$inserted      = false;
		$rentiva_items = self::get_account_nav_items();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			// Insert Rentiva items after 'orders' or 'dashboard'
			if ( ! $inserted && ( $key === 'orders' || $key === 'dashboard' ) ) {
				foreach ( self::flatten_nav_items( $rentiva_items ) as $slug => $item_label ) {
					$new_items[ $slug ] = $item_label;
				}
				$inserted = true;
			}
		}

		// If orders/dashboard not found, add Rentiva items at the beginning
		if ( ! $inserted ) {
			$new_items = array_merge( self::flatten_nav_items( $rentiva_items ), $new_items );
		}

		// Restore logout at the end
		if ( $logout ) {
			$new_items['customer-logout'] = $logout;
		}

		return $new_items;
	}

	/**
	 * `woocommerce_account_menu_items` callback (priority 21): "My bookings (2)"
	 * with the upcoming bookings, "My favorites (5)" with the saved vehicles
	 * (PR D, R2). Plain text -- WooCommerce prints the label with esc_html().
	 * Nothing is added for 0. Items are matched by their (translated) slug;
	 * every other item, the add-on's included, is left alone.
	 *
	 * Only on the front-end My Account page. WooCommerce also reads the menu
	 * items for the Appearance > Menus metabox and the Customizer, which save
	 * the label into a menu item every visitor then sees, and for the
	 * "Customer account" block's dropdown on every page.
	 *
	 * @param array<string, string> $items Menu items, slug => label.
	 * @return array<string, string>
	 */
	public static function add_menu_counts( array $items ): array {
		if ( ! self::counts_menu_here() ) {
			return $items;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return $items;
		}

		$counters = array(
			self::get_endpoint_slug( 'bookings' )  => static fn(): int => AccountBookingsQuery::counts( $user_id )['upcoming'],
			self::get_endpoint_slug( 'favorites' ) => static function () use ( $user_id ): int {
				$favorites = get_user_meta( $user_id, 'mhmrentiva_favorites', true );
				return is_array( $favorites ) ? count( $favorites ) : 0;
			},
		);

		foreach ( $counters as $slug => $counter ) {
			if ( ! isset( $items[ $slug ] ) || ! is_string( $items[ $slug ] ) ) {
				continue;
			}
			$count = $counter();
			if ( $count <= 0 ) {
				continue;
			}
			$items[ $slug ] = sprintf(
				/* translators: 1: menu label, 2: count */
				__( '%1$s (%2$s)', 'mhm-rentiva' ),
				$items[ $slug ],
				number_format_i18n( $count )
			);
		}

		return $items;
	}

	/**
	 * Whether this request draws the My Account page's own menu: front end,
	 * not a Customizer preview or REST request, on the account page.
	 */
	private static function counts_menu_here(): bool {
		if ( is_admin() || is_customize_preview() || wp_doing_ajax() ) {
			return false;
		}
		if ( function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		return function_exists( 'is_account_page' ) && is_account_page();
	}

	/**
	 * Build the WooCommerce My Account nav items Rentiva contributes.
	 *
	 * Lite supplies only its own tabs (bookings, favorites, payment_history).
	 * Extensions can append their own tabs through the neutral navigation filter.
	 *
	 * @return array<string, array{slug: string, label: string}> Keyed by semantic item key.
	 */
	public static function get_account_nav_items(): array {
		$rentiva_map = self::get_rentiva_endpoints_map();
		$items       = array();

		foreach ( $rentiva_map as $e_key => $config ) {
			if ( 'view_booking' === $e_key ) {
				continue;
			}
			$items[ $e_key ] = array(
				'slug'  => self::get_endpoint_slug( $e_key ),
				'label' => $config['label'],
			);
		}

		/**
		 * Filters the WooCommerce My Account nav items Rentiva contributes.
		 *
		 * @param array<string, array{slug: string, label: string}> $items Keyed by semantic item key.
		 */
		return apply_filters( 'mhmrentiva_account_nav_items', $items );
	}

	/**
	 * Reduce the semantic-keyed nav items array to the slug => label shape
	 * WooCommerce's own menu items array expects, dropping any malformed
	 * contribution from a filter subscriber.
	 *
	 * @param array<string, mixed> $items
	 * @return array<string, string>
	 */
	private static function flatten_nav_items( array $items ): array {
		$flat = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['slug'] ) || empty( $item['label'] ) ) {
				continue;
			}
			$flat[ (string) $item['slug'] ] = (string) $item['label'];
		}
		return $flat;
	}

	/**
	 * Query var names that already belonged to someone else before any endpoint
	 * was registered on this request.
	 *
	 * 🔴 Snapshotted, not read live. add_rewrite_endpoint() registers its slug
	 * as a public query var, so once an extension's endpoint is registered the
	 * live list contains it -- and reading the live list would make our own
	 * contribution look reserved, drop it from every later call, and leave
	 * is_wc_endpoint_url() answering "no" on a URL that works. The snapshot is
	 * taken synchronously from register(), which runs on init/2 -- before Lite's
	 * own endpoints (init/5) and WooCommerce's (init/10).
	 *
	 * @var array<int, string>|null
	 */
	private static ?array $reserved_query_vars = null;

	/**
	 * Take the snapshot. Called from register(); harmless to call twice.
	 */
	public static function snapshot_reserved_query_vars(): void {
		if ( null !== self::$reserved_query_vars ) {
			return;
		}

		self::$reserved_query_vars = isset( $GLOBALS['wp'] ) && $GLOBALS['wp'] instanceof \WP
			? array_values( (array) $GLOBALS['wp']->public_query_vars )
			: array();
	}

	/**
	 * Test seam: forget the snapshot so the next call takes a fresh one.
	 */
	public static function reset_reserved_query_vars(): void {
		self::$reserved_query_vars = null;
	}

	/**
	 * @return array<int, string>
	 */
	private static function reserved_query_vars(): array {
		if ( null === self::$reserved_query_vars ) {
			// No snapshot yet -- a CLI call, or a request that reached this
			// before init. Reading live is correct here precisely because
			// nothing has registered an endpoint yet.
			self::snapshot_reserved_query_vars();
		}

		$reserved = (array) self::$reserved_query_vars;

		foreach ( array_keys( self::get_rentiva_endpoints_map() ) as $key ) {
			$reserved[] = self::get_endpoint_slug( $key );
		}

		return $reserved;
	}

	/**
	 * Endpoint slugs contributed by extensions, validated.
	 *
	 * Fed into WooCommerce's query vars and nowhere else. WC_Query::add_endpoints()
	 * turns every query var into a rewrite endpoint using the mask WooCommerce
	 * picks for this site, so registering one here as well would duplicate the
	 * rule and hardcode a mask WooCommerce may not agree with.
	 *
	 * The validation is not decoration. add_rewrite_endpoint() checks nothing
	 * and calls $wp->add_query_var() unconditionally, so a contribution of
	 * 'name' or 'pagename' would break every permalink on the site.
	 *
	 * @param array<int, string> $taken Query var names already claimed on this
	 *                                  request. add_query_vars() passes what it
	 *                                  has collected so far, which is WooCommerce's
	 *                                  own set plus Lite's -- read from the filter
	 *                                  argument rather than from WC()->query, both
	 *                                  because it is the same answer and because
	 *                                  asking WC()->query->get_query_vars() from
	 *                                  inside its own filter would recurse.
	 * @return array<int, string>
	 */
	public static function get_extension_endpoint_slugs( array $taken = array() ): array {
		$reserved = array_merge( $taken, self::reserved_query_vars() );

		$slugs = array();

		foreach ( (array) apply_filters( 'mhmrentiva_account_endpoints', array() ) as $slug ) {
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}

			$clean = sanitize_title( $slug );

			if ( '' === $clean || in_array( $clean, $reserved, true ) ) {
				continue;
			}

			$slugs[ $clean ] = $clean;
		}

		return array_values( $slugs );
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

		// Extension-contributed endpoints. This is the only place the seam is
		// consumed: WooCommerce registers a rewrite endpoint for every query
		// var it is given, so a tab an extension adds to the nav resolves from
		// here alone.
		foreach ( self::get_extension_endpoint_slugs( array_keys( $vars ) ) as $slug ) {
			$vars[ $slug ] = $slug;
		}

		return $vars;
	}

	/**
	 * Dashboard endpoint content
	 *
	 * Overrides WooCommerce default dashboard with Rentiva custom dashboard.
	 * Triggered by woocommerce_account_dashboard action.
	 */
	public static function render_dashboard(): void {
		AccountRenderer::output_dashboard( array( 'hide_nav' => true ) );
	}

	/**
	 * Bookings endpoint content
	 */
	public static function render_bookings(): void {
		AccountRenderer::output_bookings( array( 'hide_nav' => true ) );
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

		// Security: Early ownership check (customer | vehicle owner | admin).
		if ( $id > 0 ) {
			$booking_owner_id = (int) get_post_meta( $id, '_mhmrentiva_customer_user_id', true );
			$current_user_id  = (int) get_current_user_id();
			$vehicle_id       = (int) get_post_meta( $id, '_mhmrentiva_vehicle_id', true );
			$vehicle_owner_id = $vehicle_id > 0 ? (int) get_post_field( 'post_author', $vehicle_id ) : 0;

			$is_customer = ( $booking_owner_id > 0 && $booking_owner_id === $current_user_id );
			$is_owner    = ( $vehicle_owner_id > 0 && $vehicle_owner_id === $current_user_id );
			$is_admin    = current_user_can( 'manage_options' );

			if ( ! $is_customer && ! $is_owner && ! $is_admin ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to view this booking.', 'mhm-rentiva' ) . '</p></div>';
				return;
			}
		}

		AccountRenderer::output_booking_detail( $id, true );
	}

	/**
	 * Favorites endpoint content
	 */
	public static function render_favorites(): void {
		// Vehicle cards contain inline SVG icons (favorite/compare/rating/features).
		// wp_kses_post() strips SVG tags and breaks parity with vehicle grid cards.
		AccountRenderer::output_favorites( array( 'hide_nav' => true ) );
	}

	/**
	 * Payment History endpoint content
	 */
	public static function render_payment_history(): void {
		AccountRenderer::output_payment_history( array( 'hide_nav' => true ) );
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
			// Passthrough: $title is WP core's own value for this call, unmodified
			// by us. This filter is global (fires for every post/page title on the
			// site, not only Rentiva account endpoints), so re-escaping it here
			// would double-encode titles core already ran through wptexturize()/
			// convert_chars() -- corrupting every title on the site, not just ours.
			return $title;
		}

		// Only replace the main queried page title (My Account page),
		// never titles of nested content (vehicles, bookings, etc.).
		$queried_id = (int) get_queried_object_id();
		if ( $id <= 0 || $queried_id <= 0 || $id !== $queried_id ) {
			// Passthrough -- see rationale above.
			return $title;
		}

		// The only value THIS filter actually introduces: a static, translated
		// label from self::get_rentiva_endpoints_map() (never user input), escaped
		// here at the return per WP.org's "escape as late as possible" guidance.
		return esc_html( $rentiva_map[ $active_key ]['label'] ?? $title );
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
		$flush_key   = 'mhmrentiva_woocommerce_endpoints_flushed';
		$version_key = 'mhmrentiva_woocommerce_endpoints_version';
		$hash_key    = 'mhmrentiva_woocommerce_endpoints_hash';

		$current_version = '4.21.3';

		$rentiva_map   = self::get_rentiva_endpoints_map();
		$current_slugs = array();
		foreach ( array_keys( $rentiva_map ) as $key ) {
			$current_slugs[] = self::get_endpoint_slug( $key );
		}

		// Extension endpoints belong in the hash too. The set is not static --
		// a contribution can appear or disappear between requests -- and a hash
		// blind to that never triggers the flush the change requires.
		$current_slugs = array_merge( $current_slugs, self::get_extension_endpoint_slugs() );

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
