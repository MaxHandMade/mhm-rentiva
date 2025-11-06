<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Comparison Settings
 * 
 * Vehicle comparison settings - users can select which fields to display
 * 
 * @since 4.0.0
 */
final class VehicleComparisonSettings
{
    /**
     * Register settings
     */
    public static function register(): void
    {
        self::register_settings();
    }

    /**
     * Register settings
     */
    public static function register_settings(): void
    {
        // Vehicle Comparison Section
        add_settings_section(
            'mhm_rentiva_vehicle_comparison_section',
            __('Vehicle Comparison Settings', 'mhm-rentiva'),
            [self::class, 'render_section_description'],
            'mhm_rentiva_settings'
        );
        
        // Register setting
        // Note: Main mhm_rentiva_settings is registered in SettingsCore::init() with proper sanitize_callback
        
        // AJAX save process removed - using standard WordPress Settings API

        // Comparison Fields Selection
        add_settings_field(
            'mhm_rentiva_comparison_fields',
            __('Select Comparison Fields', 'mhm-rentiva'),
            [self::class, 'render_comparison_fields'],
            'mhm_rentiva_settings',
            'mhm_rentiva_vehicle_comparison_section'
        );

    }

    /**
     * Section description
     */
    public static function render_section_description(): void
    {
        echo '<p>' . esc_html__('Configure which fields are displayed in the vehicle comparison table.', 'mhm-rentiva') . '</p>';
    }

    /**
     * Comparison fields selection
     */
    public static function render_comparison_fields(): void
    {
        $settings = get_option('mhm_rentiva_settings', []);
        $selected_fields = $settings['comparison_fields'] ?? [];
        
        // Get available meta fields
        $available_fields = self::get_available_meta_fields();
        
        // Show default fields if no selection made (visual only)
        $show_defaults = empty($selected_fields);
        
        ?>
        <div class="mhm-comparison-fields">
            <p class="description">
                <?php echo esc_html__('Select which fields to display in the comparison table:', 'mhm-rentiva'); ?>
            </p>
            
               <div class="mhm-field-categories">
                   <?php foreach ($available_fields as $category => $fields): ?>
                       <div class="mhm-field-category" data-category="<?php echo esc_attr($category); ?>">
                           <div class="mhm-category-header">
                               <h4><?php echo esc_html(ucfirst($category)); ?></h4>
                               <div class="mhm-category-actions">
                                   <button type="button" class="mhm-select-all-btn" data-category="<?php echo esc_attr($category); ?>">
                                       <?php echo esc_html__('Select All', 'mhm-rentiva'); ?>
                                   </button>
                                   <button type="button" class="mhm-deselect-all-btn" data-category="<?php echo esc_attr($category); ?>">
                                       <?php echo esc_html__('Deselect All', 'mhm-rentiva'); ?>
                                   </button>
                               </div>
                           </div>
                           <div class="mhm-field-list">
                            <?php foreach ($fields as $field_key => $field_label): ?>
                                <label class="mhm-field-item">
                                    <input type="checkbox" 
                                           name="mhm_rentiva_settings[comparison_fields][<?php echo esc_attr($category); ?>][]" 
                                           value="<?php echo esc_attr($field_key); ?>"
                                           <?php 
                                           $is_checked = false;
                                           if ($show_defaults) {
                                               // Show default fields as selected
                                               $default_fields = ['brand', 'model', 'price_per_day', 'availability', 'fuel_type', 'transmission', 'seats', 'doors'];
                                               $is_checked = in_array($field_key, $default_fields);
                                           } else {
                                               // Show saved fields as selected
                                               $is_checked = in_array($field_key, $selected_fields[$category] ?? []);
                                           }
                                           checked($is_checked);
                                           ?>>
                                    <?php echo esc_html($field_label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Select all
            $('.mhm-select-all-btn').on('click', function() {
                var category = $(this).data('category');
                $('.mhm-field-category[data-category="' + category + '"] input[type="checkbox"]').prop('checked', true);
            });
            
            // Deselect all
            $('.mhm-deselect-all-btn').on('click', function() {
                var category = $(this).data('category');
                $('.mhm-field-category[data-category="' + category + '"] input[type="checkbox"]').prop('checked', false);
            });
            
            // AJAX form submission removed - using standard WordPress Settings API
        });
        </script>
        
