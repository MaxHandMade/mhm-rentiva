<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;

if (!defined('ABSPATH')) {
    exit;
}

final class VehicleMeta extends AbstractMetaBox
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
        return [
            'mhm_rentiva_vehicle_details' => [
                'title' => __('Vehicle Details', 'mhm-rentiva'),
                'context' => 'advanced',
                'priority' => 'high',
                'template' => 'render_meta_box',
                'save_handler' => 'save_vehicle_meta',
            ],
        ];
    }

    public static function register(): void
    {
        parent::register();
        
        add_action('init', [self::class, 'register_meta_fields']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        
        add_action('admin_head', [self::class, 'hide_default_meta_boxes']);
        
        add_action('wp_ajax_mhm_save_item_order', [self::class, 'ajax_save_item_order']);
        
        add_action('wp_ajax_mhm_add_gallery_image', [self::class, 'ajax_add_gallery_image']);
        add_action('wp_ajax_mhm_remove_gallery_image', [self::class, 'ajax_remove_gallery_image']);
        add_action('wp_ajax_mhm_reorder_gallery_images', [self::class, 'ajax_reorder_gallery_images']);
        
        add_action('add_meta_boxes', [self::class, 'register_gallery_meta_box']);
        
        add_action('add_meta_boxes', [self::class, 'reorder_meta_boxes'], 999);
    }

    public static function enqueue_scripts(): void
    {
        global $post_type, $pagenow;

        if ($post_type === 'vehicle' && ($pagenow === 'post.php' || $pagenow === 'post-new.php')) {
            wp_enqueue_style(
                'mhm-vehicle-meta-css',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/components/vehicle-meta.css',
                [],
                MHM_RENTIVA_VERSION . '-' . time()
            );

            wp_enqueue_script(
                'mhm-vehicle-meta-js',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/components/vehicle-meta.js',
                ['jquery'],
                MHM_RENTIVA_VERSION . '-' . time(),
                true
            );

            wp_localize_script('mhm-vehicle-meta-js', 'mhmVehicleMeta', [
                'strings' => [
                    'orderUpdated' => __('Order updated!', 'mhm-rentiva'),
                    'enterNewFeature' => __('Enter new feature name:', 'mhm-rentiva'),
                    'enterNewEquipment' => __('Enter new equipment name:', 'mhm-rentiva'),
                    'enterNewDetail' => __('Enter new detail name:', 'mhm-rentiva'),
                    'confirmRemoveFeature' => __('Are you sure you want to remove this feature?', 'mhm-rentiva'),
                    'confirmRemoveEquipment' => __('Are you sure you want to remove this equipment?', 'mhm-rentiva'),
                    'enterValue' => __('Enter value', 'mhm-rentiva'),
                    'comingSoonCustomAdd' => __('Coming soon! For now, use the Custom Add button.', 'mhm-rentiva'),
                    'comingSoonCustomRemove' => __('Coming soon! For now, use the Custom Add button.', 'mhm-rentiva'),
                    'redirectingToSettings' => __('Redirecting to Vehicle Settings page...', 'mhm-rentiva'),
                ]
            ]);

            
            wp_enqueue_media();
            
            wp_enqueue_script('jquery-ui-sortable');
            
            wp_enqueue_script(
                'mhm-vehicle-gallery-js',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/vehicle-gallery.js',
                ['jquery', 'jquery-ui-sortable', 'media-upload', 'media-views'],
                MHM_RENTIVA_VERSION,
                true
            );

            wp_enqueue_style(
                'mhm-vehicle-gallery-css',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/vehicle-gallery.css',
                [],
                MHM_RENTIVA_VERSION
            );

            wp_localize_script('mhm-vehicle-gallery-js', 'mhmVehicleGallery', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_vehicle_gallery_nonce'),
                'strings' => [
                    'selectImages' => __('Select Images', 'mhm-rentiva'),
                    'addImages' => __('Add Image', 'mhm-rentiva'),
                    'removeImage' => __('Remove Image', 'mhm-rentiva'),
                    'setAsFeatured' => __('Set as Featured Image', 'mhm-rentiva'),
                    'noImages' => __('No images added yet', 'mhm-rentiva'),
                    'maxImages' => __('You can add maximum 10 images', 'mhm-rentiva'),
                    'confirmRemove' => __('Are you sure you want to remove this image?', 'mhm-rentiva'),
                    'uploading' => __('Uploading...', 'mhm-rentiva'),
                    'uploadError' => __('Error occurred while uploading image', 'mhm-rentiva'),
                    'addImageError' => __('An error occurred while adding image.', 'mhm-rentiva'),
                    'removeImageError' => __('An error occurred while removing image.', 'mhm-rentiva'),
                    'reorderImageError' => __('An error occurred while reordering images.', 'mhm-rentiva'),
                    'imageAdded' => __('Image successfully added!', 'mhm-rentiva'),
                    'imageRemoved' => __('Image successfully removed!', 'mhm-rentiva'),
                    'imageReordered' => __('Images successfully reordered!', 'mhm-rentiva'),
                    'securityError' => __('Security error.', 'mhm-rentiva'),
                    'permissionError' => __('Permission error.', 'mhm-rentiva'),
                    'invalidData' => __('Invalid data.', 'mhm-rentiva'),
                ]
            ]);

        }
    }

    public static function add_meta_boxes(): void
    {
        remove_meta_box('postexcerpt', 'vehicle', 'normal');
        remove_meta_box('slugdiv', 'vehicle', 'normal');
        
        parent::add_meta_boxes();
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

    public static function render_meta_box(\WP_Post $post, array $args = []): void
    {
        wp_nonce_field('mhm_rentiva_vehicle_meta_action', 'mhm_rentiva_vehicle_meta_nonce');

        $template_data = self::prepare_template_data($post);
        
        $template_path = MHM_RENTIVA_PLUGIN_DIR . 'src/Admin/Vehicle/Templates/vehicle-meta.php';
        
        if (file_exists($template_path)) {
            extract($template_data);
            include $template_path;
        } else {
            echo '<div class="error"><p>' . esc_html__('Template file not found: vehicle-meta.php', 'mhm-rentiva') . '</p></div>';
            echo '<p>Searched path: ' . esc_html($template_path) . '</p>';
        }
    }

    /**
     * Prepare data for template (performance optimization)
     */
    private static function prepare_template_data($post): array
    {
        $vehicle_id = $post->ID;
        
        $field_settings = self::get_field_settings();
        
        $meta_data = self::get_vehicle_meta_data($post->ID, $field_settings['custom_fields']);
        
        $available_details = self::build_available_fields($field_settings['selected_details'], $field_settings['default_details'], $field_settings['custom_details']);
        $available_features = self::build_available_fields($field_settings['selected_features'], $field_settings['default_features'], $field_settings['custom_features']);
        $available_equipment = self::build_available_fields($field_settings['selected_equipment'], $field_settings['default_equipment'], $field_settings['custom_equipment']);
        

        self::ensure_default_options($available_details, $available_features, $available_equipment);

        $removed_details = $meta_data['_mhm_removed_details'] ?? [];
        if (!is_array($removed_details)) {
            $removed_details = [];
        }

        $custom_details = $meta_data['_mhm_custom_details'] ?? [];
        if (!is_array($custom_details)) {
            $custom_details = [];
        }

        foreach ($custom_details as $key => $detail) {
            if (isset($available_details[$key])) {
                unset($available_details[$key]);
            }
        }

        $features = $meta_data['_mhm_rentiva_features'] ?? [];
        if (!is_array($features)) {
            $features = [];
        }

        $equipment = $meta_data['_mhm_rentiva_equipment'] ?? [];
        if (!is_array($equipment)) {
            $equipment = [];
        }

            $available_value = $meta_data['_mhm_vehicle_status'] ?? '';
            
            if (empty($available_value)) {
                $available_value = self::normalize_availability($meta_data['_mhm_vehicle_availability'] ?? 'active');
            } else {
                $available_value = self::normalize_availability($available_value);
            }

            $detail_values = [];
            foreach ($available_details as $key => $label) {
                $detail_values[$key] = $meta_data['_mhm_rentiva_' . $key] ?? '';
                
                if (strpos($key, 'custom_') === 0) {
                }
            }
            
            return [
                'post' => $post,
                'price_per_day' => $meta_data['_mhm_rentiva_price_per_day'] ?? '',
                'year' => $meta_data['_mhm_rentiva_year'] ?? '',
                'mileage' => $meta_data['_mhm_rentiva_mileage'] ?? '',
                'license_plate' => $meta_data['_mhm_rentiva_license_plate'] ?? '',
                'color' => $meta_data['_mhm_rentiva_color'] ?? '',
                'brand' => $meta_data['_mhm_rentiva_brand'] ?? '',
                'model' => $meta_data['_mhm_rentiva_model'] ?? '',
                'seats' => $meta_data['_mhm_rentiva_seats'] ?? '',
                'doors' => $meta_data['_mhm_rentiva_doors'] ?? '',
                'transmission' => $meta_data['_mhm_rentiva_transmission'] ?? '',
                'fuel_type' => $meta_data['_mhm_rentiva_fuel_type'] ?? '',
                'engine_size' => $meta_data['_mhm_rentiva_engine_size'] ?? '',
                'available' => $available_value,
                'features' => $features,
                'equipment' => $equipment,
                'available_details' => $available_details,
                'available_features' => $available_features,
                'available_equipment' => $available_equipment,
                'removed_details' => $removed_details,
                'custom_details' => $custom_details,
                'saved_order' => $meta_data['_mhm_details_order'] ?? [],
                'saved_features_order' => $meta_data['_mhm_features_order'] ?? [],
                'saved_equipment_order' => $meta_data['_mhm_equipment_order'] ?? [],
                'detail_values' => $detail_values,
            ];
    }

    /**
     * Default details
     */
    private static function get_default_details(): array
    {
        return [
            'price_per_day' => __('Daily Price', 'mhm-rentiva'),
            'year' => __('Model Year', 'mhm-rentiva'),
            'mileage' => __('Mileage', 'mhm-rentiva'),
            'license_plate' => __('License Plate', 'mhm-rentiva'),
            'color' => __('Color', 'mhm-rentiva'),
            'brand' => __('Brand', 'mhm-rentiva'),
            'model' => __('Model', 'mhm-rentiva'),
            'seats' => __('Number of Seats', 'mhm-rentiva'),
            'doors' => __('Number of Doors', 'mhm-rentiva'),
            'transmission' => __('Transmission Type', 'mhm-rentiva'),
            'fuel_type' => __('Fuel Type', 'mhm-rentiva'),
            'engine_size' => __('Engine Size', 'mhm-rentiva'),
            'deposit' => __('Deposit', 'mhm-rentiva'),
            'availability' => __('Availability Status', 'mhm-rentiva')
        ];
    }

    /**
     * Default features
     */
    private static function get_default_features(): array
    {
        return [
            'air_conditioning' => __('Air Conditioning', 'mhm-rentiva'),
            'power_steering' => __('Power Steering', 'mhm-rentiva'),
            'abs_brakes' => __('ABS Brake System', 'mhm-rentiva'),
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

    public static function save_meta(int $post_id, \WP_Post $post): void
    {
        if (!in_array($post->post_type, ['vehicle', 'vehicle_booking'], true)) {
            return;
        }
        
        if (!isset($_POST['mhm_rentiva_vehicle_meta_nonce']) || !wp_verify_nonce(self::sanitize_text_field_safe(wp_unslash($_POST['mhm_rentiva_vehicle_meta_nonce'])), 'mhm_rentiva_vehicle_meta_action')) {
            return;
        }
        

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }


        $available_details = get_option('mhm_vehicle_details', self::get_default_details());
        $available_features = get_option('mhm_vehicle_features', self::get_default_features());
        $available_equipment = get_option('mhm_vehicle_equipment', self::get_default_equipment());
        
        
        $details_order = isset($_POST['details-grid_order']) ? json_decode(stripslashes($_POST['details-grid_order']), true) : null;
        
        if ($details_order && is_array($details_order)) {
            update_post_meta($post_id, '_mhm_details_order', $details_order);
        }
        
        $removed_details = isset($_POST['removed_details']) ? json_decode(stripslashes($_POST['removed_details']), true) : [];
        if (!is_array($removed_details)) {
            $removed_details = [];
        }
        
        $meta_updates = [];
        
        if (isset($_POST['_mhm_vehicle_status'])) {
            $status = self::sanitize_text_field_safe($_POST['_mhm_vehicle_status']);
            $allowed = ['active', 'inactive', 'maintenance'];
            
            
            if (in_array($status, $allowed, true)) {
                $meta_updates['_mhm_vehicle_status'] = $status;
            } else {
            }
        } else {
        }

        $availability_value = isset($_POST['mhm_rentiva_available']) ? $_POST['mhm_rentiva_available'] : '';
        $sanitized_availability = self::sanitize_field('mhm_rentiva_available', $availability_value);
        $meta_updates['_mhm_vehicle_availability'] = $sanitized_availability;
        

        foreach ($available_details as $key => $label) {
            if (in_array($key, $removed_details) && $key !== 'engine_size') {
                continue;
            }
            
            $field_name = 'mhm_rentiva_' . $key;
            $meta_key = '_mhm_rentiva_' . $key;
            $value = isset($_POST[$field_name]) ? $_POST[$field_name] : '';
            $sanitized_value = self::sanitize_field($field_name, $value);
            $meta_updates[$meta_key] = $sanitized_value;
        }
        
        $meta_updates['_mhm_removed_details'] = $removed_details;
        
        if (!empty($removed_details)) {
            foreach ($removed_details as $removed_key) {
                unset($available_details[$removed_key]);
            }
            $option_updated = true;
        }
        
        $custom_details = get_option('mhm_custom_details', []);
        $custom_features = get_option('mhm_custom_features', []);
        $custom_equipment = get_option('mhm_custom_equipment', []);
        
        $all_custom_fields = array_merge($custom_details, $custom_features, $custom_equipment);
        
        foreach ($all_custom_fields as $field_key => $field_label) {
            $meta_key = '_mhm_rentiva_' . $field_key;
            $field_name = 'mhm_rentiva_' . $field_key;
            
            if (isset($_POST[$field_name])) {
                $value = self::sanitize_text_field_safe($_POST[$field_name]);
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                $meta_updates[$meta_key] = $value;
            }
        }
        
        $legacy_custom_details = isset($_POST['mhm_rentiva_custom_details']) ? $_POST['mhm_rentiva_custom_details'] : [];
        
        $sanitized_custom_details = [];
        
        foreach ($legacy_custom_details as $key => $detail_data) {
            if (is_array($detail_data) && isset($detail_data['label']) && isset($detail_data['value'])) {
                $sanitized_custom_details[self::sanitize_text_field_safe($key)] = [
                    'label' => self::sanitize_text_field_safe($detail_data['label']),
                    'value' => self::sanitize_text_field_safe($detail_data['value'])
                ];
                
            } else {
                $sanitized_custom_details[self::sanitize_text_field_safe($key)] = [
                    'label' => self::sanitize_text_field_safe($key),
                    'value' => self::sanitize_text_field_safe($detail_data)
                ];
                
            }
        }
        
        $meta_updates['_mhm_custom_details'] = $sanitized_custom_details;
        
        foreach ($meta_updates as $meta_key => $meta_value) {
            if ($meta_key === '_mhm_vehicle_availability') {
                delete_post_meta($post_id, $meta_key);
                add_post_meta($post_id, $meta_key, $meta_value, true);
            } elseif ($meta_key === '_mhm_vehicle_status') {
                $old_value = get_post_meta($post_id, $meta_key, true);
                
                $result = update_post_meta($post_id, $meta_key, $meta_value);
                
                if (!$result) {
                    $current_value = get_post_meta($post_id, $meta_key, true);
                }
                
                if (!$result) {
                    delete_post_meta($post_id, $meta_key);
                    $add_result = add_post_meta($post_id, $meta_key, $meta_value, true);
                }
            } else {
                $result = update_post_meta($post_id, $meta_key, $meta_value);
            }
        }
        

        $features_order = isset($_POST['features-grid_order']) ? json_decode(stripslashes($_POST['features-grid_order']), true) : null;
        $equipment_order = isset($_POST['equipment-grid_order']) ? json_decode(stripslashes($_POST['equipment-grid_order']), true) : null;
        
        if ($features_order && is_array($features_order)) {
            update_post_meta($post_id, '_mhm_features_order', $features_order);
        }
        
        if ($equipment_order && is_array($equipment_order)) {
            update_post_meta($post_id, '_mhm_equipment_order', $equipment_order);
        }

        $features = isset($_POST['mhm_rentiva_features']) ? $_POST['mhm_rentiva_features'] : [];
        $sanitized_features = self::sanitize_array($features);
        update_post_meta($post_id, '_mhm_rentiva_features', $sanitized_features);

        $equipment = isset($_POST['mhm_rentiva_equipment']) ? $_POST['mhm_rentiva_equipment'] : [];
        $sanitized_equipment = self::sanitize_array($equipment);
        update_post_meta($post_id, '_mhm_rentiva_equipment', $sanitized_equipment);
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
                $seats = intval($value);
                return max(1, min(20, $seats));

            case 'mhm_rentiva_doors':
                $doors = intval($value);
                return max(2, min(8, $doors));

            case 'mhm_rentiva_transmission':
                $allowed = ['auto', 'manual'];
                return in_array($value, $allowed, true) ? $value : 'auto';

            case 'mhm_rentiva_fuel_type':
                $allowed = ['petrol', 'diesel', 'hybrid', 'electric'];
                return in_array($value, $allowed, true) ? $value : 'petrol';

            case 'mhm_rentiva_engine_size':
                $engine = floatval($value);
                return max(0.8, min(8.0, $engine));

            case 'mhm_rentiva_color':
                return self::sanitize_text_field_safe($value);

            case 'mhm_rentiva_deposit':
                $clean_value = str_replace(['%', ' '], '', $value);
                $deposit = floatval($clean_value);
                return max(0, $deposit);

            case 'mhm_rentiva_available':
                $allowed = ['active', 'passive', 'maintenance', '1', '0'];
                if (in_array($value, $allowed, true)) {
                    // Backward compatibility: '1' -> 'active', '0' -> 'passive'
                    if ($value === '1') return 'active';
                    if ($value === '0') return 'passive';
                    return $value;
                }
                return 'active';

            default:
                return self::sanitize_text_field_safe($value);
        }
    }

    /**
     * Sanitize array values
     */
    private static function sanitize_array(array $array): array
    {
        $sanitized = [];
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
        if ($value === '1') return 'active';
        if ($value === '0') return 'passive';
        
        if (in_array($value, ['active', 'inactive', 'maintenance'], true)) {
            return $value;
        }
        
        if ($value === 'passive') return 'inactive';
        
        return 'active';
    }

    /**
     * Save Vehicle meta
     */
    public static function save_vehicle_meta(int $post_id, array $field_config): void
    {
        if (!isset($_POST['mhm_rentiva_vehicle_meta_nonce']) || !wp_verify_nonce(self::sanitize_text_field_safe(wp_unslash($_POST['mhm_rentiva_vehicle_meta_nonce'])), 'mhm_rentiva_vehicle_meta_action')) {
            return;
        }
        

        if (isset($_POST['_mhm_vehicle_status'])) {
            $status = self::sanitize_text_field_safe($_POST['_mhm_vehicle_status']);
            $allowed = ['active', 'inactive', 'maintenance'];
            
            
            if (in_array($status, $allowed, true)) {
                $result = update_post_meta($post_id, '_mhm_vehicle_status', $status);
            } else {
            }
        } else {
        }

        $meta_fields = [
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
            '_mhm_rentiva_deposit'
        ];

        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                $value = self::sanitize_text_field_safe($_POST[$field]);
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
        $result = register_post_meta('vehicle', '_mhm_vehicle_status', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                $allowed = ['active', 'inactive', 'maintenance'];
                $sanitized = in_array($value, $allowed, true) ? $value : 'active';
                return $sanitized;
            },
        ]);
        

        register_post_meta('vehicle', '_mhm_rentiva_price_per_day', [
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                return max(0, floatval($value));
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_seats', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                return max(1, min(20, intval($value)));
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_doors', [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                return max(2, min(8, intval($value)));
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_transmission', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                $allowed = ['auto', 'manual'];
                return in_array($value, $allowed, true) ? $value : 'auto';
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_fuel_type', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                $allowed = ['petrol', 'diesel', 'hybrid', 'electric'];
                return in_array($value, $allowed, true) ? $value : 'petrol';
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_engine_size', [
            'type' => 'number',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                $engine = floatval($value);
                return max(0.8, min(8.0, $engine));
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_color', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                return self::sanitize_text_field_safe($value);
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_license_plate', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                return self::sanitize_text_field_safe($value);
            },
        ]);

        register_post_meta('vehicle', '_mhm_vehicle_availability', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => function($value) {
                $allowed = ['active', 'passive', 'maintenance', '1', '0'];
                if (in_array($value, $allowed, true)) {
                    if ($value === '1') return 'active';
                    if ($value === '0') return 'passive';
                    return $value;
                }
                return 'active';
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_features', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ]
            ],
            'sanitize_callback' => function($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_map(function($item) {
                    return self::sanitize_text_field_safe($item);
                }, $value);
            },
        ]);

        register_post_meta('vehicle', '_mhm_rentiva_equipment', [
            'type' => 'array',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string'
                    ]
                ]
            ],
            'sanitize_callback' => function($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_map(function($item) {
                    return self::sanitize_text_field_safe($item);
                }, $value);
            },
        ]);
    }

    /**
     * Get field settings
     */
    private static function get_field_settings(): array
    {
        return [
            'selected_details' => (array) get_option('mhm_selected_details', []),
            'selected_features' => (array) get_option('mhm_selected_features', []),
            'selected_equipment' => (array) get_option('mhm_selected_equipment', []),
            'custom_details' => (array) get_option('mhm_custom_details', []),
            'custom_features' => (array) get_option('mhm_custom_features', []),
            'custom_equipment' => (array) get_option('mhm_custom_equipment', []),
            'default_details' => (array) get_option('mhm_vehicle_details', self::get_default_details()),
            'default_features' => (array) get_option('mhm_vehicle_features', self::get_default_features()),
            'default_equipment' => (array) get_option('mhm_vehicle_equipment', self::get_default_equipment()),
            'custom_fields' => array_merge(
                (array) get_option('mhm_custom_details', []),
                (array) get_option('mhm_custom_features', []),
                (array) get_option('mhm_custom_equipment', [])
            )
        ];
    }

    /**
     * Get vehicle meta data
     */
    private static function get_vehicle_meta_data(int $post_id, array $custom_fields): array
    {
        $meta_keys = [
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
            '_mhm_vehicle_availability',
            '_mhm_rentiva_features',
            '_mhm_rentiva_equipment',
            '_mhm_removed_details',
            '_mhm_custom_details',
            '_mhm_details_order',
            '_mhm_features_order',
            '_mhm_equipment_order'
        ];
        
        foreach ($custom_fields as $field_key => $field_label) {
            $meta_keys[] = '_mhm_rentiva_' . $field_key;
        }

        $meta_data = [];
        foreach ($meta_keys as $key) {
            $meta_data[$key] = get_post_meta($post_id, $key, true);
        }
        
        return $meta_data;
    }

    /**
     * Build available fields
     */
    private static function build_available_fields(array $selected_fields, array $default_fields, array $custom_fields): array
    {
        $available_fields = [];
        
        foreach ($selected_fields as $key) {
            if (isset($default_fields[$key])) {
                $available_fields[$key] = $default_fields[$key];
            } elseif (isset($custom_fields[$key])) {
                $available_fields[$key] = $custom_fields[$key];
            }
        }
        
        return $available_fields;
    }

    /**
     * Check and update default options
     */
    private static function ensure_default_options(array &$available_details, array &$available_features, array &$available_equipment): void
    {
    }

    /**
     * AJAX handler: Save ordering changes
     */
    public static function ajax_save_item_order(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mhm_rentiva_vehicle_meta_action')) {
            wp_send_json_error(__('Security error', 'mhm-rentiva'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission error', 'mhm-rentiva'));
        }

        $grid_type = self::sanitize_text_field_safe($_POST['grid_type'] ?? '');
        $order = $_POST['order'] ?? [];

        if (empty($grid_type) || empty($order)) {
            wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('Post ID not found');
        }

        $meta_key = '_mhm_' . $grid_type . '_order';
        update_post_meta($post_id, $meta_key, $order);

        $option_key = 'mhm_vehicle_' . $grid_type;
        $current_data = get_option($option_key, []);
        
        if (!empty($current_data)) {
            $reordered_data = [];
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
     * Register Vehicle Gallery meta box
     */
    public static function register_gallery_meta_box(): void
    {
        add_meta_box(
            'mhm_rentiva_vehicle_gallery',
            __('Vehicle Gallery', 'mhm-rentiva'),
            [self::class, 'render_gallery_meta_box'],
            'vehicle',
            'side',
            'default'
        );
    }

    /**
     * Render Vehicle Gallery meta box
     */
    public static function render_gallery_meta_box(\WP_Post $post): void
    {
        $gallery_images = get_post_meta($post->ID, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];
        
        include MHM_RENTIVA_PLUGIN_PATH . 'src/Admin/Vehicle/Templates/vehicle-gallery.php';
    }

    /**
     * AJAX: Add gallery image
     */
    public static function ajax_add_gallery_image(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mhm_vehicle_gallery_nonce')) {
            wp_send_json_error(__('Security error', 'mhm-rentiva'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission error', 'mhm-rentiva'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $image_ids = array_map('intval', $_POST['image_ids'] ?? []);

        if (!$post_id || empty($image_ids)) {
            wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
        }

        $gallery_images = get_post_meta($post_id, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];
        
        $existing_ids = array_column($gallery_images, 'id');
        
        foreach ($image_ids as $image_id) {
            if (!in_array($image_id, $existing_ids) && count($gallery_images) < 10) {
                $image_url = wp_get_attachment_image_url($image_id, 'medium');
                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                $image_title = get_the_title($image_id);
                
                $gallery_images[] = [
                    'id' => $image_id,
                    'url' => $image_url,
                    'alt' => $image_alt,
                    'title' => $image_title,
                ];
            }
        }

        update_post_meta($post_id, '_mhm_rentiva_gallery_images', wp_json_encode($gallery_images));
        
        wp_send_json_success([
            'message' => __('Images successfully added', 'mhm-rentiva'),
            'gallery_images' => $gallery_images
        ]);
    }

    /**
     * AJAX: Remove gallery image
     */
    public static function ajax_remove_gallery_image(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mhm_vehicle_gallery_nonce')) {
            wp_send_json_error(__('Security error', 'mhm-rentiva'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission error', 'mhm-rentiva'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $image_id = intval($_POST['image_id'] ?? 0);

        if (!$post_id || !$image_id) {
            wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
        }

        $gallery_images = get_post_meta($post_id, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];
        
        $gallery_images = array_filter($gallery_images, function($image) use ($image_id) {
            return $image['id'] !== $image_id;
        });

        update_post_meta($post_id, '_mhm_rentiva_gallery_images', wp_json_encode(array_values($gallery_images)));
        
        wp_send_json_success([
            'message' => __('Image successfully removed', 'mhm-rentiva'),
            'gallery_images' => array_values($gallery_images)
        ]);
    }

    /**
     * AJAX: Reorder gallery images
     */
    public static function ajax_reorder_gallery_images(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mhm_vehicle_gallery_nonce')) {
            wp_send_json_error(__('Security error', 'mhm-rentiva'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission error', 'mhm-rentiva'));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $image_order = array_map('intval', $_POST['image_order'] ?? []);

        if (!$post_id || empty($image_order)) {
            wp_send_json_error(__('Invalid data', 'mhm-rentiva'));
        }

        $gallery_images = get_post_meta($post_id, '_mhm_rentiva_gallery_images', true);
        $gallery_images = $gallery_images ? json_decode($gallery_images, true) : [];
        
        $reordered_images = [];
        foreach ($image_order as $image_id) {
            foreach ($gallery_images as $image) {
                if ($image['id'] === $image_id) {
                    $reordered_images[] = $image;
                    break;
                }
            }
        }

        update_post_meta($post_id, '_mhm_rentiva_gallery_images', wp_json_encode($reordered_images));
        
        wp_send_json_success([
            'message' => __('Images successfully reordered', 'mhm-rentiva'),
            'gallery_images' => $reordered_images
        ]);
    }

    /**
     * Reorder meta boxes
     */
    public static function reorder_meta_boxes(): void
    {
        global $wp_meta_boxes;
        
        if (!isset($wp_meta_boxes['vehicle']['side'])) {
            return;
        }
        
        $side_boxes = $wp_meta_boxes['vehicle']['side'];
        
        $desired_order = [
            'submitdiv',
            'postimagediv',
            'mhm_rentiva_vehicle_gallery',
            'vehicle_categorydiv',
        ];
        
        $reordered_boxes = [];
        
        foreach ($desired_order as $box_id) {
            if (isset($side_boxes['high'][$box_id])) {
                $reordered_boxes['high'][$box_id] = $side_boxes['high'][$box_id];
                unset($side_boxes['high'][$box_id]);
            } elseif (isset($side_boxes['core'][$box_id])) {
                $reordered_boxes['core'][$box_id] = $side_boxes['core'][$box_id];
                unset($side_boxes['core'][$box_id]);
            } elseif (isset($side_boxes['default'][$box_id])) {
                $reordered_boxes['default'][$box_id] = $side_boxes['default'][$box_id];
                unset($side_boxes['default'][$box_id]);
            } elseif (isset($side_boxes['low'][$box_id])) {
                $reordered_boxes['low'][$box_id] = $side_boxes['low'][$box_id];
                unset($side_boxes['low'][$box_id]);
            }
        }
        
        foreach ($side_boxes as $priority => $boxes) {
            if (!isset($reordered_boxes[$priority])) {
                $reordered_boxes[$priority] = [];
            }
            $reordered_boxes[$priority] = array_merge($reordered_boxes[$priority], $boxes);
        }
        
        $wp_meta_boxes['vehicle']['side'] = $reordered_boxes;
    }
}
