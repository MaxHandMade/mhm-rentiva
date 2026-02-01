<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Settings;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ VEHICLE SETTINGS - Vehicle Features and Equipment Management
 *
 * Manage vehicle features and equipment in admin panel
 */
final class VehicleSettings
{
	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;



	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe($value)
	{
		if ($value === null || $value === '') {
			return '';
		}
		return sanitize_text_field(wp_unslash((string) $value));
	}

	public static function register(): void
	{
		// Menu registration is now done centrally in Menu.php
		add_action('admin_init', array(self::class, 'register_settings'));
		add_action('wp_ajax_add_vehicle_feature', array(self::class, 'ajax_add_feature'));
		add_action('wp_ajax_remove_vehicle_feature', array(self::class, 'ajax_remove_feature'));
		add_action('wp_ajax_add_vehicle_equipment', array(self::class, 'ajax_add_equipment'));
		add_action('wp_ajax_remove_vehicle_equipment', array(self::class, 'ajax_remove_equipment'));
		add_action('wp_ajax_add_vehicle_detail', array(self::class, 'ajax_add_detail'));
		add_action('wp_ajax_remove_vehicle_detail', array(self::class, 'ajax_remove_detail'));
		add_action('wp_ajax_save_vehicle_settings', array(self::class, 'ajax_save_settings'));
		add_action('wp_ajax_update_field_labels', array(self::class, 'ajax_update_field_labels'));
		add_action('wp_ajax_remove_custom_field', array(self::class, 'ajax_remove_custom_field'));
		add_action('wp_ajax_add_custom_field', array(self::class, 'ajax_add_custom_field'));

		// Reset Settings
		add_action('wp_ajax_mhm_reset_vehicle_settings', array(self::class, 'ajax_reset_settings'));
	}

	/**
	 * ✅ Take responsibility for global setting updates from VehicleMeta
	 */
	public static function update_global_vehicle_settings(int $post_id, \WP_Post $post): void
	{
		// Nonce check
		if (
			! isset($_POST['mhm_rentiva_vehicle_meta_nonce']) ||
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhm_rentiva_vehicle_meta_nonce'])), 'mhm_rentiva_vehicle_meta_action')
		) {
			return;
		}

		// Permission check
		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		// Autosave and revision check
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		// Sanitize and validate custom details array from POST
		$legacy_custom_details = isset($_POST['mhm_rentiva_custom_details']) && is_array($_POST['mhm_rentiva_custom_details'])
			? $_POST['mhm_rentiva_custom_details']
			: array();

		if (! empty($legacy_custom_details) && is_array($legacy_custom_details)) {
			$available_details = get_option('mhm_vehicle_details', array());
			$option_updated    = false;

			foreach ($legacy_custom_details as $key => $detail_data) {
				if (is_array($detail_data) && isset($detail_data['label']) && isset($detail_data['value'])) {
					// Global option'a ekle
					$available_details[self::sanitize_text_field_safe($key)] = self::sanitize_text_field_safe($detail_data['label']);
					$option_updated = true;
				} else {
					// Backward compatibility for old format
					$available_details[self::sanitize_text_field_safe($key)] = self::sanitize_text_field_safe($key);
					$option_updated = true;
				}
			}

			// Update option
			if ($option_updated) {
				update_option('mhm_vehicle_details', $available_details);
			}
		}
	}

	/**
	 * Add to admin menu
	 */
	public static function add_admin_menu(): void
	{
		// Add under MHM Rentiva main menu
		add_submenu_page(
			'mhm-rentiva',
			__('Vehicle Settings', 'mhm-rentiva'),
			__('Vehicle Settings', 'mhm-rentiva'),
			'manage_options',
			'vehicle-settings',
			array(self::class, 'render_settings_page')
		);
	}

	/**
	 * Save settings
	 */
	public static function register_settings(): void
	{
		// Selected fields (checkbox states)
		register_setting('mhm_vehicle_settings', 'mhm_selected_details', array('sanitize_callback' => fn($input) => is_array($input) ? array_map('sanitize_text_field', $input) : array()));
		register_setting('mhm_vehicle_settings', 'mhm_selected_features', array('sanitize_callback' => fn($input) => is_array($input) ? array_map('sanitize_text_field', $input) : array()));
		register_setting('mhm_vehicle_settings', 'mhm_selected_equipment', array('sanitize_callback' => fn($input) => is_array($input) ? array_map('sanitize_text_field', $input) : array()));

		// Custom fields
		register_setting('mhm_vehicle_settings', 'mhm_custom_details', array('sanitize_callback' => fn($input) => is_array($input) ? array_map('sanitize_text_field', $input) : array()));
		register_setting('mhm_vehicle_settings', 'mhm_custom_features', array('sanitize_callback' => fn($input) => is_array($input) ? array_map('sanitize_text_field', $input) : array()));
		register_setting('mhm_vehicle_settings', 'mhm_custom_equipment', array('sanitize_callback' => fn($input) => is_array($input) ? array_map('sanitize_text_field', $input) : array()));
	}

	/**
	 * Render settings page
	 */
	/**
	 * Render settings page
	 */
	public function render_settings_page(): void
	{
		$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'definitions';

		$buttons = array(
			array(
				'type' => 'documentation',
				'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
			),
			array(
				'type' => 'reset',
				'url'  => '#',
				'id'   => 'reset-vehicle-settings',
			),
		);

		echo '<div class="wrap mhm-vehicle-settings-wrapper">';
		$this->render_admin_header((string) get_admin_page_title(), $buttons);

		// Developer Mode Banner
		$this->render_developer_mode_banner();
?>
		<nav class="nav-tab-wrapper">
			<a href="?page=vehicle-settings&tab=definitions" class="nav-tab <?php echo $active_tab === 'definitions' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e('Field Definitions', 'mhm-rentiva'); ?>
			</a>
			<a href="?page=vehicle-settings&tab=display" class="nav-tab <?php echo $active_tab === 'display' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e('Display Options', 'mhm-rentiva'); ?>
			</a>
		</nav>
		<br>

		<?php
		if ($active_tab === 'display') {
			self::render_display_tab();
		} else {
			self::render_definitions_tab();
		}
		?>
		<script>
			jQuery(document).ready(function($) {
				$('#reset-vehicle-settings').on('click', function() {
					if (confirm('<?php echo esc_js(__('Are you sure you want to reset all vehicle settings to defaults? Custom field definitions will NOT be deleted.', 'mhm-rentiva')); ?>')) {
						const btn = $(this);
						btn.prop('disabled', true);

						$.post(ajaxurl, {
							action: 'mhm_reset_vehicle_settings',
							tab: '<?php echo esc_js($active_tab); ?>',
							nonce: '<?php echo esc_js(wp_create_nonce('vehicle_settings_nonce')); ?>'
						}, function(response) {
							if (response.success) {
								window.location.reload();
							} else {
								alert(response.data.message || 'Error resetting settings');
								btn.prop('disabled', false);
							}
						});
					}
				});
			});
		</script>
		</div>
	<?php
	}

