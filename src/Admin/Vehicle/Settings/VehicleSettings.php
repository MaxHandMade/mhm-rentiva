<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Settings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ VEHICLE SETTINGS - Vehicle Features and Equipment Management
 * 
 * Manage vehicle features and equipment in admin panel
 */
final class VehicleSettings
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

    public static function register(): void
    {
        // Menu registration is now done centrally in Menu.php
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('wp_ajax_add_vehicle_feature', [self::class, 'ajax_add_feature']);
        add_action('wp_ajax_remove_vehicle_feature', [self::class, 'ajax_remove_feature']);
        add_action('wp_ajax_add_vehicle_equipment', [self::class, 'ajax_add_equipment']);
        add_action('wp_ajax_remove_vehicle_equipment', [self::class, 'ajax_remove_equipment']);
        add_action('wp_ajax_add_vehicle_detail', [self::class, 'ajax_add_detail']);
        add_action('wp_ajax_remove_vehicle_detail', [self::class, 'ajax_remove_detail']);
        add_action('wp_ajax_save_vehicle_settings', [self::class, 'ajax_save_settings']);
        add_action('wp_ajax_update_field_labels', [self::class, 'ajax_update_field_labels']);
        add_action('wp_ajax_remove_custom_field', [self::class, 'ajax_remove_custom_field']);
        add_action('wp_ajax_add_custom_field', [self::class, 'ajax_add_custom_field']);
        
        // ✅ Take responsibility for global setting updates from VehicleMeta
        add_action('save_post_vehicle', [self::class, 'update_global_vehicle_settings'], 10, 2);
    }

    /**
     * ✅ Take responsibility for global setting updates from VehicleMeta
     */
    public static function update_global_vehicle_settings(int $post_id, \WP_Post $post): void
    {
        // Nonce check
        if (!isset($_POST['mhm_rentiva_vehicle_meta_nonce']) || 
            !wp_verify_nonce($_POST['mhm_rentiva_vehicle_meta_nonce'], 'mhm_rentiva_vehicle_meta_action')) {
            return;
        }

        // Permission check
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Autosave and revision check
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Check custom details and update global settings
        $legacy_custom_details = isset($_POST['mhm_rentiva_custom_details']) ? $_POST['mhm_rentiva_custom_details'] : [];
        
        if (!empty($legacy_custom_details) && is_array($legacy_custom_details)) {
            $available_details = get_option('mhm_vehicle_details', []);
            $option_updated = false;
            
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
            [self::class, 'render_settings_page']
        );
    }

    /**
     * Save settings
     */
    public static function register_settings(): void
    {
        // Selected fields (checkbox states)
        register_setting('mhm_vehicle_settings', 'mhm_selected_details');
        register_setting('mhm_vehicle_settings', 'mhm_selected_features');
        register_setting('mhm_vehicle_settings', 'mhm_selected_equipment');
        
        // Custom fields
        register_setting('mhm_vehicle_settings', 'mhm_custom_details');
        register_setting('mhm_vehicle_settings', 'mhm_custom_features');
        register_setting('mhm_vehicle_settings', 'mhm_custom_equipment');
    }

    /**
     * Render settings page
     */
    public static function render_settings_page(): void
    {
        // Get selected fields (checkbox states)
        $selected_details = get_option('mhm_selected_details', self::get_default_selected_details());
        $selected_features = get_option('mhm_selected_features', self::get_default_selected_features());
        $selected_equipment = get_option('mhm_selected_equipment', self::get_default_selected_equipment());
        
        // Get custom fields
        $custom_details = get_option('mhm_custom_details', []);
        $custom_features = get_option('mhm_custom_features', []);
        $custom_equipment = get_option('mhm_custom_equipment', []);
        
        // Get all existing fields (standard + custom)
        $all_details = self::get_all_available_details();
        $all_features = self::get_all_available_features();
        $all_equipment = self::get_all_available_equipment();
        
        ?>
        <form method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" id="vehicle-settings-form">
        <div class="wrap">
            <h1><?php echo esc_html__('Vehicle Settings', 'mhm-rentiva'); ?></h1>
                <p class="description"><?php echo esc_html__('Select fields to use on vehicles. You can also add custom fields.', 'mhm-rentiva'); ?></p>
            
            <div class="mhm-settings-container" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">
                
                <!-- Vehicle Details -->
                <div class="mhm-settings-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2><?php echo esc_html__('Vehicle Details', 'mhm-rentiva'); ?></h2>
                    <p><?php echo esc_html__('Select the details you want to use', 'mhm-rentiva'); ?></p>
                    
                    <!-- Standard Details -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;"><?php echo esc_html__('Standard Details', 'mhm-rentiva'); ?></h4>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="select-all-details" class="button button-small"><?php esc_html_e('Select All', 'mhm-rentiva'); ?></button>
                            <button type="button" id="select-none-details" class="button button-small"><?php esc_html_e('Deselect All', 'mhm-rentiva'); ?></button>
                            <button type="button" id="rename-details" class="button button-small"><?php esc_html_e('Edit Names', 'mhm-rentiva'); ?></button>
                            </div>
                    </div>
                    <div class="mhm-checkbox-list" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 10px;">
                        <?php foreach ($all_details as $key => $label): ?>
                            <label class="mhm-checkbox-item" style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                                <input type="checkbox" name="selected_details[]" value="<?php echo esc_attr($key); ?>" 
                                       <?php checked(in_array($key, $selected_details)); ?> style="margin: 0;">
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Custom Details -->
                    <h4 style="margin-top: 20px;"><?php echo esc_html__('Custom Details', 'mhm-rentiva'); ?></h4>
                    <div class="mhm-custom-list" id="custom-details-list" style="margin-top: 10px;">
                        <?php foreach ($custom_details as $key => $label): ?>
                            <div class="mhm-custom-item" data-key="<?php echo esc_attr($key); ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #fff3cd; border-radius: 4px; margin: 5px 0;">
                                <span><?php echo esc_html($label); ?></span>
                                <button type="button" class="button button-small remove-custom-detail" data-key="<?php echo esc_attr($key); ?>"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Add New Custom Detail -->
                    <div style="margin-top: 15px;">
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="new-custom-detail-name" placeholder="<?php esc_attr_e('Custom detail name', 'mhm-rentiva'); ?>" style="width: 250px;">
                            <button type="button" id="add-custom-detail" class="button button-secondary"><?php esc_html_e('Add Custom', 'mhm-rentiva'); ?></button>
                        </div>
                    </div>
                </div>
                
                <!-- Vehicle Features -->
                <div class="mhm-settings-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2><?php echo esc_html__('Vehicle Features', 'mhm-rentiva'); ?></h2>
                    <p><?php echo esc_html__('Select the features you want to use', 'mhm-rentiva'); ?></p>
                    
                    <!-- Standard Features -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;"><?php echo esc_html__('Standard Features', 'mhm-rentiva'); ?></h4>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="select-all-features" class="button button-small"><?php esc_html_e('Select All', 'mhm-rentiva'); ?></button>
                            <button type="button" id="select-none-features" class="button button-small"><?php esc_html_e('Deselect All', 'mhm-rentiva'); ?></button>
                            <button type="button" id="rename-features" class="button button-small"><?php esc_html_e('Edit Names', 'mhm-rentiva'); ?></button>
                        </div>
                    </div>
                    <div class="mhm-checkbox-list" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 10px;">
                        <?php foreach ($all_features as $key => $label): ?>
                            <label class="mhm-checkbox-item" style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                                <input type="checkbox" name="selected_features[]" value="<?php echo esc_attr($key); ?>" 
                                       <?php checked(in_array($key, $selected_features)); ?> style="margin: 0;">
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Custom Features -->
                    <h4 style="margin-top: 20px;"><?php echo esc_html__('Custom Features', 'mhm-rentiva'); ?></h4>
                    <div class="mhm-custom-list" id="custom-features-list" style="margin-top: 10px;">
                        <?php foreach ($custom_features as $key => $label): ?>
                            <div class="mhm-custom-item" data-key="<?php echo esc_attr($key); ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #fff3cd; border-radius: 4px; margin: 5px 0;">
                                <span><?php echo esc_html($label); ?></span>
                                <button type="button" class="button button-small remove-custom-feature" data-key="<?php echo esc_attr($key); ?>"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Add New Custom Feature -->
                    <div style="margin-top: 15px;">
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="new-custom-feature-name" placeholder="<?php esc_attr_e('Custom feature name', 'mhm-rentiva'); ?>" style="width: 250px;">
                            <button type="button" id="add-custom-feature" class="button button-secondary"><?php esc_html_e('Add Custom', 'mhm-rentiva'); ?></button>
                        </div>
                    </div>
                </div>
                
                <!-- Vehicle Equipment -->
                <div class="mhm-settings-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <h2><?php echo esc_html__('Vehicle Equipment', 'mhm-rentiva'); ?></h2>
                    <p><?php echo esc_html__('Select the equipment you want to use', 'mhm-rentiva'); ?></p>
                    
                    <!-- Standard Equipment -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;"><?php echo esc_html__('Standard Equipment', 'mhm-rentiva'); ?></h4>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" id="select-all-equipment" class="button button-small"><?php esc_html_e('Select All', 'mhm-rentiva'); ?></button>
                            <button type="button" id="select-none-equipment" class="button button-small"><?php esc_html_e('Deselect All', 'mhm-rentiva'); ?></button>
                            <button type="button" id="rename-equipment" class="button button-small"><?php esc_html_e('Edit Names', 'mhm-rentiva'); ?></button>
                        </div>
                    </div>
                    <div class="mhm-checkbox-list" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 10px;">
                        <?php foreach ($all_equipment as $key => $label): ?>
                            <label class="mhm-checkbox-item" style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; cursor: pointer;">
                                <input type="checkbox" name="selected_equipment[]" value="<?php echo esc_attr($key); ?>" 
                                       <?php checked(in_array($key, $selected_equipment)); ?> style="margin: 0;">
                                <span><?php echo esc_html($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Custom Equipment -->
                    <h4 style="margin-top: 20px;"><?php echo esc_html__('Custom Equipment', 'mhm-rentiva'); ?></h4>
                    <div class="mhm-custom-list" id="custom-equipment-list" style="margin-top: 10px;">
                        <?php foreach ($custom_equipment as $key => $label): ?>
                            <div class="mhm-custom-item" data-key="<?php echo esc_attr($key); ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #fff3cd; border-radius: 4px; margin: 5px 0;">
                                <span><?php echo esc_html($label); ?></span>
                                <button type="button" class="button button-small remove-custom-equipment" data-key="<?php echo esc_attr($key); ?>"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Add New Custom Equipment -->
                    <div style="margin-top: 15px;">
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="new-custom-equipment-name" placeholder="<?php esc_attr_e('Custom equipment name', 'mhm-rentiva'); ?>" style="width: 250px;">
                            <button type="button" id="add-custom-equipment" class="button button-secondary"><?php esc_html_e('Add Custom', 'mhm-rentiva'); ?></button>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <div style="margin-top: 20px;">
                    <input type="hidden" name="action" value="save_vehicle_settings">
                    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vehicle_settings_nonce'); ?>">
                    <button type="submit" id="save-settings" class="button button-primary button-large"><?php echo esc_html__('Save Settings', 'mhm-rentiva'); ?></button>
            </div>
        </div>
        </form>
        
        <style>
        .mhm-settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        
        .mhm-settings-item:hover {
            background: #e9ecef;
        }
        
        .remove-feature, .remove-equipment {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .remove-feature:hover, .remove-equipment:hover, .remove-detail:hover {
            background: #c82333;
        }
        
        /* Responsive Grid */
        @media (max-width: 1200px) {
            .mhm-settings-container {
                grid-template-columns: 1fr 1fr !important;
            }
            .mhm-features-list, .mhm-equipment-list {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .mhm-details-list {
                grid-template-columns: 1fr !important;
            }
        }
        
        @media (max-width: 768px) {
            .mhm-settings-container {
                grid-template-columns: 1fr !important;
            }
            
            .mhm-features-list, .mhm-equipment-list, .mhm-details-list {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Define AJAX URL
            var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            
            // Custom Detail Addition
            $('#add-custom-detail').on('click', function() {
                const name = $('#new-custom-detail-name').val().trim();
                
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
                            nonce: '<?php echo wp_create_nonce('mhm_vehicle_settings_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // If successful, add to DOM
                                $('#custom-details-list').append(`
                                    <div class="mhm-custom-item" data-key="${key}" style="display: flex; justify-content: space-between; align-items: center; padding: 8px; background: #fff3cd; border-radius: 4px; margin: 5px 0;">
                            <span>${label}</span>
                                        <button type="button" class="button button-small remove-custom-detail" data-key="${key}"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
                        </div>
                    `);
                    
                                $('#new-custom-detail-name').val('');
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
                            nonce: '<?php echo wp_create_nonce('mhm_vehicle_settings_nonce'); ?>'
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
                            nonce: '<?php echo wp_create_nonce('mhm_vehicle_settings_nonce'); ?>'
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
                                alert('Hata: ' + response.data);
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
                            nonce: '<?php echo wp_create_nonce('mhm_vehicle_settings_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                item.fadeOut(300, function() {
                                    $(this).remove();
                                });
                                alert('<?php echo esc_js(__('Custom detail removed successfully!', 'mhm-rentiva')); ?>');
                            } else {
                                alert('Hata: ' + response.data);
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
                            nonce: '<?php echo wp_create_nonce('mhm_vehicle_settings_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                item.fadeOut(300, function() {
                                    $(this).remove();
                                });
                                alert('<?php echo esc_js(__('Custom feature removed successfully!', 'mhm-rentiva')); ?>');
                            } else {
                                alert('Hata: ' + response.data);
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
                            nonce: '<?php echo wp_create_nonce('mhm_vehicle_settings_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                item.fadeOut(300, function() {
                                    $(this).remove();
                                });
                                alert('<?php echo esc_js(__('Custom equipment removed successfully!', 'mhm-rentiva')); ?>');
                            } else {
                                alert('Hata: ' + response.data);
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
                        nonce: '<?php echo wp_create_nonce('vehicle_settings_nonce'); ?>'
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
                        nonce: '<?php echo wp_create_nonce('vehicle_settings_nonce'); ?>'
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
     * Default features
     */
    private static function get_default_features(): array
    {
        return [
            'air_conditioning' => __('Air Conditioning', 'mhm-rentiva'),
            'power_steering' => __('Power Steering', 'mhm-rentiva'),
            'abs_brakes' => __('ABS Brakes', 'mhm-rentiva'),
            'airbags' => __('Airbags', 'mhm-rentiva'),
            'central_locking' => __('Central Locking', 'mhm-rentiva'),
            'electric_windows' => __('Electric Windows', 'mhm-rentiva'),
            'power_mirrors' => __('Power Mirrors', 'mhm-rentiva'),
            'fog_lights' => __('Fog Lights', 'mhm-rentiva'),
            'cruise_control' => __('Cruise Control', 'mhm-rentiva'),
            'bluetooth' => __('Bluetooth', 'mhm-rentiva'),
            'usb_port' => __('USB Port', 'mhm-rentiva'),
            'navigation' => __('Navigation', 'mhm-rentiva'),
            'sunroof' => __('Sunroof', 'mhm-rentiva'),
            'leather_seats' => __('Leather Seats', 'mhm-rentiva'),
            'heated_seats' => __('Heated Seats', 'mhm-rentiva')
        ];
    }

    /**
     * Default equipment
     */
    private static function get_default_equipment(): array
    {
        return [
            'spare_tire' => __('Spare Tire', 'mhm-rentiva'),
            'jack' => __('Jack', 'mhm-rentiva'),
            'first_aid_kit' => __('First Aid Kit', 'mhm-rentiva'),
            'fire_extinguisher' => __('Fire Extinguisher', 'mhm-rentiva'),
            'warning_triangle' => __('Warning Triangle', 'mhm-rentiva'),
            'jumper_cables' => __('Jumper Cables', 'mhm-rentiva'),
            'ice_scraper' => __('Ice Scraper', 'mhm-rentiva'),
            'car_cover' => __('Car Cover', 'mhm-rentiva'),
            'child_seat' => __('Child Seat', 'mhm-rentiva'),
            'gps_tracker' => __('GPS Tracker', 'mhm-rentiva'),
            'dashcam' => __('Dashcam', 'mhm-rentiva'),
            'phone_holder' => __('Phone Holder', 'mhm-rentiva'),
            'charger' => __('Charger', 'mhm-rentiva'),
            'cleaning_kit' => __('Cleaning Kit', 'mhm-rentiva'),
            'emergency_kit' => __('Emergency Kit', 'mhm-rentiva')
        ];
    }

    /**
     * Default details
     */
    private static function get_default_details(): array
    {
        return [
            'price_per_day' => __('Daily Price', 'mhm-rentiva'),
            'year' => __('Year', 'mhm-rentiva'),
            'mileage' => __('Mileage', 'mhm-rentiva'),
            'license_plate' => __('License Plate', 'mhm-rentiva'),
            'color' => __('Color', 'mhm-rentiva'),
            'brand' => __('Brand', 'mhm-rentiva'),
            'model' => __('Model', 'mhm-rentiva'),
            'seats' => __('Seats', 'mhm-rentiva'),
            'doors' => __('Doors', 'mhm-rentiva'),
            'transmission' => __('Transmission', 'mhm-rentiva'),
            'fuel_type' => __('Fuel Type', 'mhm-rentiva'),
            'engine_size' => __('Engine Size', 'mhm-rentiva'),
            'availability' => __('Availability', 'mhm-rentiva')
        ];
    }

    /**
     * Default selected details (checkbox states)
     */
    private static function get_default_selected_details(): array
    {
        return ['price_per_day', 'year', 'mileage', 'license_plate', 'color', 'brand', 'model', 'seats', 'doors', 'transmission', 'fuel_type', 'engine_size', 'availability'];
    }

    /**
     * Default selected features (checkbox states)
     */
    private static function get_default_selected_features(): array
    {
        return ['air_conditioning', 'power_steering', 'abs_brakes', 'airbags', 'central_locking', 'electric_windows', 'power_mirrors', 'fog_lights', 'cruise_control', 'bluetooth', 'usb_port', 'navigation', 'sunroof', 'leather_seats', 'heated_seats'];
    }

    /**
     * Default selected equipment (checkbox states)
     */
    private static function get_default_selected_equipment(): array
    {
        return ['spare_tire', 'jack', 'first_aid_kit', 'fire_extinguisher', 'warning_triangle', 'jumper_cables', 'ice_scraper', 'car_cover', 'child_seat', 'gps_tracker', 'dashcam', 'phone_holder', 'charger', 'cleaning_kit', 'emergency_kit'];
    }

    /**
     * Get all available details (standard + custom)
     */
    private static function get_all_available_details(): array
    {
        $default_details = (array) get_option('mhm_vehicle_details', self::get_default_details());
        $custom_details = (array) get_option('mhm_custom_details', []);
        
        return array_merge($default_details, $custom_details);
    }

    /**
     * Get all available features (standard + custom)
     */
    private static function get_all_available_features(): array
    {
        $default_features = (array) get_option('mhm_vehicle_features', self::get_default_features());
        $custom_features = (array) get_option('mhm_custom_features', []);
        
        return array_merge($default_features, $custom_features);
    }

    /**
     * Get all available equipment (standard + custom)
     */
    private static function get_all_available_equipment(): array
    {
        $default_equipment = (array) get_option('mhm_vehicle_equipment', self::get_default_equipment());
        $custom_equipment = (array) get_option('mhm_custom_equipment', []);
        
        return array_merge($default_equipment, $custom_equipment);
    }

    /**
     * AJAX: Add feature
     */
    public static function ajax_add_feature(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission', 'mhm-rentiva'));
        }
        
        $name = self::sanitize_text_field_safe($_POST['name']);
        $key = 'custom_' . time();
        $label = $name;
        
        $features = get_option('mhm_vehicle_features', self::get_default_features());
        $features[$key] = $label;
        update_option('mhm_vehicle_features', $features);
        
        // PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
        wp_die(wp_json_encode(['success' => true, 'data' => ['key' => $key, 'label' => $label]]));
    }

    /**
     * AJAX: Remove feature
     */
    public static function ajax_remove_feature(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission', 'mhm-rentiva'));
        }
        
        $key = self::sanitize_text_field_safe($_POST['key']);
        $features = get_option('mhm_vehicle_features', self::get_default_features());
        unset($features[$key]);
        update_option('mhm_vehicle_features', $features);
        
        // PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
        wp_die(wp_json_encode(['success' => true]));
    }

    /**
     * AJAX: Add equipment
     */
    public static function ajax_add_equipment(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission', 'mhm-rentiva'));
        }
        
        $name = self::sanitize_text_field_safe($_POST['name']);
        $key = 'custom_' . time();
        $label = $name;
        
        $equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());
        $equipment[$key] = $label;
        update_option('mhm_vehicle_equipment', $equipment);
        
        // PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
        wp_die(wp_json_encode(['success' => true, 'data' => ['key' => $key, 'label' => $label]]));
    }

    /**
     * AJAX: Remove equipment
     */
    public static function ajax_remove_equipment(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission', 'mhm-rentiva'));
        }
        
        $key = self::sanitize_text_field_safe($_POST['key']);
        $equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());
        unset($equipment[$key]);
        update_option('mhm_vehicle_equipment', $equipment);
        
        // PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
        wp_die(wp_json_encode(['success' => true]));
    }

    /**
     * AJAX: Add detail
     */
    public static function ajax_add_detail(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission', 'mhm-rentiva'));
        }
        
        $name = self::sanitize_text_field_safe($_POST['name']);
        $key = 'custom_' . time();
        $label = $name;
        
        $details = get_option('mhm_vehicle_details', self::get_default_details());
        $details[$key] = $label;
        update_option('mhm_vehicle_details', $details);
        
        // PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
        wp_die(wp_json_encode(['success' => true, 'data' => ['key' => $key, 'label' => $label]]));
    }

    /**
     * AJAX: Remove detail
     */
    public static function ajax_remove_detail(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission', 'mhm-rentiva'));
        }
        
        $key = self::sanitize_text_field_safe($_POST['key']);
        $details = get_option('mhm_vehicle_details', self::get_default_details());
        unset($details[$key]);
        update_option('mhm_vehicle_details', $details);
        
        // PERFORMANCE OPTIMIZATION: Use wp_die instead of wp_send_json
        wp_die(wp_json_encode(['success' => true]));
    }

    /**
     * AJAX: Save settings
     */
    public static function ajax_save_settings(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission', 'mhm-rentiva'));
        }
        
        // Save selected fields
        $selected_details = isset($_POST['selected_details']) ? array_map('sanitize_text_field', $_POST['selected_details']) : [];
        $selected_features = isset($_POST['selected_features']) ? array_map('sanitize_text_field', $_POST['selected_features']) : [];
        $selected_equipment = isset($_POST['selected_equipment']) ? array_map('sanitize_text_field', $_POST['selected_equipment']) : [];
        
        // Save custom fields
        $custom_details = isset($_POST['custom_details']) ? array_map('sanitize_text_field', $_POST['custom_details']) : [];
        $custom_features = isset($_POST['custom_features']) ? array_map('sanitize_text_field', $_POST['custom_features']) : [];
        $custom_equipment = isset($_POST['custom_equipment']) ? array_map('sanitize_text_field', $_POST['custom_equipment']) : [];
        
        // Update field names (rename feature)
        if (isset($_POST['updated_labels'])) {
            $updated_labels = $_POST['updated_labels'];
            
            // Update labels for each field type
            foreach ($updated_labels as $type => $labels) {
                if ($type === 'details') {
                    $current_details = get_option('mhm_vehicle_details', self::get_default_details());
                    $custom_details = get_option('mhm_custom_details', []);
                    
                    foreach ($labels as $key => $new_label) {
                        // Update standard fields
                        if (isset($current_details[$key])) {
                            $current_details[$key] = self::sanitize_text_field_safe($new_label);
                        }
                        // Update custom fields
                        elseif (isset($custom_details[$key])) {
                            $custom_details[$key] = self::sanitize_text_field_safe($new_label);
                        }
                    }
                    
                    update_option('mhm_vehicle_details', $current_details);
                    update_option('mhm_custom_details', $custom_details);
                } elseif ($type === 'features') {
                    $current_features = get_option('mhm_vehicle_features', self::get_default_features());
                    $custom_features = get_option('mhm_custom_features', []);
                    
                    foreach ($labels as $key => $new_label) {
                        // Update standard fields
                        if (isset($current_features[$key])) {
                            $current_features[$key] = self::sanitize_text_field_safe($new_label);
                        }
                        // Update custom fields
                        elseif (isset($custom_features[$key])) {
                            $custom_features[$key] = self::sanitize_text_field_safe($new_label);
                        }
                    }
                    
                    update_option('mhm_vehicle_features', $current_features);
                    update_option('mhm_custom_features', $custom_features);
                } elseif ($type === 'equipment') {
                    $current_equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());
                    $custom_equipment = get_option('mhm_custom_equipment', []);
                    
                    foreach ($labels as $key => $new_label) {
                        // Update standard fields
                        if (isset($current_equipment[$key])) {
                            $current_equipment[$key] = self::sanitize_text_field_safe($new_label);
                        }
                        // Update custom fields
                        elseif (isset($custom_equipment[$key])) {
                            $custom_equipment[$key] = self::sanitize_text_field_safe($new_label);
                        }
                    }
                    
                    update_option('mhm_vehicle_equipment', $current_equipment);
                    update_option('mhm_custom_equipment', $custom_equipment);
                }
            }
        }
        
        // Save to database
        update_option('mhm_selected_details', $selected_details);
        update_option('mhm_selected_features', $selected_features);
        update_option('mhm_selected_equipment', $selected_equipment);
        update_option('mhm_custom_details', $custom_details);
        update_option('mhm_custom_features', $custom_features);
        update_option('mhm_custom_equipment', $custom_equipment);
        
        wp_send_json_success(__('Settings saved successfully!', 'mhm-rentiva'));
    }

    /**
     * AJAX: Update field names
     */
    public static function ajax_update_field_labels(): void
    {
        check_ajax_referer('vehicle_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission', 'mhm-rentiva'));
        }
        
        $type = self::sanitize_text_field_safe($_POST['type'] ?? '');
        $labels = $_POST['labels'] ?? [];
        
        // Sanitize labels
        $sanitized_labels = [];
        foreach ($labels as $key => $label) {
            $sanitized_key = self::sanitize_text_field_safe($key);
            $sanitized_label = self::sanitize_text_field_safe($label);
            // Encoding fix - For Turkish characters
            $sanitized_label = mb_convert_encoding($sanitized_label, 'UTF-8', 'auto');
            $sanitized_labels[$sanitized_key] = $sanitized_label;
        }
        
        // Get existing fields (updated ones)
        if ($type === 'details') {
            $current_details = get_option('mhm_vehicle_details', self::get_default_details());
            $custom_details = get_option('mhm_custom_details', []);
            
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
            $custom_features = get_option('mhm_custom_features', []);
            
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
            $custom_equipment = get_option('mhm_custom_equipment', []);
            
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
        if (!wp_verify_nonce($_POST['nonce'], 'mhm_vehicle_settings_nonce')) {
            wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
            return;
        }

        $field_key = self::sanitize_text_field_safe($_POST['field_key']);
        $field_type = self::sanitize_text_field_safe($_POST['field_type']); // details, features, equipment

        if ($field_type === 'details') {
            $custom_details = get_option('mhm_custom_details', []);
            if (isset($custom_details[$field_key])) {
                unset($custom_details[$field_key]);
                update_option('mhm_custom_details', $custom_details);
                
                // Clean related post meta
                global $wpdb;
                $wpdb->delete($wpdb->postmeta, [
                    'meta_key' => '_mhm_rentiva_' . $field_key
                ]);
                
                wp_send_json_success(__('Custom detail removed successfully', 'mhm-rentiva'));
            } else {
                wp_send_json_error('Custom detail not found');
            }
        } elseif ($field_type === 'features') {
            $custom_features = get_option('mhm_custom_features', []);
            if (isset($custom_features[$field_key])) {
                unset($custom_features[$field_key]);
                update_option('mhm_custom_features', $custom_features);
                
                // Clean related post meta
                global $wpdb;
                $wpdb->delete($wpdb->postmeta, [
                    'meta_key' => '_mhm_rentiva_' . $field_key
                ]);
                
                wp_send_json_success(__('Custom feature removed successfully', 'mhm-rentiva'));
            } else {
                wp_send_json_error('Custom feature not found');
            }
        } elseif ($field_type === 'equipment') {
            $custom_equipment = get_option('mhm_custom_equipment', []);
            if (isset($custom_equipment[$field_key])) {
                unset($custom_equipment[$field_key]);
                update_option('mhm_custom_equipment', $custom_equipment);
                
                // Clean related post meta
                global $wpdb;
                $wpdb->delete($wpdb->postmeta, [
                    'meta_key' => '_mhm_rentiva_' . $field_key
                ]);
                
                wp_send_json_success(__('Custom equipment removed successfully', 'mhm-rentiva'));
            } else {
                wp_send_json_error('Custom equipment not found');
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
        if (!wp_verify_nonce($_POST['nonce'], 'mhm_vehicle_settings_nonce')) {
            wp_send_json_error(__('Security check failed', 'mhm-rentiva'));
            return;
        }

        $field_key = self::sanitize_text_field_safe($_POST['field_key']);
        $field_label = self::sanitize_text_field_safe($_POST['field_label']);
        $field_type = self::sanitize_text_field_safe($_POST['field_type']); // details, features, equipment

        // Encoding fix - For Turkish characters
        $field_label = mb_convert_encoding($field_label, 'UTF-8', 'auto');

        if ($field_type === 'details') {
            $custom_details = get_option('mhm_custom_details', []);
            $custom_details[$field_key] = $field_label;
            update_option('mhm_custom_details', $custom_details);
            
            wp_send_json_success(__('Custom detail added successfully', 'mhm-rentiva'));
        } elseif ($field_type === 'features') {
            $custom_features = get_option('mhm_custom_features', []);
            $custom_features[$field_key] = $field_label;
            update_option('mhm_custom_features', $custom_features);
            
            wp_send_json_success(__('Custom feature added successfully', 'mhm-rentiva'));
        } elseif ($field_type === 'equipment') {
            $custom_equipment = get_option('mhm_custom_equipment', []);
            $custom_equipment[$field_key] = $field_label;
            update_option('mhm_custom_equipment', $custom_equipment);
            
            wp_send_json_success(__('Custom equipment added successfully', 'mhm-rentiva'));
        } else {
            wp_send_json_error('Invalid field type');
        }
    }
}
