<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Core\Status;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking Edit Meta Box
 * 
 * For editing existing bookings
 */
final class BookingEditMetaBox extends AbstractMetaBox
{
    protected static function get_post_type(): string
    {
        return 'vehicle_booking';
    }

    protected static function get_meta_box_id(): string
    {
        return 'mhm_rentiva_booking_edit';
    }

    protected static function get_title(): string
    {
        return __('Edit Booking Details', 'mhm-rentiva');
    }

    protected static function get_context(): string
    {
        return 'normal';
    }

    protected static function get_priority(): string
    {
        return 'high';
    }

    protected static function get_fields(): array
    {
        global $post, $pagenow;
        
        // Display only when editing an existing booking
        if ($pagenow !== 'post.php' || !$post || !$post->ID || $post->post_type !== 'vehicle_booking') {
            return [];
        }
        
        return [
            'mhm_booking_edit_fields' => [
                'title' => __('Edit Booking Details', 'mhm-rentiva'),
                'context' => 'normal',
                'priority' => 'high',
                'template' => 'render',
            ],
        ];
    }

    public static function register(): void
    {
        // Register meta box
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        
        // Scripts and styles
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        
        // Save handler
        add_action('save_post', [self::class, 'save_booking_details']);
    }
    
    public static function add_meta_boxes(): void
    {
        global $post, $pagenow;
        
        // Display only when editing an existing booking
        if ($pagenow !== 'post.php' || !$post || !$post->ID || $post->post_type !== 'vehicle_booking') {
            return;
        }
        
        add_meta_box(
            self::get_meta_box_id(),
            self::get_title(),
            [self::class, 'render'],
            self::get_post_type(),
            self::get_context(),
            self::get_priority()
        );
    }