	/**
	 * Render Display Options Tab (Card Fields & Comparisons)
	 */
	private static function render_display_tab(): void
	{
		// 1. Visible Card Items (Drag & Drop)
		$available_map = VehicleFeatureHelper::get_available_fields_map();
		$selected      = VehicleFeatureHelper::get_selected_card_fields();

		// Build lookup for quick label resolution
		$available_flat = array();
		foreach ($available_map as $type => $fields) {
			foreach ($fields as $key => $field) {
				// Ensure field has label
				if (! isset($field['label'])) {
					// Fallback label generation
					$field['label'] = ucfirst(str_replace('_', ' ', $key));
				}
				$available_flat[$type . ':' . $key] = $field;
			}
		}

		$selected_items = array();
		foreach ($selected as $item) {
			$id = $item['type'] . ':' . $item['key'];
			// If item is in available_flat, use its label.
			// If not, it might be a custom field that was removed?
			// Or a custom field that needs to be looked up.

			$label = '';
			if (isset($available_flat[$id])) {
				$label = $available_flat[$id]['label'];
				unset($available_flat[$id]); // Remove from available list so it doesn't show up twice
			} else {
				// Try to resolve label for custom items even if not in map (e.g. might be missing from helper map but is valid)
				// However, helper map SHOULD contain everything.
				// Let's fallback to generated label
				$label = ucfirst(str_replace('_', ' ', $item['key']));
			}

			$selected_items[] = array(
				'type'  => $item['type'],
				'key'   => $item['key'],
				'label' => $label,
			);
		}

		// Remaining items in available_flat are "Available"
		$available_items = array();
		foreach ($available_flat as $id => $data) {
			$available_items[] = array(
				'type'  => $data['type'],
				'key'   => $data['key'] ?? $id,
				'label' => $data['label'],
			);
		}

		$hidden_value = esc_attr(wp_json_encode($selected));

		// 2. Comparison Fields
		$settings                    = get_option('mhm_rentiva_settings', array());
		$selected_comparison_fields  = $settings['comparison_fields'] ?? array();
		$available_comparison_fields = self::get_comparison_available_fields();
		$show_defaults               = empty($selected_comparison_fields);

	?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" id="vehicle-display-settings-form">

			<div class="mhm-settings-section">
				<h2><?php echo esc_html__('Visible Card Items', 'mhm-rentiva'); ?></h2>
				<div class="mhm-card-fields-wrapper">
					<input type="hidden" id="mhm-vehicle-card-fields-input" name="mhm_rentiva_vehicle_card_fields" value="<?php echo esc_attr($hidden_value); ?>" />

					<div class="mhm-card-fields-columns" style="display: flex; gap: 20px;">

						<div class="mhm-card-fields-column" style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
							<h4><?php echo esc_html__('Visible Items', 'mhm-rentiva'); ?>
								<button type="button" id="clear-card-fields" class="button button-small" style="float: right;"><?php echo esc_html__('Clear All', 'mhm-rentiva'); ?></button>
							</h4>
							<p class="description"><?php echo esc_html__('Drag to reorder or click to remove items from the vehicle card.', 'mhm-rentiva'); ?></p>
							<ul id="mhm-card-fields-selected" class="mhm-card-fields-list" style="min-height: 50px; border: 1px dashed #ccc; padding: 10px;">
								<?php if (! empty($selected_items)) : ?>
									<?php foreach ($selected_items as $item) : ?>
										<?php echo wp_kses_post(self::render_card_field_list_item($item['type'], $item['key'], $item['label'], true)); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
						</div>

						<div class="mhm-card-fields-column" style="flex: 1; background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
							<h4><?php echo esc_html__('Available Items', 'mhm-rentiva'); ?></h4>
							<p class="description"><?php echo esc_html__('Drag items here to hide them from the card.', 'mhm-rentiva'); ?></p>
							<ul id="mhm-card-fields-available" class="mhm-card-fields-list" style="min-height: 50px; border: 1px dashed #ccc; padding: 10px;">
								<?php if (! empty($available_items)) : ?>
									<?php foreach ($available_items as $item) : ?>
										<?php echo wp_kses_post(self::render_card_field_list_item($item['type'], $item['key'], $item['label'], false)); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
						</div>

					</div>
					<p class="description mhm-card-fields-footer" style="margin-top: 10px;">
						<?php echo esc_html__('Tip: The order you set here applies to vehicle grids, list views and the My Account favorites grid.', 'mhm-rentiva'); ?>
					</p>
				</div>
			</div>

			<hr style="margin: 30px 0;">

			<div class="mhm-settings-section">
				<h2><?php echo esc_html__('Comparison Table Settings', 'mhm-rentiva'); ?></h2>
				<div class="mhm-comparison-fields">
					<p class="description">
						<?php echo esc_html__('Select which fields to display in the vehicle comparison table:', 'mhm-rentiva'); ?>
					</p>

					<div class="mhm-field-categories">
						<?php foreach ($available_comparison_fields as $category => $fields) : ?>
							<div class="mhm-field-category" data-category="<?php echo esc_attr($category); ?>">
								<div class="mhm-category-header">
									<?php
									$category_labels = array(
										'details'   => __('Details', 'mhm-rentiva'),
										'features'  => __('Features', 'mhm-rentiva'),
										'equipment' => __('Equipment', 'mhm-rentiva'),
									);
									$cat_label       = $category_labels[$category] ?? ucfirst($category);
									?>
									<h4 style="margin: 0;"><?php echo esc_html($cat_label); ?></h4>
									<div class="mhm-category-actions">
										<button type="button" class="button button-small mhm-select-all-btn" data-category="<?php echo esc_attr($category); ?>">
											<?php echo esc_html__('Select All', 'mhm-rentiva'); ?>
										</button>
										<button type="button" class="button button-small mhm-deselect-all-btn" data-category="<?php echo esc_attr($category); ?>">
											<?php echo esc_html__('Deselect All', 'mhm-rentiva'); ?></button>
									</div>
								</div>
								<div class="mhm-field-list">
									<?php foreach ($fields as $field_key => $field_label) : ?>
										<div class="mhm-checkbox-item">
											<label class="mhm-checkbox-label">
												<input type="checkbox" name="comparison_fields[<?php echo esc_attr($category); ?>][]" value="<?php echo esc_attr($field_key); ?>"
													<?php checked($show_defaults || (isset($selected_comparison_fields[$category]) && in_array($field_key, $selected_comparison_fields[$category]))); ?>>
												<span><?php echo esc_html($field_label); ?></span>
											</label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div style="margin-top: 20px;">
				<input type="hidden" name="action" value="save_vehicle_settings">
				<input type="hidden" name="sub_action" value="save_display_settings">
				<input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('vehicle_settings_nonce')); ?>">
				<button type="submit" id="save-display-settings" class="button button-primary button-large"><?php echo esc_html__('Save Display Settings', 'mhm-rentiva'); ?></button>
			</div>
		</form>

