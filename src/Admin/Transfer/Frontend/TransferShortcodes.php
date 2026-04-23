<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Frontend;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Transfer Shortcodes Refactored
 *
 * @package MHMRentiva
 */





use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;
use MHMRentiva\Admin\Core\Assets\DatepickerAssets;
use MHMRentiva\Admin\Core\Utilities\Templates;



/**
 * Transfer Search Shortcode
 *
 * Handles transfer search form and AJAX results.
 * Standardized using AbstractShortcode base.
 */
final class TransferShortcodes extends AbstractShortcode {



	/**
	 * Tracks if shortcode was rendered for conditional asset loading.
	 *
	 * @var bool
	 */
	private static bool $rendered_transfer_search = false;


	/**
	 * Resolve transfer table name with fallback.
	 *
	 * @param string $legacy_suffix Legacy table suffix.
	 * @param string $current_suffix Current table suffix.
	 * @return string
	 */
	private static function resolve_transfer_table(string $legacy_suffix, string $current_suffix): string
	{
		global $wpdb;
		$legacy_table  = preg_replace('/[^A-Za-z0-9_]/', '', $wpdb->prefix . $legacy_suffix) ?? '';
		$current_table = preg_replace('/[^A-Za-z0-9_]/', '', $wpdb->prefix . $current_suffix) ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $legacy_table));
		if ($exists === $legacy_table) {
			return $legacy_table;
		}

		return $current_table;
	}


	/**
	 * Returns shortcode tag.
	 */
	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_transfer_search';
	}

	/**
	 * Registers shortcode to WordPress.
	 */
	public static function register(): void
	{
		parent::register();
	}

	/**
	 * Returns template file path.
	 */
	protected static function get_template_path(): string
	{
		return 'shortcodes/transfer-search';
	}

	/**
	 * Returns default attributes.
	 */
	protected static function get_default_attributes(): array
	{
		return array(
			'layout'       => 'horizontal',
			'button_text'  => '',
			'show_pickup'  => '1',
			'show_dropoff' => '1',
		);
	}

	/**
	 * Renders the shortcode while tracking usage.
	 *
	 * @param array       $atts    Shortcode attributes.
	 * @param string|null $content Shortcode content.
	 * @return string
	 */
	public static function render(array $atts = array(), ?string $content = null): string
	{
		// Force flag to true to ensure local assets (Premium CSS + Datepicker) are loaded.
		// This applies to both Classic Shortcodes and Gutenberg Blocks (via SSR do_shortcode).
		self::$rendered_transfer_search = true;
		return parent::render($atts, $content);
	}


	/**
	 * Prepares template data.
	 *
	 * @param array $atts Attributes.
	 * @return array
	 */
	protected static function prepare_template_data(array $atts): array
	{
		$locations = \MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_locations('transfer');

		return array(
			'locations' => $locations,
			'atts'      => $atts,
		);
	}

	/**
	 * Register hooks and AJAX.
	 */
	protected static function register_hooks(): void
	{
		add_action('wp_ajax_rentiva_transfer_search', array( self::class, 'handle_search_ajax' ));
		add_action('wp_ajax_nopriv_rentiva_transfer_search', array( self::class, 'handle_search_ajax' ));
	}

	/**
	 * Loads CSS and JS files.
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// Register Styles.
		$results_css_path = MHM_RENTIVA_PLUGIN_PATH . 'assets/css/frontend/transfer-results.css';
		if (file_exists($results_css_path)) {
			wp_enqueue_style(
				'mhm-rentiva-transfer-results-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/transfer-results.css',
				array(),
				MHM_RENTIVA_VERSION . '.' . time() // Force cache bust.
			);
		}

		$css_path = MHM_RENTIVA_PLUGIN_PATH . 'assets/css/frontend/transfer.css';
		if (file_exists($css_path)) {
			wp_enqueue_style(
				'mhm-rentiva-transfer-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/transfer.css',
				array(),
				MHM_RENTIVA_VERSION
			);
		}

		// Enqueue Premium CSS only if shortcode is actually rendered (Governance Check).
		if (self::$rendered_transfer_search) {
			wp_enqueue_style(
				'mhm-rentiva-search-premium',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/search-premium.css',
				array(),
				MHM_RENTIVA_VERSION
			);

			// Ensure datepicker assets are loaded via centralized helper.
			DatepickerAssets::enqueue();
		}

		// Register JS.
		$js_path = MHM_RENTIVA_PLUGIN_PATH . 'assets/js/rentiva-transfer.js';
		if (file_exists($js_path)) {
			wp_enqueue_script(
				'rentiva-transfer',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/rentiva-transfer.js',
				array( 'jquery' ),
				MHM_RENTIVA_VERSION,
				true
			);

			// Fetch Routes for Frontend Filtering
			global $wpdb;
			$table_routes = self::resolve_transfer_table('rentiva_transfer_routes', 'mhm_rentiva_transfer_routes');
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$routes = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_transfer_table().
				"SELECT origin_id, destination_id FROM {$table_routes}"
			);

			// Localize Data
			wp_localize_script(
				'rentiva-transfer',
				'rentiva_transfer_vars',
				array(
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce'    => wp_create_nonce('rentiva_transfer_nonce'),
					'cart_url' => function_exists('wc_get_cart_url') ? \wc_get_cart_url() : '',
					'routes'   => $routes,
					'i18n'     => array(
						'same_location_error' => __('Pick-up and Drop-off locations cannot be the same.', 'mhm-rentiva'),
						'no_route_error'      => __('No transfer route available between selected locations.', 'mhm-rentiva'),
						'searching_text'      => __('Searching...', 'mhm-rentiva'),
						'error_text'          => __('An error occurred. Please try again.', 'mhm-rentiva'),
						'processing_text'     => __('Processing...', 'mhm-rentiva'),
						'book_now_text'       => __('Book Now', 'mhm-rentiva'),
						'server_error'        => __('Server communication error!', 'mhm-rentiva'),
						'default_error'       => __('An error occurred.', 'mhm-rentiva'),
					),
				)
			);
		}
	}

	/**
	 * Handle AJAX Search
	 */
	public static function handle_search_ajax(): void
	{
		// Security Check
		check_ajax_referer('rentiva_transfer_nonce', 'security');

		// Sanitize all input data
		$criteria = array(
			'origin_id'      => isset($_POST['origin_id']) ? absint($_POST['origin_id']) : 0,
			'destination_id' => isset($_POST['destination_id']) ? absint($_POST['destination_id']) : 0,
			'date'           => isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '',
			'time'           => isset($_POST['time']) ? sanitize_text_field(wp_unslash($_POST['time'])) : '',
			'adults'         => isset($_POST['adults']) ? absint($_POST['adults']) : 1,
			'children'       => isset($_POST['children']) ? absint($_POST['children']) : 0,
			'luggage_big'    => isset($_POST['luggage_big']) ? absint($_POST['luggage_big']) : 0,
			'luggage_small'  => isset($_POST['luggage_small']) ? absint($_POST['luggage_small']) : 0,
		);

		$results = TransferSearchEngine::search($criteria);

		if (empty($results)) {
			wp_send_json_error(array( 'message' => __('No vehicles found matching your criteria.', 'mhm-rentiva') ));
		}

		// Initialize names
		global $wpdb;
		$origin_name      = '';
		$destination_name = '';

		$table_locations = self::resolve_transfer_table('rentiva_transfer_locations', 'mhm_rentiva_transfer_locations');

		if (! empty($criteria['origin_id'])) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_transfer_table().
			$origin_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_locations WHERE id = %d", $criteria['origin_id']));
		}
		if (! empty($criteria['destination_id'])) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is sanitized via resolve_transfer_table().
			$destination_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_locations WHERE id = %d", $criteria['destination_id']));
		}

		// Layout & columns: read from POST (allows JS pass-through), fallback to TransferResults defaults.
		$layout  = isset($_POST['layout']) ? sanitize_text_field(wp_unslash($_POST['layout'])) : 'list';
		$columns = isset($_POST['columns']) ? absint($_POST['columns']) : 2;

		ob_start();
		echo '<div class="mhm-transfer-results-page mhm-transfer-results rv-transfer-results rv-unified-search-results rv-transfer-results--' . esc_attr($layout) . '" data-columns="' . esc_attr( (string) $columns) . '" style="--columns: ' . esc_attr( (string) $columns) . ';">';
		echo '<div class="mhm-transfer-results__grid">';

		// Display attributes: consistent with TransferResults::get_default_attributes() defaults.
		$atts = array(
			'show_title'           => '1',
			'show_price'           => '1',
			'show_booking_button'  => '1',
			'show_vehicle_details' => '1',
			'show_luggage_info'    => '1',
			'show_passenger_count' => '1',
			'show_route_info'      => '1',
			'show_favorite_button' => '1',
			'show_compare_button'  => '1',
		);

		foreach ($results as $vehicle) {
			$transfer_card_html = Templates::render(
				'partials/transfer-card',
				array(
					'item'     => $vehicle,
					'criteria' => $criteria,
					'atts'     => $atts,
				),
				true
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally at field level.
			echo $transfer_card_html;
		}
		echo '</div></div>';
		$html = ob_get_clean();

		wp_send_json_success(array( 'html' => $html ));
	}
}
