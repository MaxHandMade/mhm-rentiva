<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Manager Class.
 *
 * @package MHMRentiva\Admin\Addons
 */


// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\Addons\AddonPricingType;
use MHMRentiva\Admin\Addons\AddonPricingCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages additional services functionality.
 */
final class AddonManager {




	/**
	 * Safe sanitize text field that handles null values.
	 *
	 * @param mixed $value Input value.
	 * @return string Sanitized string.
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( null === $value || '' === $value ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'init' ) );
		add_action( 'admin_init', array( self::class, 'admin_init' ) );

		// Hook into booking system.
		add_filter( 'mhmrentiva_booking_data', array( self::class, 'process_booking_addons' ), 10, 2 );
		add_filter( 'mhmrentiva_booking_total', array( self::class, 'calculate_addon_total' ), 10, 2 );
		add_action( 'mhmrentiva_booking_created', array( self::class, 'save_booking_addons' ), 10, 2 );

		// Admin hooks.
		if ( is_admin() ) {
			add_filter( 'mhmrentiva_admin_submenu_order', array( self::class, 'admin_menu_order' ) );

			// AJAX handlers.
			add_action( 'wp_ajax_mhmrentiva_bulk_addon_action', array( self::class, 'handle_bulk_actions' ) );
			add_action( 'wp_ajax_mhmrentiva_update_addon_price', array( self::class, 'handle_update_price' ) );
		}
	}

	/**
	 * Initialize.
	 */
	public static function init(): void {
		// Register addon post type.
		AddonPostType::register();
		AddonPostType::register_pricing_type_meta(); // NEW (v4.36.0)
	}

	/**
	 * Admin initialize.
	 */
	public static function admin_init(): void {
		// Register meta boxes.
		AddonMeta::register();

		// Add price column to WordPress post list.
		add_filter( 'manage_mhmrentiva_addon_posts_columns', array( self::class, 'add_price_column' ) );
		add_action( 'manage_mhmrentiva_addon_posts_custom_column', array( self::class, 'render_price_column' ), 10, 2 );
		add_filter( 'manage_edit-mhmrentiva_addon_sortable_columns', array( self::class, 'make_price_sortable' ) );

		add_filter( 'manage_mhmrentiva_addon_posts_columns', array( self::class, 'add_pricing_type_column' ) );
		add_action( 'manage_mhmrentiva_addon_posts_custom_column', array( self::class, 'render_pricing_type_column' ), 10, 2 );

		// Enqueue script and style.
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_addon_scripts' ) );

		// Register the live enhancements for WordPress's native CPT list table.
		// That screen is no longer in the menu, but it stays reachable by URL
		// as the way back, so its enhancements stay registered too.
		if ( class_exists( AddonListTable::class ) ) {
			AddonListTable::register();
		}

		// The screen the menu now opens, and its endpoints.
		AddonScreen::register();
	}

	/**
	 * Deprecated admin menu handler.
	 */
	public static function add_admin_menu(): void {
		// WordPress automatically adds post type menus.
	}

	/**
	 * Reorder admin menu items.
	 *
	 * @param array $menu_order Original menu order.
	 * @return array Reordered menu order.
	 */
	public static function admin_menu_order( array $menu_order ): array {
		// Insert addon menu after vehicles but before bookings.
		$addon_menu    = 'edit.php?post_type=mhmrentiva_addon';
		$vehicles_menu = 'edit.php?post_type=mhmrentiva_vehicle';
		$bookings_menu = 'edit.php?post_type=mhmrentiva_booking';

		if ( in_array( $vehicles_menu, $menu_order, true ) && in_array( $bookings_menu, $menu_order, true ) ) {
			$vehicles_pos = array_search( $vehicles_menu, $menu_order, true );
			$bookings_pos = array_search( $bookings_menu, $menu_order, true );

			if ( false !== $vehicles_pos && false !== $bookings_pos && $vehicles_pos < $bookings_pos ) {
				array_splice( $menu_order, $bookings_pos, 0, array( $addon_menu ) );
			}
		}

		return $menu_order;
	}

