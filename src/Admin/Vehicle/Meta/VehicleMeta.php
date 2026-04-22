<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;



final class VehicleMeta extends AbstractMetaBox
{


	/**
	 * ⭐ Get maximum seats limit (configurable from settings, default: 100)
	 */
	private static function get_max_seats(): int
	{
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhm_rentiva_vehicle_max_seats',
			100 // Default: 100 (supports buses)
		);
	}

	/**
	 * ⭐ Get maximum doors limit (configurable from settings, default: 20)
	 */
	private static function get_max_doors(): int
	{
		return (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhm_rentiva_vehicle_max_doors',
			20 // Default: 20
		);
	}

	/**
	 * ⭐ Get minimum engine size (configurable from settings, default: 0.0 for electric vehicles)
	 */
	private static function get_min_engine_size(): float
	{
		return (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhm_rentiva_vehicle_min_engine_size',
			0.0 // Default: 0.0 (supports electric vehicles)
		);
	}

	/**
	 * ⭐ Get maximum engine size (configurable from settings, default: 20.0 for large engines)
	 */
	private static function get_max_engine_size(): float
	{
		return (float) \MHMRentiva\Admin\Settings\Core\SettingsCore::get(
			'mhm_rentiva_vehicle_max_engine_size',
			20.0 // Default: 20.0 (supports large engines)
		);
	}

	/**
	 * ⭐ Get available fuel types
	 */
	public static function get_fuel_types(): array
	{
		return array(
			'petrol'   => __('Petrol', 'mhm-rentiva'),
			'diesel'   => __('Diesel', 'mhm-rentiva'),
			'hybrid'   => __('Hybrid', 'mhm-rentiva'),
			'electric' => __('Electric', 'mhm-rentiva'),
			'lpg'      => __('LPG', 'mhm-rentiva'),
			'cng'      => __('CNG', 'mhm-rentiva'),
			'hydrogen' => __('Hydrogen', 'mhm-rentiva'),
		);
	}

	/**
	 * ⭐ Get available transmission types
	 */
	public static function get_transmission_types(): array
	{
		return array(
			'auto'      => __('Automatic', 'mhm-rentiva'),
			'manual'    => __('Manual', 'mhm-rentiva'),
			'semi_auto' => __('Semi-Automatic', 'mhm-rentiva'),
			'cvt'       => __('CVT', 'mhm-rentiva'),
		);
	}

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

	/**
	 * Read sanitized text from POST.
	 */
	private static function post_text(string $key, string $default = ''): string
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in caller save/AJAX handlers.
		$request = (array) ($_POST ?? array());
		if (! isset($request[$key])) {
			return $default;
		}
		$value = sanitize_text_field(wp_unslash((string) $request[$key]));
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $value;
	}

	/**
	 * Read unslashed array from POST.
	 *
	 * @return array<mixed>
	 */
	private static function post_array(string $key): array
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified in caller save/AJAX handlers.
		$request = (array) ($_POST ?? array());
		if (! isset($request[$key]) || ! is_array($request[$key])) {
			return array();
		}
		$value = wp_unslash($request[$key]);
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return $value;
	}

	protected static function get_post_type(): string
	{
		return 'vehicle';
	}

	protected static function get_meta_box_id(): string
	{
		return 'mhm_rentiva_vehicle_details';
	}

	protected static function get_title(): string
	{
		return __('Vehicle Details', 'mhm-rentiva');
	}

	protected static function get_fields(): array
	{
		return array(
			'mhm_rentiva_vehicle_details' => array(
				'title'        => __('Vehicle Details', 'mhm-rentiva'),
				'context'      => 'advanced',
				'priority'     => 'high',
				'template'     => 'render_meta_box',
				'save_handler' => 'save_vehicle_meta',
			),
		);
	}

	public static function register(): void
	{
		parent::register();

		add_action('init', array(self::class, 'register_meta_fields'));
		add_action('admin_enqueue_scripts', array(self::class, 'enqueue_scripts'));
		add_action('add_meta_boxes_vehicle', array(self::class, 'add_featured_meta_box'));
		add_action('save_post_vehicle', array(self::class, 'save_featured_meta_box'));

		add_action('admin_head', array(self::class, 'hide_default_meta_boxes'));

		add_action('wp_ajax_mhm_save_item_order', array(self::class, 'ajax_save_item_order'));

		add_action('add_meta_boxes', array(self::class, 'reorder_meta_boxes'), 999);
	}

	public static function enqueue_scripts(): void
	{
		global $post_type, $pagenow;

		if ($post_type === 'vehicle' && ($pagenow === 'post.php' || $pagenow === 'post-new.php')) {
			wp_enqueue_style(
				'mhm-vehicle-meta-css',
				\MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/vehicle-meta.css',
				array(),
				\MHM_RENTIVA_VERSION . '-' . time()
			);

			wp_enqueue_script(
				'mhm-vehicle-meta-js',
				\MHM_RENTIVA_PLUGIN_URL . 'assets/js/components/vehicle-meta.js',
				array('jquery', 'jquery-ui-sortable'),
				\MHM_RENTIVA_VERSION,
				true
			);

			wp_localize_script(
				'mhm-vehicle-meta-js',
				'mhmVehicleMeta',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce'   => wp_create_nonce('mhm_vehicle_meta_nonce'),
					'strings' => array(
						'orderUpdated'           => __('Order updated!', 'mhm-rentiva'),
						'orderSaveError'         => __('Failed to save order', 'mhm-rentiva'),
						'ajaxError'              => __('AJAX error: Failed to save order', 'mhm-rentiva'),
						'enterNewFeature'        => __('Enter new feature name:', 'mhm-rentiva'),
						'enterNewEquipment'      => __('Enter new equipment name:', 'mhm-rentiva'),
						'enterNewDetail'         => __('Enter new detail name:', 'mhm-rentiva'),
						'confirmRemoveFeature'   => __('Are you sure you want to remove this feature?', 'mhm-rentiva'),
						'confirmRemoveEquipment' => __('Are you sure you want to remove this equipment?', 'mhm-rentiva'),
						'enterValue'             => __('Enter value', 'mhm-rentiva'),
						'comingSoonCustomAdd'    => __('Coming soon! For now, use the Custom Add button.', 'mhm-rentiva'),
						'comingSoonCustomRemove' => __('Coming soon! For now, use the Custom Add button.', 'mhm-rentiva'),
						'redirectingToSettings'  => __('Redirecting to Vehicle Settings page...', 'mhm-rentiva'),
					),
				)
			);
		}
	}

	public static function add_meta_boxes(): void
	{
		remove_meta_box('postexcerpt', 'vehicle', 'normal');
		remove_meta_box('slugdiv', 'vehicle', 'normal');

		parent::add_meta_boxes();
	}

	/**
	 * Add featured flag UI on vehicle edit screen.
	 */
	public static function add_featured_meta_box(): void
	{
		add_meta_box(
			'mhm_rentiva_vehicle_featured',
			__('Featured', 'mhm-rentiva'),
			array(self::class, 'render_featured_meta_box'),
			'vehicle',
			'side',
			'default'
		);
	}

	/**
	 * Render featured checkbox field.
	 */
	public static function render_featured_meta_box(\WP_Post $post): void
	{
		wp_nonce_field('mhm_rentiva_vehicle_featured_action', 'mhm_rentiva_vehicle_featured_nonce');
		$is_featured = get_post_meta($post->ID, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FEATURED, true) === '1';
?>
		<p>
			<label for="mhm_rentiva_is_featured">
				<input type="checkbox" id="mhm_rentiva_is_featured" name="mhm_rentiva_is_featured" value="1" <?php checked($is_featured); ?> />
				<?php esc_html_e('Featured vehicle', 'mhm-rentiva'); ?>
			</label>
		</p>
<?php
	}

	/**
	 * Save featured checkbox value with nonce/capability checks.
	 */
	public static function save_featured_meta_box(int $post_id): void
	{
		if (! isset($_POST['mhm_rentiva_vehicle_featured_nonce']) || ! wp_verify_nonce(self::post_text('mhm_rentiva_vehicle_featured_nonce'), 'mhm_rentiva_vehicle_featured_action')) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		$is_featured = isset($_POST['mhm_rentiva_is_featured']) ? '1' : '0';
		update_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FEATURED, $is_featured);

		// Keep frontend featured/list outputs consistent immediately after admin save.
		\MHMRentiva\Admin\Core\Utilities\CacheManager::clear_vehicle_cache($post_id);
		if (class_exists(\MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles::class)) {
			\MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles::cleanup();
		}
		if (class_exists(\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::class)) {
			\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::cleanup();
		}
		if (class_exists(\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::class)) {
			\MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::cleanup();
		}
	}

	/**
	 * Hide default meta boxes
	 */
	public static function hide_default_meta_boxes(): void
	{
		global $post_type;

		if ($post_type === 'vehicle') {
			echo '<style>
                #postexcerpt,
                #slugdiv
                { display: none !important; }
            </style>';
		}
	}

	public static function render_meta_box(\WP_Post $post, array $args = array()): void
	{
		wp_nonce_field('mhm_rentiva_vehicle_meta_action', 'mhm_rentiva_vehicle_meta_nonce');

		$template_data = self::prepare_template_data($post);

		$template_path = \MHM_RENTIVA_PLUGIN_DIR . 'src/Admin/Vehicle/Templates/vehicle-meta.php';

		if (file_exists($template_path)) {
			self::include_template_with_vars($template_path, $template_data);
		} else {
			echo '<div class="error"><p>' . esc_html__('Template file not found: vehicle-meta.php', 'mhm-rentiva') . '</p></div>';
			echo '<p>Searched path: ' . esc_html($template_path) . '</p>';
		}
	}

	/**
	 * Include template with validated variable names.
	 */
	private static function include_template_with_vars(string $template_path, array $template_data): void
	{
		(static function () use ($template_path, $template_data): void {
			foreach ($template_data as $key => $value) {
				if (! is_string($key) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
					continue;
				}
				${$key} = $value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			}
			include $template_path;
		})();
	}

	/**
	 * Prepare data for template (performance optimization)
	 */
	private static function prepare_template_data($post): array
	{
		$vehicle_id = $post->ID;

		$field_settings = self::get_field_settings();

		$meta_data = self::get_vehicle_meta_data($post->ID, $field_settings['custom_fields']);

		$available_details   = self::build_available_fields($field_settings['selected_details'], $field_settings['default_details'], $field_settings['custom_details']);
		$available_features  = self::build_available_fields($field_settings['selected_features'], $field_settings['default_features'], $field_settings['custom_features']);
		$available_equipment = self::build_available_fields($field_settings['selected_equipment'], $field_settings['default_equipment'], $field_settings['custom_equipment']);

		// Custom Field Meta (Type & Options)
		$field_meta = get_option('mhm_custom_field_meta', array());

		self::ensure_default_options($available_details, $available_features, $available_equipment);

		$removed_details = $meta_data['_mhm_removed_details'] ?? array();
		if (! is_array($removed_details)) {
			$removed_details = array();
		}

		$custom_details = $meta_data['_mhm_custom_details'] ?? array();
		if (! is_array($custom_details)) {
			$custom_details = array();
		}

		foreach ($custom_details as $key => $detail) {
			if (isset($available_details[$key])) {
				unset($available_details[$key]);
			}
		}

		$features = $meta_data['_mhm_rentiva_features'] ?? array();
		if (! is_array($features)) {
			$features = array();
		}

		$equipment = $meta_data['_mhm_rentiva_equipment'] ?? array();
		if (! is_array($equipment)) {
			$equipment = array();
		}

		$available_value = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_status($post->ID);

		foreach ($available_details as $key => $label) {
			$detail_values[$key] = $meta_data['_mhm_rentiva_' . $key] ?? '';

			// Default Deposit: 10% (v4.9.0)
			// Treat empty, null, or '0' as 10 to ensure persistence and visual clarity
			if ($key === 'deposit' && ($detail_values[$key] === '' || $detail_values[$key] === null || $detail_values[$key] === '0' || $detail_values[$key] === 0)) {
				$detail_values[$key] = '10';
			}

			if (strpos($key, 'custom_') === 0) {
			}
		}

		return array(
			'post'                  => $post,
			'price_per_day'         => $meta_data['_mhm_rentiva_price_per_day'] ?? '',
			'year'                  => $meta_data['_mhm_rentiva_year'] ?? '',
			'mileage'               => $meta_data['_mhm_rentiva_mileage'] ?? '',
			'license_plate'         => $meta_data['_mhm_rentiva_license_plate'] ?? '',
			'color'                 => $meta_data['_mhm_rentiva_color'] ?? '',
			'brand'                 => $meta_data['_mhm_rentiva_brand'] ?? '',
			'model'                 => $meta_data['_mhm_rentiva_model'] ?? '',
			'seats'                 => $meta_data['_mhm_rentiva_seats'] ?? '',
			'doors'                 => $meta_data['_mhm_rentiva_doors'] ?? '',
			'transmission'          => $meta_data['_mhm_rentiva_transmission'] ?? '',
			'fuel_type'             => $meta_data['_mhm_rentiva_fuel_type'] ?? '',
			'engine_size'           => $meta_data['_mhm_rentiva_engine_size'] ?? '',
			'available'             => $available_value,
			'features'              => $features,
			'equipment'             => $equipment,
			'available_details'     => $available_details,
			'available_features'    => $available_features,
			'available_equipment'   => $available_equipment,
			'removed_details'       => $removed_details,
			'custom_details'        => $custom_details,
			'saved_order'           => $meta_data['_mhm_details_order'] ?? array(),
			'saved_features_order'  => $meta_data['_mhm_features_order'] ?? array(),
			'saved_equipment_order' => $meta_data['_mhm_equipment_order'] ?? array(),
			'detail_values'         => $detail_values,
			'field_meta'            => $field_meta,
			'location_id'           => get_post_meta($post->ID, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID, true),
			'available_locations'   => \MHMRentiva\Admin\Transfer\Engine\LocationProvider::get_locations('rental'),
		);
	}

	/**
	 * Default details
	 */
	private static function get_default_details(): array
	{
		return array(
			'price_per_day' => __('Daily Price', 'mhm-rentiva'),
			'year'          => __('Model Year', 'mhm-rentiva'),
			'mileage'       => __('Mileage', 'mhm-rentiva'),
			'license_plate' => __('License Plate', 'mhm-rentiva'),
			'color'         => __('Color', 'mhm-rentiva'),
			'brand'         => __('Brand', 'mhm-rentiva'),
			'model'         => __('Model', 'mhm-rentiva'),
			'seats'         => __('Number of Seats', 'mhm-rentiva'),
			'doors'         => __('Number of Doors', 'mhm-rentiva'),
			'transmission'  => __('Transmission Type', 'mhm-rentiva'),
			'fuel_type'     => __('Fuel Type', 'mhm-rentiva'),
			'engine_size'   => __('Engine Size', 'mhm-rentiva'),
			'deposit'       => __('Deposit', 'mhm-rentiva'),
			'availability'  => __('Availability Status', 'mhm-rentiva'),
		);
	}

	/**
	 * Default features
	 */
	private static function get_default_features(): array
	{
		return array(
			'air_conditioning' => __('Air Conditioning', 'mhm-rentiva'),
			'power_steering'   => __('Power Steering', 'mhm-rentiva'),
			'abs_brakes'       => __('ABS Brake System', 'mhm-rentiva'),
			'airbags'          => __('Airbags', 'mhm-rentiva'),
			'central_locking'  => __('Central Locking', 'mhm-rentiva'),
			'electric_windows' => __('Electric Windows', 'mhm-rentiva'),
			'power_mirrors'    => __('Power Mirrors', 'mhm-rentiva'),
			'fog_lights'       => __('Fog Lights', 'mhm-rentiva'),
			'cruise_control'   => __('Cruise Control', 'mhm-rentiva'),
			'bluetooth'        => __('Bluetooth', 'mhm-rentiva'),
			'usb_port'         => __('USB Port', 'mhm-rentiva'),
			'navigation'       => __('Navigation', 'mhm-rentiva'),
			'sunroof'          => __('Sunroof', 'mhm-rentiva'),
			'leather_seats'    => __('Leather Seats', 'mhm-rentiva'),
			'heated_seats'     => __('Heated Seats', 'mhm-rentiva'),
		);
	}

	/**
	 * Default equipment
	 */
	private static function get_default_equipment(): array
	{
		return array(
			'spare_tire'        => __('Spare Tire', 'mhm-rentiva'),
			'jack'              => __('Jack', 'mhm-rentiva'),
			'first_aid_kit'     => __('First Aid Kit', 'mhm-rentiva'),
			'fire_extinguisher' => __('Fire Extinguisher', 'mhm-rentiva'),
			'warning_triangle'  => __('Warning Triangle', 'mhm-rentiva'),
			'jumper_cables'     => __('Jumper Cables', 'mhm-rentiva'),
			'ice_scraper'       => __('Ice Scraper', 'mhm-rentiva'),
			'car_cover'         => __('Car Cover', 'mhm-rentiva'),
			'child_seat'        => __('Child Seat', 'mhm-rentiva'),
			'gps_tracker'       => __('GPS Tracker', 'mhm-rentiva'),
			'dashcam'           => __('Dashcam', 'mhm-rentiva'),
			'phone_holder'      => __('Phone Holder', 'mhm-rentiva'),
			'charger'           => __('Charger', 'mhm-rentiva'),
			'cleaning_kit'      => __('Cleaning Kit', 'mhm-rentiva'),
			'emergency_kit'     => __('Emergency Kit', 'mhm-rentiva'),
		);
	}

	public static function save_meta(int $post_id, \WP_Post $post): void
	{
		if (! in_array($post->post_type, array('vehicle', 'vehicle_booking'), true)) {
			return;
		}

		if (! isset($_POST['mhm_rentiva_vehicle_meta_nonce']) || ! wp_verify_nonce(self::post_text('mhm_rentiva_vehicle_meta_nonce'), 'mhm_rentiva_vehicle_meta_action')) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		$available_details   = get_option('mhm_vehicle_details', self::get_default_details());
		$available_features  = get_option('mhm_vehicle_features', self::get_default_features());
		$available_equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());

		$details_order = json_decode(self::post_text('details-grid_order'), true);

		if ($details_order && is_array($details_order)) {
			update_post_meta($post_id, '_mhm_details_order', $details_order);
		}

		$removed_details = json_decode(self::post_text('removed_details'), true);
		if (! is_array($removed_details)) {
			$removed_details = array();
		}

		$meta_updates = array();

		$status_to_save = '';
		if (isset($_POST['_mhm_vehicle_status'])) {
			$status_to_save = self::post_text('_mhm_vehicle_status');
		} elseif (isset($_POST['mhm_rentiva_available'])) {
			$status_to_save = self::post_text('mhm_rentiva_available');
		}

		if (! empty($status_to_save)) {
			$normalized = self::normalize_availability($status_to_save);
			$meta_updates[\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS] = $normalized;
		}

		foreach ($available_details as $key => $label) {
			if (in_array($key, $removed_details) && $key !== 'engine_size') {
				continue;
			}

			$field_name = 'mhm_rentiva_' . $key;
			$meta_key   = '_mhm_rentiva_' . $key;
			// Sanitize value from POST
			$value                     = self::post_text($field_name);
			$sanitized_value           = self::sanitize_field($field_name, $value);
			$meta_updates[$meta_key] = $sanitized_value;
		}

		$meta_updates['_mhm_removed_details'] = $removed_details;

		if (! empty($removed_details)) {
			foreach ($removed_details as $removed_key) {
				unset($available_details[$removed_key]);
			}
			$option_updated = true;
		}

		$custom_details   = get_option('mhm_custom_details', array());
		$custom_features  = get_option('mhm_custom_features', array());
		$custom_equipment = get_option('mhm_custom_equipment', array());

		// Include Taxonomy Terms in Save Logic
		$taxonomy_features  = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::get_taxonomy_features();
		$taxonomy_equipment = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::get_taxonomy_equipment();

		$custom_features  = array_merge($custom_features, $taxonomy_features);
		$custom_equipment = array_merge($custom_equipment, $taxonomy_equipment);

		$all_custom_fields = array_merge($custom_details, $custom_features, $custom_equipment);

		foreach ($all_custom_fields as $field_key => $field_label) {
			$meta_key   = '_mhm_rentiva_' . $field_key;
			$field_name = 'mhm_rentiva_' . $field_key;

			if (isset($_POST[$field_name])) {
				$value                     = self::post_text($field_name);
				$value                     = mb_convert_encoding($value, 'UTF-8', 'auto');
				$meta_updates[$meta_key] = $value;
			}
		}

		// Sanitize legacy custom details array
		$legacy_custom_details = self::post_array('mhm_rentiva_custom_details');

		$sanitized_custom_details = array();

		foreach ($legacy_custom_details as $key => $detail_data) {
			if (is_array($detail_data) && isset($detail_data['label']) && isset($detail_data['value'])) {
				$sanitized_custom_details[self::sanitize_text_field_safe($key)] = array(
					'label' => self::sanitize_text_field_safe($detail_data['label']),
					'value' => self::sanitize_text_field_safe($detail_data['value']),
				);
			} else {
				$sanitized_custom_details[self::sanitize_text_field_safe($key)] = array(
					'label' => self::sanitize_text_field_safe($key),
					'value' => self::sanitize_text_field_safe($detail_data),
				);
			}
		}

		$meta_updates['_mhm_custom_details'] = $sanitized_custom_details;

		foreach ($meta_updates as $meta_key => $meta_value) {
			update_post_meta($post_id, $meta_key, $meta_value);
		}

		$features_order  = json_decode(self::post_text('features-grid_order'), true);
		$equipment_order = json_decode(self::post_text('equipment-grid_order'), true);

		if ($features_order && is_array($features_order)) {
			update_post_meta($post_id, '_mhm_features_order', $features_order);
		}

		if ($equipment_order && is_array($equipment_order)) {
			update_post_meta($post_id, '_mhm_equipment_order', $equipment_order);
		}

		// Sanitize features array before processing
		$features           = array_map(array(self::class, 'sanitize_text_field_safe'), self::post_array('mhm_rentiva_features'));
		$sanitized_features = self::sanitize_array($features);
		update_post_meta($post_id, '_mhm_rentiva_features', $sanitized_features);

		// Sanitize equipment array before processing
		$equipment           = array_map(array(self::class, 'sanitize_text_field_safe'), self::post_array('mhm_rentiva_equipment'));
		$sanitized_equipment = self::sanitize_array($equipment);
		update_post_meta($post_id, '_mhm_rentiva_equipment', $sanitized_equipment);

		// Save vehicle location (field name is the meta key itself, empty = Inherit/Vendor/Global)
		$location_key = \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID;
		if (isset($_POST[ $location_key ])) {
			$location_val = self::post_text($location_key);
			if ($location_val === '') {
				delete_post_meta($post_id, $location_key);
			} else {
				update_post_meta($post_id, $location_key, absint($location_val));
			}
		}
	}

	/**
	 * Sanitize field values
	 */
	private static function sanitize_field(string $field_name, $value)
	{
		switch ($field_name) {
			case 'mhm_rentiva_license_plate':
				return strtoupper(self::sanitize_text_field_safe($value));

			case 'mhm_rentiva_price_per_day':
				$price = floatval($value);
				return max(0, $price);

			case 'mhm_rentiva_seats':
				$seats     = intval($value);
				$max_seats = self::get_max_seats();
				return max(1, min($max_seats, $seats));

			case 'mhm_rentiva_doors':
				$doors     = intval($value);
				$max_doors = self::get_max_doors();
				return max(2, min($max_doors, $doors));

			case 'mhm_rentiva_transmission':
				$allowed = array_keys(self::get_transmission_types());
				return in_array($value, $allowed, true) ? $value : 'auto';

			case 'mhm_rentiva_fuel_type':
				$allowed = array_keys(self::get_fuel_types());
				return in_array($value, $allowed, true) ? $value : 'petrol';

			case 'mhm_rentiva_engine_size':
				$engine     = floatval($value);
				$min_engine = self::get_min_engine_size();
				$max_engine = self::get_max_engine_size();
				return max($min_engine, min($max_engine, $engine));

			case 'mhm_rentiva_color':
				return self::sanitize_text_field_safe($value);

			case 'mhm_rentiva_deposit':
				$clean_value = str_replace(array('%', ' '), '', (string) $value);

				// If empty, force default 10 (v4.9.0)
				if ($clean_value === '') {
					return 10.0;
				}

				$deposit = floatval($clean_value);
				return max(0, min(50, $deposit));

			case 'mhm_rentiva_available':
				return self::normalize_availability($value);

			default:
				return self::sanitize_text_field_safe($value);
		}
	}

	/**
	 * Sanitize array values
	 */
	private static function sanitize_array(array $array): array
	{
		$sanitized = array();
		foreach ($array as $item) {
			$sanitized[] = self::sanitize_text_field_safe($item);
		}
		return $sanitized;
	}

	/**
	 * Normalize availability value (backward compatibility)
	 */
	private static function normalize_availability($value): string
	{
		$mapping = array(
			'1'           => 'active',
			'active'      => 'active',
			'yes'         => 'active',
			'0'           => 'inactive',
			'passive'     => 'inactive',
			'inactive'    => 'inactive',
			'no'          => 'inactive',
			'maintenance' => 'maintenance',
		);

		if (isset($mapping[$value])) {
			return $mapping[$value];
		}

		if (in_array($value, array('active', 'inactive', 'maintenance'), true)) {
			return $value;
		}

		return 'active';
	}

	/**
	 * Save Vehicle meta
	 */
	public static function save_vehicle_meta(int $post_id, array $field_config): void
	{
		if (! isset($_POST['mhm_rentiva_vehicle_meta_nonce']) || ! wp_verify_nonce(self::post_text('mhm_rentiva_vehicle_meta_nonce'), 'mhm_rentiva_vehicle_meta_action')) {
			return;
		}

		if (isset($_POST['_mhm_vehicle_status']) || isset($_POST['mhm_rentiva_available'])) {
			$status_val = isset($_POST['_mhm_vehicle_status'])
				? self::post_text('_mhm_vehicle_status')
				: self::post_text('mhm_rentiva_available');
			$normalized = self::normalize_availability($status_val);
			update_post_meta($post_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS, $normalized);
		}

		$meta_fields = array(
			'_mhm_rentiva_price_per_day',
			'_mhm_rentiva_seats',
			'_mhm_rentiva_doors',
			'_mhm_rentiva_transmission',
			'_mhm_rentiva_fuel_type',
			'_mhm_rentiva_engine_size',
			'_mhm_rentiva_color',
			'_mhm_rentiva_brand',
			'_mhm_rentiva_model',
			'_mhm_rentiva_model_year',
			'_mhm_rentiva_mileage',
			'_mhm_rentiva_license_plate',
			'_mhm_rentiva_deposit',
			\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_LOCATION_ID,
		);

		foreach ($meta_fields as $field) {
			if (isset($_POST[$field])) {
				$value = self::post_text($field);
				update_post_meta($post_id, $field, $value);
			}
		}
	}

	/**
	 * Register meta fields
	 */
	public static function register_meta_fields(): void
	{
		// Vehicle Status
		register_post_meta(
			'vehicle',
			\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_STATUS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					$allowed   = array('active', 'inactive', 'maintenance');
					$sanitized = in_array($value, $allowed, true) ? $value : 'active';
					return $sanitized;
				},
			)
		);

		register_post_meta(
			'vehicle',
			\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FEATURED,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					return (string) ((string) $value === '1' ? '1' : '0');
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_price_per_day',
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					return max(0, floatval($value));
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_seats',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					return max(1, min(20, intval($value)));
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_doors',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					return max(2, min(8, intval($value)));
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_transmission',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					$allowed = array('auto', 'manual');
					return in_array($value, $allowed, true) ? $value : 'auto';
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_fuel_type',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					$allowed = array('petrol', 'diesel', 'hybrid', 'electric');
					return in_array($value, $allowed, true) ? $value : 'petrol';
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_engine_size',
			array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					$engine     = floatval($value);
					$min_engine = self::get_min_engine_size();
					$max_engine = self::get_max_engine_size();
					return max($min_engine, min($max_engine, $engine));
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_color',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					return self::sanitize_text_field_safe($value);
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_license_plate',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					return self::sanitize_text_field_safe($value);
				},
			)
		);

		// Legacy Availability (Maintained for read fallback in Phase 1)
		register_post_meta(
			'vehicle',
			'_mhm_vehicle_availability',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function ($value) {
					$allowed = array('active', 'passive', 'maintenance', '1', '0');
					if (in_array($value, $allowed, true)) {
						if ($value === '1') {
							return 'active';
						}
						if ($value === '0') {
							return 'passive';
						}
						return $value;
					}
					return 'active';
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_features',
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'sanitize_callback' => function ($value) {
					if (! is_array($value)) {
						return array();
					}
					return array_map(
						function ($item) {
							return self::sanitize_text_field_safe($item);
						},
						$value
					);
				},
			)
		);

		register_post_meta(
			'vehicle',
			'_mhm_rentiva_equipment',
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'string',
						),
					),
				),
				'sanitize_callback' => function ($value) {
					if (! is_array($value)) {
						return array();
					}
					return array_map(
						function ($item) {
							return self::sanitize_text_field_safe($item);
						},
						$value
					);
				},
			)
		);
	}

	/**
	 * Get field settings
	 */
	private static function get_field_settings(): array
	{
		$custom_features  = (array) get_option('mhm_custom_features', array());
		$custom_equipment = (array) get_option('mhm_custom_equipment', array());

		$taxonomy_features  = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::get_taxonomy_features();
		$taxonomy_equipment = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::get_taxonomy_equipment();

		// Merge taxonomy terms into custom lists for display logic
		$custom_features  = array_merge($custom_features, $taxonomy_features);
		$custom_equipment = array_merge($custom_equipment, $taxonomy_equipment);

		return array(
			'selected_details'   => (array) get_option('mhm_selected_details', array()),
			'selected_features'  => (array) get_option('mhm_selected_features', array()),
			'selected_equipment' => (array) get_option('mhm_selected_equipment', array()),
			'custom_details'     => (array) get_option('mhm_custom_details', array()),
			'custom_features'    => $custom_features,
			'custom_equipment'   => $custom_equipment,
			'default_details'    => (array) get_option('mhm_vehicle_details', self::get_default_details()),
			'default_features'   => (array) get_option('mhm_vehicle_features', self::get_default_features()),
			'default_equipment'  => (array) get_option('mhm_vehicle_equipment', self::get_default_equipment()),
			'custom_fields'      => array_merge(
				(array) get_option('mhm_custom_details', array()),
				$custom_features,
				$custom_equipment
			),
		);
	}

	/**
	 * Get vehicle meta data
	 */
	private static function get_vehicle_meta_data(int $post_id, array $custom_fields): array
	{
		$meta_keys = array(
			'_mhm_rentiva_price_per_day',
			'_mhm_rentiva_year',
			'_mhm_rentiva_mileage',
			'_mhm_rentiva_license_plate',
			'_mhm_rentiva_color',
			'_mhm_rentiva_brand',
			'_mhm_rentiva_model',
			'_mhm_rentiva_seats',
			'_mhm_rentiva_doors',
			'_mhm_rentiva_transmission',
			'_mhm_rentiva_fuel_type',
			'_mhm_rentiva_engine_size',
			'_mhm_rentiva_deposit',
			'_mhm_vehicle_status',
			\MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FEATURED,
			'_mhm_rentiva_features',
			'_mhm_rentiva_equipment',
			'_mhm_removed_details',
			'_mhm_custom_details',
			'_mhm_details_order',
			'_mhm_features_order',
			'_mhm_equipment_order',
		);

		foreach ($custom_fields as $field_key => $field_label) {
			$meta_keys[] = '_mhm_rentiva_' . $field_key;
		}

		$meta_data = array();
		foreach ($meta_keys as $key) {
			$meta_data[$key] = get_post_meta($post_id, $key, true);
		}

		return $meta_data;
	}

	/**
	 * Build available fields
	 */
	private static function build_available_fields(array $selected_fields, array $stored_fields, array $custom_fields): array
	{
		$available_fields = array();

		foreach ($selected_fields as $key) {
			if (! empty($stored_fields[$key])) {
				$available_fields[$key] = $stored_fields[$key];
			} elseif (! empty($custom_fields[$key])) {
				$available_fields[$key] = $custom_fields[$key];
			} else {
				// DATA RECOVERY FALLBACK: If label is missing from stored options, search in hardcoded defaults
				$all_defaults = array_merge(
					self::get_default_details(),
					self::get_default_features(),
					self::get_default_equipment()
				);
				if (isset($all_defaults[$key])) {
					$available_fields[$key] = $all_defaults[$key];
				} else {
					// Last resort: use the key name as label
					$available_fields[$key] = ucwords(str_replace('_', ' ', (string) $key));
				}
			}
		}

		return $available_fields;
	}

	/**
	 * Check and update default options
	 */
	private static function ensure_default_options(array &$available_details, array &$available_features, array &$available_equipment): void
	{
		$default_details   = self::get_default_details();
		$default_features  = self::get_default_features();
		$default_equipment = self::get_default_equipment();

		// Ensure core option sets exist (fresh installs may not have them yet)
		if (get_option('mhm_vehicle_details', array()) === array()) {
			update_option('mhm_vehicle_details', $default_details);
		}
		if (get_option('mhm_vehicle_features', array()) === array()) {
			update_option('mhm_vehicle_features', $default_features);
		}
		if (get_option('mhm_vehicle_equipment', array()) === array()) {
			update_option('mhm_vehicle_equipment', $default_equipment);
		}

		// Ensure selected keys are populated; otherwise fall back to defaults
		$selected_details = (array) get_option('mhm_selected_details', array());
		if (empty($selected_details)) {
			$selected_details = array_keys($default_details);
			update_option('mhm_selected_details', $selected_details);
		}

		$selected_features = (array) get_option('mhm_selected_features', array());
		if (empty($selected_features)) {
			$selected_features = array_keys($default_features);
			update_option('mhm_selected_features', $selected_features);
		}

		$selected_equipment = (array) get_option('mhm_selected_equipment', array());
		if (empty($selected_equipment)) {
			$selected_equipment = array_keys($default_equipment);
			update_option('mhm_selected_equipment', $selected_equipment);
		}

		// Populate available arrays when empty (fresh install fallback)
		if (empty($available_details)) {
			foreach ($selected_details as $key) {
				if (isset($default_details[$key])) {
					$available_details[$key] = $default_details[$key];
				}
			}

			if (empty($available_details)) {
				$available_details = $default_details;
			}
		}

		if (empty($available_features)) {
			foreach ($selected_features as $key) {
				if (isset($default_features[$key])) {
					$available_features[$key] = $default_features[$key];
				}
			}

			if (empty($available_features)) {
				$available_features = $default_features;
			}
		}

		if (empty($available_equipment)) {
			foreach ($selected_equipment as $key) {
				if (isset($default_equipment[$key])) {
					$available_equipment[$key] = $default_equipment[$key];
				}
			}

			if (empty($available_equipment)) {
				$available_equipment = $default_equipment;
			}
		}
	}

	/**
	 * AJAX handler: Save ordering changes
	 */
	public static function ajax_save_item_order(): void
	{
		if (! isset($_POST['nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'mhm_rentiva_vehicle_meta_action')) {
			wp_send_json_error(__('Security error', 'mhm-rentiva'));
		}

		if (! current_user_can('edit_posts')) {
			wp_send_json_error(__('Permission error', 'mhm-rentiva'));
		}

		$grid_type = self::post_text('grid_type');
		// Sanitize order array - each element should be a string key
		$order = array_map(array(self::class, 'sanitize_text_field_safe'), self::post_array('order'));

		if (empty($grid_type) || empty($order)) {
			wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
		}

		$post_id = intval($_POST['post_id'] ?? 0);
		if (! $post_id) {
			wp_send_json_error('Post ID not found');
		}

		$meta_key = '_mhm_' . $grid_type . '_order';
		update_post_meta($post_id, $meta_key, $order);

		$option_key   = 'mhm_vehicle_' . $grid_type;
		$current_data = get_option($option_key, array());

		if (! empty($current_data)) {
			$reordered_data = array();
			foreach ($order as $key) {
				if (isset($current_data[$key])) {
					$reordered_data[$key] = $current_data[$key];
				}
			}
			update_option($option_key, $reordered_data);
		}

		wp_send_json_success('Ordering saved');
	}

	/**
	 * Reorder meta boxes
	 */
	public static function reorder_meta_boxes(): void
	{
		global $wp_meta_boxes;

		if (! isset($wp_meta_boxes['vehicle']['side'])) {
			return;
		}

		$desired_order = array(
			'submitdiv',
			'vehicle_categorydiv',
			'postimagediv',
			'mhm_rentiva_vehicle_gallery',
			'mhm_rentiva_vehicle_featured',
			'mhm_rentiva_vehicle_transfer_settings',
		);

		// Flatten all priority buckets into a single map.
		$all_boxes = array();
		foreach ($wp_meta_boxes['vehicle']['side'] as $boxes) {
			foreach ($boxes as $id => $box) {
				$all_boxes[$id] = $box;
			}
		}

		// Rebuild under a single 'default' bucket so priority doesn't interfere with order.
		$reordered = array( 'default' => array() );

		foreach ($desired_order as $box_id) {
			if (isset($all_boxes[$box_id])) {
				$reordered['default'][$box_id] = $all_boxes[$box_id];
				unset($all_boxes[$box_id]);
			}
		}

		// Append any remaining boxes not explicitly listed.
		foreach ($all_boxes as $id => $box) {
			$reordered['default'][$id] = $box;
		}

		$wp_meta_boxes['vehicle']['side'] = $reordered;
	}
}
