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


// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\Addons\AddonContextMetaBox;
use MHMRentiva\Admin\Addons\AddonContextTaxonomy;
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
		add_filter( 'mhm_rentiva_booking_data', array( self::class, 'process_booking_addons' ), 10, 2 );
		add_filter( 'mhm_rentiva_booking_total', array( self::class, 'calculate_addon_total' ), 10, 2 );
		add_action( 'mhm_rentiva_booking_created', array( self::class, 'save_booking_addons' ), 10, 2 );

		// Admin hooks.
		if ( is_admin() ) {
			add_filter( 'mhm_rentiva_admin_submenu_order', array( self::class, 'admin_menu_order' ) );

			// AJAX handlers.
			add_action( 'wp_ajax_mhm_bulk_addon_action', array( self::class, 'handle_bulk_action' ) );
			add_action( 'wp_ajax_mhm_update_addon_price', array( self::class, 'handle_update_price' ) );
		}
	}

	/**
	 * Initialize.
	 */
	public static function init(): void {
		// Register addon post type.
		AddonPostType::register();
		AddonPostType::register_pricing_type_meta(); // NEW (v4.36.0 Task 4)
	}

	/**
	 * Admin initialize.
	 */
	public static function admin_init(): void {
		// Register meta boxes.
		AddonMeta::register();

		// Add price column to WordPress post list.
		add_filter( 'manage_vehicle_addon_posts_columns', array( self::class, 'add_price_column' ) );
		add_action( 'manage_vehicle_addon_posts_custom_column', array( self::class, 'render_price_column' ), 10, 2 );
		add_filter( 'manage_edit-vehicle_addon_sortable_columns', array( self::class, 'make_price_sortable' ) );

		// Add context and pricing type columns (v4.36.0 Task 9).
		add_filter( 'manage_vehicle_addon_posts_columns', array( self::class, 'add_context_pricing_columns' ) );
		add_action( 'manage_vehicle_addon_posts_custom_column', array( self::class, 'render_context_pricing_column' ), 10, 2 );

		// Enqueue script and style.
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_addon_scripts' ) );

		// Register list table for enhanced functionality.
		if ( class_exists( AddonListTable::class ) ) {
			new AddonListTable();
		}

		// Register addon context metabox and save handler.
		AddonContextMetaBox::register();
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
		$addon_menu    = 'edit.php?post_type=vehicle_addon';
		$vehicles_menu = 'edit.php?post_type=vehicle';
		$bookings_menu = 'edit.php?post_type=vehicle_booking';

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
				$new_columns['addon_price'] = __( 'Price', 'mhm-rentiva' );
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
		if ( 'addon_price' === $column ) {
			$price           = get_post_meta( $post_id, 'addon_price', true );
			$currency_code   = self::get_default_currency();
			$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol( $currency_code );

			if ( $price ) {
				$formatted_price = number_format( (float) $price, 2, ',', '.' ) . ' ' . $currency_symbol;
				printf(
					'<span class="addon-price-display" data-addon-id="%d" data-price="%s">%s</span>',
					(int) $post_id,
					esc_attr( $price ),
					esc_html( $formatted_price )
				);
			} else {
				printf(
					'<span class="addon-price-display" data-addon-id="%d" data-price="0">0,00 %s</span>',
					(int) $post_id,
					esc_html( $currency_symbol )
				);
			}
		}
	}

	/**
	 * Add context and pricing type columns (v4.36.0 Task 9).
	 *
	 * @param array $columns List of columns.
	 * @return array Modified columns.
	 */
	public static function add_context_pricing_columns( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'addon_price' === $key ) {
				$new['mhm_addon_context']      = __( 'Context', 'mhm-rentiva' );
				$new['mhm_addon_pricing_type'] = __( 'Pricing Type', 'mhm-rentiva' );
			}
		}
		return $new;
	}

	/**
	 * Render context and pricing type columns.
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_context_pricing_column( string $column, int $post_id ): void {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query -- bounded query for single addon context.
		if ( 'mhm_addon_context' === $column ) {
			$terms = wp_get_object_terms( $post_id, AddonContextTaxonomy::TAXONOMY, array( 'fields' => 'slugs' ) );
			$slug  = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';

			$labels = array(
				AddonContextTaxonomy::TERM_RENTAL   => __( 'Rental', 'mhm-rentiva' ),
				AddonContextTaxonomy::TERM_TRANSFER => __( 'Transfer', 'mhm-rentiva' ),
				AddonContextTaxonomy::TERM_BOTH     => __( 'Both', 'mhm-rentiva' ),
			);

			$label = $labels[ $slug ] ?? __( 'Unassigned', 'mhm-rentiva' );

			$badge_class = $slug ? 'mhm-addon-context-badge--' . $slug : 'mhm-addon-context-badge--unset';
			printf(
				'<span class="mhm-addon-context-badge %s">%s</span>',
				esc_attr( $badge_class ),
				esc_html( $label )
			);
			return;
		}

		if ( 'mhm_addon_pricing_type' === $column ) {
			$type = AddonPricingType::sanitize( get_post_meta( $post_id, '_mhm_addon_pricing_type', true ) );
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
		$columns['addon_price'] = 'addon_price';
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
		if ( 'edit.php' === $hook && 'vehicle_addon' === $post_type ) {
			wp_enqueue_style(
				'mhm-addon-list',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/addon-list.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			wp_enqueue_script(
				'mhm-addon-list',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/addon-list.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			// Localize JavaScript variables.
			wp_localize_script(
				'mhm-addon-list',
				'mhm_addon_list_vars',
				array(
					'ajax_url'                => admin_url( 'admin-ajax.php' ),
					'nonce'                   => wp_create_nonce( 'mhm_addon_list_nonce' ),
					'no_items_selected'       => __( 'No items selected.', 'mhm-rentiva' ),
					'items_selected'          => __( 'items selected', 'mhm-rentiva' ),
					'confirm_enable'          => __( 'Are you sure you want to enable selected additional services?', 'mhm-rentiva' ),
					'confirm_disable'         => __( 'Are you sure you want to disable selected additional services?', 'mhm-rentiva' ),
					'confirm_delete'          => __( 'Are you sure you want to delete selected additional services? This action cannot be undone.', 'mhm-rentiva' ),
					'processing'              => __( 'Processing...', 'mhm-rentiva' ),
					'error_occurred'          => __( 'An error occurred. Please try again.', 'mhm-rentiva' ),
					'auto_refresh'            => false,
				)
			);
		}

		// Enqueue context↔pricing-type constraint script on addon edit screens (v4.36.0 Task 10).
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true )
			&& 'vehicle_addon' === $post_type
		) {
			wp_enqueue_script(
				'mhm-addon-context',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/addon-context.js',
				array(),
				MHM_RENTIVA_VERSION . '.' . filemtime( MHM_RENTIVA_PLUGIN_PATH . 'assets/js/admin/addon-context.js' ),
				true
			);
			wp_localize_script(
				'mhm-addon-context',
				'mhmRentivaAddonContextI18n',
				array(
					'incompatible' => __( ' (incompatible with context)', 'mhm-rentiva' ),
				)
			);
		}
	}


	/**
	 * Get all published and enabled additional services.
	 *
	 * @param string $context Addon context ('rental', 'transfer', or 'both'). Defaults to 'rental' for backwards compatibility.
	 * @return array List of addons.
	 */
	public static function get_available_addons( string $context = 'rental' ): array {
		$args = array(
			'post_type'      => 'vehicle_addon',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => 'addon_enabled',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'tax_query'      => array(
				array(
					'taxonomy' => AddonContextTaxonomy::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( $context, AddonContextTaxonomy::TERM_BOTH ),
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
				'price'        => (float) get_post_meta( $addon->ID, 'addon_price', true ),
				'pricing_type' => AddonPricingType::sanitize(
					get_post_meta( $addon->ID, '_mhm_addon_pricing_type', true )
				),
				'required'     => (bool) get_post_meta( $addon->ID, 'addon_required', true ),
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

		if ( ! $addon || 'vehicle_addon' !== $addon->post_type ) {
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
			'price'       => (float) get_post_meta( $addon->ID, 'addon_price', true ),
			'enabled'     => (bool) get_post_meta( $addon->ID, 'addon_enabled', true ),
			'required'    => (bool) get_post_meta( $addon->ID, 'addon_required', true ),
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
			'adults'      => (int) ( $booking_data['transfer_adults'] ?? $booking_data['guests'] ?? 0 ),
			'children'    => (int) ( $booking_data['transfer_children'] ?? 0 ),
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
			'adults'      => (int) ( $booking_data['transfer_adults'] ?? $booking_data['guests'] ?? 0 ),
			'children'    => (int) ( $booking_data['transfer_children'] ?? 0 ),
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
				get_post_meta( (int) $addon_id, '_mhm_addon_pricing_type', true )
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

		update_post_meta( $booking_id, '_mhm_selected_addons', $selected_addons );
		update_post_meta( $booking_id, '_mhm_addon_total', $addon_total );
		update_post_meta( $booking_id, '_mhm_addon_details', $addon_details );
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
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_addon_list_nonce' ) ) {
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
					$result = update_post_meta( $addon_id, 'addon_enabled', '1' );
					break;

				case 'disable_addons':
					$result = update_post_meta( $addon_id, 'addon_enabled', '0' );
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
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency', 'USD' );
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
		return (bool) self::get_setting( 'mhm_rentiva_addon_require_confirmation', false );
	}

	/**
	 * Check if addon prices should be shown in calendar.
	 *
	 * @return bool True if should show.
	 */
	public static function show_prices_in_calendar(): bool {
		return (bool) self::get_setting( 'mhm_rentiva_addon_show_prices_in_calendar', true );
	}

	/**
	 * Get display order of addons.
	 *
	 * @return string Display order.
	 */
	public static function get_display_order(): string {
		return self::get_setting( 'mhm_rentiva_addon_display_order', 'menu_order' );
	}

	/**
	 * Check if prices are tax inclusive.
	 *
	 * @return bool True if inclusive.
	 */
	public static function is_tax_inclusive(): bool {
		return (bool) self::get_setting( 'mhm_rentiva_addon_tax_inclusive', true );
	}

	/**
	 * Get tax rate.
	 *
	 * @return float Tax rate.
	 */
	public static function get_tax_rate(): float {
		return (float) self::get_setting( 'mhm_rentiva_addon_tax_rate', 20.00 );
	}

	/**
	 * AJAX: Price update.
	 */
	public static function handle_update_price(): void {
		// Nonce check.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_addon_list_nonce' ) ) {
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
		if ( ! $addon || 'vehicle_addon' !== $addon->post_type ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Additional service not found.', 'mhm-rentiva' ) ) );
			return;
		}

		// Update price.
		$result = update_post_meta( $addon_id, 'addon_price', $price );

		if ( false !== $result ) {
			$currency_code   = self::get_default_currency();
			$currency_symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol( $currency_code );
			wp_send_json_success(
				array(
					'message'         => esc_html__( 'Price successfully updated.', 'mhm-rentiva' ),
					'currency'        => $currency_code,
					'formatted_price' => number_format( $price, 2, ',', '.' ) . ' ' . $currency_symbol,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Error occurred while updating price.', 'mhm-rentiva' ) ) );
		}
	}

	/**
	 * Create default additional services.
	 */
	public static function create_default_addons(): void {
		$default_addons = array(
			array(
				'title'       => __( 'Child Seat', 'mhm-rentiva' ),
				'description' => __( 'Safety first. ISOFIX compatible child seat.', 'mhm-rentiva' ),
				'price'       => 15.00,
			),
			array(
				'title'       => __( 'GPS Navigation', 'mhm-rentiva' ),
				'description' => __( 'Don\'t get lost. Pre-installed current maps.', 'mhm-rentiva' ),
				'price'       => 10.00,
			),
			array(
				'title'       => __( 'Full Insurance', 'mhm-rentiva' ),
				'description' => __( 'Zero deductible. Peace of mind during your trip.', 'mhm-rentiva' ),
				'price'       => 25.00,
			),
			array(
				'title'       => __( 'Extra Driver', 'mhm-rentiva' ),
				'description' => __( 'Share the wheel. Add one more authorized driver.', 'mhm-rentiva' ),
				'price'       => 10.00,
			),
		);

		foreach ( $default_addons as $addon_data ) {
			$post_id = wp_insert_post(
				array(
					'post_title'   => $addon_data['title'],
					'post_content' => $addon_data['description'],
					'post_status'  => 'publish',
					'post_type'    => AddonPostType::POST_TYPE,
				)
			);

			if ( $post_id ) {
				update_post_meta( $post_id, 'addon_price', $addon_data['price'] );
				update_post_meta( $post_id, 'addon_enabled', '1' );
				update_post_meta( $post_id, 'addon_required', '0' );
			}
		}
	}
}