        <style>
        .mhm-comparison-fields {
            max-width: 800px;
        }
        .mhm-field-category {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .mhm-category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .mhm-category-header h4 {
            margin: 0;
            color: #333;
        }
        .mhm-category-actions {
            display: flex;
            gap: 10px;
        }
        .mhm-select-all-btn, .mhm-deselect-all-btn {
            padding: 5px 10px;
            border: 1px solid #0073aa;
            background: #0073aa;
            color: white;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .mhm-select-all-btn:hover, .mhm-deselect-all-btn:hover {
            background: #005a87;
        }
        .mhm-field-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }
        .mhm-field-item {
            display: flex;
            align-items: center;
            padding: 5px;
            background: white;
            border-radius: 3px;
        }
        .mhm-field-item input[type="checkbox"] {
            margin-right: 8px;
        }
        </style>
        <?php
    }

    /**
     * Get available meta fields
     */
    private static function get_available_meta_fields(): array
    {
        global $wpdb;
        
        // Get meta keys from all vehicles
        $meta_keys = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT meta_key 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key LIKE %s
            ORDER BY meta_key
        ", 'vehicle', '_mhm_rentiva_%'));
        
        $fields = [
            'all' => []
        ];
        
        // Group fields from database into single category
        foreach ($meta_keys as $meta_key) {
            $field_key = str_replace('_mhm_rentiva_', '', $meta_key);
            $field_label = self::get_field_label($field_key);
            
            // Add all fields to all category
            $fields['all'][$field_key] = $field_label;
        }
        
        // Get comfort features from _mhm_rentiva_features array
        if (in_array('_mhm_rentiva_features', $meta_keys)) {
            $features_meta = $wpdb->get_var($wpdb->prepare("
                SELECT meta_value 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = %s 
                AND meta_value != '' 
                LIMIT 1
            ", '_mhm_rentiva_features'));
            
            if ($features_meta) {
                $features = maybe_unserialize($features_meta);
                if (is_array($features)) {
                    foreach ($features as $feature) {
                        $field_label = self::get_field_label($feature);
                        $fields['all'][$feature] = $field_label;
                    }
                }
            }
        }
        
        return $fields;
    }

    /**
     * Get field label
     */
    private static function get_field_label(string $field_key): string
    {
        $labels = [
            'availability' => __('Availability', 'mhm-rentiva'),
            'available' => __('Available', 'mhm-rentiva'),
            'brand' => __('Brand', 'mhm-rentiva'),
            'model' => __('Model', 'mhm-rentiva'),
            'price_per_day' => __('Daily Price', 'mhm-rentiva'),
            'fuel_type' => __('Fuel Type', 'mhm-rentiva'),
            'transmission' => __('Transmission', 'mhm-rentiva'),
            'seats' => __('Seats', 'mhm-rentiva'),
            'doors' => __('Doors', 'mhm-rentiva'),
            'engine_size' => __('Engine Size', 'mhm-rentiva'),
            'year' => __('Model Year', 'mhm-rentiva'),
            'mileage' => __('Mileage', 'mhm-rentiva'),
            'color' => __('Color', 'mhm-rentiva'),
            'deposit' => __('Deposit', 'mhm-rentiva'),
            'license_plate' => __('License Plate', 'mhm-rentiva'),
            'rating_average' => __('Rating Average', 'mhm-rentiva'),
            'rating_count' => __('Rating Count', 'mhm-rentiva'),
            'gallery_images' => __('Gallery Images', 'mhm-rentiva'),
            'features' => __('Features', 'mhm-rentiva'),
            'equipment' => __('Equipment', 'mhm-rentiva'),
            'air_conditioning' => __('Air Conditioning', 'mhm-rentiva'),
            'gps' => __('GPS', 'mhm-rentiva'),
            'bluetooth' => __('Bluetooth', 'mhm-rentiva'),
            'usb_port' => __('USB Port', 'mhm-rentiva'),
            'sunroof' => __('Sunroof', 'mhm-rentiva'),
        ];
        
        return $labels[$field_key] ?? ucfirst(str_replace('_', ' ', $field_key));
    }
}
