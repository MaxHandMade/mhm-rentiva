<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Settings;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ VEHICLE SETTINGS - Vehicle Features and Equipment Management
 *
 * Manage vehicle features and equipment in admin panel
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Vehicle settings screens rely on controlled analytical/meta queries for admin management.
use MHMRentiva\Admin\Core\Security\VerifiedRequest;

final class VehicleSettings {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;



	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Read a sanitized key from $_GET.
	 */
	private static function get_key( string $key, string $default = '' ): string {
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default;
		}

		$value = sanitize_key( wp_unslash( $_GET[ $key ] ) );

		return $value;
	}

	public static function register(): void {
		// Menu registration is now done centrally in Menu.php
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
		add_action( 'wp_ajax_mhmrentiva_save_vehicle_settings', array( self::class, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_mhmrentiva_update_field_labels', array( self::class, 'ajax_update_field_labels' ) );
		add_action( 'wp_ajax_mhmrentiva_remove_custom_field', array( self::class, 'ajax_remove_custom_field' ) );
		add_action( 'wp_ajax_mhmrentiva_add_custom_field', array( self::class, 'ajax_add_custom_field' ) );

		// Reset Settings
		add_action( 'wp_ajax_mhmrentiva_reset_vehicle_settings', array( self::class, 'ajax_reset_settings' ) );
	}

	/**
	 * ✅ Take responsibility for global setting updates from VehicleMeta
	 */
	public static function update_global_vehicle_settings( int $post_id, \WP_Post $post ): void {
		// Nonce check
		$nonce = sanitize_text_field( wp_unslash( $_POST['mhmrentiva_vehicle_meta_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_vehicle_meta_action' ) ) {
			return;
		}

		// Permission check
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Nothing hooks or calls this today (measured 2026-08-27), so it is unreachable.
		// The check is here anyway: its signature is save_post-shaped, and the day someone
		// hooks it the M-1 defect arrives with it -- edit_post proves permission, never
		// identity, and this writes global vehicle settings.
		if ( 'mhmrentiva_vehicle' !== $post->post_type ) {
			return;
		}

		// Autosave and revision check
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Sanitize and validate custom details array from POST
		$custom_details = VerifiedRequest::from( $_POST )->arr( 'mhmrentiva_custom_details' );

		if ( ! empty( $custom_details ) ) {
			$available_details = get_option( 'mhmrentiva_vehicle_details', array() );
			$option_updated    = false;

			foreach ( $custom_details as $key => $detail_data ) {
				if ( is_array( $detail_data ) && isset( $detail_data['label'] ) && isset( $detail_data['value'] ) ) {
					// Add to global options
					$available_details[ self::sanitize_text_field_safe( $key ) ] = self::sanitize_text_field_safe( $detail_data['label'] );
					$option_updated = true;
				}
			}

			// Update option
			if ( $option_updated ) {
				update_option( 'mhmrentiva_vehicle_details', $available_details );
			}
		}
	}

	/**
	 * Sanitize an array-shaped option: `sanitize_key()` on the KEY, `sanitize_text_field()`
	 * on the VALUE.
	 *
	 * Used for `mhmrentiva_selected_*` (selected field-key lists) and `mhmrentiva_custom_*`
	 * (custom field slug => label maps). In both cases the array KEY is an
	 * internal slug -- for `mhmrentiva_custom_*` it gates `isset()` lookups and is
	 * suffixed onto a postmeta key (`_mhmrentiva_<key>`); it is normally
	 * server-generated (`custom_<time>_<rand>`, see ajax_add_custom_field())
	 * or taxonomy-derived (`tax_<taxonomy>_<slug>`), both already
	 * `[a-z0-9_-]`, so `sanitize_key()` is lossless for real data. The VALUE
	 * is a plain label/selection string, so `sanitize_text_field()` (as
	 * before) is correct there.
	 *
	 * `array_map( 'sanitize_text_field', $input )` sanitized
	 * VALUES only -- `array_map()` never touches keys -- so a dirty array key
	 * (e.g. submitted through the Settings API, or any `update_option()`
	 * call, since core's `sanitize_option()` always routes through the
	 * registered callback) could persist raw markup into the stored option.
	 *
	 * @param mixed $input Raw option payload.
	 * @return array<string,string>
	 */
	public static function sanitize_array_option( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$out = array();
		foreach ( $input as $key => $value ) {
			$safe_key = sanitize_key( (string) $key );
			if ( '' === $safe_key ) {
				continue;
			}
			$out[ $safe_key ] = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		}

		return $out;
	}

	/**
	 * Save settings
	 */
	public static function register_settings(): void {
		$sanitize_callback = array( self::class, 'sanitize_array_option' );

		// Selected fields (checkbox states)
		register_setting( 'mhmrentiva_vehicle_settings', 'mhmrentiva_selected_details', array( 'sanitize_callback' => $sanitize_callback ) );
		register_setting( 'mhmrentiva_vehicle_settings', 'mhmrentiva_selected_features', array( 'sanitize_callback' => $sanitize_callback ) );
		register_setting( 'mhmrentiva_vehicle_settings', 'mhmrentiva_selected_equipment', array( 'sanitize_callback' => $sanitize_callback ) );

		// Custom fields
		register_setting( 'mhmrentiva_vehicle_settings', 'mhmrentiva_custom_details', array( 'sanitize_callback' => $sanitize_callback ) );
		register_setting( 'mhmrentiva_vehicle_settings', 'mhmrentiva_custom_features', array( 'sanitize_callback' => $sanitize_callback ) );
		register_setting( 'mhmrentiva_vehicle_settings', 'mhmrentiva_custom_equipment', array( 'sanitize_callback' => $sanitize_callback ) );
	}

	/**
	 * Render settings page
	 */
	/**
	 * Whether to render the redesigned (v2) Vehicle Settings UI.
	 *
	 * The rebuild is complete, so v2 is now the default. The previous server-rendered
	 * tabs remain reachable as a fallback via ?ui=legacy (kept for one release in case a
	 * site needs the old screen); everything else — including no flag at all — gets v2.
	 */
	public static function is_v2_ui(): bool {
		return 'legacy' !== self::get_key( 'ui' );
	}

	public function render_settings_page(): void {
		$active_tab = self::get_key( 'tab', 'definitions' );

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
		$this->render_admin_header( (string) get_admin_page_title(), $buttons );

		// Developer Mode Banner
		$this->render_developer_mode_banner();
		?>
		<?php
		if ( self::is_v2_ui() ) {
			// Redesigned UI: a single mount point; assets/js/admin/vehicle-settings-v2.js renders
			// both tabs client-side from the localized mhmVehicleSettings.state payload.
			echo '<div id="rv-vs-app" class="rv-vs"></div>';
		} else {
			?>
			<nav class="nav-tab-wrapper">
				<a href="?page=vehicle-settings&ui=legacy&tab=definitions" class="nav-tab <?php echo $active_tab === 'definitions' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Field Definitions', 'mhm-rentiva' ); ?>
				</a>
				<a href="?page=vehicle-settings&ui=legacy&tab=display" class="nav-tab <?php echo $active_tab === 'display' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Display Options', 'mhm-rentiva' ); ?>
				</a>
			</nav>

			<?php
			if ( $active_tab === 'display' ) {
				self::render_display_tab();
			} else {
				self::render_definitions_tab();
			}
		}
		?>
		</div>
		<?php
	}

	/**
	 * Render Display Options Tab (Card Fields & Comparisons)
	 */
	private static function render_display_tab(): void {
		// 1. Visible Card Items (Drag & Drop)
		$available_map = VehicleFeatureHelper::get_available_fields_map();
		$selected      = VehicleFeatureHelper::get_selected_card_fields();

		// Build lookup for quick label resolution
		$available_flat = array();
		foreach ( $available_map as $type => $fields ) {
			foreach ( $fields as $key => $field ) {
				// Ensure field has label
				if ( ! isset( $field['label'] ) ) {
					// Fallback label generation
					$field['label'] = ucfirst( str_replace( '_', ' ', $key ) );
				}
				$available_flat[ $type . ':' . $key ] = $field;
			}
		}

		$selected_items = array();
		foreach ( $selected as $item ) {
			$id = $item['type'] . ':' . $item['key'];
			// If item is in available_flat, use its label.
			// If not, it might be a custom field that was removed?
			// Or a custom field that needs to be looked up.

			$label = '';
			if ( isset( $available_flat[ $id ] ) ) {
				$label = $available_flat[ $id ]['label'];
				unset( $available_flat[ $id ] ); // Remove from available list so it doesn't show up twice
			} else {
				// Try to resolve label for custom items even if not in map (e.g. might be missing from helper map but is valid)
				// However, helper map SHOULD contain everything.
				// Let's fallback to generated label
				$label = ucfirst( str_replace( '_', ' ', $item['key'] ) );
			}

			$selected_items[] = array(
				'type'  => $item['type'],
				'key'   => $item['key'],
				'label' => $label,
			);
		}

		// Detail page highlighted features selection.
		$detail_selected_rows      = VehicleFeatureHelper::get_selected_detail_fields();
		$available_flat_for_detail = array();
		foreach ( $available_map as $type => $fields ) {
			foreach ( $fields as $key => $field ) {
				if ( ! isset( $field['label'] ) ) {
					$field['label'] = ucfirst( str_replace( '_', ' ', $key ) );
				}
				$available_flat_for_detail[ $type . ':' . $key ] = $field;
			}
		}

		$detail_selected_items = array();
		foreach ( $detail_selected_rows as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
			$key  = isset( $item['key'] ) ? sanitize_key( (string) $item['key'] ) : '';
			if ( $type === '' || $key === '' ) {
				continue;
			}

			$id    = $type . ':' . $key;
			$label = '';
			if ( isset( $available_flat_for_detail[ $id ] ) ) {
				$label = $available_flat_for_detail[ $id ]['label'];
				unset( $available_flat_for_detail[ $id ] );
			} else {
				$label = ucfirst( str_replace( '_', ' ', $key ) );
			}

			$detail_selected_items[] = array(
				'type'  => $type,
				'key'   => $key,
				'label' => $label,
			);
		}

		$detail_available_items = array();
		foreach ( $available_flat_for_detail as $id => $data ) {
			$detail_available_items[] = array(
				'type'  => $data['type'],
				'key'   => $data['key'] ?? $id,
				'label' => $data['label'],
			);
		}

		// Remaining items in available_flat are "Available"
		$available_items = array();
		foreach ( $available_flat as $id => $data ) {
			$available_items[] = array(
				'type'  => $data['type'],
				'key'   => $data['key'] ?? $id,
				'label' => $data['label'],
			);
		}

		$hidden_value        = esc_attr( wp_json_encode( $selected ) );
		$detail_hidden_value = esc_attr( wp_json_encode( $detail_selected_rows ) );

		// 2. Comparison Fields
		$settings                    = get_option( 'mhmrentiva_settings', array() );
		$selected_comparison_fields  = $settings['comparison_fields'] ?? array();
		$available_comparison_fields = self::get_comparison_available_fields();
		$show_defaults               = empty( $selected_comparison_fields );

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="vehicle-display-settings-form">

			<div class="mhm-settings-section">
				<h2><?php echo esc_html__( 'Visible Card Items', 'mhm-rentiva' ); ?></h2>
				<div class="mhm-card-fields-wrapper">
					<input type="hidden" id="mhm-vehicle-card-fields-input" name="mhmrentiva_vehicle_card_fields" value="<?php echo esc_attr( $hidden_value ); ?>" />

					<div class="mhm-card-fields-columns">

						<div class="mhm-card-fields-column">
							<h4><?php echo esc_html__( 'Visible Items', 'mhm-rentiva' ); ?>
								<button type="button" id="clear-card-fields" class="button button-small"><?php echo esc_html__( 'Clear All', 'mhm-rentiva' ); ?></button>
							</h4>
							<p class="description"><?php echo esc_html__( 'Drag to reorder or click to remove items from the vehicle card.', 'mhm-rentiva' ); ?></p>
							<input type="search" class="regular-text mhm-card-field-search" data-target="#mhm-card-fields-selected" placeholder="<?php echo esc_attr__( 'Search visible items...', 'mhm-rentiva' ); ?>">
							<ul id="mhm-card-fields-selected" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items selected', 'mhm-rentiva' ); ?>">
								<?php if ( ! empty( $selected_items ) ) : ?>
									<?php foreach ( $selected_items as $item ) : ?>
										<?php echo wp_kses_post( self::render_card_field_list_item( $item['type'], $item['key'], $item['label'], true ) ); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
						</div>

						<div class="mhm-card-fields-column">
							<h4><?php echo esc_html__( 'Available Items', 'mhm-rentiva' ); ?></h4>
							<p class="description"><?php echo esc_html__( 'Drag items here to hide them from the card.', 'mhm-rentiva' ); ?></p>
							<input type="search" class="regular-text mhm-card-field-search" data-target="#mhm-card-fields-available" placeholder="<?php echo esc_attr__( 'Search available items...', 'mhm-rentiva' ); ?>">
							<ul id="mhm-card-fields-available" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items available', 'mhm-rentiva' ); ?>">
								<?php if ( ! empty( $available_items ) ) : ?>
									<?php foreach ( $available_items as $item ) : ?>
										<?php echo wp_kses_post( self::render_card_field_list_item( $item['type'], $item['key'], $item['label'], false ) ); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
						</div>

					</div>
					<p class="description mhm-card-fields-footer">
						<?php echo esc_html__( 'Tip: The order you set here applies to vehicle grids, list views and the My Account favorites grid.', 'mhm-rentiva' ); ?>
					</p>
				</div>
			</div>

			<div class="mhm-settings-section">
				<h2><?php echo esc_html__( 'Vehicle Detail Highlighted Features', 'mhm-rentiva' ); ?></h2>
				<div class="mhm-card-fields-wrapper mhm-detail-fields-wrapper">
					<input type="hidden" id="mhm-vehicle-detail-fields-input" name="mhmrentiva_vehicle_detail_fields" value="<?php echo esc_attr( $detail_hidden_value ); ?>" />

					<div class="mhm-card-fields-columns">
						<div class="mhm-card-fields-column">
							<h4><?php echo esc_html__( 'Visible in Vehicle Detail', 'mhm-rentiva' ); ?>
								<button type="button" id="clear-detail-fields" class="button button-small"><?php echo esc_html__( 'Clear All', 'mhm-rentiva' ); ?></button>
							</h4>
							<p class="description"><?php echo esc_html__( 'Drag to reorder or click remove to hide features in the detail page highlighted section.', 'mhm-rentiva' ); ?></p>
							<input type="search" class="regular-text mhm-card-field-search" data-target="#mhm-detail-fields-selected" placeholder="<?php echo esc_attr__( 'Search visible detail items...', 'mhm-rentiva' ); ?>">
							<ul id="mhm-detail-fields-selected" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items selected', 'mhm-rentiva' ); ?>">
								<?php if ( ! empty( $detail_selected_items ) ) : ?>
									<?php foreach ( $detail_selected_items as $item ) : ?>
										<?php echo wp_kses_post( self::render_card_field_list_item( $item['type'], $item['key'], $item['label'], true ) ); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
						</div>

						<div class="mhm-card-fields-column">
							<h4><?php echo esc_html__( 'Available for Vehicle Detail', 'mhm-rentiva' ); ?></h4>
							<p class="description"><?php echo esc_html__( 'Drag items here to hide them from the detail page highlighted section.', 'mhm-rentiva' ); ?></p>
							<input type="search" class="regular-text mhm-card-field-search" data-target="#mhm-detail-fields-available" placeholder="<?php echo esc_attr__( 'Search available detail items...', 'mhm-rentiva' ); ?>">
							<ul id="mhm-detail-fields-available" class="mhm-card-fields-list" data-empty-label="<?php esc_attr_e( 'No items available', 'mhm-rentiva' ); ?>">
								<?php if ( ! empty( $detail_available_items ) ) : ?>
									<?php foreach ( $detail_available_items as $item ) : ?>
										<?php echo wp_kses_post( self::render_card_field_list_item( $item['type'], $item['key'], $item['label'], false ) ); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</ul>
						</div>
					</div>
					<p class="description mhm-card-fields-footer">
						<?php echo esc_html__( 'Tip: This controls the "Highlighted Features" section in vehicle detail pages and shortcode output.', 'mhm-rentiva' ); ?>
					</p>
				</div>
			</div>

			<hr class="mhm-section-divider">

			<div class="mhm-settings-section">
				<h2><?php echo esc_html__( 'Comparison Table Settings', 'mhm-rentiva' ); ?></h2>
				<div class="mhm-comparison-fields">
					<p class="description">
						<?php echo esc_html__( 'Select which fields to display in the vehicle comparison table:', 'mhm-rentiva' ); ?>
					</p>

					<div class="mhm-field-categories">
						<?php foreach ( $available_comparison_fields as $category => $fields ) : ?>
							<div class="mhm-field-category" data-category="<?php echo esc_attr( $category ); ?>">
								<div class="mhm-category-header">
									<?php
									$category_labels = array(
										'details'   => __( 'Details', 'mhm-rentiva' ),
										'features'  => __( 'Features', 'mhm-rentiva' ),
										'equipment' => __( 'Equipment', 'mhm-rentiva' ),
									);
									$cat_label       = $category_labels[ $category ] ?? ucfirst( $category );
									?>
									<h4><?php echo esc_html( $cat_label ); ?></h4>
									<div class="mhm-category-actions">
										<button type="button" class="button button-small mhm-select-all-btn" data-category="<?php echo esc_attr( $category ); ?>">
											<?php echo esc_html__( 'Select All', 'mhm-rentiva' ); ?>
										</button>
										<button type="button" class="button button-small mhm-deselect-all-btn" data-category="<?php echo esc_attr( $category ); ?>">
											<?php echo esc_html__( 'Deselect All', 'mhm-rentiva' ); ?></button>
									</div>
								</div>
								<div class="mhm-field-list">
									<?php foreach ( $fields as $field_key => $field_label ) : ?>
										<div class="mhm-checkbox-item">
											<label class="mhm-checkbox-label">
												<input type="checkbox" name="comparison_fields[<?php echo esc_attr( $category ); ?>][]" value="<?php echo esc_attr( $field_key ); ?>"
													<?php checked( $show_defaults || ( isset( $selected_comparison_fields[ $category ] ) && in_array( $field_key, $selected_comparison_fields[ $category ] ) ) ); ?>>
												<span><?php echo esc_html( $field_label ); ?></span>
											</label>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="mhm-display-save-actions submit-section">
				<input type="hidden" name="action" value="mhmrentiva_save_vehicle_settings">
				<input type="hidden" name="sub_action" value="save_display_settings">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'vehicle_settings_nonce' ) ); ?>">
				<button type="submit" id="save-display-settings" class="button button-primary button-large"><?php echo esc_html__( 'Save Display Settings', 'mhm-rentiva' ); ?></button>
			</div>
		</form>

		<?php
	}

	/**
	 * Render Definitions Tab (Original Content)
	 */
	public static function render_definitions_tab(): void {
		$selected_details   = get_option( 'mhmrentiva_selected_details', self::get_default_selected_details() );
		$selected_features  = get_option( 'mhmrentiva_selected_features', self::get_default_selected_features() );
		$selected_equipment = get_option( 'mhmrentiva_selected_equipment', self::get_default_selected_equipment() );

		// Get custom fields
		$custom_details   = get_option( 'mhmrentiva_custom_details', array() );
		$custom_features  = get_option( 'mhmrentiva_custom_features', array() );
		$custom_equipment = get_option( 'mhmrentiva_custom_equipment', array() );

		// Get all existing fields (standard + custom)
		$all_details   = self::get_all_available_details();
		$all_features  = self::get_all_available_features();
		$all_equipment = self::get_all_available_equipment();

		// Custom key sets — used to exclude custom fields from standard loops
		$custom_feature_keys   = array_keys( $custom_features );
		$custom_equipment_keys = array_keys( $custom_equipment );

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" id="vehicle-settings-form">
			<div class="mhm-vehicle-definitions-content">
				<p class="description"><?php echo esc_html__( 'Select fields to use on vehicles. You can also add custom fields.', 'mhm-rentiva' ); ?></p>

				<div class="mhm-settings-container">

					<!-- Vehicle Details -->
					<div class="mhm-settings-card">
						<h2><?php echo esc_html__( 'Vehicle Details', 'mhm-rentiva' ); ?></h2>
						<p><?php echo esc_html__( 'Select the details you want to use', 'mhm-rentiva' ); ?></p>

						<!-- Core Details (Permanent) -->
						<h4 class="mhm-section-subtitle"><?php echo esc_html__( 'Core Details (Essential)', 'mhm-rentiva' ); ?></h4>
						<div class="mhm-checkbox-list mhm-core-details-grid">
							<?php
							$core_fields = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_core_fields();
							foreach ( $all_details as $key => $label ) :
								if ( ! in_array( $key, $core_fields ) ) {
									continue;
								}
								?>
								<div class="mhm-checkbox-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_details[]" value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $selected_details ) ); ?>
											disabled="disabled"
											title="<?php esc_attr_e( 'Core fields cannot be disabled', 'mhm-rentiva' ); ?>">
										<span><?php echo esc_html( ! empty( $label ) ? $label : $key ); ?></span>
									</label>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Optional & Custom Details (Removable) -->
						<div class="mhm-card-section-header">
							<h4><?php echo esc_html__( 'Attributes & Custom Details', 'mhm-rentiva' ); ?></h4>
							<div class="mhm-category-actions">
								<button type="button" id="select-all-details" class="button button-small"><?php esc_html_e( 'Select All', 'mhm-rentiva' ); ?></button>
								<button type="button" id="select-none-details" class="button button-small"><?php esc_html_e( 'Deselect All', 'mhm-rentiva' ); ?></button>
								<button type="button" id="rename-details" class="button button-small"><?php esc_html_e( 'Edit Names', 'mhm-rentiva' ); ?></button>
							</div>
						</div>
						<div class="mhm-checkbox-list" id="custom-details-list">
							<?php
							foreach ( $all_details as $key => $label ) :
								if ( in_array( $key, $core_fields ) ) {
									continue;
								}
								?>
								<div class="mhm-checkbox-item mhm-removable-item mhm-custom-row-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_details[]" value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $selected_details ) ); ?>>
										<span><?php echo esc_html( ! empty( $label ) ? $label : $key ); ?></span>
									</label>
									<button type="button" class="button-link remove-custom-detail" data-key="<?php echo esc_attr( $key ); ?>">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Add New Custom Detail -->
						<div class="mhm-add-custom-wrapper">
							<div class="mhm-add-custom-row">
								<input type="text" id="new-custom-detail-name" placeholder="<?php esc_attr_e( 'Custom detail name', 'mhm-rentiva' ); ?>">

								<select id="new-custom-detail-type">
									<option value="text"><?php esc_html_e( 'Text', 'mhm-rentiva' ); ?></option>
									<option value="number"><?php esc_html_e( 'Number', 'mhm-rentiva' ); ?></option>
									<option value="select"><?php esc_html_e( 'Select (Dropdown)', 'mhm-rentiva' ); ?></option>
								</select>

								<div id="new-custom-detail-options-wrapper" style="display: none;">
									<input type="text" id="new-custom-detail-options" class="mhm-select-options-input" placeholder="<?php esc_attr_e( 'Options (comma separated: Petrol, Diesel)', 'mhm-rentiva' ); ?>">
								</div>

								<button type="button" id="add-custom-detail" class="button button-secondary"><?php esc_html_e( 'Add Custom', 'mhm-rentiva' ); ?></button>
							</div>
						</div>
					</div>

					<!-- Vehicle Features -->
					<div class="mhm-settings-card">
						<h2><?php echo esc_html__( 'Vehicle Features', 'mhm-rentiva' ); ?></h2>
						<p><?php echo esc_html__( 'Select the features you want to use', 'mhm-rentiva' ); ?></p>

						<!-- Standard Features -->
						<div class="mhm-card-section-header">
							<h4><?php echo esc_html__( 'Standard Features', 'mhm-rentiva' ); ?></h4>
							<div class="mhm-category-actions">
								<button type="button" id="select-all-features" class="button button-small"><?php esc_html_e( 'Select All', 'mhm-rentiva' ); ?></button>
								<button type="button" id="select-none-features" class="button button-small"><?php esc_html_e( 'Deselect All', 'mhm-rentiva' ); ?></button>
								<button type="button" id="rename-features" class="button button-small"><?php esc_html_e( 'Edit Names', 'mhm-rentiva' ); ?></button>
							</div>
						</div>
						<div class="mhm-checkbox-list">
							<?php
                            foreach ( $all_features as $key => $label ) :
								if ( in_array( $key, $custom_feature_keys, true ) ) {
									continue;
								}
								?>
								<div class="mhm-checkbox-item mhm-removable-item mhm-custom-row-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_features[]" value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $selected_features ) ); ?>>
										<span><?php echo esc_html( ! empty( $label ) ? $label : $key ); ?></span>
									</label>
									<button type="button" class="button-link remove-custom-feature" data-key="<?php echo esc_attr( $key ); ?>">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Custom Features Header (Optional) -->
						<h4 class="mhm-custom-section-header"><?php echo esc_html__( 'Custom Features', 'mhm-rentiva' ); ?></h4>
						<div class="mhm-custom-list" id="custom-features-list">
							<?php foreach ( $custom_features as $key => $label ) : ?>
								<div class="mhm-custom-item mhm-custom-feature-item" data-key="<?php echo esc_attr( $key ); ?>">
									<span><?php echo esc_html( $label ); ?></span>
									<button type="button" class="button button-small remove-custom-feature" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Remove', 'mhm-rentiva' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Add New Custom Feature -->
						<div class="mhm-add-custom-wrapper">
							<div class="mhm-add-custom-row">
								<input type="text" id="new-custom-feature-name" placeholder="<?php esc_attr_e( 'Custom feature name', 'mhm-rentiva' ); ?>">
								<button type="button" id="add-custom-feature" class="button button-secondary"><?php esc_html_e( 'Add Custom', 'mhm-rentiva' ); ?></button>
							</div>
						</div>
					</div>

					<!-- Vehicle Equipment -->
					<div class="mhm-settings-card">
						<h2><?php echo esc_html__( 'Vehicle Equipment', 'mhm-rentiva' ); ?></h2>
						<p><?php echo esc_html__( 'Select the equipment you want to use', 'mhm-rentiva' ); ?></p>

						<!-- Standard Equipment -->
						<div class="mhm-card-section-header">
							<h4><?php echo esc_html__( 'Standard Equipment', 'mhm-rentiva' ); ?></h4>
							<div class="mhm-category-actions">
								<button type="button" id="select-all-equipment" class="button button-small"><?php esc_html_e( 'Select All', 'mhm-rentiva' ); ?></button>
								<button type="button" id="select-none-equipment" class="button button-small"><?php esc_html_e( 'Deselect All', 'mhm-rentiva' ); ?></button>
								<button type="button" id="rename-equipment" class="button button-small"><?php esc_html_e( 'Edit Names', 'mhm-rentiva' ); ?></button>
							</div>
						</div>
						<div class="mhm-checkbox-list">
							<?php
                            foreach ( $all_equipment as $key => $label ) :
								if ( in_array( $key, $custom_equipment_keys, true ) ) {
									continue;
								}
								?>
								<div class="mhm-checkbox-item mhm-removable-item mhm-custom-row-item">
									<label class="mhm-checkbox-label">
										<input type="checkbox" name="selected_equipment[]" value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $selected_equipment ) ); ?>>
										<span><?php echo esc_html( ! empty( $label ) ? $label : $key ); ?></span>
									</label>
									<button type="button" class="button-link remove-custom-equipment" data-key="<?php echo esc_attr( $key ); ?>">&times;</button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Custom Equipment -->
						<h4 class="mhm-custom-section-header"><?php echo esc_html__( 'Custom Equipment', 'mhm-rentiva' ); ?></h4>
						<div class="mhm-custom-list" id="custom-equipment-list">
							<?php foreach ( $custom_equipment as $key => $label ) : ?>
								<div class="mhm-custom-item mhm-custom-equipment-item" data-key="<?php echo esc_attr( $key ); ?>">
									<span><?php echo esc_html( $label ); ?></span>
									<button type="button" class="button button-small remove-custom-equipment" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Remove', 'mhm-rentiva' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Add New Custom Equipment -->
						<div class="mhm-add-custom-wrapper">
							<div class="mhm-add-custom-row">
								<input type="text" id="new-custom-equipment-name" placeholder="<?php esc_attr_e( 'Custom equipment name', 'mhm-rentiva' ); ?>">
								<button type="button" id="add-custom-equipment" class="button button-secondary"><?php esc_html_e( 'Add Custom', 'mhm-rentiva' ); ?></button>
							</div>
						</div>
					</div>

				</div>

				<div class="mhm-settings-footer-actions">
					<input type="hidden" name="action" value="mhmrentiva_save_vehicle_settings">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'vehicle_settings_nonce' ) ); ?>">
					<button type="submit" id="save-settings" class="button button-primary button-large"><?php echo esc_html__( 'Save Settings', 'mhm-rentiva' ); ?></button>
				</div>
			</div>
		</form>

		<?php
		// Scripts only below
		?>


		<?php
	}

	/**
	 * Helper: Render Sortable List Item
	 */
	private static function render_card_field_list_item( string $type, string $key, string $label, bool $selected ): string {
		$type  = sanitize_key( $type );
		$key   = sanitize_key( $key );
		$label = esc_html( $label );

		$remove_button = $selected
			? '<button type="button" class="button-link remove-field" aria-label="' . esc_attr__( 'Remove item', 'mhm-rentiva' ) . '">&times;</button>'
			: '';

		$class = 'mhm-card-field-item';
		if ( $selected ) {
			$class .= ' selected';
		}

		$drag_handle = '<span class="mhm-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>';

		return sprintf(
			'<li class="%5$s" data-field-type="%1$s" data-field-key="%2$s">%6$s<span class="mhm-card-field-label">%3$s</span>%4$s</li>',
			esc_attr( $type ),
			esc_attr( $key ),
			$label,
			$remove_button,
			$class,
			$drag_handle
		);
	}

	/**
	 * Helper: Get Available Fields for Comparison Table
	 * Consolidates Standard, Taxonomy, and Custom fields.
	 */
	private static function get_comparison_available_fields(): array {
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
	public static function get_default_features(): array {
		return array(
			'air_conditioning' => __( 'Air Conditioning', 'mhm-rentiva' ),
			'power_steering'   => __( 'Power Steering', 'mhm-rentiva' ),
			'abs_brakes'       => __( 'ABS Brakes', 'mhm-rentiva' ),
			'airbags'          => __( 'Airbags', 'mhm-rentiva' ),
			'central_locking'  => __( 'Central Locking', 'mhm-rentiva' ),
			'electric_windows' => __( 'Electric Windows', 'mhm-rentiva' ),
			'power_mirrors'    => __( 'Power Mirrors', 'mhm-rentiva' ),
			'fog_lights'       => __( 'Fog Lights', 'mhm-rentiva' ),
			'cruise_control'   => __( 'Cruise Control', 'mhm-rentiva' ),
			'bluetooth'        => __( 'Bluetooth', 'mhm-rentiva' ),
			'usb_port'         => __( 'USB Port', 'mhm-rentiva' ),
			'navigation'       => __( 'Navigation', 'mhm-rentiva' ),
			'sunroof'          => __( 'Sunroof', 'mhm-rentiva' ),
			'leather_seats'    => __( 'Leather Seats', 'mhm-rentiva' ),
			'heated_seats'     => __( 'Heated Seats', 'mhm-rentiva' ),
		);
	}

	/**
	 * Default equipment
	 */
	public static function get_default_equipment(): array {
		return array(
			'spare_tire'        => __( 'Spare Tire', 'mhm-rentiva' ),
			'jack'              => __( 'Jack', 'mhm-rentiva' ),
			'first_aid_kit'     => __( 'First Aid Kit', 'mhm-rentiva' ),
			'fire_extinguisher' => __( 'Fire Extinguisher', 'mhm-rentiva' ),
			'warning_triangle'  => __( 'Warning Triangle', 'mhm-rentiva' ),
			'jumper_cables'     => __( 'Jumper Cables', 'mhm-rentiva' ),
			'ice_scraper'       => __( 'Ice Scraper', 'mhm-rentiva' ),
			'car_cover'         => __( 'Car Cover', 'mhm-rentiva' ),
			'child_seat'        => __( 'Child Seat', 'mhm-rentiva' ),
			'gps_tracker'       => __( 'GPS Tracker', 'mhm-rentiva' ),
			'dashcam'           => __( 'Dashcam', 'mhm-rentiva' ),
			'phone_holder'      => __( 'Phone Holder', 'mhm-rentiva' ),
			'charger'           => __( 'Charger', 'mhm-rentiva' ),
			'cleaning_kit'      => __( 'Cleaning Kit', 'mhm-rentiva' ),
			'emergency_kit'     => __( 'Emergency Kit', 'mhm-rentiva' ),
		);
	}

	/**
	 * Default details
	 */
	public static function get_default_details(): array {
		return array(
			'price_per_day' => __( 'Daily Price', 'mhm-rentiva' ),
			'year'          => __( 'Year', 'mhm-rentiva' ),
			'mileage'       => __( 'Mileage', 'mhm-rentiva' ),
			'license_plate' => __( 'License Plate', 'mhm-rentiva' ),
			'color'         => __( 'Color', 'mhm-rentiva' ),
			'brand'         => __( 'Brand', 'mhm-rentiva' ),
			'model'         => __( 'Model', 'mhm-rentiva' ),
			'seats'         => __( 'Seats', 'mhm-rentiva' ),
			'doors'         => __( 'Doors', 'mhm-rentiva' ),
			'transmission'  => __( 'Transmission', 'mhm-rentiva' ),
			'fuel_type'     => __( 'Fuel Type', 'mhm-rentiva' ),
			'engine_size'   => __( 'Engine Size', 'mhm-rentiva' ),
			'deposit'       => __( 'Deposit', 'mhm-rentiva' ),
			'availability'  => __( 'Availability', 'mhm-rentiva' ),
		);
	}

	/**
	 * Default selected details (checkbox states)
	 */
	public static function get_default_selected_details(): array {
		return array( 'fuel_type', 'transmission', 'seats', 'mileage', 'year', 'deposit' );
	}

	/**
	 * Default selected features (checkbox states)
	 */
	public static function get_default_selected_features(): array {
		return array( 'abs_brakes', 'air_conditioning', 'central_locking' );
	}

	/**
	 * Default selected equipment (checkbox states)
	 */
	public static function get_default_selected_equipment(): array {
		return array( 'gps_tracker', 'child_seat' );
	}

	/**
	 * Get taxonomy features
	 */
	public static function get_taxonomy_features(): array {
		$taxonomy_features = array();
		$taxonomies        = array( 'mhmrentiva_feature', 'vehicle_feature', 'vehicle_features' );
		foreach ( $taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $tax,
						'hide_empty' => false,
					)
				);
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						// Use 'tax_' prefix to identify taxonomy terms and avoid collisions
						// Also include taxonomy slug to ensure uniqueness across taxonomies
						$key                       = 'tax_' . $tax . '_' . $term->slug;
						$taxonomy_features[ $key ] = $term->name;
					}
				}
			}
		}
		return $taxonomy_features;
	}

	/**
	 * Get taxonomy equipment
	 */
	public static function get_taxonomy_equipment(): array {
		$taxonomy_equipment = array();
		$taxonomies         = array( 'mhmrentiva_equipment', 'vehicle_equipment' );
		foreach ( $taxonomies as $tax ) {
			if ( taxonomy_exists( $tax ) ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $tax,
						'hide_empty' => false,
					)
				);
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$key                        = 'tax_' . $tax . '_' . $term->slug;
						$taxonomy_equipment[ $key ] = $term->name;
					}
				}
			}
		}
		return $taxonomy_equipment;
	}

	/**
	 * Get all available details (standard + custom)
	 */
	public static function get_all_available_details(): array {
		$details        = self::get_default_details();
		$stored_details = (array) get_option( 'mhmrentiva_vehicle_details', array() );
		foreach ( $stored_details as $key => $label ) {
			if ( ! empty( $label ) ) {
				$details[ $key ] = $label;
			}
		}

		$custom_details = (array) get_option( 'mhmrentiva_custom_details', array() );
		foreach ( $custom_details as $key => $label ) {
			if ( ! empty( $label ) ) {
				$details[ $key ] = $label;
			}
		}

		return $details;
	}

	/**
	 * Get all available features (standard + custom + taxonomy)
	 */
	public static function get_all_available_features(): array {
		$features        = self::get_default_features();
		$stored_features = (array) get_option( 'mhmrentiva_vehicle_features', array() );
		foreach ( $stored_features as $key => $label ) {
			if ( ! empty( $label ) ) {
				$features[ $key ] = $label;
			}
		}

		$custom_features = (array) get_option( 'mhmrentiva_custom_features', array() );
		foreach ( $custom_features as $key => $label ) {
			if ( ! empty( $label ) ) {
				$features[ $key ] = $label;
			}
		}

		$taxonomy_features = self::get_taxonomy_features();
		return array_merge( $features, $taxonomy_features );
	}

	/**
	 * Get all available equipment (standard + custom + taxonomy)
	 */
	public static function get_all_available_equipment(): array {
		$equipment        = self::get_default_equipment();
		$stored_equipment = (array) get_option( 'mhmrentiva_vehicle_equipment', array() );
		foreach ( $stored_equipment as $key => $label ) {
			if ( ! empty( $label ) ) {
				$equipment[ $key ] = $label;
			}
		}

		$custom_equipment = (array) get_option( 'mhmrentiva_custom_equipment', array() );
		foreach ( $custom_equipment as $key => $label ) {
			if ( ! empty( $label ) ) {
				$equipment[ $key ] = $label;
			}
		}

		$taxonomy_equipment = self::get_taxonomy_equipment();
		return array_merge( $equipment, $taxonomy_equipment );
	}

	/**
	 * Map a singular field type to its plural option/category name.
	 */
	private static function type_to_category( string $type ): string {
		$map = array(
			'detail'    => 'details',
			'feature'   => 'features',
			'equipment' => 'equipment',
		);
		return $map[ $type ] ?? $type;
	}

	/**
	 * Convert a [{type,key}] selection into a list of "type:key" ids.
	 *
	 * @param array<int,array{type?:string,key?:string}> $selection
	 * @return string[]
	 */
	private static function selection_to_ids( array $selection ): array {
		$ids = array();
		foreach ( $selection as $item ) {
			if ( is_array( $item ) && isset( $item['type'], $item['key'] ) ) {
				$ids[] = sanitize_key( (string) $item['type'] ) . ':' . sanitize_key( (string) $item['key'] );
			}
		}
		return $ids;
	}

	/**
	 * Build the client-side state payload for the Vehicle Settings UI.
	 *
	 * @return array{fields:array<int,array>,cardOrder:string[],detailOrder:string[]}
	 */
	public static function build_settings_state(): array {
		$universes = array(
			'detail'    => self::get_all_available_details(),
			'feature'   => self::get_all_available_features(),
			'equipment' => self::get_all_available_equipment(),
		);

		// D5: taxonomy-backed keys are out of scope for this UI. Subtract them by key set,
		// not by string prefix.
		foreach ( array_keys( self::get_taxonomy_features() ) as $tax_key ) {
			unset( $universes['feature'][ $tax_key ] );
		}
		foreach ( array_keys( self::get_taxonomy_equipment() ) as $tax_key ) {
			unset( $universes['equipment'][ $tax_key ] );
		}

		$custom = array(
			'detail'    => (array) get_option( 'mhmrentiva_custom_details', array() ),
			'feature'   => (array) get_option( 'mhmrentiva_custom_features', array() ),
			'equipment' => (array) get_option( 'mhmrentiva_custom_equipment', array() ),
		);

		// Trap 4: REQUIRED rows are core keys that actually exist as detail fields.
		$core_keys = array_values( array_intersect(
			\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_core_fields(),
			array_keys( $universes['detail'] )
		) );

		$custom_meta = (array) get_option( 'mhmrentiva_custom_field_meta', array() );

		$card_ids   = self::selection_to_ids( \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_selected_card_fields() );
		$detail_ids = self::selection_to_ids( \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_selected_detail_fields() );

		// The comparison table renders exactly the keys stored in comparison_fields
		// (VehicleComparison::get_dynamic_features()); an empty set renders NO comparison rows, so
		// there is no "empty means all" default -- an empty selection means nothing compares.
		$settings   = (array) get_option( 'mhmrentiva_settings', array() );
		$comparison = ( isset( $settings['comparison_fields'] ) && is_array( $settings['comparison_fields'] ) )
			? $settings['comparison_fields']
			: array();

		$fields = array();
		foreach ( $universes as $type => $universe ) {
			$category = self::type_to_category( $type );
			foreach ( $universe as $key => $label ) {
				$key  = (string) $key;
				$id   = $type . ':' . $key;
				$core = ( 'detail' === $type && in_array( $key, $core_keys, true ) );

				$fields[] = array(
					'id'      => $id,
					'type'    => $type,
					'key'     => $key,
					'label'   => (string) $label,
					// Active = the field is in use, per the frontend render predicate (details:
					// core or selected; features/equipment: selected). Matches what actually renders.
					'enabled' => \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::is_field_active( $type, $key ),
					'core'    => $core,
					'custom'  => isset( $custom[ $type ][ $key ] ),
					'meta'    => ( 'detail' === $type && isset( $custom_meta[ $key ] ) && is_array( $custom_meta[ $key ] ) )
						? $custom_meta[ $key ]
						: null,
					'card'    => in_array( $id, $card_ids, true ),
					'detail'  => in_array( $id, $detail_ids, true ),
					'compare' => (
						isset( $comparison[ $category ] )
						&& is_array( $comparison[ $category ] )
						&& in_array( $key, array_map( 'strval', $comparison[ $category ] ), true )
					),
				);
			}
		}

		// Orders may legitimately reference ids we do not render (e.g. taxonomy selections
		// stored previously). Keep only ids that exist as rows so the UI cannot desync.
		$known        = array_column( $fields, 'id' );
		$filter_known = static function ( array $ids ) use ( $known ): array {
			return array_values( array_filter( $ids, static function ( $id ) use ( $known ) {
				return in_array( $id, $known, true );
			} ) );
		};

		// The single editing order (spec §6) is persisted explicitly so it round-trips exactly;
		// two filtered subsets (card/detail) cannot reconstruct one merged order. Empty until
		// the first v2 save -- the UI then derives one from card/detail order as a fallback.
		$stored_settings = (array) get_option( 'mhmrentiva_settings', array() );
		$matrix_order    = ( isset( $stored_settings['mhmrentiva_vehicle_matrix_order'] ) && is_array( $stored_settings['mhmrentiva_vehicle_matrix_order'] ) )
			? array_map( 'strval', $stored_settings['mhmrentiva_vehicle_matrix_order'] )
			: array();

		return array(
			'fields'      => $fields,
			'cardOrder'   => $filter_known( $card_ids ),
			'detailOrder' => $filter_known( $detail_ids ),
			'matrixOrder' => $filter_known( $matrix_order ),
		);
	}

	/**
	 * AJAX: Save settings
	 */
	public static function ajax_save_settings(): void {
		check_ajax_referer( 'vehicle_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission', 'mhm-rentiva' ) );
		}

		$req = VerifiedRequest::from( $_POST );

		$sub_action = $req->text( 'sub_action' );

		if ( 'save_display_settings' === $sub_action ) {
			self::save_display_payload( $req );
			wp_send_json_success( __( 'Display settings saved!', 'mhm-rentiva' ) );
			return;
		}

		if ( 'save_all' === $sub_action ) {
			// Definitions FIRST: save_display_payload() sanitises the card/detail selection against
			// get_available_fields_map(), which reflects the just-written selected sets, so a detail
			// removed here is dropped from the card/detail selection. Contract: save_all requires a
			// COMPLETE definitions payload -- save_definitions_payload() writes the selected_* options
			// unconditionally, so an omitted key is stored as an empty set (not "leave unchanged").
			// See the docblock on save_definitions_payload() for detail.
			self::save_definitions_payload( $req );
			self::save_display_payload( $req );
			wp_send_json_success( __( 'Settings saved!', 'mhm-rentiva' ) );
			return;
		}

		self::save_definitions_payload( $req );
		wp_send_json_success( __( 'Settings saved successfully!', 'mhm-rentiva' ) );
	}

	/**
	 * Persist the "Display" tab payload (card/detail/comparison selections + the editing order).
	 *
	 * Card/detail selections pass through VehicleFeatureHelper::sanitize_card_field_selection(),
	 * which drops any entry not in get_available_fields_map() -- the same availability truth the
	 * frontend renders from. Comparison is stored as submitted (the frontend gates Passive keys at
	 * render, VehicleComparison::flatten_gated_selected_keys()), so a Passive-field compare flag
	 * may persist as harmless storage cruft. The matrix order (mhmrentiva_vehicle_matrix_order)
	 * is stored as the id list so the single editing order round-trips exactly (spec §6).
	 */
	private static function save_display_payload( VerifiedRequest $req ): void {
		$settings         = get_option( 'mhmrentiva_settings', array() );
		$settings_updated = false;

		// Save Card Fields
		if ( $req->has( 'mhmrentiva_vehicle_card_fields' ) ) {
			// It comes as a JSON string from the hidden input
			$json_value = $req->text( 'mhmrentiva_vehicle_card_fields' );
			$decoded    = json_decode( $json_value, true );

			// Validate structure. sanitize_card_field_selection() gates every entry through
			// get_available_fields_map() -- the same availability truth the frontend renders from --
			// so an unavailable (disabled) detail is dropped here, while features/equipment pass.
			if ( is_array( $decoded ) ) {
				$settings['mhmrentiva_vehicle_card_fields'] = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::sanitize_card_field_selection( $decoded );
				$settings_updated                           = true;
			}
		}
		// If the field is not present in $_POST we leave the existing setting in place.
		// JS submits "[]" for an explicit empty selection; missing field means "no change".

		// Save Vehicle Detail Highlighted Fields
		if ( $req->has( 'mhmrentiva_vehicle_detail_fields' ) ) {
			$json_value = $req->text( 'mhmrentiva_vehicle_detail_fields' );
			$decoded    = json_decode( $json_value, true );

			if ( is_array( $decoded ) ) {
				$settings['mhmrentiva_vehicle_detail_fields'] = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::sanitize_card_field_selection( $decoded );
				$settings_updated                             = true;
			}
		}

		// Save Comparison Fields
		// Note: checkboxes are not sent if unchecked. So we must handle "not set" as "empty" if we know we are in this context.
		$comparison_fields    = $req->arr( 'comparison_fields' );
		$sanitized_comparison = array();

		foreach ( $comparison_fields as $cat => $fields ) {
			if ( is_array( $fields ) ) {
				$sanitized_comparison[ $cat ] = array_map( 'sanitize_text_field', $fields );
			}
		}

		// Store the submitted comparison selection as-is (the admin UI only offers available fields,
		// and the frontend comparison table renders exactly what is stored). Empty = nothing compares.
		$settings['comparison_fields'] = $sanitized_comparison;
		$settings_updated              = true;

		// Persist the single editing order (spec §6) as a "type:key" id list, so it round-trips
		// exactly instead of being re-inferred from the card/detail subsets.
		if ( $req->has( 'mhmrentiva_vehicle_matrix_order' ) ) {
			$decoded_order = json_decode( $req->text( 'mhmrentiva_vehicle_matrix_order' ), true );
			if ( is_array( $decoded_order ) ) {
				$settings['mhmrentiva_vehicle_matrix_order'] = array_values( array_filter( array_map(
					static function ( $id ) {
						return is_string( $id ) ? preg_replace( '/[^a-z0-9_:]/', '', $id ) : '';
					},
					$decoded_order
				) ) );
			}
		}

		if ( $settings_updated ) {
			update_option( 'mhmrentiva_settings', $settings );
		}
	}

	/**
	 * Persist the "Definitions" tab payload (which fields exist / are enabled + custom fields).
	 *
	 * Contract: the three `selected_*` writes below (`mhmrentiva_selected_details/features/equipment`)
	 * are UNCONDITIONAL -- each is taken from `post_array()`, which yields `array()` when the
	 * corresponding POST key is absent, unlike the `isset()`-guarded `custom_*` writes further
	 * down. A caller that invokes `sub_action=save_all` MUST submit a complete definitions
	 * payload (all three `selected_*` arrays), because an omitted key clears that set; and
	 * `save_all` runs this method before `save_display_payload()` so that the card/detail
	 * sanitisation there sees the freshly-written availability (a detail removed here is then
	 * dropped from the card/detail selection by sanitize_card_field_selection()).
	 */
	private static function save_definitions_payload( VerifiedRequest $req ): void {
		// Every payload below goes through sanitize_array_option(), which cleans the
		// array KEY with sanitize_key() as well as the value -- array_map() only ever
		// reached the values, so a submitted key such as `<script>k</script>` used to
		// reach update_option() raw. The same helper is the registered
		// sanitize_callback for these six options, so the write path and the option
		// filter now normalize identically instead of one relying on the other.
		// Save selected fields (Definitions Tab)
		$selected_details = self::sanitize_array_option( $req->arr( 'selected_details' ) );
		// Core fields are always selected - enforce even if disabled checkboxes weren't
		// submitted. Intersected with the detail universe first, the same way the field
		// map does at the read end ("Trap 4" there): get_core_fields() names keys that are
		// NOT detail fields -- 'image' and 'gallery_images' are handled by their own meta
		// boxes and have no entry in get_all_available_details(). Injecting them here put
		// two keys into mhmrentiva_selected_details that the detail grid then rendered as
		// empty, untranslated "Image" / "Gallery Images" boxes on every vehicle screen.
		// The read end was already guarded; only this write end was not.
		$core_fields_list = array_intersect(
			\MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_core_fields(),
			array_keys( self::get_all_available_details() )
		);
		foreach ( $core_fields_list as $core_key ) {
			if ( ! in_array( $core_key, $selected_details, true ) ) {
				$selected_details[] = $core_key;
			}
		}
		$selected_features  = self::sanitize_array_option( $req->arr( 'selected_features' ) );
		$selected_equipment = self::sanitize_array_option( $req->arr( 'selected_equipment' ) );

		// Save custom fields
		$custom_details   = self::sanitize_array_option( $req->arr( 'custom_details' ) );
		$custom_features  = self::sanitize_array_option( $req->arr( 'custom_features' ) );
		$custom_equipment = self::sanitize_array_option( $req->arr( 'custom_equipment' ) );

		// REMOVED destructive updated_labels logic.
		// Renaming is handled by the dedicated ajax_update_field_labels method.

		// Save to database
		update_option( 'mhmrentiva_selected_details', $selected_details );
		update_option( 'mhmrentiva_selected_features', $selected_features );
		update_option( 'mhmrentiva_selected_equipment', $selected_equipment );

		// FIXED: Only update custom fields if they were actually sent in the POST.
		// Usually custom fields are only managed via the specific Add/Remove AJAX calls.
		if ( $req->has( 'custom_details' ) ) {
			update_option( 'mhmrentiva_custom_details', $custom_details );
		}
		if ( $req->has( 'custom_features' ) ) {
			update_option( 'mhmrentiva_custom_features', $custom_features );
		}
		if ( $req->has( 'custom_equipment' ) ) {
			update_option( 'mhmrentiva_custom_equipment', $custom_equipment );
		}
	}

	/**
	 * AJAX: Update field names
	 */
	public static function ajax_update_field_labels(): void {
		check_ajax_referer( 'vehicle_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission', 'mhm-rentiva' ) );
		}

		$req = VerifiedRequest::from( $_POST );

		$type = $req->text( 'type' );

		// Same key+value sanitizer the definitions payload and the registered
		// sanitize_callback use: the KEY is an internal field slug, so sanitize_key()
		// both closes the injection vector and normalizes the spelling the isset()
		// lookups below match on.
		$sanitized_labels = array();
		foreach ( self::sanitize_array_option( $req->arr( 'labels' ) ) as $key => $label ) {
			// Encoding fix - For Turkish characters
			$sanitized_labels[ $key ] = mb_convert_encoding( $label, 'UTF-8', 'auto' );
		}

		self::apply_label_updates( $type, $sanitized_labels );

		wp_send_json_success( __( 'Field names updated successfully!', 'mhm-rentiva' ) );
	}

	/**
	 * Persist edited field labels under the canonical storage rule: the
	 * `mhmrentiva_vehicle_*` options hold ONLY user-renamed overrides.
	 *
	 * The Edit Names modal posts every rendered label, and the rendered
	 * defaults are `__()` output in the admin's session locale. The previous
	 * implementation seeded the whole translated map into the option on first
	 * save, freezing that locale into the database (the v4.27.1 locale-leakage
	 * bug, reopened through this path). A submitted label equal to its
	 * translatable default therefore stores nothing — and removes a stale
	 * override, so typing the default back in un-freezes the field.
	 *
	 * @param string                $type             'details' | 'features' | 'equipment'.
	 * @param array<string, string> $sanitized_labels Field key => submitted label.
	 */
	public static function apply_label_updates( string $type, array $sanitized_labels ): void {
		$defaults_by_type = array(
			'details'   => array( self::class, 'get_default_details' ),
			'features'  => array( self::class, 'get_default_features' ),
			'equipment' => array( self::class, 'get_default_equipment' ),
		);
		if ( ! isset( $defaults_by_type[ $type ] ) ) {
			return;
		}

		$defaults           = call_user_func( $defaults_by_type[ $type ] );
		$original_overrides = (array) get_option( 'mhmrentiva_vehicle_' . $type, array() );
		$original_customs   = (array) get_option( 'mhmrentiva_custom_' . $type, array() );
		$overrides          = $original_overrides;
		$customs            = $original_customs;

		foreach ( $sanitized_labels as $key => $new_label ) {
			if ( isset( $defaults[ $key ] ) ) {
				if ( $new_label === $defaults[ $key ] || '' === $new_label ) {
					unset( $overrides[ $key ] );
				} else {
					$overrides[ $key ] = $new_label;
				}
			} elseif ( isset( $customs[ $key ] ) ) {
				$customs[ $key ] = $new_label;
			}
		}

		if ( $overrides !== $original_overrides ) {
			update_option( 'mhmrentiva_vehicle_' . $type, $overrides );
		}
		if ( $customs !== $original_customs ) {
			update_option( 'mhmrentiva_custom_' . $type, $customs );
		}
	}

	/**
	 * Remove custom field
	 */
	public static function ajax_remove_custom_field(): void {
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'vehicle_settings_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', 'mhm-rentiva' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission', 'mhm-rentiva' ) );
			return;
		}

		$req = VerifiedRequest::from( $_POST );

		$field_key  = $req->text( 'field_key' );
		$field_type = $req->text( 'field_type' ); // details, features, equipment

		if ( $field_type === 'details' ) {
			// 1. Check if Core (Cannot remove)
			$core_fields = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_core_fields();
			if ( in_array( $field_key, $core_fields ) ) {
				wp_send_json_error( __( 'This is a core field and cannot be removed.', 'mhm-rentiva' ) );
				return;
			}

			// 2. Try removing from Standard Details
			// Overrides-only map (canonical rule): seeding the translated
			// defaults here froze the session locale into the option.
			$current_details = get_option( 'mhmrentiva_vehicle_details', array() );
			if ( isset( $current_details[ $field_key ] ) ) {
				unset( $current_details[ $field_key ] );
				update_option( 'mhmrentiva_vehicle_details', $current_details );
				wp_send_json_success( __( 'Field removed successfully.', 'mhm-rentiva' ) );
				return;
			}

			// 3. Try removing from Custom Details
			$custom_details = get_option( 'mhmrentiva_custom_details', array() );
			if ( isset( $custom_details[ $field_key ] ) ) {
				unset( $custom_details[ $field_key ] );
				update_option( 'mhmrentiva_custom_details', $custom_details );

				// Clean related post meta
				global $wpdb;
				$wpdb->delete(
					$wpdb->postmeta,
					array(
						'meta_key' => '_mhmrentiva_' . $field_key,
					)
				);

				// Clean the custom-field meta (type/options) so it does not orphan.
				$field_meta = get_option( 'mhmrentiva_custom_field_meta', array() );
				if ( isset( $field_meta[ $field_key ] ) ) {
					unset( $field_meta[ $field_key ] );
					update_option( 'mhmrentiva_custom_field_meta', $field_meta );
				}

				wp_send_json_success( __( 'Custom detail removed successfully', 'mhm-rentiva' ) );
			} else {
				wp_send_json_error( 'Field not found' );
			}
		} elseif ( $field_type === 'features' ) {
			// 1. Try removing from Standard Features
			$current_features = get_option( 'mhmrentiva_vehicle_features', array() );
			if ( isset( $current_features[ $field_key ] ) ) {
				unset( $current_features[ $field_key ] );
				update_option( 'mhmrentiva_vehicle_features', $current_features );
				wp_send_json_success( __( 'Feature removed successfully', 'mhm-rentiva' ) );
				return;
			}

			// 2. Try removing from Custom Features
			$custom_features = get_option( 'mhmrentiva_custom_features', array() );
			if ( isset( $custom_features[ $field_key ] ) ) {
				unset( $custom_features[ $field_key ] );
				update_option( 'mhmrentiva_custom_features', $custom_features );

				// Clean related post meta
				global $wpdb;
				$wpdb->delete(
					$wpdb->postmeta,
					array(
						'meta_key' => '_mhmrentiva_' . $field_key,
					)
				);

				wp_send_json_success( __( 'Custom feature removed successfully', 'mhm-rentiva' ) );
			} else {
				wp_send_json_error( 'Feature not found' );
			}
		} elseif ( $field_type === 'equipment' ) {
			// 1. Try removing from Standard Equipment
			$current_equipment = get_option( 'mhmrentiva_vehicle_equipment', array() );
			if ( isset( $current_equipment[ $field_key ] ) ) {
				unset( $current_equipment[ $field_key ] );
				update_option( 'mhmrentiva_vehicle_equipment', $current_equipment );
				wp_send_json_success( __( 'Equipment removed successfully', 'mhm-rentiva' ) );
				return;
			}

			// 2. Try removing from Custom Equipment
			$custom_equipment = get_option( 'mhmrentiva_custom_equipment', array() );
			if ( isset( $custom_equipment[ $field_key ] ) ) {
				unset( $custom_equipment[ $field_key ] );
				update_option( 'mhmrentiva_custom_equipment', $custom_equipment );

				// Clean related post meta
				global $wpdb;
				$wpdb->delete(
					$wpdb->postmeta,
					array(
						'meta_key' => '_mhmrentiva_' . $field_key,
					)
				);

				wp_send_json_success( __( 'Custom equipment removed successfully', 'mhm-rentiva' ) );
			} else {
				wp_send_json_error( 'Equipment not found' );
			}
		} else {
			wp_send_json_error( 'Invalid field type' );
		}
	}

	/**
	 * Add custom field
	 */
	public static function ajax_add_custom_field(): void {
		// Nonce check
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'vehicle_settings_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed', 'mhm-rentiva' ) );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission', 'mhm-rentiva' ) );
			return;
		}

		$req = VerifiedRequest::from( $_POST );

		// Key always generated server-side — never trust client-provided keys
		$field_key   = 'custom_' . time() . '_' . wp_rand( 1000, 9999 );
		$field_label = $req->text( 'field_label' );
		$field_type  = $req->text( 'field_type' ); // details, features, equipment

		// Encoding fix - For Turkish characters
		$field_label = mb_convert_encoding( $field_label, 'UTF-8', 'auto' );

		if ( 'details' === $field_type ) {
			$custom_details               = get_option( 'mhmrentiva_custom_details', array() );
			$custom_details[ $field_key ] = $field_label;
			update_option( 'mhmrentiva_custom_details', $custom_details );

			// Save extended meta (Type & Options)
			if ( '' !== $req->text( 'type' ) ) {
				$field_meta               = get_option( 'mhmrentiva_custom_field_meta', array() );
				$field_meta[ $field_key ] = array(
					'type'    => $req->text( 'type' ),
					'options' => $req->text( 'options' ),
				);
				update_option( 'mhmrentiva_custom_field_meta', $field_meta );
			}

			wp_send_json_success( array(
				'key'     => $field_key,
				'message' => esc_html__( 'Custom detail added successfully', 'mhm-rentiva' ),
			) );
		} elseif ( 'features' === $field_type ) {
			$custom_features               = get_option( 'mhmrentiva_custom_features', array() );
			$custom_features[ $field_key ] = $field_label;
			update_option( 'mhmrentiva_custom_features', $custom_features );

			wp_send_json_success( array(
				'key'     => $field_key,
				'message' => esc_html__( 'Custom feature added successfully', 'mhm-rentiva' ),
			) );
		} elseif ( 'equipment' === $field_type ) {
			$custom_equipment               = get_option( 'mhmrentiva_custom_equipment', array() );
			$custom_equipment[ $field_key ] = $field_label;
			update_option( 'mhmrentiva_custom_equipment', $custom_equipment );

			wp_send_json_success( array(
				'key'     => $field_key,
				'message' => esc_html__( 'Custom equipment added successfully', 'mhm-rentiva' ),
			) );
		} else {
			wp_send_json_error( esc_html__( 'Invalid field type', 'mhm-rentiva' ) );
		}
	}

	/**
	 * AJAX Reset Settings
	 */
	public static function ajax_reset_settings(): void {
		check_ajax_referer( 'vehicle_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'mhm-rentiva' ) ) );
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'definitions';

		if ( $tab === 'display' ) {
			// Reset Display Options to empty arrays
			$settings                                     = get_option( 'mhmrentiva_settings', array() );
			$settings['mhmrentiva_vehicle_card_fields']   = array();
			$settings['mhmrentiva_vehicle_detail_fields'] = array();
			$settings['comparison_fields']                = array();
			update_option( 'mhmrentiva_settings', $settings );
		} else {
			// Reset Selection Options (Checkboxes) to default values (Definitions Tab)
			update_option( 'mhmrentiva_selected_details', self::get_default_selected_details() );
			update_option( 'mhmrentiva_selected_features', self::get_default_selected_features() );
			update_option( 'mhmrentiva_selected_equipment', self::get_default_selected_equipment() );
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Settings reset to defaults.', 'mhm-rentiva' ) ) );
	}
}