	/**
	 * Add price column to WordPress post list.
	 *
	 * @param array $columns List of columns.
	 * @return array Modified columns.
	 */
	public static function add_price_column( array $columns ): array {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			// Add price column after title column.
			if ( 'title' === $key ) {
				$new_columns['mhmrentiva_addon_price'] = __( 'Price', 'mhm-rentiva' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render price column.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_price_column( string $column, int $post_id ): void {
		if ( 'mhmrentiva_addon_price' === $column ) {
			$price = get_post_meta( $post_id, 'mhmrentiva_addon_price', true );

			if ( $price ) {
				printf(
					'<span class="addon-price-display" data-addon-id="%d" data-price="%s">%s</span>',
					(int) $post_id,
					esc_attr( $price ),
					esc_html( self::format_addon_price( (float) $price ) )
				);
			} else {
				printf(
					'<span class="addon-price-display" data-addon-id="%d" data-price="0">%s</span>',
					(int) $post_id,
					esc_html( self::format_addon_price( 0.0 ) )
				);
			}
		}
	}

	/**
	 * Format an addon price for display.
	 *
	 * Canonical currency formatting (WC-aware symbol/position/separators). These
	 * call sites used to concatenate the symbol on the right unconditionally,
	 * which contradicted a `left` woocommerce_currency_pos.
	 *
	 * @param float $price Numeric price.
	 * @return string
	 */
	private static function format_addon_price( float $price ): string {
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price( $price, 2 );
	}

	/**
	 * Add the pricing type column.
	 *
	 * @param array $columns List of columns.
	 * @return array Modified columns.
	 */
	public static function add_pricing_type_column( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'mhmrentiva_addon_price' === $key ) {
				$new['mhmrentiva_addon_pricing_type'] = __( 'Pricing Type', 'mhm-rentiva' );
			}
		}
		return $new;
	}

	/**
	 * Render the pricing type column.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_pricing_type_column( string $column, int $post_id ): void {
		if ( 'mhmrentiva_addon_pricing_type' === $column ) {
			$type = AddonPricingType::sanitize( get_post_meta( $post_id, '_mhmrentiva_addon_pricing_type', true ) );
			echo esc_html( AddonPricingType::label( $type ) );
		}
	}

	/**
	 * Make price column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public static function make_price_sortable( array $columns ): array {
		$columns['mhmrentiva_addon_price'] = 'mhmrentiva_addon_price';
		return $columns;
	}

	/**
	 * Enqueue script and style for addon page.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function enqueue_addon_scripts( string $hook ): void {
		global $post_type;

		// Only enqueue on addon list page.
		if ( 'edit.php' === $hook && 'mhmrentiva_addon' === $post_type ) {
			wp_enqueue_style(
				'mhm-rentiva-addon-list',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/addon-list.css',
				array(),
				MHMRENTIVA_VERSION
			);

			wp_enqueue_script(
				'mhm-rentiva-addon-list',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/addon-list.js',
				array( 'jquery' ),
				MHMRENTIVA_VERSION,
				true
			);

			// Localize JavaScript variables.
			wp_localize_script(
				'mhm-rentiva-addon-list',
				'mhmrentiva_addon_list_vars',
				array(
					'ajax_url'          => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'mhmrentiva_addon_list_nonce' ),
					'no_items_selected' => __( 'No items selected.', 'mhm-rentiva' ),
					'items_selected'    => __( 'items selected', 'mhm-rentiva' ),
					'confirm_enable'    => __( 'Are you sure you want to enable selected additional services?', 'mhm-rentiva' ),
					'confirm_disable'   => __( 'Are you sure you want to disable selected additional services?', 'mhm-rentiva' ),
					'confirm_delete'    => __( 'Are you sure you want to delete selected additional services? This action cannot be undone.', 'mhm-rentiva' ),
					'processing'        => __( 'Processing...', 'mhm-rentiva' ),
					'error_occurred'    => __( 'An error occurred. Please try again.', 'mhm-rentiva' ),
					'auto_refresh'      => false,
					'strings'           => array(
						'invalidPrice'     => __( 'Invalid price value!', 'mhm-rentiva' ),
						'priceUpdateError' => __( 'Error updating price', 'mhm-rentiva' ),
						'unknownError'     => __( 'Unknown error', 'mhm-rentiva' ),
					),
				)
			);
		}
	}


	/**
	 * The meta key carrying the Aktif/Pasif switch.
	 */
	public const ENABLED_META = 'mhmrentiva_addon_enabled';

	/**
	 * May this id be sold as an additional service right now?
	 *
	 * The one answer to that question. Four surfaces used to decide it for
	 * themselves and only one of them looked at the switch at all, so an
	 * operator could set a service to Pasif, watch it go grey on the add-ons
	 * screen, and have the customer booking form keep selling it.
	 *
	 * Three things have to hold, and they are separate questions: the id names a
	 * post, that post is one of ours and published, and the switch is not off.
	 *
	 * "Not off" rather than "on" is deliberate and is the house rule, stated in
	 * AddonScreen's quick-create endpoint: *absent means active*. Unchecking the
	 * box writes '0' (AddonMeta::update_addon_meta) rather than deleting the
	 * row, so an absent flag can only mean the service predates the field. Those
	 * services stay sellable; reading absent as false would empty the booking
	 * form on every site that upgraded into the flag.
	 *
	 * @param int $addon_id Candidate id, straight from a request in the hot path.
	 * @return bool
	 */
	public static function is_sellable( int $addon_id ): bool {
		if ( $addon_id <= 0 ) {
			return false;
		}

		$addon = get_post( $addon_id );

		if ( ! $addon instanceof \WP_Post ) {
			return false;
		}

		if ( AddonPostType::POST_TYPE !== $addon->post_type || 'publish' !== $addon->post_status ) {
			return false;
		}

		return '0' !== (string) get_post_meta( $addon_id, self::ENABLED_META, true );
	}

	/**
	 * Keep only the ids that may be sold, in the order they arrived.
	 *
	 * This is the acceptance point, not a display filter. The booking form runs
	 * submitted ids through SecurityHelper::validate_numeric_array(), which only
	 * asks whether they are numbers -- so before this existed, a replayed form
	 * could buy a service that had since been switched off, and a hand-made
	 * request could attach any post on the site as a "service". Hiding the
	 * checkbox never closed that; refusing the id does.
	 *
	 * @param array<int, mixed> $addon_ids Candidate ids.
	 * @return array<int, int> Sellable ids, de-duplicated.
	 */
	public static function filter_sellable( array $addon_ids ): array {
		$sellable = array();

		foreach ( $addon_ids as $candidate ) {
			$addon_id = (int) $candidate;

			if ( isset( $sellable[ $addon_id ] ) || ! self::is_sellable( $addon_id ) ) {
				continue;
			}

			$sellable[ $addon_id ] = true;
		}

		return array_map( 'intval', array_keys( $sellable ) );
	}

	/**
	 * Get all published and enabled additional services.
	 *
	 * @return array List of addons.
	 */
	public static function get_available_addons(): array {
		$args = array(
			'post_type'      => 'mhmrentiva_addon',
			'post_status'    => 'publish',
			// Two clauses, not one. `compare => '='` alone is an INNER JOIN on
			// postmeta, so it dropped every service that has never carried the
			// flag -- exactly the ones an upgraded site has, and exactly the
			// ones is_sellable() calls active. This is the SQL spelling of
			// "anything but an explicit '0'".
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => self::ENABLED_META,
					'value'   => '0',
					'compare' => '!=',
				),
				array(
					'key'     => self::ENABLED_META,
					'compare' => 'NOT EXISTS',
				),
			),
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'posts_per_page' => -1,
		);

