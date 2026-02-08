<?php

/**
 * Transfer Shortcodes Refactored
 * 
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Frontend;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Transfer\Engine\TransferSearchEngine;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Transfer Search Shortcode
 *
 * Handles transfer search form and AJAX results.
 * Standardized using AbstractShortcode base.
 */
final class TransferShortcodes extends AbstractShortcode
{

	/**
	 * Returns shortcode tag
	 */
	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_transfer_search';
	}

	/**
	 * Registers shortcode to WordPress
	 */
	public static function register(): void
	{
		parent::register();
	}

	/**
	 * Returns template file path
	 */
	protected static function get_template_path(): string
	{
		return 'shortcodes/transfer-search';
	}

	/**
	 * Returns default attributes
	 */
	protected static function get_default_attributes(): array
	{
		return array();
	}

	/**
	 * Prepares template data
	 */
	protected static function prepare_template_data(array $atts): array
	{
		global $wpdb;
		$table_locations = $wpdb->prefix . 'rentiva_transfer_locations';
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_locations'") != $table_locations) {
			$table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$locations = $wpdb->get_results($wpdb->prepare("SELECT id, name, type FROM {$table_locations} WHERE is_active = %d ORDER BY priority ASC, name ASC", 1));

		return array(
			'locations' => $locations,
			'atts'      => $atts,
		);
	}

	/**
	 * Register hooks and AJAX
	 */
	protected static function register_hooks(): void
	{
		add_action('wp_ajax_rentiva_transfer_search', array(self::class, 'handle_search_ajax'));
		add_action('wp_ajax_nopriv_rentiva_transfer_search', array(self::class, 'handle_search_ajax'));
	}

	/**
	 * Loads CSS and JS files
	 */
	protected static function enqueue_assets(array $atts = []): void
	{
		// Register CSS
		$results_css_path = MHM_RENTIVA_PLUGIN_PATH . 'assets/css/frontend/transfer-results.css';
		if (file_exists($results_css_path)) {
			wp_enqueue_style(
				'mhm-rentiva-transfer-results-css',
				MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/transfer-results.css',
				array(),
				MHM_RENTIVA_VERSION . '.' . time() // Force cache bust
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

		// Register JS
		$js_path = MHM_RENTIVA_PLUGIN_PATH . 'assets/js/rentiva-transfer.js';
		if (file_exists($js_path)) {
			wp_enqueue_script(
				'rentiva-transfer',
				MHM_RENTIVA_PLUGIN_URL . 'assets/js/rentiva-transfer.js',
				array('jquery'),
				MHM_RENTIVA_VERSION,
				true
			);

			// Fetch Routes for Frontend Filtering
			global $wpdb;
			$table_routes = $wpdb->prefix . 'rentiva_transfer_routes';
			if ($wpdb->get_var("SHOW TABLES LIKE '$table_routes'") != $table_routes) {
				$table_routes = $wpdb->prefix . 'mhm_rentiva_transfer_routes';
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$routes = $wpdb->get_results("SELECT origin_id, destination_id FROM {$table_routes}");

			// Localize Data
			wp_localize_script(
				'rentiva-transfer',
				'rentiva_transfer_vars',
				array(
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce'    => wp_create_nonce('rentiva_transfer_nonce'),
					'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
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
			wp_send_json_error(array('message' => __('No vehicles found matching your criteria.', 'mhm-rentiva')));
		}

		// Initialize names
		global $wpdb;
		$origin_name      = '';
		$destination_name = '';

		$table_locations = $wpdb->prefix . 'rentiva_transfer_locations';
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_locations'") != $table_locations) {
			$table_locations = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
		}

		if (! empty($criteria['origin_id'])) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$origin_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table_locations} WHERE id = %d", $criteria['origin_id']));
		}
		if (! empty($criteria['destination_id'])) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$destination_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table_locations} WHERE id = %d", $criteria['destination_id']));
		}

		ob_start();
		echo '<div class="mhm-transfer-results-grid">';
		foreach ($results as $vehicle) {
			// Prepare Transfer Meta per Vehicle
			$vehicle_meta                     = $criteria;
			$vehicle_meta['price']            = $vehicle['price'];
			$vehicle_meta['duration']         = $vehicle['duration'];
			$vehicle_meta['distance']         = $vehicle['distance'];
			$vehicle_meta['origin_name']      = $origin_name;
			$vehicle_meta['destination_name'] = $destination_name;
			$transfer_meta_json               = wp_json_encode($vehicle_meta);
?>
			<div class="mhm-transfer-card">
				<div class="mhm-transfer-card-image">
					<img src="<?php echo esc_url($vehicle['image']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>">
				</div>
				<div class="mhm-transfer-card-content">
					<h3><?php echo esc_html($vehicle['title']); ?></h3>
					<div class="mhm-transfer-features">
						<span>
							<svg class="rv-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
								<circle cx="9" cy="7" r="4"></circle>
								<path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
								<path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
							</svg>
							<?php echo esc_html((string) $vehicle['max_pax']); ?> <?php esc_html_e('Person', 'mhm-rentiva'); ?>
						</span>
						<span>
							<svg class="rv-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<circle cx="12" cy="12" r="10"></circle>
								<polyline points="12 6 12 12 16 14"></polyline>
							</svg>
							<?php echo esc_html((string) $vehicle['duration']); ?> <?php esc_html_e('min', 'mhm-rentiva'); ?>
						</span>
					</div>
					<div class="mhm-transfer-price">
						<strong><?php echo wp_kses_post(wc_price($vehicle['price'])); ?></strong>
					</div>
					<button class="mhm-transfer-book-btn"
						data-vehicle-id="<?php echo esc_attr((string) $vehicle['id']); ?>"
						data-transfer-meta="<?php echo esc_attr($transfer_meta_json); ?>">
						<?php esc_html_e('Book Now', 'mhm-rentiva'); ?>
					</button>
				</div>
			</div>
<?php
		}
		echo '</div>';
		$html = ob_get_clean();

		wp_send_json_success(array('html' => $html));
	}
}