		<script>
			jQuery(document).ready(function($) {
				// Select all / Deselect all for comparison fields
				$('.mhm-select-all-btn').on('click', function() {
					var category = $(this).data('category');
					$('.mhm-field-category[data-category="' + category + '"] input[type="checkbox"]').prop('checked', true);
				});

				$('.mhm-deselect-all-btn').on('click', function() {
					var category = $(this).data('category');
					$('.mhm-field-category[data-category="' + category + '"] input[type="checkbox"]').prop('checked', false);
				});

				// Display Settings Form Submit
				$('#vehicle-display-settings-form').on('submit', function(e) {
					e.preventDefault();

					// Update hidden input for card fields (assuming sortable JS updates DOM, we need to serialize)
					// The 'vehicle-card-fields.js' usually handles update of input value on sort update.
					// But we should make sure.
					// Actually, if we use the same ID #mhm-vehicle-card-fields-input, the JS should work.

					var formData = $(this).serialize();

					$.post(ajaxurl, formData, function(response) {
						if (response.success) {
							alert('<?php echo esc_js(__('Settings saved successfully!', 'mhm-rentiva')); ?>');
						} else {
							alert('<?php echo esc_js(__('Error saving settings.', 'mhm-rentiva')); ?>');
						}
					});
				});

				// Clear All Card Fields
				$('#clear-card-fields').on('click', function() {
					var selectedList = $('#mhm-card-fields-selected');
					var availableList = $('#mhm-card-fields-available');

					// Move all items from selected to available
					selectedList.children('li').each(function() {
						var item = $(this);
						// Remove the 'remove-field' button as it's no longer in the selected list
						item.find('.remove-field').remove();
						availableList.append(item);
					});

					// Update hidden input to an empty array
					$('#mhm-vehicle-card-fields-input').val('[]');
				});
			});
		</script>
	<?php
	}

	/**
	 * Render Definitions Tab (Original Content)
	 */
	public static function render_definitions_tab(): void
	{
		$selected_details   = get_option('mhm_selected_details', self::get_default_selected_details());
		$selected_features  = get_option('mhm_selected_features', self::get_default_selected_features());
		$selected_equipment = get_option('mhm_selected_equipment', self::get_default_selected_equipment());

		// Get custom fields
		$custom_details   = get_option('mhm_custom_details', array());
		$custom_features  = get_option('mhm_custom_features', array());
		$custom_equipment = get_option('mhm_custom_equipment', array());

		// Get all existing fields (standard + custom)
		$all_details   = self::get_all_available_details();
		$all_features  = self::get_all_available_features();
		$all_equipment = self::get_all_available_equipment();

	?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" id="vehicle-settings-form">
			<div class="mhm-vehicle-definitions-content">
				<p class="description"><?php echo esc_html__('Select fields to use on vehicles. You can also add custom fields.', 'mhm-rentiva'); ?></p>

				<div class="mhm-settings-container">

					<!-- Vehicle Details -->
					<div class="mhm-settings-card">
						<h2><?php echo esc_html__('Vehicle Details', 'mhm-rentiva'); ?></h2>
						<p><?php echo esc_html__('Select the details you want to use', 'mhm-rentiva'); ?></p>

						<!-- Core Details (Permanent) -->
						<h4 class="mhm-section-subtitle"><?php echo esc_html__('Core Details (Essential)', 'mhm-rentiva'); ?></h4>
						<div class="mhm-checkbox-list mhm-core-details-grid">
							<?php
							$core_fields = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_core_fields();
							$core_fields[] = 'deposit'; // Promote deposit to core protected field (v4.8.2)
							foreach ($all_details as $key => $label) :
								if (! in_array($key, $core_fields)) {
									continue;
								}
							?>
								<div class="mhm-checkbox-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_details[]" value="<?php echo esc_attr($key); ?>"
											<?php checked(in_array($key, $selected_details)); ?>>
										<span><?php echo esc_html(! empty($label) ? $label : $key); ?></span>
									</label>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Optional & Custom Details (Removable) -->
						<div class="mhm-card-section-header">
							<h4 style="margin: 0;"><?php echo esc_html__('Attributes & Custom Details', 'mhm-rentiva'); ?></h4>
							<div class="mhm-category-actions">
								<button type="button" id="select-all-details" class="button button-small"><?php esc_html_e('Select All', 'mhm-rentiva'); ?></button>
								<button type="button" id="select-none-details" class="button button-small"><?php esc_html_e('Deselect All', 'mhm-rentiva'); ?></button>
								<button type="button" id="rename-details" class="button button-small"><?php esc_html_e('Edit Names', 'mhm-rentiva'); ?></button>
							</div>
						</div>
						<div class="mhm-checkbox-list" id="custom-details-list">
							<?php
							foreach ($all_details as $key => $label) :
								if (in_array($key, $core_fields)) {
									continue;
								}
							?>
								<div class="mhm-checkbox-item mhm-removable-item mhm-custom-row-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_details[]" value="<?php echo esc_attr($key); ?>"
											<?php checked(in_array($key, $selected_details)); ?>>
										<span><?php echo esc_html(! empty($label) ? $label : $key); ?></span>
									</label>
									<button type="button" class="button-link remove-custom-detail" data-key="<?php echo esc_attr($key); ?>" style="color: #dc3545; line-height: 1;">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Add New Custom Detail -->
						<div style="margin-top: 15px;">
							<div class="mhm-add-custom-row">
								<input type="text" id="new-custom-detail-name" placeholder="<?php esc_attr_e('Custom detail name', 'mhm-rentiva'); ?>" style="width: 200px;">

								<select id="new-custom-detail-type" style="width: 120px;">
									<option value="text"><?php esc_html_e('Text', 'mhm-rentiva'); ?></option>
									<option value="number"><?php esc_html_e('Number', 'mhm-rentiva'); ?></option>
									<option value="select"><?php esc_html_e('Select (Dropdown)', 'mhm-rentiva'); ?></option>
								</select>

								<div id="new-custom-detail-options-wrapper" style="display: none;">
									<input type="text" id="new-custom-detail-options" placeholder="<?php esc_attr_e('Options (comma separated: Petrol, Diesel)', 'mhm-rentiva'); ?>" style="width: 300px;">
								</div>

								<button type="button" id="add-custom-detail" class="button button-secondary"><?php esc_html_e('Add Custom', 'mhm-rentiva'); ?></button>
							</div>
						</div>
					</div>

					<!-- Vehicle Features -->
					<div class="mhm-settings-card">
						<h2><?php echo esc_html__('Vehicle Features', 'mhm-rentiva'); ?></h2>
						<p><?php echo esc_html__('Select the features you want to use', 'mhm-rentiva'); ?></p>

						<!-- Standard Features -->
						<div class="mhm-card-section-header">
							<h4 style="margin: 0;"><?php echo esc_html__('Standard Features', 'mhm-rentiva'); ?></h4>
							<div class="mhm-category-actions">
								<button type="button" id="select-all-features" class="button button-small"><?php esc_html_e('Select All', 'mhm-rentiva'); ?></button>
								<button type="button" id="select-none-features" class="button button-small"><?php esc_html_e('Deselect All', 'mhm-rentiva'); ?></button>
								<button type="button" id="rename-features" class="button button-small"><?php esc_html_e('Edit Names', 'mhm-rentiva'); ?></button>
							</div>
						</div>
						<div class="mhm-checkbox-list">
							<?php foreach ($all_features as $key => $label) : ?>
								<div class="mhm-checkbox-item mhm-removable-item mhm-custom-row-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_features[]" value="<?php echo esc_attr($key); ?>"
											<?php checked(in_array($key, $selected_features)); ?>>
										<span><?php echo esc_html(! empty($label) ? $label : $key); ?></span>
									</label>
									<button type="button" class="button-link remove-custom-feature" data-key="<?php echo esc_attr($key); ?>" style="color: #dc3545; line-height: 1;">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Custom Features Header (Optional) -->
						<h4 style="margin-top: 20px; font-size: 11px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.05em;"><?php echo esc_html__('Custom Features', 'mhm-rentiva'); ?></h4>
						<div class="mhm-custom-list" id="custom-features-list" style="margin-top: 10px;">
							<?php foreach ($custom_features as $key => $label) : ?>
								<div class="mhm-custom-item mhm-custom-feature-item" data-key="<?php echo esc_attr($key); ?>">
									<span><?php echo esc_html($label); ?></span>
									<button type="button" class="button button-small remove-custom-feature" data-key="<?php echo esc_attr($key); ?>"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Add New Custom Feature -->
						<div style="margin-top: 15px;">
							<div class="mhm-add-custom-row">
								<input type="text" id="new-custom-feature-name" placeholder="<?php esc_attr_e('Custom feature name', 'mhm-rentiva'); ?>" style="width: 250px;">
								<button type="button" id="add-custom-feature" class="button button-secondary"><?php esc_html_e('Add Custom', 'mhm-rentiva'); ?></button>
							</div>
						</div>
					</div>

					<!-- Vehicle Equipment -->
					<div class="mhm-settings-card">
						<h2><?php echo esc_html__('Vehicle Equipment', 'mhm-rentiva'); ?></h2>
						<p><?php echo esc_html__('Select the equipment you want to use', 'mhm-rentiva'); ?></p>

						<!-- Standard Equipment -->
						<div class="mhm-card-section-header">
							<h4 style="margin: 0;"><?php echo esc_html__('Standard Equipment', 'mhm-rentiva'); ?></h4>
							<div class="mhm-category-actions">
								<button type="button" id="select-all-equipment" class="button button-small"><?php esc_html_e('Select All', 'mhm-rentiva'); ?></button>
								<button type="button" id="select-none-equipment" class="button button-small"><?php esc_html_e('Deselect All', 'mhm-rentiva'); ?></button>
								<button type="button" id="rename-equipment" class="button button-small"><?php esc_html_e('Edit Names', 'mhm-rentiva'); ?></button>
							</div>
						</div>
						<div class="mhm-checkbox-list">
							<?php foreach ($all_equipment as $key => $label) : ?>
								<div class="mhm-checkbox-item mhm-removable-item mhm-custom-row-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_equipment[]" value="<?php echo esc_attr($key); ?>"
											<?php checked(in_array($key, $selected_equipment)); ?>>
										<span><?php echo esc_html(! empty($label) ? $label : $key); ?></span>
									</label>
									<button type="button" class="button-link remove-custom-equipment" data-key="<?php echo esc_attr($key); ?>" style="color: #dc3545; line-height: 1;">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Custom Equipment -->
						<h4 style="margin-top: 20px;"><?php echo esc_html__('Custom Equipment', 'mhm-rentiva'); ?></h4>
						<div class="mhm-custom-list" id="custom-equipment-list" style="margin-top: 10px;">
							<?php foreach ($custom_equipment as $key => $label) : ?>
								<div class="mhm-custom-item mhm-custom-equipment-item" data-key="<?php echo esc_attr($key); ?>">
									<span><?php echo esc_html($label); ?></span>
									<button type="button" class="button button-small remove-custom-equipment" data-key="<?php echo esc_attr($key); ?>"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Add New Custom Equipment -->
						<div style="margin-top: 15px;">
							<div class="mhm-add-custom-row">
								<input type="text" id="new-custom-equipment-name" placeholder="<?php esc_attr_e('Custom equipment name', 'mhm-rentiva'); ?>" style="width: 250px;">
								<button type="button" id="add-custom-equipment" class="button button-secondary"><?php esc_html_e('Add Custom', 'mhm-rentiva'); ?></button>
							</div>
						</div>
					</div>

				</div>

				<div class="mhm-settings-footer-actions">
					<input type="hidden" name="action" value="save_vehicle_settings">
					<input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('vehicle_settings_nonce')); ?>">
					<button type="submit" id="save-settings" class="button button-primary button-large"><?php echo esc_html__('Save Settings', 'mhm-rentiva'); ?></button>
				</div>
			</div>
		</form>

		<?php
		// Scripts only below
		?>


		<script>
			jQuery(document).ready(function($) {
				// Define AJAX URL
				var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';


				// Show/Hide Options based on Type
				$('#new-custom-detail-type').on('change', function() {
					if ($(this).val() === 'select') {
						$('#new-custom-detail-options-wrapper').show();
					} else {
						$('#new-custom-detail-options-wrapper').hide();
					}
				});

				// Custom Detail Addition
				$('#add-custom-detail').on('click', function() {
					const name = $('#new-custom-detail-name').val().trim();
					const type = $('#new-custom-detail-type').val();
					const options = $('#new-custom-detail-options').val().trim();

					if (name) {
						const key = 'custom_' + Date.now();
						const label = name;

						// Save to database via AJAX
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'add_custom_field',
								field_key: key,
								field_label: label,
								field_type: 'details',
								type: type,
								options: options,
								nonce: '<?php echo esc_attr(wp_create_nonce('mhm_vehicle_settings_nonce')); ?>'
							},
							success: function(response) {
								if (response.success) {
									// If successful, add to DOM
									let typeLabel = '';
									if (type === 'select') typeLabel = ' (<?php echo esc_js(__('Select', 'mhm-rentiva')); ?>)';
									else if (type === 'number') typeLabel = ' (<?php echo esc_js(__('Number', 'mhm-rentiva')); ?>)';

									$('#custom-details-list').append(`
									<div class="mhm-custom-item" data-key="${key}" style="display: flex; justify-content: space-between; align-items: center; padding: 5px 8px; background: #fff3cd; border-radius: 4px; border: 1px solid #ddd; margin-bottom: 5px;">
										<label class="mhm-checkbox-item" style="display: flex; align-items: center; gap: 8px; cursor: pointer; flex-grow: 1;">
											<input type="checkbox" name="selected_details[]" value="${key}" checked style="margin: 0;">
											<span>${label}${typeLabel}</span>
										</label>
										<button type="button" class="button-link remove-custom-detail" data-key="${key}" style="color: #dc3545; text-decoration: none;">&times;</button>
									</div>
									`);

									$('#new-custom-detail-name').val('');
									$('#new-custom-detail-options').val('');
									// Reset type to text
									$('#new-custom-detail-type').val('text').trigger('change');

									alert('<?php echo esc_js(__('Custom detail added successfully!', 'mhm-rentiva')); ?>');
								} else {
									alert('<?php echo esc_js(__('Error:', 'mhm-rentiva')); ?> ' + response.data);
								}
							},
							error: function() {
								alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>');
							}
						});
					}
				});

				// Custom Feature Addition
				$('#add-custom-feature').on('click', function() {
					const name = $('#new-custom-feature-name').val().trim();

					if (name) {
						const key = 'custom_' + Date.now();
						const label = name;

						// Save to database via AJAX
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'add_custom_field',
								field_key: key,
								field_label: label,
								field_type: 'features',
								nonce: '<?php echo esc_attr(wp_create_nonce('mhm_vehicle_settings_nonce')); ?>'
							},
							success: function(response) {
								if (response.success) {
									// If successful, add to DOM
									$('#custom-features-list').append(`
									<div class="mhm-custom-item" data-key="${key}" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #fff3cd; border-radius: 4px; margin: 5px 0;">
							<span>${label}</span>
										<button type="button" class="button button-small remove-custom-feature" data-key="${key}"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
						</div>
					`);

									$('#new-custom-feature-name').val('');
									alert('<?php echo esc_js(__('Custom feature added successfully!', 'mhm-rentiva')); ?>');
								} else {
									alert('<?php echo esc_js(__('Error:', 'mhm-rentiva')); ?> ' + response.data);
								}
							},
							error: function() {
								alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>');
							}
						});
					}
				});

				// Custom Equipment Addition
				$('#add-custom-equipment').on('click', function() {
					const name = $('#new-custom-equipment-name').val().trim();

					if (name) {
						const key = 'custom_' + Date.now();
						const label = name;

						// Save to database via AJAX
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'add_custom_field',
								field_key: key,
								field_label: label,
								field_type: 'equipment',
								nonce: '<?php echo esc_attr(wp_create_nonce('mhm_vehicle_settings_nonce')); ?>'
							},
							success: function(response) {
								if (response.success) {
									// If successful, add to DOM
									$('#custom-equipment-list').append(`
									<div class="mhm-custom-item" data-key="${key}" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #fff3cd; border-radius: 4px; margin: 5px 0;">
							<span>${label}</span>
										<button type="button" class="button button-small remove-custom-equipment" data-key="${key}"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
						</div>
					`);

									$('#new-custom-equipment-name').val('');
									alert('<?php echo esc_js(__('Custom equipment added successfully!', 'mhm-rentiva')); ?>');
								} else {
									alert('<?php echo esc_js(__('Error:', 'mhm-rentiva')); ?> ' + response.data);
								}
							},
							error: function() {
								alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>');
							}
						});
					}
				});

				// Custom Detail Removal
				$(document).on('click', '.remove-custom-detail', function() {
					if (confirm('<?php echo esc_js(__('Are you sure you want to remove this custom detail?', 'mhm-rentiva')); ?>')) {
						const fieldKey = $(this).data('key');
						const item = $(this).closest('.mhm-custom-item');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'remove_custom_field',
								field_key: fieldKey,
								field_type: 'details',
								nonce: '<?php echo esc_attr(wp_create_nonce('mhm_vehicle_settings_nonce')); ?>'
							},
							success: function(response) {
								if (response.success) {
									item.fadeOut(300, function() {
										$(this).remove();
									});
									alert('<?php echo esc_js(__('Custom detail removed successfully!', 'mhm-rentiva')); ?>');
								} else {
									alert('<?php echo esc_js(__('Error:', 'mhm-rentiva')); ?> ' + response.data);
								}
							},
							error: function() {
								alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>');
							}
						});
					}
				});

				// Custom Feature Removal
				$(document).on('click', '.remove-custom-feature', function() {
					if (confirm('<?php echo esc_js(__('Are you sure you want to remove this custom feature?', 'mhm-rentiva')); ?>')) {
						const fieldKey = $(this).data('key');
						const item = $(this).closest('.mhm-custom-item');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'remove_custom_field',
								field_key: fieldKey,
								field_type: 'features',
								nonce: '<?php echo esc_attr(wp_create_nonce('mhm_vehicle_settings_nonce')); ?>'
							},
							success: function(response) {
								if (response.success) {
									item.fadeOut(300, function() {
										$(this).remove();
									});
									alert('<?php echo esc_js(__('Custom feature removed successfully!', 'mhm-rentiva')); ?>');
								} else {
									alert('<?php echo esc_js(__('Error:', 'mhm-rentiva')); ?> ' + response.data);
								}
							},
							error: function() {
								alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>');
							}
						});
					}
				});

				// Custom Equipment Removal
				$(document).on('click', '.remove-custom-equipment', function() {
					if (confirm('<?php echo esc_js(__('Are you sure you want to remove this custom equipment?', 'mhm-rentiva')); ?>')) {
						const fieldKey = $(this).data('key');
						const item = $(this).closest('.mhm-custom-item');

						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'remove_custom_field',
								field_key: fieldKey,
								field_type: 'equipment',
								nonce: '<?php echo esc_attr(wp_create_nonce('mhm_vehicle_settings_nonce')); ?>'
							},
							success: function(response) {
								if (response.success) {
									item.fadeOut(300, function() {
										$(this).remove();
									});
									alert('<?php echo esc_js(__('Custom equipment removed successfully!', 'mhm-rentiva')); ?>');
								} else {
									alert('<?php echo esc_js(__('Error:', 'mhm-rentiva')); ?> ' + response.data);
								}
							},
							error: function() {
								alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>');
							}
						});
					}
				});

				// BULK OPERATIONS - Details
				$('#select-all-details').on('click', function() {
					$('input[name="selected_details[]"]').prop('checked', true);
				});

				$('#select-none-details').on('click', function() {
					$('input[name="selected_details[]"]').prop('checked', false);
				});

				$('#rename-details').on('click', function() {
					showRenameModal('details');
				});

				// BULK OPERATIONS - Features
				$('#select-all-features').on('click', function() {
					$('input[name="selected_features[]"]').prop('checked', true);
				});

				$('#select-none-features').on('click', function() {
					$('input[name="selected_features[]"]').prop('checked', false);
				});

				$('#rename-features').on('click', function() {
					showRenameModal('features');
				});

				// BULK OPERATIONS - Equipment
				$('#select-all-equipment').on('click', function() {
					$('input[name="selected_equipment[]"]').prop('checked', true);
				});

				$('#select-none-equipment').on('click', function() {
					$('input[name="selected_equipment[]"]').prop('checked', false);
				});

				$('#rename-equipment').on('click', function() {
					showRenameModal('equipment');
				});

				// Form Submit (Save Settings)
				$('#vehicle-settings-form').on('submit', function(e) {
					e.preventDefault();

					const selectedDetails = [];
					const selectedFeatures = [];
					const selectedEquipment = [];

					// Collect selected checkboxes
					$('input[name="selected_details[]"]:checked').each(function() {
						selectedDetails.push($(this).val());
					});

					$('input[name="selected_features[]"]:checked').each(function() {
						selectedFeatures.push($(this).val());
					});

					$('input[name="selected_equipment[]"]:checked').each(function() {
						selectedEquipment.push($(this).val());
					});

					// Collect custom fields
					const customDetails = {};
					const customFeatures = {};
					const customEquipment = {};

					$('#custom-details-list .mhm-custom-item').each(function() {
						const key = $(this).data('key');
						const label = $(this).find('span').text();
						customDetails[key] = label;
					});

					$('#custom-features-list .mhm-custom-item').each(function() {
						const key = $(this).data('key');
						const label = $(this).find('span').text();
						customFeatures[key] = label;
					});

					$('#custom-equipment-list .mhm-custom-item').each(function() {
						const key = $(this).data('key');
						const label = $(this).find('span').text();
						customEquipment[key] = label;
					});

					// Collect updated field names
					const updatedLabels = {
						details: {},
						features: {},
						equipment: {}
					};

					// Collect current labels for each field type
					['details', 'features', 'equipment'].forEach(type => {
						$(`.mhm-checkbox-list input[name="selected_${type}[]"]`).each(function() {
							const key = $(this).val();
							const label = $(this).siblings('span').text();
							updatedLabels[type][key] = label;
						});
					});


					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'save_vehicle_settings',
							selected_details: selectedDetails,
							selected_features: selectedFeatures,
							selected_equipment: selectedEquipment,
							custom_details: customDetails,
							custom_features: customFeatures,
							custom_equipment: customEquipment,
							updated_labels: updatedLabels,
							nonce: '<?php echo esc_attr(wp_create_nonce('vehicle_settings_nonce')); ?>'
						},
						success: function(response) {
							if (response && response.success) {
								alert('<?php echo esc_js(__('Settings saved successfully!', 'mhm-rentiva')); ?>');
								location.reload(); // Reload page
							} else {
								alert('<?php echo esc_js(__('Error:', 'mhm-rentiva')); ?> ' + (response && response.data ? response.data : 'Unknown error'));
							}
						},
						error: function(xhr, status, error) {
							alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>: ' + error);
						}
					});
				});
			});

			// RENAME MODAL FUNCTION - Use jQuery
			window.showRenameModal = function(type) {
				const fields = {};

				// Collect existing fields
				jQuery(`.mhm-checkbox-list input[name="selected_${type}[]"]`).each(function() {
					const key = jQuery(this).val();
					const label = jQuery(this).siblings('span').text();
					fields[key] = label;
				});

				// Create modal HTML
				let modalHtml = `
				<div id="rename-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
					<div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; max-height: 80%; overflow-y: auto;">
						<h3><?php esc_html_e('Edit Field Names', 'mhm-rentiva'); ?></h3>
						<div id="rename-fields-container" style="margin: 20px 0;">
			`;

				// Create input for each field
				for (const [key, label] of Object.entries(fields)) {
					modalHtml += `
					<div style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
						<label style="min-width: 120px; font-weight: bold;">${label}:</label>
						<input type="text" data-key="${key}" value="${label}" style="flex: 1; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
					</div>
				`;
				}

				modalHtml += `
						</div>
						<div style="text-align: right; margin-top: 20px;">
							<button type="button" id="cancel-rename" class="button"><?php esc_html_e('Cancel', 'mhm-rentiva'); ?></button>
							<button type="button" id="save-rename" class="button button-primary"><?php esc_html_e('Save', 'mhm-rentiva'); ?></button>
						</div>
					</div>
				</div>
			`;

				// Add modal
				jQuery('body').append(modalHtml);

				// Event handlers
				jQuery('#cancel-rename').on('click', function() {
					jQuery('#rename-modal').remove();
				});

				jQuery('#save-rename').on('click', function() {
					const newLabels = {};
					jQuery('#rename-fields-container input').each(function() {
						const key = jQuery(this).data('key');
						const newLabel = jQuery(this).val();
						newLabels[key] = newLabel;
					});

					// Update labels
					jQuery('#rename-fields-container input').each(function() {
						const key = jQuery(this).data('key');
						const newLabel = newLabels[key];

						// Update label on page
						jQuery(`input[name="selected_${type}[]"][value="${key}"]`).siblings('span').text(newLabel);
					});

					// Save to database via AJAX
					jQuery.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'update_field_labels',
							type: type,
							labels: newLabels,
							nonce: '<?php echo esc_attr(wp_create_nonce('vehicle_settings_nonce')); ?>'
						},
						success: function(response) {
							if (response && response.success) {
								// Close modal
								jQuery('#rename-modal').remove();

								// Success message
								alert('<?php echo esc_js(__('Field names updated and saved!', 'mhm-rentiva')); ?>');
							} else {
								alert('<?php echo esc_js(__('Error: Field names could not be saved!', 'mhm-rentiva')); ?>');
							}
						},
						error: function() {
							alert('<?php echo esc_js(__('An error occurred!', 'mhm-rentiva')); ?>');
						}
					});
				});
			}
		</script>