		$addons = get_posts( $args );
		$result = array();

		foreach ( $addons as $addon ) {
			$description = $addon->post_excerpt;
			if ( ! $description ) {
				$description = $addon->post_content;
			}
			$result[] = array(
				'id'           => $addon->ID,
				'title'        => $addon->post_title,
				'description'  => $description,
				'price'        => (float) get_post_meta( $addon->ID, 'mhmrentiva_addon_price', true ),
				'pricing_type' => AddonPricingType::sanitize(
					get_post_meta( $addon->ID, '_mhmrentiva_addon_pricing_type', true )
				),
				'required'     => (bool) get_post_meta( $addon->ID, 'mhmrentiva_addon_required', true ),
			);
		}

		return $result;
	}

	/**
	 * Get a single addon by its ID.
	 *
	 * @param int $addon_id Addon ID.
	 * @return array|null Addon data or null if not found.
	 */
	public static function get_addon_by_id( int $addon_id ): ?array {
		$addon = get_post( $addon_id );

		if ( ! $addon || 'mhmrentiva_addon' !== $addon->post_type ) {
			return null;
		}

		$description = $addon->post_excerpt;
		if ( ! $description ) {
			$description = $addon->post_content;
		}

		return array(
			'id'          => $addon->ID,
			'title'       => $addon->post_title,
			'description' => $description,
			'price'       => (float) get_post_meta( $addon->ID, 'mhmrentiva_addon_price', true ),
			'enabled'     => (bool) get_post_meta( $addon->ID, 'mhmrentiva_addon_enabled', true ),
			'required'    => (bool) get_post_meta( $addon->ID, 'mhmrentiva_addon_required', true ),
		);
	}

	/**
	 * Process selected addons during booking.
	 *
	 * @param array $booking_data Current booking data.
	 * @param array $post_data Submitted form data.
	 * @return array Modified booking data.
	 */
	public static function process_booking_addons( array $booking_data, array $post_data ): array {
		$selected_addons = $post_data['selected_addons'] ?? array();

		if ( ! is_array( $selected_addons ) ) {
			$selected_addons = array();
		}

		// Validate selected addons.
		$available_addons = self::get_available_addons();
		$available_ids    = array_column( $available_addons, 'id' );
		$selected_addons  = array_intersect( $selected_addons, $available_ids );

		$booking_data['selected_addons'] = array_map( 'intval', $selected_addons );

		return $booking_data;
	}

	/**
	 * Calculate total price including addons.
	 *
	 * @param float $total Original total.
	 * @param array $booking_data Booking data with selected addons.
	 * @return float Modified total.
	 */
	public static function calculate_addon_total( float $total, array $booking_data ): float {
		$selected_addons = $booking_data['selected_addons'] ?? array();
		if ( empty( $selected_addons ) || ! is_array( $selected_addons ) ) {
			return $total;
		}

		$context = array(
			'rental_days' => (int) ( $booking_data['rental_days'] ?? 1 ),
			'adults'      => (int) ( $booking_data['guests'] ?? 0 ),
			'children'    => 0,
		);

		foreach ( $selected_addons as $addon_id ) {
			$total += AddonPricingCalculator::calculate( (int) $addon_id, $context );
		}

		return $total;
	}

	/**
	 * Save selected addons for a booking.
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Final booking data.
	 */
	public static function save_booking_addons( int $booking_id, array $booking_data ): void {
		$selected_addons = $booking_data['selected_addons'] ?? array();
		if ( empty( $selected_addons ) || ! is_array( $selected_addons ) ) {
			return;
		}

		$context = array(
			'rental_days' => (int) ( $booking_data['rental_days'] ?? 1 ),
			'adults'      => (int) ( $booking_data['guests'] ?? 0 ),
			'children'    => 0,
		);

		$addon_total   = 0.0;
		$addon_details = array();

		foreach ( $selected_addons as $addon_id ) {
			$addon = self::get_addon_by_id( (int) $addon_id );
			if ( ! $addon ) {
				continue;
			}
			$line_total = AddonPricingCalculator::calculate( (int) $addon_id, $context );
			$type       = AddonPricingType::sanitize(
				get_post_meta( (int) $addon_id, '_mhmrentiva_addon_pricing_type', true )
			);
			$multiplier = AddonPricingCalculator::multiplier( $type, $context );

			$addon_total    += $line_total;
			$addon_details[] = array(
				'id'           => $addon['id'],
				'title'        => $addon['title'],
				'price'        => $addon['price'],
				'pricing_type' => $type,
				'multiplier'   => $multiplier,
				'line_total'   => $line_total,
			);
		}

		update_post_meta( $booking_id, '_mhmrentiva_selected_addons', $selected_addons );
		update_post_meta( $booking_id, '_mhmrentiva_addon_total', $addon_total );
		update_post_meta( $booking_id, '_mhmrentiva_addon_details', $addon_details );
	}

	/**
	 * Check if a new addon can be created.
	 *
	 * @return bool True if can create.
	 */
	public static function can_create_addon(): bool {
		return true;
	}


	/**
	 * Handle bulk actions.
	 */
	public static function handle_bulk_actions(): void {
		// Nonce check.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhmrentiva_addon_list_nonce' ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'mhm-rentiva' ) );
			return;
		}

		// Permission check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) );
		}

		$action    = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
		$addon_ids = isset( $_POST['addon_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['addon_ids'] ) ) : array();

		if ( empty( $addon_ids ) ) {
			wp_send_json_error( esc_html__( 'No additional services selected.', 'mhm-rentiva' ) );
		}

		$success_count = 0;
		$error_count   = 0;

		foreach ( $addon_ids as $addon_id ) {
			$result = false;

			switch ( $action ) {
				case 'enable_addons':
					$result = update_post_meta( $addon_id, 'mhmrentiva_addon_enabled', '1' );
					break;

				case 'disable_addons':
					$result = update_post_meta( $addon_id, 'mhmrentiva_addon_enabled', '0' );
					break;

				case 'delete':
					$result = wp_delete_post( $addon_id, true );
					break;

				default:
					wp_send_json_error( esc_html__( 'Invalid action.', 'mhm-rentiva' ) );
			}

			if ( $result ) {
				++$success_count;
			} else {
				++$error_count;
			}
		}

		if ( $error_count > 0 ) {
			/* translators: 1: Successful count, 2: Failed count */
			wp_send_json_error(
				sprintf(
					/* translators: 1: successful process count, 2: failed process count. */
					__( '%1$d additional services processed, %2$d additional services failed.', 'mhm-rentiva' ),
					(int) $success_count,
					(int) $error_count
				)
			);
		} else {
			wp_send_json_success(
				sprintf(
					/* translators: %d: successful process count. */
					__( '%d additional services successfully processed.', 'mhm-rentiva' ),
					(int) $success_count
				)
			);
		}
	}


	/**
	 * Get currency code for addon display.
	 *
	 * Prefers WooCommerce currency when WC is active so addon prices stay in
	 * sync with the rest of the booking flow. Falls back to the plugin's own
	 * setting when WC is not present.
	 */
	public static function get_default_currency(): string {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return get_woocommerce_currency();
		}
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_currency', 'USD' );
	}

	/**
	 * Get value from settings.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value.
	 * @return mixed Setting value.
	 */
	private static function get_setting( string $key, $default_value = null ) {
		// Use Settings class.
		if ( class_exists( '\MHMRentiva\Admin\Settings\Settings' ) ) {
			return \MHMRentiva\Admin\Settings\Settings::get( $key, $default_value );
		}

		// Fallback: direct WordPress options.
		return get_option( $key, $default_value );
	}


	/**
	 * Check if confirmation is required for addons.
	 *
	 * @return bool True if required.
	 */
	public static function require_confirmation_for_addons(): bool {
		return (bool) self::get_setting( 'mhmrentiva_addon_require_confirmation', false );
	}

	/**
	 * Check if addon prices should be shown in calendar.
	 *
	 * @return bool True if should show.
	 */
	public static function show_prices_in_calendar(): bool {
		return (bool) self::get_setting( 'mhmrentiva_addon_show_prices_in_calendar', true );
	}

	/**
	 * Get display order of addons.
	 *
	 * @return string Display order.
	 */
	public static function get_display_order(): string {
		return self::get_setting( 'mhmrentiva_addon_display_order', 'menu_order' );
	}

	/**
	 * Check if prices are tax inclusive.
	 *
	 * @return bool True if inclusive.
	 */
	public static function is_tax_inclusive(): bool {
		return (bool) self::get_setting( 'mhmrentiva_addon_tax_inclusive', true );
	}

	/**
	 * Get tax rate.
	 *
	 * @return float Tax rate.
	 */
	public static function get_tax_rate(): float {
		return (float) self::get_setting( 'mhmrentiva_addon_tax_rate', 20.00 );
	}

	/**
	 * AJAX: Price update.
	 */
	public static function handle_update_price(): void {
		// Nonce check.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhmrentiva_addon_list_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'mhm-rentiva' ) ) );
			return;
		}

		// Permission check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission for this action.', 'mhm-rentiva' ) ) );
			return;
		}

		$addon_id = isset( $_POST['addon_id'] ) ? absint( wp_unslash( $_POST['addon_id'] ) ) : 0;
		$price    = isset( $_POST['price'] ) ? (float) sanitize_text_field( wp_unslash( (string) $_POST['price'] ) ) : 0.0;

		if ( $addon_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid additional service ID.', 'mhm-rentiva' ) ) );
			return;
		}

		if ( $price < 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Price cannot be negative.', 'mhm-rentiva' ) ) );
			return;
		}

		// Check if addon exists.
		$addon = get_post( $addon_id );
		if ( ! $addon || 'mhmrentiva_addon' !== $addon->post_type ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Additional service not found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Update price.
		$result = update_post_meta( $addon_id, 'mhmrentiva_addon_price', $price );

		if ( false !== $result ) {
			wp_send_json_success(
				array(
					'message'         => esc_html__( 'Price successfully updated.', 'mhm-rentiva' ),
					'currency'        => self::get_default_currency(),
					'formatted_price' => self::format_addon_price( $price ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Error occurred while updating price.', 'mhm-rentiva' ) ) );
		}
	}
}
