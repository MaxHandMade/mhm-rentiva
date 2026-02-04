<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Reports\Reports;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_Query;
use Exception;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Vehicle Search Shortcode
 *
 * [rentiva_search] - General search form
 * [rentiva_search show_date_picker="0"] - Without date picker
 * [rentiva_search redirect_page="123"] - Redirect after search
 */
final class VehicleSearch extends AbstractShortcode
{
	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field((string) $value);
	}

	public const SHORTCODE = 'rentiva_search';

	protected static function get_shortcode_tag(): string
	{
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string
	{
		// DYNAMIC TEMPLATE SELECTION IS HANDLED IN render_template()
		return 'shortcodes/vehicle-search';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'layout'              => 'compact', // compact/full
			'show_date_picker'    => '1',     // 1/0
			'show_price_range'    => '1',     // 1/0
			'show_categories'     => '0',     // 1/0 - Disabled
			'show_fuel_type'      => '1',     // 1/0
			'show_transmission'   => '1',     // 1/0
			'show_seats'          => '1',     // 1/0
			'redirect_page'       => '',      // Page ID
			'results_per_page'    => '12',    // Number of results
			'show_instant_search' => '1',     // 1/0 - Instant search
			'class'               => '',      // Custom CSS class
			'style'               => '',      // Custom Inline Styles from Gutenberg
			'minwidth'            => '',      // Fallback/direct support
			'maxwidth'            => '',      // Fallback/direct support
			'height'              => '',      // Fallback/direct support
		);
	}

	protected static function register_ajax_handlers(): void
	{
		add_action('wp_ajax_mhm_rentiva_search_vehicles', array(self::class, 'ajax_search_vehicles'));
		add_action('wp_ajax_nopriv_mhm_rentiva_search_vehicles', array(self::class, 'ajax_search_vehicles'));
	}

	/**
	 * Override main render template logic to support dynamic templates
	 */
	protected static function render_template(array $template_data): string
	{
		$layout = $template_data['atts']['layout'] ?? 'compact';
		$template_name = ($layout === 'full') ? 'shortcodes/vehicle-search' : 'shortcodes/vehicle-search-compact';

		return Templates::render(
			$template_name,
			$template_data,
			true
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		// ⭐ Attribute Normalization (CamelCase -> lowercase)
		$atts = array_change_key_case($atts, CASE_LOWER);

		// Prepare form data
		$form_data = self::prepare_form_data($atts);

		// ⭐ Format dates for display in template
		$wp_format = get_option('date_format', 'Y-m-d');
		$pickup_date = self::sanitize_text_field_safe($_GET['pickup_date'] ?? $_GET['start_date'] ?? '');
		$return_date = self::sanitize_text_field_safe($_GET['return_date'] ?? $_GET['end_date'] ?? '');

		// ⭐ Default date calculation based on settings
		if (empty($pickup_date)) {
			$pickup_date = date('Y-m-d');
		}

		if (empty($return_date)) {
			$default_duration = (int) SettingsCore::get('mhm_rentiva_default_rental_days', 1);
			$return_date = date('Y-m-d', strtotime($pickup_date . " +$default_duration days"));
		}

		$form_data['pickup_time'] = date('H:00'); // Set current hour as default

		if ($pickup_date && strtotime($pickup_date)) {
			$form_data['pickup_date_formatted'] = date_i18n($wp_format, strtotime($pickup_date));
			$form_data['pickup_date'] = gmdate('Y-m-d', strtotime($pickup_date));
		} else {
			$form_data['pickup_date_formatted'] = '';
			$form_data['pickup_date'] = '';
		}

		if ($return_date && strtotime($return_date)) {
			$form_data['return_date_formatted'] = date_i18n($wp_format, strtotime($return_date));
			$form_data['return_date'] = gmdate('Y-m-d', strtotime($return_date));
		} else {
			$form_data['return_date_formatted'] = '';
			$form_data['return_date'] = '';
		}

		return array(
			'atts'        => $atts,
			'form_data'   => $form_data,
			'nonce_field' => wp_nonce_field('mhm_rentiva_search_action', 'mhm_rentiva_search_nonce', false, false),
		);
	}

	protected static function get_css_files(array $atts = []): array
	{
		$files = [];

		// Shared Datepicker CSS
		$files[] = 'assets/css/frontend/datepicker-custom.css';

		if (($atts['layout'] ?? 'compact') === 'full') {
			$files[] = 'assets/css/frontend/vehicle-search.css';
		} else {
			$files[] = 'assets/css/frontend/vehicle-search-compact.css';
		}

		return $files;
	}

	protected static function get_css_dependencies(): array
	{
		return ['mhm-rentiva-core-variables'];
	}

	protected static function get_js_files(array $atts = []): array
	{
		if (($atts['layout'] ?? 'compact') === 'full') {
			return ['assets/js/frontend/vehicle-search.js'];
		} else {
			return ['assets/js/frontend/vehicle-search-compact.js'];
		}
	}

	protected static function get_js_dependencies(): array
	{
		// FIX: Added 'jquery-ui-datepicker' as explicit dependency
		return array('jquery', 'jquery-ui-datepicker');
	}

	protected static function localize_script(string $handle): void
	{
		// Custom localization to match original implementation
		wp_localize_script(
			$handle,
			'mhmRentivaSearch',
			array(
				'ajax_url'           => admin_url('admin-ajax.php'),
				'nonce'              => wp_create_nonce('mhm_rentiva_search_nonce'),
				'search_results_url' => ShortcodeUrlManager::get_page_url('rentiva_search_results'),
				'i18n'               => array(
					'search_vehicles'     => __('Search Vehicles', 'mhm-rentiva'),
					'loading'             => __('Searching...', 'mhm-rentiva'),
					'no_results'          => __('No vehicles found matching your search criteria.', 'mhm-rentiva'),
					'error'               => __('An error occurred. Please try again.', 'mhm-rentiva'),
					'select_dates'        => __('Please select start and end dates.', 'mhm-rentiva'),
					'invalid_dates'       => __('Invalid date range.', 'mhm-rentiva'),
					'return_after_pickup' => __('Return date must be after pickup date', 'mhm-rentiva'),
					'pickup_past'         => __('Pickup date cannot be in the past', 'mhm-rentiva'),
					'field_required'      => __('This field is required', 'mhm-rentiva'),
					'invalid_price'       => __('Please enter a valid price', 'mhm-rentiva'),
					'min_price_error'     => __('Min price cannot be greater than max price', 'mhm-rentiva'),
					'max_price_error'     => __('Max price cannot be less than min price', 'mhm-rentiva'),
				),
				'datepicker_options' => array(
					'dateFormat'         => self::map_php_to_datepicker_format(get_option('date_format', 'Y-m-d')),
					'minDate'            => 0, // Today
					'showButtonPanel'    => true,
					'closeText'          => __('Close', 'mhm-rentiva'),
					'currentText'        => __('Today', 'mhm-rentiva'),
					'clearText'          => __('Clear', 'mhm-rentiva'),
					'monthNames'         => array(__('January', 'mhm-rentiva'), __('February', 'mhm-rentiva'), __('March', 'mhm-rentiva'), __('April', 'mhm-rentiva'), __('May', 'mhm-rentiva'), __('June', 'mhm-rentiva'), __('July', 'mhm-rentiva'), __('August', 'mhm-rentiva'), __('September', 'mhm-rentiva'), __('October', 'mhm-rentiva'), __('November', 'mhm-rentiva'), __('December', 'mhm-rentiva')),
					'monthNamesShort'    => array(__('Jan', 'mhm-rentiva'), __('Feb', 'mhm-rentiva'), __('Mar', 'mhm-rentiva'), __('Apr', 'mhm-rentiva'), __('May', 'mhm-rentiva'), __('Jun', 'mhm-rentiva'), __('Jul', 'mhm-rentiva'), __('Aug', 'mhm-rentiva'), __('Sep', 'mhm-rentiva'), __('Oct', 'mhm-rentiva'), __('Nov', 'mhm-rentiva'), __('Dec', 'mhm-rentiva')),
					'dayNames'           => array(__('Sunday', 'mhm-rentiva'), __('Monday', 'mhm-rentiva'), __('Tuesday', 'mhm-rentiva'), __('Wednesday', 'mhm-rentiva'), __('Thursday', 'mhm-rentiva'), __('Friday', 'mhm-rentiva'), __('Saturday', 'mhm-rentiva')),
					'dayNamesShort'      => array(__('Sun', 'mhm-rentiva'), __('Mon', 'mhm-rentiva'), __('Tue', 'mhm-rentiva'), __('Wed', 'mhm-rentiva'), __('Thu', 'mhm-rentiva'), __('Fri', 'mhm-rentiva'), __('Sat', 'mhm-rentiva')),
					'dayNamesMin'        => array(__('Su', 'mhm-rentiva'), __('Mo', 'mhm-rentiva'), __('Tu', 'mhm-rentiva'), __('We', 'mhm-rentiva'), __('Th', 'mhm-rentiva'), __('Fr', 'mhm-rentiva'), __('Sa', 'mhm-rentiva')),
					'weekHeader'         => __('Hf', 'mhm-rentiva'),
					'firstDay'           => 1, // Monday
					'isRTL'              => (is_rtl()),
					'showMonthAfterYear' => false,
					'yearSuffix'         => '',
				),
			)
		);
	}

	/**
	 * Map PHP date format to jQuery UI Datepicker format
	 */
	private static function map_php_to_datepicker_format(string $php_format): string
	{
		$replacements = array(
			'Y' => 'yy',
			'y' => 'y',
			'm' => 'mm',
			'n' => 'm',
			'd' => 'dd',
			'j' => 'd',
			'F' => 'MM',
			'M' => 'M',
			'l' => 'DD',
			'D' => 'D',
		);
		return strtr($php_format, $replacements);
	}

	public static function ajax_search_vehicles(): void
	{
		// Security check
		if (! check_ajax_referer('mhm_rentiva_search_nonce', 'nonce', false)) {
			wp_send_json_error(array('message' => __('Security check failed.', 'mhm-rentiva')));
			return;
		}

		$start_date = isset($_POST['start_date']) ? self::sanitize_text_field_safe(wp_unslash($_POST['start_date'])) : (isset($_POST['pickup_date']) ? self::sanitize_text_field_safe(wp_unslash($_POST['pickup_date'])) : '');
		$end_date   = isset($_POST['end_date']) ? self::sanitize_text_field_safe(wp_unslash($_POST['end_date'])) : (isset($_POST['return_date']) ? self::sanitize_text_field_safe(wp_unslash($_POST['return_date'])) : '');

		// Sanitize and validate input data
		$search_params = array(
			'keyword'      => isset($_POST['keyword']) ? self::sanitize_text_field_safe(wp_unslash($_POST['keyword'])) : '',
			'start_date'   => $start_date,
			'end_date'     => $end_date,
			'min_price'    => isset($_POST['min_price']) ? floatval(wp_unslash($_POST['min_price'])) : 0,
			'max_price'    => isset($_POST['max_price']) ? floatval(wp_unslash($_POST['max_price'])) : 0,
			'category'     => isset($_POST['category']) ? self::sanitize_text_field_safe(wp_unslash($_POST['category'])) : '',
			'fuel_type'    => isset($_POST['fuel_type']) ? self::sanitize_text_field_safe(wp_unslash($_POST['fuel_type'])) : '',
			'transmission' => isset($_POST['transmission']) ? self::sanitize_text_field_safe(wp_unslash($_POST['transmission'])) : '',
			'min_seats'    => isset($_POST['min_seats']) ? intval(wp_unslash($_POST['min_seats'])) : 0,
			'page'         => isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1,
			'per_page'     => isset($_POST['per_page']) ? max(1, min(50, intval(wp_unslash($_POST['per_page'])))) : 12,
		);

		try {
			$results = self::perform_search($search_params);
			wp_send_json_success($results);
		} catch (Exception $e) {
			wp_send_json_error(array('message' => $e->getMessage()));
		}
	}

	/**
	 * Prepare form data
	 */
	private static function prepare_form_data(array $atts): array
	{
		$redirect_url = '';
		if (! empty($atts['redirect_page'])) {
			$redirect_url = get_permalink($atts['redirect_page']);
		}

		// Fallback to automatic discovery if still empty
		if (empty($redirect_url) && class_exists('\MHMRentiva\Admin\Core\ShortcodeUrlManager')) {
			$redirect_url = ShortcodeUrlManager::get_page_url('rentiva_search_results');
		}

		return array(
			'categories'    => self::get_vehicle_categories(),
			'fuel_types'    => self::get_fuel_types(),
			'transmissions' => self::get_transmissions(),
			'seat_options'  => self::get_seat_options(),
			'price_ranges'  => self::get_price_ranges(),
			'redirect_url'  => $redirect_url,
		);
	}

	/**
	 * Get IDs of vehicles that are booked during the specified period
	 * 
	 * Uses Optimized SQL for high-speed availability filtering during search
	 */
	private static function get_busy_vehicle_ids(string $start_date, string $end_date): array
	{
		global $wpdb;

		// Convert dates to timestamps
		$start_ts = strtotime($start_date . ' 00:00:00');
		$end_ts   = strtotime($end_date . ' 23:59:59');

		if (! $start_ts || ! $end_ts) {
			return array();
		}

		// Buffer time
		$buffer_minutes = (int) SettingsCore::get('mhm_rentiva_booking_buffer_time', '60');
		$buffer_seconds = $buffer_minutes * 60;

		$current_time_gmt = current_time('mysql', 1);

		$busy_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
            SELECT DISTINCT pm_vid.meta_value 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = '_mhm_vehicle_id'
            INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
            INNER JOIN {$wpdb->postmeta} pm_date_s ON p.ID = pm_date_s.post_id AND pm_date_s.meta_key = '_mhm_pickup_date'
            INNER JOIN {$wpdb->postmeta} pm_date_e ON p.ID = pm_date_e.post_id AND pm_date_e.meta_key = '_mhm_dropoff_date'
            LEFT JOIN {$wpdb->postmeta} pm_time_s ON p.ID = pm_time_s.post_id AND pm_time_s.meta_key IN ('_mhm_pickup_time', '_mhm_start_time')
            LEFT JOIN {$wpdb->postmeta} pm_time_e ON p.ID = pm_time_e.post_id AND pm_time_e.meta_key IN ('_mhm_dropoff_time', '_mhm_end_time')
            LEFT JOIN {$wpdb->postmeta} pm_ts_s ON p.ID = pm_ts_s.post_id AND pm_ts_s.meta_key = '_mhm_start_ts'
            LEFT JOIN {$wpdb->postmeta} pm_ts_e ON p.ID = pm_ts_e.post_id AND pm_ts_e.meta_key = '_mhm_end_ts'
            LEFT JOIN {$wpdb->postmeta} pm_deadline ON p.ID = pm_deadline.post_id AND pm_deadline.meta_key = '_mhm_payment_deadline'
            
            WHERE p.post_type = 'vehicle_booking' 
            AND p.post_status = 'publish'
            AND pm_status.meta_value IN ('pending', 'confirmed', 'in_progress')
            AND (
                COALESCE(CAST(pm_ts_s.meta_value AS UNSIGNED), UNIX_TIMESTAMP(CONCAT(pm_date_s.meta_value, ' ', COALESCE(pm_time_s.meta_value, '10:00')))) < (%d + %d) 
                AND 
                %d < (COALESCE(CAST(pm_ts_e.meta_value AS UNSIGNED), UNIX_TIMESTAMP(CONCAT(pm_date_e.meta_value, ' ', COALESCE(pm_time_e.meta_value, '10:00')))) + %d)
            )
            AND (
                pm_status.meta_value != 'pending' OR 
                pm_deadline.meta_value IS NULL OR 
                pm_deadline.meta_value = '' OR 
                pm_deadline.meta_value > %s
            )
        ",
				$end_ts,
				$buffer_seconds,
				$start_ts,
				$buffer_seconds,
				$current_time_gmt
			)
		);

		return array_map('intval', array_unique(array_filter($busy_ids)));
	}

	/**
	 * Perform search operation
	 */
	private static function perform_search(array $params): array
	{
		// ⭐ Vehicle Management Settings'den ayarları al
		$cards_per_page = SettingsCore::get('mhm_rentiva_vehicle_cards_per_page', 12);
		$default_sort   = SettingsCore::get('mhm_rentiva_vehicle_default_sort', 'price_asc');

		$args = array(
			'post_type'      => PT_Vehicle::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $cards_per_page,
			'paged'          => $params['page'] ?? 1,
			'meta_query'     => array(),
		);

		// ⭐ Availability Filter: Exclude vehicles with overlaps
		if (! empty($params['start_date']) && ! empty($params['end_date'])) {
			$busy_ids = self::get_busy_vehicle_ids($params['start_date'], $params['end_date']);
			if (! empty($busy_ids)) {
				$args['post__not_in'] = $busy_ids;
			}
		}

		// Sort order mapping
		$sort_mapping = array(
			'price_asc'  => array(
				'meta_key' => '_mhm_rentiva_price_per_day',
				'orderby'  => 'meta_value_num',
				'order'    => 'ASC',
			),
			'price_desc' => array(
				'meta_key' => '_mhm_rentiva_price_per_day',
				'orderby'  => 'meta_value_num',
				'order'    => 'DESC',
			),
			'title_asc'  => array(
				'orderby' => 'title',
				'order'   => 'ASC',
			),
			'title_desc' => array(
				'orderby' => 'title',
				'order'   => 'DESC',
			),
			'date_asc'   => array(
				'orderby' => 'date',
				'order'   => 'ASC',
			),
			'date_desc'  => array(
				'orderby' => 'date',
				'order'   => 'DESC',
			),
		);

		$sort_config = $sort_mapping[$default_sort] ?? $sort_mapping['price_asc'];


		// Sort order uygula
		if (isset($sort_config['meta_key'])) {
			$args['meta_key'] = $sort_config['meta_key'];
			$args['orderby']  = $sort_config['orderby'];
		} else {
			$args['orderby'] = $sort_config['orderby'];
		}
		$args['order'] = $sort_config['order'];

		// Keyword search
		if (! empty($params['keyword'])) {
			$args['s'] = $params['keyword'];
		}

		// Price range
		if ($params['min_price'] > 0 || $params['max_price'] > 0) {
			$price_query = array(
				'key'  => '_mhm_rentiva_price_per_day',
				'type' => 'NUMERIC',
			);

			if ($params['min_price'] > 0 && $params['max_price'] > 0) {
				$price_query['value']   = array($params['min_price'], $params['max_price']);
				$price_query['compare'] = 'BETWEEN';
			} elseif ($params['min_price'] > 0) {
				$price_query['value']   = $params['min_price'];
				$price_query['compare'] = '>=';
			} elseif ($params['max_price'] > 0) {
				$price_query['value']   = $params['max_price'];
				$price_query['compare'] = '<=';
			}

			$args['meta_query'][] = $price_query;
		}

		// Fuel type
		if (! empty($params['fuel_type'])) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_fuel_type',
				'value'   => $params['fuel_type'],
				'compare' => '=',
			);
		}

		// Transmission type
		if (! empty($params['transmission'])) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_transmission',
				'value'   => $params['transmission'],
				'compare' => '=',
			);
		}

		// Minimum seats
		if ($params['min_seats'] > 0) {
			$args['meta_query'][] = array(
				'key'     => '_mhm_rentiva_seats',
				'value'   => $params['min_seats'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		// Category
		if (! empty($params['category'])) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'vehicle_category',
					'field'    => 'slug',
					'terms'    => $params['category'],
				),
			);
		}

		$query    = new \WP_Query($args);
		$vehicles = array();

		if ($query->have_posts()) {
			// N+1 Query Problem solution - Bulk meta data retrieval
			$post_ids  = array_column($query->posts, 'ID');
			$meta_data = self::get_bulk_meta_data($post_ids);

			while ($query->have_posts()) {
				$query->the_post();
				$vehicle_id = get_the_ID();

				$vehicles[] = array(
					'id'             => $vehicle_id,
					'title'          => get_the_title($vehicle_id),
					'excerpt'        => get_the_excerpt($vehicle_id),
					'permalink'      => get_permalink($vehicle_id),
					'featured_image' => get_the_post_thumbnail_url($vehicle_id, 'medium'),
					'price_per_day'  => $meta_data[$vehicle_id]['price_per_day'] ?? '0',
					'fuel_type'      => $meta_data[$vehicle_id]['fuel_type'] ?? '',
					'transmission'   => $meta_data[$vehicle_id]['transmission'] ?? '',
					'seats'          => $meta_data[$vehicle_id]['seats'] ?? '',
					'engine_size'    => $meta_data[$vehicle_id]['engine_size'] ?? '',
				);
			}
		}
		wp_reset_postdata();

		return array(
			'vehicles'     => $vehicles,
			'total'        => $query->found_posts,
			'pages'        => $query->max_num_pages,
			'current_page' => $params['page'],
			'per_page'     => $params['per_page'],
		);
	}

	/**
	 * Bulk meta data retrieval - N+1 Query Problem solution
	 */
	private static function get_bulk_meta_data(array $post_ids): array
	{
		if (empty($post_ids)) {
			return array();
		}

		global $wpdb;
		$meta_keys = array(
			'_mhm_rentiva_price_per_day',
			'_mhm_rentiva_fuel_type',
			'_mhm_rentiva_transmission',
			'_mhm_rentiva_seats',
			'_mhm_rentiva_engine_size',
		);

		$placeholders      = implode(',', array_fill(0, count($post_ids), '%d'));
		$meta_placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

		$results   = $wpdb->get_results(
			$wpdb->prepare(
				"
            SELECT post_id, meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($placeholders) 
            AND meta_key IN ($meta_placeholders)
        ", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge($post_ids, $meta_keys)
			)
		);
		$meta_data = array();

		foreach ($results as $row) {
			$post_id                       = (int) $row->post_id;
			$key                           = str_replace('_mhm_rentiva_', '', $row->meta_key);
			$meta_data[$post_id][$key] = $row->meta_value;
		}

		return $meta_data;
	}

	/**
	 * Get vehicle categories
	 */
	private static function get_vehicle_categories(): array
	{
		$categories = get_terms(
			array(
				'taxonomy'   => 'vehicle_category',
				'hide_empty' => true,
			)
		);

		$result = array('' => __('All Categories', 'mhm-rentiva'));

		if (! is_wp_error($categories) && is_array($categories)) {
			foreach ($categories as $category) {
				if (is_object($category) && isset($category->slug, $category->name)) {
					$result[$category->slug] = $category->name;
				}
			}
		}

		return $result;
	}

	/**
	 * Get fuel types
	 */
	private static function get_fuel_types(): array
	{
		$fuel_types = VehicleMeta::get_fuel_types();
		$result     = array('' => __('All Fuel Types', 'mhm-rentiva'));
		return array_merge($result, $fuel_types);
	}

	/**
	 * Get transmission types
	 */
	private static function get_transmissions(): array
	{
		$transmission_types = VehicleMeta::get_transmission_types();
		$result             = array('' => __('All Transmission Types', 'mhm-rentiva'));
		return array_merge($result, $transmission_types);
	}

	/**
	 * Get seat options
	 */
	private static function get_seat_options(): array
	{
		return array(
			''  => __('All Seat Counts', 'mhm-rentiva'),
			'2' => __('2 Seats', 'mhm-rentiva'),
			'4' => __('4 Seats', 'mhm-rentiva'),
			'5' => __('5 Seats', 'mhm-rentiva'),
			'7' => __('7 Seats', 'mhm-rentiva'),
			'8' => __('8+ Seats', 'mhm-rentiva'),
		);
	}

	/**
	 * Get price ranges
	 */
	private static function get_price_ranges(): array
	{
		$currency_symbol = Reports::get_currency_symbol();

		return array(
			/* translators: %s: currency symbol */
			'0-100'    => sprintf(__('0 - 100 %s', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'100-200'  => sprintf(__('100 - 200 %s', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'200-300'  => sprintf(__('200 - 300 %s', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'300-500'  => sprintf(__('300 - 500 %s', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'500-1000' => sprintf(__('500 - 1000 %s', 'mhm-rentiva'), $currency_symbol),
			/* translators: %s: currency symbol */
			'1000+'    => sprintf(__('1000+ %s', 'mhm-rentiva'), $currency_symbol),
		);
	}
}