    public static function enqueue_scripts(string $hook): void
    {
        global $post_type;
        
        // Load assets only on the booking edit screen
        if ($hook === 'post.php' && $post_type === 'vehicle_booking') {
            wp_enqueue_style(
                'mhm-booking-edit-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/booking-edit-meta.css',
                [],
                MHM_RENTIVA_VERSION
            );
            
            wp_enqueue_script(
                'mhm-booking-edit-meta',
                MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/booking-edit-meta.js',
                ['jquery'],
                MHM_RENTIVA_VERSION,
                true
            );
            
            
            // Localize script for AJAX usage
            wp_localize_script('mhm-booking-edit-meta', 'mhmBookingEdit', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mhm_booking_edit_nonce'),
                'text' => [
                    'saving' => __('Saving...', 'mhm-rentiva'),
                    'error' => __('An error occurred', 'mhm-rentiva'),
                    'success' => __('Booking updated', 'mhm-rentiva'),
                ]
            ]);
        }
    }

    public static function render(\WP_Post $post, array $args = []): void
    {
        wp_nonce_field('mhm_booking_edit_action', 'mhm_booking_edit_meta_nonce');
        
        // Fetch current booking data
        $vehicle_id = get_post_meta($post->ID, '_mhm_vehicle_id', true) ?: get_post_meta($post->ID, '_booking_vehicle_id', true);
        $pickup_date = get_post_meta($post->ID, '_mhm_pickup_date', true) ?: get_post_meta($post->ID, '_booking_pickup_date', true);
        $pickup_time = get_post_meta($post->ID, '_mhm_start_time', true) ?: get_post_meta($post->ID, '_mhm_pickup_time', true) ?: get_post_meta($post->ID, '_booking_pickup_time', true);
        $dropoff_date = get_post_meta($post->ID, '_mhm_dropoff_date', true) ?: get_post_meta($post->ID, '_booking_dropoff_date', true);
        $dropoff_time = get_post_meta($post->ID, '_mhm_end_time', true) ?: get_post_meta($post->ID, '_mhm_dropoff_time', true) ?: get_post_meta($post->ID, '_booking_dropoff_time', true);
        
        $guests = get_post_meta($post->ID, '_mhm_guests', true) ?: get_post_meta($post->ID, '_booking_guests', true) ?: 1;
        $status = Status::get($post->ID);
        
        echo '<div class="mhm-booking-edit-form">';
        
        // Vehicle information (read-only)
        echo '<div class="mhm-field-group">';
        echo '<label class="mhm-field-label">' . __('Vehicle', 'mhm-rentiva') . '</label>';
        if ($vehicle_id) {
            $vehicle = get_post($vehicle_id);
            echo '<div class="mhm-field-readonly">' . esc_html($vehicle ? $vehicle->post_title : __('Unknown Vehicle', 'mhm-rentiva')) . '</div>';
        } else {
            echo '<div class="mhm-field-readonly">' . __('No vehicle assigned', 'mhm-rentiva') . '</div>';
        }
        echo '</div>';
        
        // Booking details
        echo '<div class="mhm-booking-details">';
        echo '<h4>' . __('Booking Details', 'mhm-rentiva') . '</h4>';
        
        echo '<div class="mhm-field-row">';
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_edit_pickup_date" class="mhm-field-label">' . __('Pickup Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" id="mhm_booking_edit_pickup_date" name="mhm_edit_pickup_date" class="mhm-field-input" value="' . esc_attr($pickup_date) . '">';
        echo '</div>';
        
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_edit_pickup_time" class="mhm-field-label">' . __('Pickup Time', 'mhm-rentiva') . '</label>';
        echo '<input type="time" id="mhm_booking_edit_pickup_time" name="mhm_edit_pickup_time" class="mhm-field-input" value="' . esc_attr($pickup_time) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="mhm-field-row">';
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_edit_dropoff_date" class="mhm-field-label">' . __('Return Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" id="mhm_booking_edit_dropoff_date" name="mhm_edit_dropoff_date" class="mhm-field-input" value="' . esc_attr($dropoff_date) . '">';
        echo '</div>';
        
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_edit_dropoff_time" class="mhm-field-label">' . __('Return Time', 'mhm-rentiva') . '</label>';
        echo '<input type="time" id="mhm_booking_edit_dropoff_time" name="mhm_edit_dropoff_time" class="mhm-field-input" value="' . esc_attr($dropoff_time) . '">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="mhm-field-row">';
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_edit_guests" class="mhm-field-label">' . __('Number of Guests', 'mhm-rentiva') . '</label>';
        echo '<input type="number" id="mhm_booking_edit_guests" name="mhm_edit_guests" class="mhm-field-input" value="' . esc_attr($guests) . '" min="1" max="10">';
        echo '</div>';
        
        echo '<div class="mhm-field-group mhm-field-half">';
        echo '<label for="mhm_edit_status" class="mhm-field-label">' . __('Status', 'mhm-rentiva') . '</label>';
        echo '<select id="mhm_booking_edit_status" name="mhm_edit_status" class="mhm-field-select">';
        echo '<option value="pending"' . selected($status, 'pending', false) . '>' . __('Pending', 'mhm-rentiva') . '</option>';
        echo '<option value="confirmed"' . selected($status, 'confirmed', false) . '>' . __('Confirmed', 'mhm-rentiva') . '</option>';
        echo '<option value="in_progress"' . selected($status, 'in_progress', false) . '>' . __('In Progress', 'mhm-rentiva') . '</option>';
        echo '<option value="completed"' . selected($status, 'completed', false) . '>' . __('Completed', 'mhm-rentiva') . '</option>';
        echo '<option value="cancelled"' . selected($status, 'cancelled', false) . '>' . __('Cancelled', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        
        // Additional services selection
        echo '<div class="mhm-field-group">';
        echo '<label class="mhm-field-label">' . __('Additional Services', 'mhm-rentiva') . '</label>';
        
        // Fetch current add-ons
        $addons = get_posts([
            'post_type' => 'vehicle_addon',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);

        $available_addons = [];
        foreach ($addons as $addon) {
            $available_addons[] = [
                'id' => $addon->ID,
                'title' => $addon->post_title,
                'price' => get_post_meta($addon->ID, 'addon_price', true) ?: '0',
                'description' => $addon->post_excerpt,
                'required' => (bool) get_post_meta($addon->ID, 'addon_required', true)
            ];
        }
        
        // Fetch currently selected add-ons
        $selected_addons = get_post_meta($post->ID, '_mhm_selected_addons', true) ?: [];
        
        if (!empty($available_addons)) {
            echo '<div class="mhm-addon-selection">';
            echo '<p class="description">' . __('Select the additional services needed for this booking.', 'mhm-rentiva') . '</p>';
            
            echo '<div class="mhm-addon-grid">';
            foreach ($available_addons as $addon) {
                $checked = in_array($addon['id'], $selected_addons) ? 'checked' : '';
                $checked .= $addon['required'] ? ' disabled' : '';
                $required_text = $addon['required'] ? ' <span class="required">*</span>' : '';
                
                echo '<div class="mhm-addon-card">';
                echo '<label class="mhm-addon-item">';
                echo '<input type="checkbox" name="mhm_edit_selected_addons[]" value="' . esc_attr($addon['id']) . '" class="mhm-addon-checkbox" data-price="' . esc_attr($addon['price']) . '" ' . $checked . '>';
                echo '<div class="mhm-addon-content">';
                echo '<div class="mhm-addon-header">';
                echo '<span class="mhm-addon-title">' . esc_html($addon['title']) . $required_text . '</span>';
                echo '<span class="mhm-addon-price">+ ' . esc_html(number_format((float)$addon['price'], 2, ',', '.')) . ' ₺</span>';
                echo '</div>';
                if (!empty($addon['description'])) {
                    echo '<div class="mhm-addon-description">' . esc_html($addon['description']) . '</div>';
                }
                echo '</div>';
                echo '</label>';
                echo '</div>';
            }
            echo '</div>';
            
            echo '<div class="mhm-addon-total" style="display: none;">';
            echo '<strong>' . __('Additional Services Total:', 'mhm-rentiva') . ' <span class="mhm-addon-total-amount">0,00 ₺</span></strong>';
            echo '</div>';
            
            echo '</div>';
        } else {
            echo '<p class="description">' . __('No additional services available.', 'mhm-rentiva') . '</p>';
        }
        echo '</div>';
        
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Persist booking edits.
     */
    public static function save_booking_details(int $post_id): void
    {
        // Nonce validation
        if (!isset($_POST['mhm_booking_edit_meta_nonce']) || 
            !wp_verify_nonce($_POST['mhm_booking_edit_meta_nonce'], 'mhm_booking_edit_action')) {
            return;
        }

        // Capability check
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Ensure post type is vehicle_booking
        if (get_post_type($post_id) !== 'vehicle_booking') {
            return;
        }

        // Fetch and persist data
        $pickup_date = static::sanitize_text_field_safe($_POST['mhm_edit_pickup_date'] ?? '');
        $pickup_time = static::sanitize_text_field_safe($_POST['mhm_edit_pickup_time'] ?? '');
        $dropoff_date = static::sanitize_text_field_safe($_POST['mhm_edit_dropoff_date'] ?? '');
        $dropoff_time = static::sanitize_text_field_safe($_POST['mhm_edit_dropoff_time'] ?? '');
        $guests = max(1, intval($_POST['mhm_edit_guests'] ?? 1));
        $status = static::sanitize_text_field_safe($_POST['mhm_edit_status'] ?? 'pending');
        
        // Process selected add-ons
        $selected_addons = array_map('intval', $_POST['mhm_edit_selected_addons'] ?? []);
        $addon_details = [];
        $addon_total = 0;
        
        if (!empty($selected_addons)) {
            foreach ($selected_addons as $addon_id) {
                $addon_post = get_post($addon_id);
                if ($addon_post && $addon_post->post_type === 'vehicle_addon') {
                    $price = (float) get_post_meta($addon_id, 'addon_price', true);
                    $addon_details[] = [
                        'id' => $addon_id,
                        'title' => $addon_post->post_title,
                        'price' => $price
                    ];
                    $addon_total += $price;
                }
            }
        }

        // Update meta values
        update_post_meta($post_id, '_mhm_pickup_date', $pickup_date);
        update_post_meta($post_id, '_mhm_start_time', $pickup_time);
        update_post_meta($post_id, '_mhm_pickup_time', $pickup_time);
        update_post_meta($post_id, '_mhm_dropoff_date', $dropoff_date);
        update_post_meta($post_id, '_mhm_end_time', $dropoff_time);
        update_post_meta($post_id, '_mhm_dropoff_time', $dropoff_time);
        update_post_meta($post_id, '_mhm_guests', $guests);
        
        // Save add-on meta data
        update_post_meta($post_id, '_mhm_selected_addons', $selected_addons);
        update_post_meta($post_id, '_mhm_addon_details', $addon_details);
        update_post_meta($post_id, '_mhm_addon_total', $addon_total);

        // Update status
        $old_status = get_post_meta($post_id, '_mhm_status', true);
        Status::update_status($post_id, $status, get_current_user_id());
        
        // Append automatic status change note
        if ($old_status !== $status) {
            \MHMRentiva\Admin\Booking\Meta\BookingMeta::auto_add_status_change_note($post_id, $old_status, $status);
        }

        // Update legacy meta keys for backward compatibility
        update_post_meta($post_id, '_booking_pickup_date', $pickup_date);
        update_post_meta($post_id, '_booking_pickup_time', $pickup_time);
        update_post_meta($post_id, '_booking_dropoff_date', $dropoff_date);
        update_post_meta($post_id, '_booking_dropoff_time', $dropoff_time);
        update_post_meta($post_id, '_booking_guests', $guests);
    }
}