<?php
	}

	/**
	 * Helper: Render Sortable List Item
	 */
	private static function render_card_field_list_item(string $type, string $key, string $label, bool $selected): string
	{
		$type  = sanitize_key($type);
		$key   = sanitize_key($key);
		$label = esc_html($label);

		$remove_button = $selected
			? '<button type="button" class="button-link remove-field" aria-label="' . esc_attr__('Remove item', 'mhm-rentiva') . '">&times;</button>'
			: '';

		// Add class for styling
		$class = 'mhm-card-field-item';
		if ($selected) {
			$class .= ' selected';
		}

		return sprintf(
			'<li class="%5$s" data-field-type="%1$s" data-field-key="%2$s" style="padding: 8px; margin: 5px 0; background: #f0f0f1; border: 1px solid #ccc; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; cursor: move;">
                <span class="mhm-card-field-label" style="font-weight: 500;">%3$s</span>
                %4$s
            </li>',
			esc_attr($type),
			esc_attr($key),
			$label,
			$remove_button,
			$class
		);
	}

	/**
	 * Helper: Get Available Fields for Comparison Table
	 * Consolidates Standard, Taxonomy, and Custom fields.
	 */
	private static function get_comparison_available_fields(): array
	{
		$fields = array(
			'details'   => self::get_all_available_details(),
			'features'  => self::get_all_available_features(),
			'equipment' => self::get_all_available_equipment(),
		);

		// Add basic vehicle info often used in comparison but not in "details" (like price, brand)
		// Note: 'brand', 'model' are in standard details usually?
		// Let's check get_all_available_details.
		// It merges get_available_details_list() + custom.
		// get_available_details_list has brand, model, price etc.
		// So we are covered.

		return $fields;
	}

	/**
	 * Default features
	 */
	public static function get_default_features(): array
	{
		return array(
			'air_conditioning' => __('Air Conditioning', 'mhm-rentiva'),
			'power_steering'   => __('Power Steering', 'mhm-rentiva'),
			'abs_brakes'       => __('ABS Brakes', 'mhm-rentiva'),
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
	public static function get_default_equipment(): array
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

	/**
	 * Default details
	 */
	public static function get_default_details(): array
	{
		return array(
			'price_per_day' => __('Daily Price', 'mhm-rentiva'),
			'year'          => __('Year', 'mhm-rentiva'),
			'mileage'       => __('Mileage', 'mhm-rentiva'),
			'license_plate' => __('License Plate', 'mhm-rentiva'),
			'color'         => __('Color', 'mhm-rentiva'),
			'brand'         => __('Brand', 'mhm-rentiva'),
			'model'         => __('Model', 'mhm-rentiva'),
			'seats'         => __('Seats', 'mhm-rentiva'),
			'doors'         => __('Doors', 'mhm-rentiva'),
			'transmission'  => __('Transmission', 'mhm-rentiva'),
			'fuel_type'     => __('Fuel Type', 'mhm-rentiva'),
			'engine_size'   => __('Engine Size', 'mhm-rentiva'),
			'deposit'       => __('Deposit', 'mhm-rentiva'),
			'availability'  => __('Availability', 'mhm-rentiva'),
		);
	}

	/**
	 * Default selected details (checkbox states)
	 */
	public static function get_default_selected_details(): array
	{
		return array('fuel_type', 'transmission', 'seats', 'mileage', 'year', 'deposit');
	}

	/**
	 * Default selected features (checkbox states)
	 */
	public static function get_default_selected_features(): array
	{
		return array('abs', 'air_conditioning', 'central_locking');
	}

	/**
	 * Default selected equipment (checkbox states)
	 */
	public static function get_default_selected_equipment(): array
	{
		return array('gps', 'child_seat');
	}

	/**
	 * Get taxonomy features
	 */
	public static function get_taxonomy_features(): array
	{
		$taxonomy_features = array();
		$taxonomies        = array('mhm_rentiva_feature', 'vehicle_feature', 'vehicle_features');
		foreach ($taxonomies as $tax) {
			if (taxonomy_exists($tax)) {
				$terms = get_terms(
					array(
						'taxonomy'   => $tax,
						'hide_empty' => false,
					)
				);
				if (! empty($terms) && ! is_wp_error($terms)) {
					foreach ($terms as $term) {
						// Use 'tax_' prefix to identify taxonomy terms and avoid collisions
						// Also include taxonomy slug to ensure uniqueness across taxonomies
						$key                       = 'tax_' . $tax . '_' . $term->slug;
						$taxonomy_features[$key] = $term->name;
					}
				}
			}
		}
		return $taxonomy_features;
	}

	/**
	 * Get taxonomy equipment
	 */
	public static function get_taxonomy_equipment(): array
	{
		$taxonomy_equipment = array();
		$taxonomies         = array('mhm_rentiva_equipment', 'vehicle_equipment');
		foreach ($taxonomies as $tax) {
			if (taxonomy_exists($tax)) {
				$terms = get_terms(
					array(
						'taxonomy'   => $tax,
						'hide_empty' => false,
					)
				);
				if (! empty($terms) && ! is_wp_error($terms)) {
					foreach ($terms as $term) {
						$key                        = 'tax_' . $tax . '_' . $term->slug;
						$taxonomy_equipment[$key] = $term->name;
					}
				}
			}
		}
		return $taxonomy_equipment;
	}

	/**
	 * Get all available details (standard + custom)
	 */
	public static function get_all_available_details(): array
	{
		$details = self::get_default_details();
		$stored_details = (array) get_option('mhm_vehicle_details', array());
		foreach ($stored_details as $key => $label) {
			if (! empty($label)) {
				$details[$key] = $label;
			}
		}

		// DATA RECOVERY: If a default field is missing or empty in stored settings, ensure it has a label
		$defaults = self::get_default_details();
		foreach ($defaults as $key => $label) {
			if (empty($details[$key])) {
				$details[$key] = $label;
			}
		}

		$custom_details = (array) get_option('mhm_custom_details', array());
		foreach ($custom_details as $key => $label) {
			if (! empty($label)) {
				$details[$key] = $label;
			}
		}

		return $details;
	}

	/**
	 * Get all available features (standard + custom + taxonomy)
	 */
	public static function get_all_available_features(): array
	{
		$features = self::get_default_features();
		$stored_features = (array) get_option('mhm_vehicle_features', array());
		foreach ($stored_features as $key => $label) {
			if (! empty($label)) {
				$features[$key] = $label;
			}
		}

		// DATA RECOVERY: If a default field is missing or empty in stored settings, ensure it has a label
		$defaults = self::get_default_features();
		foreach ($defaults as $key => $label) {
			if (empty($features[$key])) {
				$features[$key] = $label;
			}
		}

		$custom_features = (array) get_option('mhm_custom_features', array());
		foreach ($custom_features as $key => $label) {
			if (! empty($label)) {
				$features[$key] = $label;
			}
		}

		$taxonomy_features = self::get_taxonomy_features();
		return array_merge($features, $taxonomy_features);
	}

	/**
	 * Get all available equipment (standard + custom + taxonomy)
	 */
	public static function get_all_available_equipment(): array
	{
		$equipment = self::get_default_equipment();
		$stored_equipment = (array) get_option('mhm_vehicle_equipment', array());
		foreach ($stored_equipment as $key => $label) {
			if (! empty($label)) {
				$equipment[$key] = $label;
			}
		}

		// DATA RECOVERY: If a default field is missing or empty in stored settings, ensure it has a label
		$defaults = self::get_default_equipment();
		foreach ($defaults as $key => $label) {
			if (empty($equipment[$key])) {
				$equipment[$key] = $label;
			}
		}

		$custom_equipment = (array) get_option('mhm_custom_equipment', array());
		foreach ($custom_equipment as $key => $label) {
			if (! empty($label)) {
				$equipment[$key] = $label;
			}
		}

		$taxonomy_equipment = self::get_taxonomy_equipment();
		return array_merge($equipment, $taxonomy_equipment);
	}

	/**
	 * AJAX: Add feature
	 */
	public static function ajax_add_feature(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('You do not have permission', 'mhm-rentiva'));
		}

		$name  = self::sanitize_text_field_safe($_POST['name']);
		$key   = 'custom_' . time();
		$label = $name;

		$features         = get_option('mhm_vehicle_features', self::get_default_features());
		$features[$key] = $label;
		update_option('mhm_vehicle_features', $features);

		// PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
		wp_die(
			wp_json_encode(
				array(
					'success' => true,
					'data'    => array(
						'key'   => $key,
						'label' => $label,
					),
				)
			)
		);
	}

	/**
	 * AJAX: Remove feature
	 */
	public static function ajax_remove_feature(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission', 'mhm-rentiva'));
		}

		$key      = self::sanitize_text_field_safe($_POST['key']);
		$features = get_option('mhm_vehicle_features', self::get_default_features());
		unset($features[$key]);
		update_option('mhm_vehicle_features', $features);

		// PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
		wp_die(wp_json_encode(array('success' => true)));
	}

	/**
	 * AJAX: Add equipment
	 */
	public static function ajax_add_equipment(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission', 'mhm-rentiva'));
		}

		$name  = self::sanitize_text_field_safe($_POST['name']);
		$key   = 'custom_' . time();
		$label = $name;

		$equipment         = get_option('mhm_vehicle_equipment', self::get_default_equipment());
		$equipment[$key] = $label;
		update_option('mhm_vehicle_equipment', $equipment);

		// PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
		wp_die(
			wp_json_encode(
				array(
					'success' => true,
					'data'    => array(
						'key'   => $key,
						'label' => $label,
					),
				)
			)
		);
	}

	/**
	 * AJAX: Remove equipment
	 */
	public static function ajax_remove_equipment(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission', 'mhm-rentiva'));
		}

		$key       = self::sanitize_text_field_safe($_POST['key']);
		$equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());
		unset($equipment[$key]);
		update_option('mhm_vehicle_equipment', $equipment);

		// PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
		wp_die(wp_json_encode(array('success' => true)));
	}

	/**
	 * AJAX: Add detail
	 */
	public static function ajax_add_detail(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission', 'mhm-rentiva'));
		}

		$name  = self::sanitize_text_field_safe($_POST['name']);
		$key   = 'custom_' . time();
		$label = $name;

		$details         = get_option('mhm_vehicle_details', self::get_default_details());
		$details[$key] = $label;
		update_option('mhm_vehicle_details', $details);

		// PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
		wp_die(
			wp_json_encode(
				array(
					'success' => true,
					'data'    => array(
						'key'   => $key,
						'label' => $label,
					),
				)
			)
		);
	}

	/**
	 * AJAX: Remove detail
	 */
	public static function ajax_remove_detail(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission', 'mhm-rentiva'));
		}

		$key     = self::sanitize_text_field_safe($_POST['key']);
		$details = get_option('mhm_vehicle_details', self::get_default_details());
		unset($details[$key]);
		update_option('mhm_vehicle_details', $details);

		// PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
		wp_die(wp_json_encode(array('success' => true)));
	}

	/**
	 * AJAX: Save settings
	 */
	public static function ajax_save_settings(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('You do not have permission', 'mhm-rentiva'));
		}

		// CHECK FOR SUB-ACTION (Display Settings)
		if (isset($_POST['sub_action']) && $_POST['sub_action'] === 'save_display_settings') {
			$settings         = get_option('mhm_rentiva_settings', array());
			$settings_updated = false;

			// Save Card Fields
			if (isset($_POST['mhm_rentiva_vehicle_card_fields'])) {
				// It comes as a JSON string from the hidden input
				$json_value = stripslashes($_POST['mhm_rentiva_vehicle_card_fields']);
				$decoded    = json_decode($json_value, true);

				// Validate structure
				if (is_array($decoded)) {
					$settings['mhm_rentiva_vehicle_card_fields'] = $decoded;
					$settings_updated                            = true;
				}
			} else {
				// If not set, it might mean empty?
				// Hidden input usually sends "[]" if empty via JS, but if empty string...
				// Let's assume valid JSON should always be sent if JS works.
			}

			// Save Comparison Fields
			// Note: checkboxes are not sent if unchecked. So we must handle "not set" as "empty" if we know we are in this context.
			$comparison_fields    = $_POST['comparison_fields'] ?? array();
			$sanitized_comparison = array();

			if (is_array($comparison_fields)) {
				foreach ($comparison_fields as $cat => $fields) {
					if (is_array($fields)) {
						$sanitized_comparison[$cat] = array_map('sanitize_text_field', $fields);
					}
				}
			}

			// Should we save if empty? Yes, user might have deselected all.
			$settings['comparison_fields'] = $sanitized_comparison;
			$settings_updated              = true;

			if ($settings_updated) {
				update_option('mhm_rentiva_settings', $settings);
			}

			wp_send_json_success(__('Display settings saved!', 'mhm-rentiva'));
			return;
		}

		// Save selected fields (Definitions Tab)
		$selected_details   = isset($_POST['selected_details']) ? array_map('sanitize_text_field', $_POST['selected_details']) : array();
		$selected_features  = isset($_POST['selected_features']) ? array_map('sanitize_text_field', $_POST['selected_features']) : array();
		$selected_equipment = isset($_POST['selected_equipment']) ? array_map('sanitize_text_field', $_POST['selected_equipment']) : array();

		// Save custom fields
		$custom_details   = isset($_POST['custom_details']) ? array_map('sanitize_text_field', $_POST['custom_details']) : array();
		$custom_features  = isset($_POST['custom_features']) ? array_map('sanitize_text_field', $_POST['custom_features']) : array();
		$custom_equipment = isset($_POST['custom_equipment']) ? array_map('sanitize_text_field', $_POST['custom_equipment']) : array();

		// REMOVED destructive updated_labels logic.
		// Renaming is handled by the dedicated ajax_update_field_labels method.

		// Save to database
		update_option('mhm_selected_details', $selected_details);
		update_option('mhm_selected_features', $selected_features);
		update_option('mhm_selected_equipment', $selected_equipment);

		// FIXED: Only update custom fields if they were actually sent in the POST.
		// Usually custom fields are only managed via the specific Add/Remove AJAX calls.
		if (isset($_POST['custom_details'])) {
			update_option('mhm_custom_details', $custom_details);
		}
		if (isset($_POST['custom_features'])) {
			update_option('mhm_custom_features', $custom_features);
		}
		if (isset($_POST['custom_equipment'])) {
			update_option('mhm_custom_equipment', $custom_equipment);
		}

		wp_send_json_success(__('Settings saved successfully!', 'mhm-rentiva'));
	}

	/**
	 * AJAX: Update field names
	 */
	public static function ajax_update_field_labels(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(__('You do not have permission', 'mhm-rentiva'));
		}

		$type = self::sanitize_text_field_safe($_POST['type'] ?? '');
		// Sanitize labels array properly
		$labels = isset($_POST['labels']) && is_array($_POST['labels']) ? $_POST['labels'] : array();

		// Sanitize labels
		$sanitized_labels = array();
		foreach ($labels as $key => $label) {
			$sanitized_key   = self::sanitize_text_field_safe($key);
			$sanitized_label = self::sanitize_text_field_safe($label);
			// Encoding fix - For Turkish characters
			$sanitized_label                    = mb_convert_encoding($sanitized_label, 'UTF-8', 'auto');
			$sanitized_labels[$sanitized_key] = $sanitized_label;
		}

		// Get existing fields (updated ones)
		if ($type === 'details') {
			$current_details = get_option('mhm_vehicle_details', self::get_default_details());
			$custom_details  = get_option('mhm_custom_details', array());

			foreach ($sanitized_labels as $key => $new_label) {
				// Update standard fields
				if (isset($current_details[$key])) {
					$current_details[$key] = $new_label;
				}
				// Update custom fields
				elseif (isset($custom_details[$key])) {
					$custom_details[$key] = $new_label;
				}
			}

			update_option('mhm_vehicle_details', $current_details);
			update_option('mhm_custom_details', $custom_details);
		} elseif ($type === 'features') {
			$current_features = get_option('mhm_vehicle_features', self::get_default_features());
			$custom_features  = get_option('mhm_custom_features', array());

			foreach ($sanitized_labels as $key => $new_label) {
				// Update standard fields
				if (isset($current_features[$key])) {
					$current_features[$key] = $new_label;
				}
				// Update custom fields
				elseif (isset($custom_features[$key])) {
					$custom_features[$key] = $new_label;
				}
			}

			update_option('mhm_vehicle_features', $current_features);
			update_option('mhm_custom_features', $custom_features);
		} elseif ($type === 'equipment') {
			$current_equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());
			$custom_equipment  = get_option('mhm_custom_equipment', array());

			foreach ($sanitized_labels as $key => $new_label) {
				// Update standard fields
				if (isset($current_equipment[$key])) {
					$current_equipment[$key] = $new_label;
				}
				// Update custom fields
				elseif (isset($custom_equipment[$key])) {
					$custom_equipment[$key] = $new_label;
				}
			}

			update_option('mhm_vehicle_equipment', $current_equipment);
			update_option('mhm_custom_equipment', $custom_equipment);
		}

		wp_send_json_success(__('Field names updated successfully!', 'mhm-rentiva'));
	}

	/**
	 * Remove custom field
	 */
	public static function ajax_remove_custom_field(): void
	{
		// Nonce check
		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_vehicle_settings_nonce')) {
			wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
			return;
		}

		$field_key  = self::sanitize_text_field_safe($_POST['field_key']);
		$field_type = self::sanitize_text_field_safe($_POST['field_type']); // details, features, equipment

		if ($field_type === 'details') {
			// 1. Check if Core (Cannot remove)
			$core_fields = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_core_fields();
			$core_fields[] = 'deposit'; // Block deletion of deposit field (v4.8.2)
			if (in_array($field_key, $core_fields)) {
				wp_send_json_error(__('This is a core field and cannot be removed.', 'mhm-rentiva'));
				return;
			}

			// 2. Try removing from Standard Details
			$current_details = get_option('mhm_vehicle_details', self::get_default_details());
			if (isset($current_details[$field_key])) {
				unset($current_details[$field_key]);
				update_option('mhm_vehicle_details', $current_details);

				// Clean meta
				global $wpdb;
				$wpdb->delete($wpdb->postmeta, array('meta_key' => '_mhm_rentiva_' . $field_key));

				wp_send_json_success(__('Standard field removed successfully.', 'mhm-rentiva'));
				return;
			}

			// 3. Try removing from Custom Details
			$custom_details = get_option('mhm_custom_details', array());
			if (isset($custom_details[$field_key])) {
				unset($custom_details[$field_key]);
				update_option('mhm_custom_details', $custom_details);

				// Clean related post meta
				global $wpdb;
				$wpdb->delete(
					$wpdb->postmeta,
					array(
						'meta_key' => '_mhm_rentiva_' . $field_key,
					)
				);

				wp_send_json_success(__('Custom detail removed successfully', 'mhm-rentiva'));
			} else {
				wp_send_json_error('Field not found');
			}
		} elseif ($field_type === 'features') {
			// 1. Try removing from Standard Features
			$current_features = get_option('mhm_vehicle_features', self::get_default_features());
			if (isset($current_features[$field_key])) {
				unset($current_features[$field_key]);
				update_option('mhm_vehicle_features', $current_features);

				// Clean meta
				global $wpdb;
				$wpdb->delete($wpdb->postmeta, array('meta_key' => '_mhm_rentiva_' . $field_key));

				wp_send_json_success(__('Standard feature removed successfully', 'mhm-rentiva'));
				return;
			}

			// 2. Try removing from Custom Features
			$custom_features = get_option('mhm_custom_features', array());
			if (isset($custom_features[$field_key])) {
				unset($custom_features[$field_key]);
				update_option('mhm_custom_features', $custom_features);

				// Clean related post meta
				global $wpdb;
				$wpdb->delete(
					$wpdb->postmeta,
					array(
						'meta_key' => '_mhm_rentiva_' . $field_key,
					)
				);

				wp_send_json_success(__('Custom feature removed successfully', 'mhm-rentiva'));
			} else {
				wp_send_json_error('Feature not found');
			}
		} elseif ($field_type === 'equipment') {
			// 1. Try removing from Standard Equipment
			$current_equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());
			if (isset($current_equipment[$field_key])) {
				unset($current_equipment[$field_key]);
				update_option('mhm_vehicle_equipment', $current_equipment);

				// Clean meta
				global $wpdb;
				$wpdb->delete($wpdb->postmeta, array('meta_key' => '_mhm_rentiva_' . $field_key));

				wp_send_json_success(__('Standard equipment removed successfully', 'mhm-rentiva'));
				return;
			}

			// 2. Try removing from Custom Equipment
			$custom_equipment = get_option('mhm_custom_equipment', array());
			if (isset($custom_equipment[$field_key])) {
				unset($custom_equipment[$field_key]);
				update_option('mhm_custom_equipment', $custom_equipment);

				// Clean related post meta
				global $wpdb;
				$wpdb->delete(
					$wpdb->postmeta,
					array(
						'meta_key' => '_mhm_rentiva_' . $field_key,
					)
				);

				wp_send_json_success(__('Custom equipment removed successfully', 'mhm-rentiva'));
			} else {
				wp_send_json_error('Equipment not found');
			}
		} else {
			wp_send_json_error('Invalid field type');
		}
	}

	/**
	 * Add custom field
	 */
	public static function ajax_add_custom_field(): void
	{
		// Nonce check
		if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_vehicle_settings_nonce')) {
			wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
			return;
		}

		$field_key   = self::sanitize_text_field_safe($_POST['field_key']);
		$field_label = self::sanitize_text_field_safe($_POST['field_label']);
		$field_type  = self::sanitize_text_field_safe($_POST['field_type']); // details, features, equipment

		// Encoding fix - For Turkish characters
		$field_label = mb_convert_encoding($field_label, 'UTF-8', 'auto');

		if ('details' === $field_type) {
			$custom_details               = get_option('mhm_custom_details', array());
			$custom_details[$field_key] = $field_label;
			update_option('mhm_custom_details', $custom_details);

			// Save extended meta (Type & Options)
			if (! empty($_POST['type'])) {
				$field_meta               = get_option('mhm_custom_field_meta', array());
				$field_meta[$field_key] = array(
					'type'    => self::sanitize_text_field_safe($_POST['type']),
					'options' => isset($_POST['options']) ? self::sanitize_text_field_safe($_POST['options']) : '',
				);
				update_option('mhm_custom_field_meta', $field_meta);
			}

			wp_send_json_success(esc_html__('Custom detail added successfully', 'mhm-rentiva'));
		} elseif ('features' === $field_type) {
			$custom_features               = get_option('mhm_custom_features', array());
			$custom_features[$field_key] = $field_label;
			update_option('mhm_custom_features', $custom_features);

			wp_send_json_success(esc_html__('Custom feature added successfully', 'mhm-rentiva'));
		} elseif ('equipment' === $field_type) {
			$custom_equipment               = get_option('mhm_custom_equipment', array());
			$custom_equipment[$field_key] = $field_label;
			update_option('mhm_custom_equipment', $custom_equipment);

			wp_send_json_success(esc_html__('Custom equipment added successfully', 'mhm-rentiva'));
		} else {
			wp_send_json_error(esc_html__('Invalid field type', 'mhm-rentiva'));
		}
	}

	/**
	 * AJAX Reset Settings
	 */
	public static function ajax_reset_settings(): void
	{
		check_ajax_referer('vehicle_settings_nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => esc_html__('Insufficient permissions.', 'mhm-rentiva')));
		}

		$tab = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : 'definitions';

		if ($tab === 'display') {
			// Reset Display Options to empty arrays
			$settings = get_option('mhm_rentiva_settings', array());
			$settings['mhm_rentiva_vehicle_card_fields'] = array();
			$settings['comparison_fields']               = array();
			update_option('mhm_rentiva_settings', $settings);
		} else {
			// Reset Selection Options (Checkboxes) to empty arrays (Definitions Tab)
			update_option('mhm_selected_details', array());
			update_option('mhm_selected_features', array());
			update_option('mhm_selected_equipment', array());
		}

		wp_send_json_success(array('message' => esc_html__('Settings reset to defaults.', 'mhm-rentiva')));
	}
}
