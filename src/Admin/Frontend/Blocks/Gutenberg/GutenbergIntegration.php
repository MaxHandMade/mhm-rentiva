<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Gutenberg;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg Integration Class
 *
 * Registers MHM Rentiva shortcodes as Gutenberg blocks
 *
 * @since 3.0.1
 */
class GutenbergIntegration {

	/**
	 * Registers blocks
	 */
	public static function register_blocks(): void {
		// Vehicle Card Block
		$vehicle_card_block = new VehicleCardBlock();
		$vehicle_card_block->register();

		// Vehicles List Block
		$vehicles_list_block = new VehiclesListBlock();
		$vehicles_list_block->register();

		// Booking Form Block
		$booking_form_block = new BookingFormBlock();
		$booking_form_block->register();

		// Other blocks will be added here
		// $vehicle_search_block = new VehicleSearchBlock();
		// $quick_booking_block = new QuickBookingBlock();
	}

	/**
	 * Registers block category
	 */
	public static function register_category(): void {
		// Add block category
		add_filter( 'block_categories_all', array( self::class, 'add_block_category' ), 10, 2 );
	}

	/**
	 * Adds block category
	 *
	 * @param array                    $categories Current categories
	 * @param \WP_Block_Editor_Context $context Block editor context
	 * @return array Updated categories
	 */
	public static function add_block_category( array $categories, $context ): array {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'mhm-rentiva',
					'title' => __( 'MHM Rentiva', 'mhm-rentiva' ),
					'icon'  => 'car',
				),
			)
		);
	}

	/**
	 * Loads Gutenberg CSS files
	 */
	public static function enqueue_editor_styles(): void {
		// Editor CSS
		wp_enqueue_style(
			'mhm-rentiva-gutenberg-blocks-editor',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/gutenberg-blocks-editor.css',
			array( 'wp-edit-blocks' ),
			MHM_RENTIVA_VERSION
		);
	}

	/**
	 * Loads Gutenberg JavaScript files
	 */
	public static function enqueue_editor_scripts(): void {
		// Editor JavaScript
		wp_enqueue_script(
			'mhm-rentiva-gutenberg-blocks',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/gutenberg-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor' ),
			MHM_RENTIVA_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'mhm-rentiva-gutenberg-blocks',
			'mhmRentivaGutenberg',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'mhm_rentiva_gutenberg' ),
				'vehicleOptions' => self::get_vehicle_options_for_js(),
				'i18n'           => array(
					'select_vehicle' => __( 'Select Vehicle', 'mhm-rentiva' ),
					'no_vehicles'    => __( 'No vehicles found', 'mhm-rentiva' ),
					'loading'        => __( 'Loading...', 'mhm-rentiva' ),
				),
			)
		);

		// JavaScript translation
		wp_set_script_translations(
			'mhm-rentiva-gutenberg-blocks',
			'mhm-rentiva',
			MHM_RENTIVA_PLUGIN_PATH . 'languages'
		);
	}

	/**
	 * Loads frontend CSS files
	 */
	public static function enqueue_frontend_styles(): void {
		// Frontend CSS
		wp_enqueue_style(
			'mhm-rentiva-gutenberg-blocks',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/gutenberg-blocks.css',
			array(),
			MHM_RENTIVA_VERSION
		);
	}

	/**
	 * Returns vehicle options for JavaScript
	 *
	 * @return array Vehicle options
	 */
	protected static function get_vehicle_options_for_js(): array {
		$vehicles = get_posts(
			array(
				'post_type'   => 'vehicle',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$options = array(
			array(
				'value' => 0,
				'label' => __( 'Select Vehicle', 'mhm-rentiva' ),
			),
		);

		foreach ( $vehicles as $vehicle ) {
			$options[] = array(
				'value' => $vehicle->ID,
				'label' => $vehicle->post_title,
			);
		}

		return $options;
	}

	/**
	 * AJAX handler - Get vehicle options
	 */
	public static function ajax_get_vehicle_options(): void {
		// Nonce verification
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_rentiva_gutenberg' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security error', 'mhm-rentiva' ) ) );
		}

		// Capability check
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission error', 'mhm-rentiva' ) ) );
		}

		$options = self::get_vehicle_options_for_js();
		wp_send_json_success( array( 'options' => $options ) );
	}

	/**
	 * Registers Gutenberg hooks
	 */
	public static function register_hooks(): void {
		// Register blocks
		add_action( 'init', array( self::class, 'register_blocks' ) );

		// Register block category
		self::register_category();

		// Load editor CSS/JS
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_editor_scripts' ) );

		// Load frontend CSS
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_frontend_styles' ) );

		// AJAX handlers
		add_action( 'wp_ajax_mhm_rentiva_get_vehicle_options', array( self::class, 'ajax_get_vehicle_options' ) );
	}

	/**
	 * Initializes Gutenberg integration
	 */
	public static function init(): void {
		// Check if Gutenberg is active
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register hooks
		self::register_hooks();
	}
}
