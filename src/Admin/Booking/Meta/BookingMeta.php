<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\Core\MetaBoxes\AbstractMetaBox;

if (!defined('ABSPATH')) {
    exit;
}

// Include manual booking meta box
require_once __DIR__ . '/ManualBookingMetaBox.php';

// Include booking edit meta box
require_once __DIR__ . '/BookingEditMetaBox.php';

final class BookingMeta extends AbstractMetaBox
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

    /**
     * Flag to ensure register() is called only once
     */
    private static bool $registered = false;

    /**
     * Flag to ensure admin_action_editpost hook is registered only once
     */
    private static bool $editpostHookRegistered = false;

    protected static function get_post_type(): string
    {
        return 'vehicle_booking';
    }

    protected static function get_meta_box_id(): string
    {
        return 'mhm_rentiva_booking_status';
    }

    protected static function get_title(): string
    {
        return __('Booking Status', 'mhm-rentiva');
    }

    protected static function get_fields(): array
    {
        return [
            'mhm_rentiva_booking_status' => [
                'title' => __('Booking Status', 'mhm-rentiva'),
                'context' => 'side',
                'priority' => 'high',
                'template' => 'render_meta_box',
            ],
            'mhm_rentiva_payment_box' => [
                'title' => __('Payment', 'mhm-rentiva'),
                'context' => 'side',
                'priority' => 'default',
                'template' => 'render_payment_box',
            ],
            'mhm_rentiva_offline_box' => [
                'title' => __('Offline Payment', 'mhm-rentiva'),
                'context' => 'side',
                'priority' => 'default',
                'template' => 'render_offline_box',
            ],
        ];
    }

    /**
     * Registers meta box - only for existing bookings
     */
    public static function register(): void
    {
        // Exit if already registered
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        // Show meta box only for existing bookings
        add_action('add_meta_boxes', [self::class, 'add_meta_boxes']);
        
        // Load scripts
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
        
        // Save meta
        add_action('save_post', [self::class, 'save_meta'], 10, 2);
        
        // Email sending handler
        add_action('admin_post_mhm_rentiva_send_customer_email', [self::class, 'handle_send_customer_email']);
        
        // History note adding handler
        add_action('admin_post_mhm_rentiva_add_booking_history_note', [self::class, 'handle_add_booking_history_note']);
        
        // AJAX handlers
        add_action('wp_ajax_mhm_rentiva_send_customer_email', [self::class, 'ajax_send_customer_email']);
        add_action('wp_ajax_mhm_rentiva_add_booking_history_note', [self::class, 'ajax_add_booking_history_note']);
        add_action('wp_ajax_mhm_rentiva_update_booking', [self::class, 'ajax_update_booking']);
        
        // Hide WordPress standard "Update" button
        add_action('admin_footer', [self::class, 'hide_standard_update_button']);
        
        // Override WordPress post update completely for booking CPT
        add_action('admin_init', [self::class, 'intercept_booking_update'], 1);
        
        // Auto note adding hooks
        // Note: "Booking created" note is added manually (in ManualBookingMetaBox and BookingForm)
        add_action('mhm_booking_status_changed', [self::class, 'auto_add_status_change_note'], 10, 3);
        add_action('mhm_payment_status_changed', [self::class, 'auto_add_payment_note'], 10, 3);
        
        // Show admin notices
        add_action('admin_notices', [self::class, 'show_admin_notices']);
        
        // ✅ Auto calculation hook
        add_action('mhm_rentiva_booking_meta_updated', [self::class, 'on_booking_meta_updated'], 10, 3);
    }

    /**
     * Adds meta box - only for existing bookings
     */
    public static function add_meta_boxes(): void
    {
        global $post, $pagenow;
        
        // Show only for existing bookings (not when creating new booking)
        if ($pagenow === 'post-new.php' || !$post || !$post->ID || $post->post_type !== 'vehicle_booking') {
            return;
        }
        
        // Hide booking summary meta box (conflicts with BookingEditMetaBox)
        // add_meta_box(
        //     self::get_meta_box_id(),
        //     self::get_title(),
        //     [self::class, 'render_meta_box'],
        //     self::get_post_type(),
        //     'normal',
        //     'high'
        // );
        
        add_meta_box(
            'mhm_rentiva_payment_box',
            __('Payment', 'mhm-rentiva'),
            [self::class, 'render_payment_box'],
            self::get_post_type(),
            'side',
            'default'
        );
        
        // Offline payment meta box
        add_meta_box(
            'mhm_rentiva_offline_box',
            __('Offline Payment', 'mhm-rentiva'),
            [self::class, 'render_offline_box'],
            self::get_post_type(),
            'side',
            'default'
        );
        
        // Customer email meta box
        add_meta_box(
            'mhm_rentiva_customer_email_box',
            __('Send Email to Customer', 'mhm-rentiva'),
            [self::class, 'render_customer_email_box'],
            self::get_post_type(),
            'side',
            'default'
        );
        
        // Booking history meta box
        add_meta_box(
            'mhm_rentiva_booking_history_box',
            __('Booking History', 'mhm-rentiva'),
            [self::class, 'render_booking_history_box'],
            self::get_post_type(),
            'normal',
            'low'
        );

        // Receipt review meta box
        add_meta_box(
            'mhm_rentiva_receipt_box',
            __('Payment Receipt', 'mhm-rentiva'),
            [self::class, 'render_receipt_box'],
            self::get_post_type(),
            'side',
            'default'
        );
    }

    public static function enqueue_scripts(string $hook): void
    {
        global $post_type;
        
        // Managed in AssetManager.php
    }

    /**
     * Render receipt review meta box
     */
    public static function render_receipt_box(\WP_Post $post): void
    {
        $attach_id = (int) get_post_meta($post->ID, '_mhm_receipt_attachment_id', true);
        $status = get_post_meta($post->ID, '_mhm_receipt_status', true);
        $url = $attach_id ? wp_get_attachment_url($attach_id) : '';
        $note = get_post_meta($post->ID, '_mhm_receipt_note', true);

        wp_nonce_field('mhm_rentiva_receipt_review', 'mhm_receipt_nonce');

        // Template kullan
        $template_data = [
            'attach_id' => $attach_id,
            'url' => $url,
            'status' => $status,
            'note' => $note
        ];
        
        $template_path = plugin_dir_path(__FILE__) . '../../../templates/admin/booking-meta/receipt-box.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback - old method
            echo '<div class="mhm-receipt-box">';
            if ($attach_id && $url) {
                echo '<p><strong>' . esc_html__('Receipt file:', 'mhm-rentiva') . '</strong><br/>';
                echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html__('View / Download', 'mhm-rentiva') . '</a></p>';
            } else {
                echo '<p>' . esc_html__('No receipt uploaded.', 'mhm-rentiva') . '</p>';
            }
            echo '<p><strong>' . esc_html__('Status:', 'mhm-rentiva') . '</strong> ' . esc_html($status ?: '-') . '</p>';
            echo '<p><label for="mhm_receipt_note"><strong>' . esc_html__('Admin Note', 'mhm-rentiva') . '</strong></label><br/>';
            echo '<textarea id="mhm_receipt_note" name="mhm_receipt_note" rows="3" style="width:100%">' . esc_textarea($note) . '</textarea></p>';
            echo '<p>';
            echo '<button type="submit" name="mhm_receipt_action" value="approve" class="button button-primary">' . esc_html__('Approve Receipt', 'mhm-rentiva') . '</button> ';
            echo '<button type="submit" name="mhm_receipt_action" value="reject" class="button">' . esc_html__('Reject Receipt', 'mhm-rentiva') . '</button>';
            echo '</p>';
            echo '</div>';
        }
    }

    

    /**
     * Email customer about receipt status
     */
    private static function email_customer_receipt_status(int $booking_id, string $status): void
    {
        $user_id = (int) get_post_field('post_author', $booking_id);
        $user = get_user_by('id', $user_id);
        if (!$user) return;

        // Get customer name from booking meta
        $customer_name = get_post_meta($booking_id, '_mhm_customer_name', true);
        if (!$customer_name) {
            $customer_name = $user->display_name ?: $user->user_login;
        }

        $subject = ($status === 'approved') 
            ? __('Your payment receipt has been approved', 'mhm-rentiva') 
            : __('Your payment receipt has been rejected', 'mhm-rentiva');
            
        $note = get_post_meta($booking_id, '_mhm_receipt_note', true);
        $booking_title = get_the_title($booking_id);
        $account_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();
        $link = add_query_arg(['endpoint' => 'payment-history'], $account_url);

        // Prepare template data
        $template_data = [
            'status' => $status,
            'customer_name' => $customer_name,
            'booking_title' => $booking_title,
            'admin_note' => $note,
            'account_url' => $link
        ];

        // Load HTML template
        $template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/emails/receipt-status-email.html.php';
        if (file_exists($template_path)) {
            ob_start();
            // Pass template data as $args for template
            $args = $template_data;
            include $template_path;
            $html_body = ob_get_clean();
        } else {
            // Fallback to plain text
            $html_body = self::get_receipt_email_plain_text($template_data);
        }

        // Send HTML email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        wp_mail($user->user_email, $subject, $html_body, $headers);
    }

    /**
     * Get plain text fallback for receipt email
     */
    private static function get_receipt_email_plain_text(array $data): string
    {
        $status_text = ($data['status'] === 'approved') 
            ? __('Your payment receipt has been approved', 'mhm-rentiva')
            : __('Your payment receipt has been rejected', 'mhm-rentiva');

        $body = sprintf("%s\n\n%s: %s\n%s: %s\n\n%s: %s",
            $status_text,
            __('Booking', 'mhm-rentiva'), $data['booking_title'],
            __('Status', 'mhm-rentiva'), ucfirst($data['status']),
            __('Details', 'mhm-rentiva'), $data['account_url']
        );
        
        if (!empty($data['admin_note'])) {
            $body .= "\n\n" . __('Admin Note', 'mhm-rentiva') . ": " . $data['admin_note'];
        }

        return $body;
    }

    public static function render_meta_box(\WP_Post $post, array $args = []): void
    {
        // Nonce field
        wp_nonce_field('mhm_rentiva_booking_meta_action', 'mhm_rentiva_booking_meta_main_nonce');

        // Mevcut durum
        $current_status = Status::get($post->ID);

        // Booking details
        $vehicle_id = (int) get_post_meta($post->ID, '_mhm_vehicle_id', true);
        $pickup_date = get_post_meta($post->ID, '_mhm_pickup_date', true);
        $pickup_time = get_post_meta($post->ID, '_mhm_pickup_time', true);
        $dropoff_date = get_post_meta($post->ID, '_mhm_dropoff_date', true);
        $dropoff_time = get_post_meta($post->ID, '_mhm_dropoff_time', true);
        $rental_days = (int) get_post_meta($post->ID, '_mhm_rental_days', true);
        $total_price = (float) get_post_meta($post->ID, '_mhm_total_price', true);
        $contact_name = get_post_meta($post->ID, '_mhm_contact_name', true);
        $contact_email = get_post_meta($post->ID, '_mhm_contact_email', true);

        echo '<div class="mhm-rentiva-wrap">';

        // Status selection
        echo '<div>';
        echo '<label for="mhm_booking_status_main">' . esc_html__('Status', 'mhm-rentiva') . '</label>';
        echo '<select id="mhm_booking_status_main" name="mhm_booking_status" data-current-status="' . esc_attr($current_status) . '">';
        
        foreach (Status::allowed() as $status) {
            $selected = selected($current_status, $status, false);
            $label = Status::get_label($status);
            echo '<option value="' . esc_attr($status) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';

        // Booking summary
        echo '<div class="booking-summary">';
        echo '<h4>' . esc_html__('Booking Summary', 'mhm-rentiva') . '</h4>';

        // Vehicle
        if ($vehicle_id) {
            $vehicle_title = get_the_title($vehicle_id);
            $vehicle_link = get_edit_post_link($vehicle_id);
            echo '<p><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong> ';
            if ($vehicle_link) {
                echo '<a href="' . esc_url($vehicle_link) . '" target="_blank">' . esc_html($vehicle_title) . '</a>';
            } else {
                echo esc_html($vehicle_title);
            }
            echo '</p>';
        }

        // Editable booking details
        $customer_name = get_post_meta($post->ID, '_mhm_customer_name', true) ?: get_post_meta($post->ID, '_booking_customer_name', true);
        $customer_email = get_post_meta($post->ID, '_mhm_customer_email', true) ?: get_post_meta($post->ID, '_booking_customer_email', true);
        $customer_phone = get_post_meta($post->ID, '_mhm_customer_phone', true) ?: get_post_meta($post->ID, '_booking_customer_phone', true);
        $guests = get_post_meta($post->ID, '_mhm_guests', true) ?: get_post_meta($post->ID, '_booking_guests', true) ?: 1;
        $payment_method = get_post_meta($post->ID, '_mhm_payment_method', true) ?: get_post_meta($post->ID, '_booking_payment_method', true);
        $notes = $post->post_content;

        // Get time data from correct meta keys
        $pickup_time_correct = get_post_meta($post->ID, '_mhm_start_time', true) ?: get_post_meta($post->ID, '_mhm_pickup_time', true) ?: get_post_meta($post->ID, '_booking_pickup_time', true);
        $dropoff_time_correct = get_post_meta($post->ID, '_mhm_end_time', true) ?: get_post_meta($post->ID, '_mhm_dropoff_time', true) ?: get_post_meta($post->ID, '_booking_dropoff_time', true);

        echo '<div class="edit-section">';
        echo '<h5>' . esc_html__('Customer Information', 'mhm-rentiva') . '</h5>';
        
        echo '<div class="field-row">';
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_customer_name">' . esc_html__('Customer Name', 'mhm-rentiva') . '</label>';
        echo '<input type="text" id="mhm_edit_customer_name" name="mhm_edit_customer_name" value="' . esc_attr($customer_name) . '" class="regular-text">';
        echo '</div>';
        
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_customer_phone">' . esc_html__('Phone', 'mhm-rentiva') . '</label>';
        echo '<input type="text" id="mhm_edit_customer_phone" name="mhm_edit_customer_phone" value="' . esc_attr($customer_phone) . '" class="regular-text">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_customer_email">' . esc_html__('Email', 'mhm-rentiva') . '</label>';
        echo '<input type="email" id="mhm_edit_customer_email" name="mhm_edit_customer_email" value="' . esc_attr($customer_email) . '" class="regular-text">';
        echo '</div>';
        echo '</div>';

        echo '<div class="edit-section">';
        echo '<h5>' . esc_html__('Booking Details', 'mhm-rentiva') . '</h5>';
        
        echo '<div class="field-row">';
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_pickup_date">' . esc_html__('Pickup Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" id="mhm_edit_pickup_date" name="mhm_edit_pickup_date" value="' . esc_attr($pickup_date) . '" class="regular-text">';
        echo '</div>';
        
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_pickup_time">' . esc_html__('Pickup Time', 'mhm-rentiva') . '</label>';
        echo '<input type="time" id="mhm_edit_pickup_time" name="mhm_edit_pickup_time" value="' . esc_attr($pickup_time_correct) . '" class="regular-text">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_dropoff_date">' . esc_html__('Return Date', 'mhm-rentiva') . '</label>';
        echo '<input type="date" id="mhm_edit_dropoff_date" name="mhm_edit_dropoff_date" value="' . esc_attr($dropoff_date) . '" class="regular-text">';
        echo '</div>';
        
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_dropoff_time">' . esc_html__('Return Time', 'mhm-rentiva') . '</label>';
        echo '<input type="time" id="mhm_edit_dropoff_time" name="mhm_edit_dropoff_time" value="' . esc_attr($dropoff_time_correct) . '" class="regular-text">';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-row">';
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_guests">' . esc_html__('Number of Guests', 'mhm-rentiva') . '</label>';
        echo '<input type="number" id="mhm_edit_guests" name="mhm_edit_guests" value="' . esc_attr($guests) . '" min="1" max="10" class="small-text">';
        echo '</div>';
        
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_payment_method">' . esc_html__('Payment Method', 'mhm-rentiva') . '</label>';
        echo '<select id="mhm_edit_payment_method" name="mhm_edit_payment_method" class="regular-text">';
        echo '<option value="offline"' . selected($payment_method, 'offline', false) . '>' . esc_html__('Offline', 'mhm-rentiva') . '</option>';
        echo '<option value="online"' . selected($payment_method, 'online', false) . '>' . esc_html__('Online', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';
        
        echo '<div class="field-group">';
        echo '<label for="mhm_edit_notes">' . esc_html__('Notes', 'mhm-rentiva') . '</label>';
        echo '<textarea id="mhm_edit_notes" name="mhm_edit_notes" rows="3" class="large-text">' . esc_textarea($notes) . '</textarea>';
        echo '</div>';
        echo '</div>';

        // Read-only summary information
        echo '<div class="readonly-section">';
        echo '<h5>' . esc_html__('Booking Summary', 'mhm-rentiva') . '</h5>';

        // Vehicle (read-only)
        if ($vehicle_id) {
            $vehicle_title = get_the_title($vehicle_id);
            $vehicle_link = get_edit_post_link($vehicle_id);
            echo '<p><strong>' . esc_html__('Vehicle:', 'mhm-rentiva') . '</strong> ';
            if ($vehicle_link) {
                echo '<a href="' . esc_url($vehicle_link) . '" target="_blank">' . esc_html($vehicle_title) . '</a>';
            } else {
                echo esc_html($vehicle_title);
            }
            echo '</p>';
        }

        // Days count (read-only) - Can be updated via JavaScript
        if ($rental_days > 0) {
            echo '<p><strong>' . esc_html__('Days:', 'mhm-rentiva') . '</strong> <span id="mhm_rental_days_display">' . esc_html((string) $rental_days) . '</span></p>';
        }

        // Total price (read-only) - Can be updated via JavaScript
        if ($total_price > 0) {
            echo '<p><strong>' . esc_html__('Total:', 'mhm-rentiva') . '</strong> <span id="mhm_total_price_display">' . esc_html(self::format_price($total_price)) . '</span></p>';
        }
        echo '</div>';

        echo '</div>';

        echo '</div>';
    }

    public static function save_meta(int $post_id, \WP_Post $post): void
    {
        // Nonce check
        if (!isset($_POST['mhm_rentiva_booking_meta_main_nonce']) || 
            !wp_verify_nonce($_POST['mhm_rentiva_booking_meta_main_nonce'], 'mhm_rentiva_booking_meta_action')) {
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

        // Status update
        if (isset($_POST['mhm_booking_status'])) {
            $new_status = self::sanitize_text_field_safe($_POST['mhm_booking_status']);
            $actor_user_id = get_current_user_id();
            
            Status::update_status($post_id, $new_status, $actor_user_id);
        }

        // Update booking details
        $customer_name = self::sanitize_text_field_safe($_POST['mhm_edit_customer_name'] ?? '');
        $customer_email = sanitize_email((string) ($_POST['mhm_edit_customer_email'] ?? ''));
        $customer_phone = self::sanitize_text_field_safe($_POST['mhm_edit_customer_phone'] ?? '');
        $pickup_date = self::sanitize_text_field_safe($_POST['mhm_edit_pickup_date'] ?? '');
        $pickup_time = self::sanitize_text_field_safe($_POST['mhm_edit_pickup_time'] ?? '');
        $dropoff_date = self::sanitize_text_field_safe($_POST['mhm_edit_dropoff_date'] ?? '');
        $dropoff_time = self::sanitize_text_field_safe($_POST['mhm_edit_dropoff_time'] ?? '');
        $guests = max(1, intval($_POST['mhm_edit_guests'] ?? 1));
        $payment_method = self::sanitize_text_field_safe($_POST['mhm_edit_payment_method'] ?? 'offline');
        $notes = sanitize_textarea_field((string) ($_POST['mhm_edit_notes'] ?? ''));

        // Update meta data
        update_post_meta($post_id, '_mhm_customer_name', $customer_name);
        update_post_meta($post_id, '_mhm_customer_email', $customer_email);
        update_post_meta($post_id, '_mhm_customer_phone', $customer_phone);
        update_post_meta($post_id, '_mhm_pickup_date', $pickup_date);
        update_post_meta($post_id, '_mhm_start_time', $pickup_time); // Correct meta key
        update_post_meta($post_id, '_mhm_dropoff_date', $dropoff_date);
        update_post_meta($post_id, '_mhm_end_time', $dropoff_time); // Correct meta key
        update_post_meta($post_id, '_mhm_guests', $guests);
        update_post_meta($post_id, '_mhm_payment_method', $payment_method);

        // ✅ Auto calculation - When date is changed
        if ($pickup_date && $dropoff_date) {
            self::recalculate_booking_costs($post_id, $pickup_date, $dropoff_date);
        }

        // Update old meta keys for compatibility
        update_post_meta($post_id, '_mhm_pickup_time', $pickup_time);
        update_post_meta($post_id, '_mhm_dropoff_time', $dropoff_time);
        update_post_meta($post_id, '_booking_customer_name', $customer_name);
        
        // ✅ WordPress hook with auto calculation
        do_action('mhm_rentiva_booking_meta_updated', $post_id, $pickup_date, $dropoff_date);
        update_post_meta($post_id, '_booking_customer_email', $customer_email);
        update_post_meta($post_id, '_booking_customer_phone', $customer_phone);
        update_post_meta($post_id, '_booking_pickup_date', $pickup_date);
        update_post_meta($post_id, '_booking_pickup_time', $pickup_time);
        update_post_meta($post_id, '_booking_dropoff_date', $dropoff_date);
        update_post_meta($post_id, '_booking_dropoff_time', $dropoff_time);
        update_post_meta($post_id, '_booking_guests', $guests);
        update_post_meta($post_id, '_booking_payment_method', $payment_method);

        // Update notes
        if ($notes !== get_post_field('post_content', $post_id)) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $notes
            ]);
        }

        // Receipt review processing
        if (isset($_POST['mhm_receipt_nonce']) && wp_verify_nonce(self::sanitize_text_field_safe($_POST['mhm_receipt_nonce']), 'mhm_rentiva_receipt_review')) {
            if (isset($_POST['mhm_receipt_note'])) {
                update_post_meta($post_id, '_mhm_receipt_note', sanitize_textarea_field((string) ($_POST['mhm_receipt_note'] ?? '')));
            }
            if (!empty($_POST['mhm_receipt_action'])) {
                $action = self::sanitize_text_field_safe($_POST['mhm_receipt_action']);
                if ($action === 'approve') {
                    update_post_meta($post_id, '_mhm_receipt_status', 'approved');
                    self::email_customer_receipt_status($post_id, 'approved');
                } elseif ($action === 'reject') {
                    update_post_meta($post_id, '_mhm_receipt_status', 'rejected');
                    self::email_customer_receipt_status($post_id, 'rejected');
                }
            }
        }
    }

    public static function render_payment_box(\WP_Post $post): void
    {
        $payStatus = (string) get_post_meta($post->ID, '_mhm_payment_status', true);
        $amount    = (int) get_post_meta($post->ID, '_mhm_payment_amount', true);
        
        // If _mhm_payment_amount is empty, use _mhm_total_price
        if (!$amount) {
            $total_price = (float) get_post_meta($post->ID, '_mhm_total_price', true);
            $amount = $total_price * 100; // Convert to cents
        }
        
        $currency  = (string) get_post_meta($post->ID, '_mhm_payment_currency', true);
        $gateway   = (string) get_post_meta($post->ID, '_mhm_payment_gateway', true);
        $receiptId = (int) get_post_meta($post->ID, '_mhm_offline_receipt_id', true);

        $refundId  = (string) get_post_meta($post->ID, '_mhm_refund_id', true);
        $refundSt  = (string) get_post_meta($post->ID, '_mhm_refund_status', true);
        $refunded  = (int) get_post_meta($post->ID, '_mhm_refunded_amount', true);

        if ($currency === '') {
            $currency = is_callable([Settings::class, 'get']) ? (string) Settings::get('currency', 'USD') : 'USD';
        }
        $gatewayLabel = $gateway !== '' ? $gateway : ($receiptId ? 'offline' : '—');

        // Depozito bilgilerini al
        $deposit_amount = (float) get_post_meta($post->ID, '_mhm_deposit_amount', true);
        $remaining_amount = (float) get_post_meta($post->ID, '_mhm_remaining_amount', true);
        $payment_type = get_post_meta($post->ID, '_mhm_payment_type', true);
        
        // Template kullan
        $template_data = [
            'payStatus' => $payStatus,
            'amount' => $amount,
            'currency' => $currency,
            'payment_type' => $payment_type,
            'deposit_amount' => $deposit_amount,
            'remaining_amount' => $remaining_amount,
            'gatewayLabel' => $gatewayLabel,
            'gateway' => $gateway,
            'refundId' => $refundId,
            'refundSt' => $refundSt,
            'refunded' => $refunded
        ];
        
        $template_path = plugin_dir_path(__FILE__) . '../../../templates/admin/booking-meta/payment-box.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback - old method
            echo '<p><strong>' . esc_html__('Payment Status', 'mhm-rentiva') . ':</strong> ' . esc_html($payStatus ?: 'unpaid') . '</p>';
            echo '<p><strong>' . esc_html__('Amount', 'mhm-rentiva') . ':</strong> ' . esc_html(number_format_i18n($amount / 100, 2)) . ' ' . esc_html(strtoupper($currency)) . '</p>';
            
            if ($payment_type === 'deposit' && $deposit_amount > 0) {
                echo '<p><strong>' . esc_html__('Deposit Amount', 'mhm-rentiva') . ':</strong> ' . esc_html(number_format_i18n($deposit_amount, 2)) . ' ' . esc_html(strtoupper($currency)) . '</p>';
                echo '<p><strong>' . esc_html__('Remaining Amount', 'mhm-rentiva') . ':</strong> ' . esc_html(number_format_i18n($remaining_amount, 2)) . ' ' . esc_html(strtoupper($currency)) . '</p>';
            }
            
            echo '<p><strong>' . esc_html__('Payment Method', 'mhm-rentiva') . ':</strong> ' . esc_html(strtoupper($gatewayLabel)) . '</p>';

            if ($refundId) {
                echo '<p><strong>' . esc_html__('Last Refund', 'mhm-rentiva') . ':</strong><br/>';
                echo esc_html(sprintf('%s – %s (%s %s)', $refundId, $refundSt ?: '-', number_format_i18n($refunded / 100, 2), $currency)) . '</p>';
            }
        }

        // Refund button (only when paid)
        if ($payStatus === 'paid') {
            $nonce = wp_create_nonce('wp_rest');
            $rest  = esc_url_raw(get_rest_url(null, 'mhm-rentiva/v1/payments/refund'));
            echo '<hr/>';
            echo '<div id="mhm-refund-msg" class="notice inline" style="display:none;"></div>';
            echo '<button type="button" class="button button-secondary" id="mhm-refund-btn" data-booking="' . esc_attr((string) $post->ID) . '" data-rest="' . esc_attr($rest) . '" data-nonce="' . esc_attr($nonce) . '">' . esc_html__('Process Full Refund', 'mhm-rentiva') . '</button>';
        } else {
            echo '<p class="description">' . esc_html__('Refund is only available after successful payment.', 'mhm-rentiva') . '</p>';
        }
    }

    public static function render_offline_box(\WP_Post $post): void
    {
        $aid = (int) get_post_meta($post->ID, '_mhm_offline_receipt_id', true);
        $pay = (string) get_post_meta($post->ID, '_mhm_payment_status', true) ?: 'unpaid';
        
        echo '<div class="mhm-offline-box">';
        echo '<p><strong>' . esc_html__('Payment Status', 'mhm-rentiva') . ':</strong> ' . esc_html($pay) . '</p>';
        
        if ($aid) {
            $url = wp_get_attachment_url($aid);
            echo '<p><a href="' . esc_url($url) . '" target="_blank" class="button button-small">' . esc_html__('View Receipt', 'mhm-rentiva') . '</a></p>';
        } else {
            echo '<p class="description">' . esc_html__('No receipt uploaded yet.', 'mhm-rentiva') . '</p>';
        }
        
        if (in_array($pay, ['pending_verification'], true)) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('mhm_rentiva_offline_action', 'mhm_rentiva_offline_nonce');
            echo '<input type="hidden" name="action" value="mhm_rentiva_offline_verify"/>';
            echo '<input type="hidden" name="booking_id" value="' . (int) $post->ID . '"/>';
            echo '<p>';
            echo '<button class="button button-primary button-small" name="decision" value="approve" style="width: 100%; margin-bottom: 5px;">' . esc_html__('Approve', 'mhm-rentiva') . '</button><br/>';
            echo '<button class="button button-small" name="decision" value="reject" style="width: 100%;">' . esc_html__('Reject', 'mhm-rentiva') . '</button>';
            echo '</p>';
            echo '</form>';
        }
        echo '</div>';
    }

    /**
     * Customer email sending meta box
     */
    public static function render_customer_email_box(\WP_Post $post): void
    {
        $customer_email = get_post_meta($post->ID, '_mhm_customer_email', true);
        $customer_name = get_post_meta($post->ID, '_mhm_customer_name', true);
        
        if (!$customer_email) {
            echo '<p class="description">' . __('No customer email found.', 'mhm-rentiva') . '</p>';
            return;
        }
        
        echo '<div class="mhm-customer-email-box">';
        echo '<p><strong>' . __('Customer:', 'mhm-rentiva') . '</strong> ' . esc_html($customer_name ?: 'N/A') . '</p>';
        echo '<p><strong>' . __('Email:', 'mhm-rentiva') . '</strong> ' . esc_html($customer_email) . '</p>';
        
        echo '<hr style="margin: 15px 0;">';
        
        // Email types
        echo '<h4>' . __('Send Email', 'mhm-rentiva') . '</h4>';
        
        echo '<form class="mhm-email-form" data-booking-id="' . (int) $post->ID . '">';
        wp_nonce_field('mhm_rentiva_send_email', 'mhm_rentiva_email_nonce');
        
        echo '<p>';
        echo '<label for="email_type">' . __('Email Type:', 'mhm-rentiva') . '</label><br/>';
        echo '<select id="email_type" name="email_type" class="widefat" style="margin-bottom: 10px;">';
        echo '<option value="booking_confirmation">' . __('Booking Confirmation', 'mhm-rentiva') . '</option>';
        echo '<option value="payment_reminder">' . __('Payment Reminder', 'mhm-rentiva') . '</option>';
        echo '<option value="booking_reminder">' . __('Booking Reminder', 'mhm-rentiva') . '</option>';
        echo '<option value="booking_cancelled">' . __('Booking Cancelled', 'mhm-rentiva') . '</option>';
        echo '<option value="custom">' . __('Custom Message', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="email_subject">' . __('Subject:', 'mhm-rentiva') . '</label><br/>';
        echo '<input type="text" id="email_subject" name="email_subject" class="widefat" placeholder="' . __('Email subject...', 'mhm-rentiva') . '" style="margin-bottom: 10px;">';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="email_message">' . __('Message:', 'mhm-rentiva') . '</label><br/>';
        echo '<textarea id="email_message" name="email_message" rows="4" class="widefat" placeholder="' . __('Your message...', 'mhm-rentiva') . '" style="margin-bottom: 10px;"></textarea>';
        echo '</p>';
        
        echo '<p>';
        echo '<button type="submit" class="button button-primary" style="width: 100%;">' . __('Send Email', 'mhm-rentiva') . '</button>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
    }

    /**
     * Customer email sending handler
     */
    public static function handle_send_customer_email(): void
    {
        // Nonce check
        if (!isset($_POST['mhm_rentiva_email_nonce']) || 
            !wp_verify_nonce($_POST['mhm_rentiva_email_nonce'], 'mhm_rentiva_send_email')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
        }
        
        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to send emails.', 'mhm-rentiva'));
        }
        
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $email_type = self::sanitize_text_field_safe($_POST['email_type'] ?? '');
        $subject = self::sanitize_text_field_safe($_POST['email_subject'] ?? '');
        $message = sanitize_textarea_field((string) ($_POST['email_message'] ?? ''));
        
        if (!$booking_id) {
            wp_die(__('Invalid booking ID.', 'mhm-rentiva'));
        }
        
        $customer_email = get_post_meta($booking_id, '_mhm_customer_email', true);
        $customer_name = get_post_meta($booking_id, '_mhm_customer_name', true);
        
        if (!$customer_email) {
            wp_die(__('Customer email not found.', 'mhm-rentiva'));
        }
        
        // Prepare subject and message based on email type
        $email_data = self::prepare_email_content($booking_id, $email_type, $subject, $message);
        
        // Send email
        $sent = wp_mail(
            $customer_email,
            $email_data['subject'],
            $email_data['message'],
            [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            ]
        );
        
        if ($sent) {
            // JavaScript redirect kullan
            $redirect_url = add_query_arg([
                'post' => $booking_id,
                'action' => 'edit',
                'message' => 'email_sent'
            ], admin_url('post.php'));
            
            // Redirect via JavaScript
            echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
            echo '<p>' . __('Email sent successfully! Redirecting...', 'mhm-rentiva') . '</p>';
            exit;
        } else {
            wp_die(__('Failed to send email.', 'mhm-rentiva'));
        }
    }
    
    /**
     * Prepares email content
     */
    private static function prepare_email_content(int $booking_id, string $email_type, string $subject, string $message): array
    {
        $customer_name = get_post_meta($booking_id, '_mhm_customer_name', true);
        $vehicle_id = get_post_meta($booking_id, '_mhm_vehicle_id', true);
        $vehicle_name = $vehicle_id ? get_the_title($vehicle_id) : __('Unknown Vehicle', 'mhm-rentiva');
        
        $pickup_date = get_post_meta($booking_id, '_mhm_pickup_date', true);
        $dropoff_date = get_post_meta($booking_id, '_mhm_dropoff_date', true);
        $total_amount = (float) get_post_meta($booking_id, '_mhm_total_price', true);
        
        // Default subjects
        $default_subjects = [
            /* translators: %s placeholder. */
            'booking_confirmation' => sprintf(__('Booking Confirmation - %s', 'mhm-rentiva'), $vehicle_name),
            /* translators: %s placeholder. */
            'payment_reminder' => sprintf(__('Payment Reminder - %s', 'mhm-rentiva'), $vehicle_name),
            /* translators: %s placeholder. */
            'booking_reminder' => sprintf(__('Booking Reminder - %s', 'mhm-rentiva'), $vehicle_name),
            /* translators: %s placeholder. */
            'booking_cancelled' => sprintf(__('Booking Cancelled - %s', 'mhm-rentiva'), $vehicle_name),
            'custom' => $subject ?: __('Message from ' . get_bloginfo('name'), 'mhm-rentiva')
        ];
        
        // Default messages
        $default_messages = [
            'booking_confirmation' => sprintf(
                /* translators: 1: customer name; 2: vehicle name; 3: pickup date; 4: return date; 5: total amount. */
                __('Hello %1$s,<br><br>Your booking for %2$s has been confirmed.<br><br>Pickup Date: %3$s<br>Return Date: %4$s<br>Total Amount: %5$s<br><br>Thank you for choosing us!', 'mhm-rentiva'),
                $customer_name,
                $vehicle_name,
                $pickup_date,
                $dropoff_date,
                self::format_price((float) $total_amount)
            ),
            'payment_reminder' => sprintf(
                /* translators: 1: customer name; 2: vehicle name; 3: total amount. */
                __('Hello %1$s,<br><br>This is a reminder about your payment for %2$s.<br><br>Total Amount: %3$s<br><br>Please complete your payment as soon as possible.', 'mhm-rentiva'),
                $customer_name,
                $vehicle_name,
                self::format_price((float) $total_amount)
            ),
            'booking_reminder' => sprintf(
                /* translators: 1: customer name; 2: vehicle name; 3: pickup date; 4: return date. */
                __('Hello %1$s,<br><br>This is a reminder about your upcoming booking for %2$s.<br><br>Pickup Date: %3$s<br>Return Date: %4$s<br><br>We look forward to serving you!', 'mhm-rentiva'),
                $customer_name,
                $vehicle_name,
                $pickup_date,
                $dropoff_date
            ),
            'booking_cancelled' => sprintf(
                /* translators: 1: customer name; 2: vehicle name. */
                __('Hello %1$s,<br><br>We regret to inform you that your booking for %2$s has been cancelled.<br><br>If you have any questions, please contact us.', 'mhm-rentiva'),
                $customer_name,
                $vehicle_name
            ),
            'custom' => $message ?: __('You have received a message from ' . get_bloginfo('name'), 'mhm-rentiva')
        ];
        
        return [
            'subject' => $subject ?: $default_subjects[$email_type],
            'message' => $message ?: $default_messages[$email_type]
        ];
    }

    /**
     * Booking history meta box
     */
    public static function render_booking_history_box(\WP_Post $post): void
    {
        $history = get_post_meta($post->ID, '_mhm_booking_history', true) ?: [];
        
        echo '<div class="mhm-booking-history-box">';
        
        // Yeni not ekleme formu
        echo '<div class="mhm-add-history-note">';
        echo '<h4>' . __('Add Note', 'mhm-rentiva') . '</h4>';
        
        echo '<form class="mhm-history-form" data-booking-id="' . (int) $post->ID . '">';
        wp_nonce_field('mhm_rentiva_add_history_note', 'mhm_rentiva_history_nonce');
        
        echo '<p>';
        echo '<label for="note_content">' . __('Note:', 'mhm-rentiva') . '</label><br/>';
        echo '<textarea id="note_content" name="note_content" rows="3" class="widefat" placeholder="' . __('Add a note about this booking...', 'mhm-rentiva') . '" required></textarea>';
        echo '</p>';
        
        echo '<p>';
        echo '<label for="note_type">' . __('Type:', 'mhm-rentiva') . '</label><br/>';
        echo '<select id="note_type" name="note_type" class="widefat" style="margin-bottom: 10px;">';
        echo '<option value="note">' . __('Note', 'mhm-rentiva') . '</option>';
        echo '<option value="status_change">' . __('Status Change', 'mhm-rentiva') . '</option>';
        echo '<option value="payment_update">' . __('Payment Update', 'mhm-rentiva') . '</option>';
        echo '<option value="customer_contact">' . __('Customer Contact', 'mhm-rentiva') . '</option>';
        echo '<option value="system">' . __('System', 'mhm-rentiva') . '</option>';
        echo '</select>';
        echo '</p>';
        
        echo '<p>';
        echo '<button type="submit" class="button button-primary">' . __('Add Note', 'mhm-rentiva') . '</button>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
        
        // JavaScript for AJAX forms
        echo '<script>
        jQuery(document).ready(function($) {
            // Email sending
            $(".mhm-email-form").on("submit", function(e) {
                e.preventDefault();
                
                var form = $(this);
                var bookingId = form.data("booking-id");
                var formData = form.serialize();
                formData += "&booking_id=" + bookingId;
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "mhm_rentiva_send_customer_email",
                        ...Object.fromEntries(new URLSearchParams(formData))
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("Email sent successfully!");
                            form[0].reset();
                        } else {
                            alert("Hata: " + response.data);
                        }
                    },
                    error: function() {
                        alert("An error occurred!");
                    }
                });
            });
            
            // Not ekleme
            $(".mhm-history-form").on("submit", function(e) {
                e.preventDefault();
                
                var form = $(this);
                var bookingId = form.data("booking-id");
                var formData = form.serialize();
                formData += "&booking_id=" + bookingId;
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "mhm_rentiva_add_booking_history_note",
                        ...Object.fromEntries(new URLSearchParams(formData))
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("Note added successfully!");
                            form[0].reset();
                            location.reload(); // Reload page
                        } else {
                            alert("Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("An error occurred!");
                    }
                });
            });
        });
        </script>';
        
        echo '<hr style="margin: 20px 0;">';
        
        // Show history notes
        echo '<div class="mhm-history-list">';
        echo '<h4>' . __('History', 'mhm-rentiva') . '</h4>';
        
        if (empty($history)) {
            echo '<p class="description">' . __('No history found.', 'mhm-rentiva') . '</p>';
        } else {
            // Sort by date (newest first)
            krsort($history);
            
            foreach ($history as $timestamp => $note) {
                $date = date_i18n(get_option('date_format'), $timestamp);
                $time = date_i18n(get_option('time_format'), $timestamp);
                $user = get_userdata($note['user_id']);
                $user_name = $user ? $user->display_name : __('System', 'mhm-rentiva');
                
                $type_class = 'mhm-history-' . $note['type'];
                $type_label = self::get_history_type_label($note['type']);
                
                echo '<div class="mhm-history-item ' . esc_attr($type_class) . '">';
                echo '<div class="mhm-history-header">';
                echo '<span class="mhm-history-type">' . esc_html($type_label) . '</span>';
                echo '<span class="mhm-history-date">' . esc_html($date . ' ' . $time) . '</span>';
                echo '</div>';
                echo '<div class="mhm-history-content">';
                echo '<p>' . esc_html($note['note']) . '</p>';
                echo '<div class="mhm-history-meta">';
                /* translators: %s placeholder. */
                echo '<span class="mhm-history-user">' . sprintf(__('By %s', 'mhm-rentiva'), esc_html($user_name)) . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Gets history type label
     */
    private static function get_history_type_label(string $type): string
    {
        $labels = [
            'note' => __('Note', 'mhm-rentiva'),
            'status_change' => __('Status Change', 'mhm-rentiva'),
            'payment_update' => __('Payment Update', 'mhm-rentiva'),
            'customer_contact' => __('Customer Contact', 'mhm-rentiva'),
            'system' => __('System', 'mhm-rentiva')
        ];
        
        return $labels[$type] ?? __('Note', 'mhm-rentiva');
    }

    /**
     * History note adding handler
     */
    public static function handle_add_booking_history_note(): void
    {
        // Nonce check
        if (!isset($_POST['mhm_rentiva_history_nonce']) || 
            !wp_verify_nonce($_POST['mhm_rentiva_history_nonce'], 'mhm_rentiva_add_history_note')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
        }
        
        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to add notes.', 'mhm-rentiva'));
        }
        
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $note = sanitize_textarea_field((string) ($_POST['history_note'] ?? ''));
        $type = self::sanitize_text_field_safe($_POST['history_type'] ?? 'note');
        
        if (!$booking_id || !$note) {
            wp_die(__('Invalid booking ID or empty note.', 'mhm-rentiva'));
        }
        
        // Get existing history
        $history = get_post_meta($booking_id, '_mhm_booking_history', true) ?: [];
        
        // Yeni not ekle
        $timestamp = current_time('timestamp');
        $history[$timestamp] = [
            'note' => $note,
            'type' => $type,
            'user_id' => get_current_user_id(),
            'timestamp' => $timestamp
        ];
        
        // Save history
        update_post_meta($booking_id, '_mhm_booking_history', $history);
        
        // JavaScript redirect kullan
        $redirect_url = add_query_arg([
            'post' => $booking_id,
            'action' => 'edit',
            'message' => 'note_added'
        ], admin_url('post.php'));
        
        // Redirect via JavaScript
        echo '<script>window.location.href = "' . esc_js($redirect_url) . '";</script>';
        echo '<p>' . __('Note added successfully! Redirecting...', 'mhm-rentiva') . '</p>';
        exit;
    }
    
    /**
     * Add history note (programmatically)
     */
    public static function add_history_note(int $booking_id, string $note, string $type = 'note', int $user_id = null): bool
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $history = get_post_meta($booking_id, '_mhm_booking_history', true) ?: [];
        $timestamp = current_time('timestamp');
        
        $history[$timestamp] = [
            'note' => $note,
            'type' => $type,
            'user_id' => $user_id,
            'timestamp' => $timestamp
        ];
        
        return update_post_meta($booking_id, '_mhm_booking_history', $history) !== false;
    }

    /**
     * ✅ Rezervasyon maliyetlerini yeniden hesapla
     */
    public static function recalculate_booking_costs(int $booking_id, string $pickup_date, string $dropoff_date): void
    {
        
        // Normalize date format
        $pickup_timestamp = strtotime($pickup_date);
        $dropoff_timestamp = strtotime($dropoff_date);
        
        if (!$pickup_timestamp || !$dropoff_timestamp) {
            return;
        }
        
        // Calculate days
        $days = max(1, ceil(($dropoff_timestamp - $pickup_timestamp) / (24 * 60 * 60)));
        
        // Get vehicle ID
        $vehicle_id = get_post_meta($booking_id, '_mhm_vehicle_id', true);
        
        if (!$vehicle_id) {
            return;
        }
        
        // Get vehicle daily price - CORRECT META KEY
        $daily_price = (float) get_post_meta($vehicle_id, '_mhm_rentiva_price_per_day', true);
        
        if ($daily_price <= 0) {
            return;
        }
        
        // Calculate total price
        $total_price = $daily_price * $days;
        
        // Get additional services price
        $additional_services_price = (float) get_post_meta($booking_id, '_mhm_additional_services_price', true);
        $total_price += $additional_services_price;
        
        // ✅ Deposit calculation
        $payment_type = get_post_meta($booking_id, '_mhm_payment_type', true);
        $deposit_amount = 0;
        $remaining_amount = $total_price;
        
        if ($payment_type === 'deposit') {
            // Get deposit percentage (default 20%)
            $deposit_percentage = (float) get_post_meta($booking_id, '_mhm_deposit_percentage', true);
            if ($deposit_percentage <= 0) {
                $deposit_percentage = 20.0; // Default 20%
            }
            
            $deposit_amount = ($total_price * $deposit_percentage) / 100;
            $remaining_amount = $total_price - $deposit_amount;
        }
        
        // Update meta keys
        update_post_meta($booking_id, '_mhm_rental_days', $days);
        update_post_meta($booking_id, '_mhm_total_price', $total_price);
        
        // ✅ Update deposit meta keys
        if ($payment_type === 'deposit') {
            update_post_meta($booking_id, '_mhm_deposit_amount', $deposit_amount);
            update_post_meta($booking_id, '_mhm_remaining_amount', $remaining_amount);
        }
        
        // Update old meta keys for compatibility
        update_post_meta($booking_id, '_booking_rental_days', $days);
        update_post_meta($booking_id, '_booking_total_price', $total_price);
    }
    
    /**
     * ✅ WordPress hook listener - Auto calculation when meta is updated
     */
    public static function on_booking_meta_updated(int $post_id, string $pickup_date, string $dropoff_date): void
    {
        self::recalculate_booking_costs($post_id, $pickup_date, $dropoff_date);
    }

    /**
     * Auto add booking notes
     */
    public static function auto_add_booking_notes(int $post_id, \WP_Post $post): void
    {
        // Only for booking post type
        if ($post->post_type !== 'vehicle_booking') {
            return;
        }
        
        // Autosave and revision check
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        $already_created = get_post_meta($post_id, '_mhm_booking_created', true);
        
        // When new booking is created
        if ($post->post_status === 'publish' && $already_created !== '1') {
            self::add_history_note(
                $post_id,
                __('Booking created', 'mhm-rentiva'),
                'system'
            );
            update_post_meta($post_id, '_mhm_booking_created', '1');
        }
    }
    
    /**
     * Add status change note
     */
    public static function auto_add_status_change_note(int $booking_id, string $old_status, string $new_status): void
    {
        $status_labels = [
            'pending' => __('Pending', 'mhm-rentiva'),
            'confirmed' => __('Confirmed', 'mhm-rentiva'),
            'in_progress' => __('In Progress', 'mhm-rentiva'),
            'completed' => __('Completed', 'mhm-rentiva'),
            'cancelled' => __('Cancelled', 'mhm-rentiva')
        ];
        
        $old_label = $status_labels[$old_status] ?? $old_status;
        $new_label = $status_labels[$new_status] ?? $new_status;
        
        $note = sprintf(
            /* translators: 1: %s; 2: %s. */
            __('Status changed from %1$s to %2$s', 'mhm-rentiva'),
            $old_label,
            $new_label
        );
        
        self::add_history_note($booking_id, $note, 'status_change');
    }
    
    /**
     * Add payment status change note
     */
    public static function auto_add_payment_note(int $booking_id, string $old_status, string $new_status): void
    {
        $status_labels = [
            'unpaid' => __('Unpaid', 'mhm-rentiva'),
            'pending_verification' => __('Pending Verification', 'mhm-rentiva'),
            'paid' => __('Paid', 'mhm-rentiva'),
            'partially_paid' => __('Partially Paid', 'mhm-rentiva'),
            'refunded' => __('Refunded', 'mhm-rentiva')
        ];
        
        $old_label = $status_labels[$old_status] ?? $old_status;
        $new_label = $status_labels[$new_status] ?? $new_status;
        
        $note = sprintf(
            /* translators: 1: %s; 2: %s. */
            __('Payment status changed from %1$s to %2$s', 'mhm-rentiva'),
            $old_label,
            $new_label
        );
        
        self::add_history_note($booking_id, $note, 'payment_update');
    }
    
    /**
     * Shows admin notices
     */
    public static function show_admin_notices(): void
    {
        global $pagenow, $post;
        
        // Show only on booking edit page
        if ($pagenow !== 'post.php' || !$post || $post->post_type !== 'vehicle_booking') {
            return;
        }
        
        // Fetch message parameter from URL
        $message = $_GET['message'] ?? '';
        
        switch ($message) {
            case 'email_sent':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Email sent successfully!', 'mhm-rentiva') . '</p></div>';
                break;
            case 'note_added':
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Note added successfully!', 'mhm-rentiva') . '</p></div>';
                break;
        }
    }

    private static function format_price(float $price): string
    {
        $currency = Settings::get('currency', 'USD');
        $position = Settings::get('currency_position', 'right_space');
        $amount = number_format_i18n($price, 2);
        $symbol = $currency;

        switch ($position) {
            case 'left':
                return $symbol . $amount;
            case 'right':
                return $amount . $symbol;
            case 'left_space':
                return $symbol . ' ' . $amount;
            case 'right_space':
            default:
                return $amount . ' ' . $symbol;
        }
    }
    
    /**
     * Hides WordPress standard "Update" button
     */
    public static function hide_standard_update_button()
    {
        // Only for post.php page
        if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'edit') {
            return;
        }
        
        $post_id = (int) $_GET['post'];
        
        // Only for vehicle_booking post type
        if (get_post_type($post_id) !== 'vehicle_booking') {
            return;
        }
        
        // Hide WordPress standard "Update" button
        echo '<script>
        jQuery(document).ready(function($) {
            // Hide standard "Update" button
            $("#publish").hide();
            $("#save-post").hide();
            
            // Add our own "Update" button
            if ($("#mhm-custom-update-button").length === 0) {
                $("#publish").after("<button type=\"button\" id=\"mhm-custom-update-button\" class=\"button button-primary button-large\">Update</button>");
            }
            
            // Click event for our own "Update" button
            $("#mhm-custom-update-button").on("click", function(e) {
                e.preventDefault();
                
                // Collect form data manually
                var formData = {
                    action: "mhm_rentiva_update_booking",
                    booking_id: ' . $post_id . ',
                    _wpnonce: $("#_wpnonce").val(),
                    post_ID: ' . $post_id . ',
                    mhm_edit_customer_first_name: $("#mhm_booking_edit_customer_first_name").val(),
                    mhm_edit_customer_last_name: $("#mhm_booking_edit_customer_last_name").val(),
                    mhm_edit_customer_email: $("#mhm_booking_edit_customer_email").val(),
                    mhm_edit_customer_phone: $("#mhm_booking_edit_customer_phone").val(),
                    mhm_edit_pickup_date: $("#mhm_booking_edit_pickup_date").val(),
                    mhm_edit_pickup_time: $("#mhm_booking_edit_pickup_time").val(),
                    mhm_edit_dropoff_date: $("#mhm_booking_edit_dropoff_date").val(),
                    mhm_edit_dropoff_time: $("#mhm_booking_edit_dropoff_time").val(),
                    mhm_edit_guests: $("#mhm_booking_edit_guests").val(),
                    mhm_edit_payment_method: $("#mhm_booking_edit_payment_method").val(),
                    mhm_edit_status: $("#mhm_booking_edit_status").val(),
                    mhm_edit_notes: $("#mhm_booking_edit_notes").val()
                };
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            // ✅ Auto update - Without reloading page
                            if (response.data && response.data.updated_data) {
                                var data = response.data.updated_data;
                                
                                // Update Days field
                                if (data.rental_days) {
                                    $("#mhm_rental_days_display").text(data.rental_days);
                                }
                                
                                // Update Total price field
                                if (data.total_price) {
                                    $("#mhm_total_price_display").text(data.total_price);
                                }
                                
                                // Update Deposit amount field
                                if (data.deposit_amount) {
                                    $(".deposit-amount").text(data.deposit_amount);
                                }
                                
                                // Update Remaining amount field
                                if (data.remaining_amount) {
                                    $(".remaining-amount").text(data.remaining_amount);
                                }
                                
                                // ✅ Update Deposit Management field
                                if (data.rental_days) {
                                    $(".deposit-info-value[data-field=\"rental-days\"]").each(function() {
                                        var suffix = $(this).data("suffix") || "";
                                        var spacer = suffix ? " " : "";
                                        $(this).text(data.rental_days + spacer + suffix);
                                    });
                                }
                                
                                if (data.total_price) {
                                    $(".deposit-info-value[data-field=\"total-amount\"]").text(data.total_price);
                                }
                                
                                if (data.deposit_amount) {
                                    $(".deposit-info-value[data-field=\"deposit-amount\"]").text(data.deposit_amount);
                                }
                                
                                if (data.remaining_amount) {
                                    $(".deposit-info-value[data-field=\"remaining-amount\"]").text(data.remaining_amount);
                                }
                            }
                            
                            alert("Booking updated successfully!");
                            // Reload page
                            location.reload();
                        } else {
                            alert("Hata: " + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert("An error occurred!");
                    }
                });
            });
        });
        </script>';
    }
    
    /**
     * AJAX: Update booking
     */
    public static function ajax_update_booking()
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'update-post_' . ($_POST['post_ID'] ?? 0))) {
            wp_send_json_error(__('Security check failed.', 'mhm-rentiva'));
            return;
        }
        
        // Permission check
        if (!current_user_can('edit_post', $_POST['post_ID'] ?? 0)) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
            return;
        }
        
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        
        if (!$booking_id) {
            wp_send_json_error(__('Invalid booking ID.', 'mhm-rentiva'));
            return;
        }
        
        // Update booking details
        $customer_first_name = self::sanitize_text_field_safe($_POST['mhm_edit_customer_first_name'] ?? '');
        $customer_last_name = self::sanitize_text_field_safe($_POST['mhm_edit_customer_last_name'] ?? '');
        $customer_email = sanitize_email((string) ($_POST['mhm_edit_customer_email'] ?? ''));
        $customer_phone = self::sanitize_text_field_safe($_POST['mhm_edit_customer_phone'] ?? '');
        $pickup_date = self::sanitize_text_field_safe($_POST['mhm_edit_pickup_date'] ?? '');
        $pickup_time = self::sanitize_text_field_safe($_POST['mhm_edit_pickup_time'] ?? '');
        $dropoff_date = self::sanitize_text_field_safe($_POST['mhm_edit_dropoff_date'] ?? '');
        $dropoff_time = self::sanitize_text_field_safe($_POST['mhm_edit_dropoff_time'] ?? '');
        $guests = (int) ($_POST['mhm_edit_guests'] ?? 0);
        $payment_method = self::sanitize_text_field_safe($_POST['mhm_edit_payment_method'] ?? '');
        $status = self::sanitize_text_field_safe($_POST['mhm_edit_status'] ?? '');
        $notes = sanitize_textarea_field((string) ($_POST['mhm_edit_notes'] ?? ''));
        
        // Get old values (for change detection)
        $old_pickup_date = get_post_meta($booking_id, '_mhm_pickup_date', true);
        $old_pickup_time = get_post_meta($booking_id, '_mhm_start_time', true);
        $old_dropoff_date = get_post_meta($booking_id, '_mhm_dropoff_date', true);
        $old_dropoff_time = get_post_meta($booking_id, '_mhm_end_time', true);
        $old_guests = get_post_meta($booking_id, '_mhm_guests', true);
        $old_payment_method = get_post_meta($booking_id, '_mhm_payment_method', true);
        
        // Update meta data
        update_post_meta($booking_id, '_mhm_customer_first_name', $customer_first_name);
        update_post_meta($booking_id, '_mhm_customer_last_name', $customer_last_name);
        update_post_meta($booking_id, '_mhm_customer_name', $customer_first_name . ' ' . $customer_last_name);
        update_post_meta($booking_id, '_mhm_customer_email', $customer_email);
        update_post_meta($booking_id, '_mhm_customer_phone', $customer_phone);
        update_post_meta($booking_id, '_mhm_pickup_date', $pickup_date);
        update_post_meta($booking_id, '_mhm_start_time', $pickup_time);
        update_post_meta($booking_id, '_mhm_pickup_time', $pickup_time);
        update_post_meta($booking_id, '_mhm_dropoff_date', $dropoff_date);
        update_post_meta($booking_id, '_mhm_end_time', $dropoff_time);
        update_post_meta($booking_id, '_mhm_dropoff_time', $dropoff_time);
        update_post_meta($booking_id, '_mhm_guests', $guests);
        update_post_meta($booking_id, '_mhm_payment_method', $payment_method);

        // Auto calculation - When date is changed
        if ($pickup_date && $dropoff_date) {
            self::recalculate_booking_costs($booking_id, $pickup_date, $dropoff_date);
        }
        
        // Status update (Admin manual change - bypass transition control)
        $old_status = get_post_meta($booking_id, '_mhm_status', true);
        update_post_meta($booking_id, '_mhm_status', $status);
        
        // Trigger action
        if ($old_status !== $status) {
            do_action('mhm_rentiva_booking_status_changed', $booking_id, $old_status, $status);
        }
        
        // ✅ Get updated data (for AJAX response)
        $updated_rental_days = get_post_meta($booking_id, '_mhm_rental_days', true);
        $updated_total_price = get_post_meta($booking_id, '_mhm_total_price', true);
        $updated_deposit_amount = get_post_meta($booking_id, '_mhm_deposit_amount', true);
        $updated_remaining_amount = get_post_meta($booking_id, '_mhm_remaining_amount', true);
        
        // Format data
        $formatted_total_price = self::format_price((float) $updated_total_price);
        $formatted_deposit_amount = self::format_price((float) $updated_deposit_amount);
        $formatted_remaining_amount = self::format_price((float) $updated_remaining_amount);
        
        // Save changes
        $changes = [];
        
        // Date/time change check
        if ($old_pickup_date !== $pickup_date || $old_pickup_time !== $pickup_time) {
            $changes[] = sprintf(
                /* translators: 1: %s; 2: %s; 3: %s; 4: %s. */
                __('Pickup date/time changed from %1$s %2$s to %3$s %4$s', 'mhm-rentiva'),
                date_i18n(get_option('date_format'), strtotime($old_pickup_date)),
                $old_pickup_time,
                date_i18n(get_option('date_format'), strtotime($pickup_date)),
                $pickup_time
            );
        }
        
        if ($old_dropoff_date !== $dropoff_date || $old_dropoff_time !== $dropoff_time) {
            $changes[] = sprintf(
                /* translators: 1: %s; 2: %s; 3: %s; 4: %s. */
                __('Dropoff date/time changed from %1$s %2$s to %3$s %4$s', 'mhm-rentiva'),
                date_i18n(get_option('date_format'), strtotime($old_dropoff_date)),
                $old_dropoff_time,
                date_i18n(get_option('date_format'), strtotime($dropoff_date)),
                $dropoff_time
            );
        }
        
        // Guest count change
        if ($old_guests != $guests) {
            $changes[] = sprintf(
                /* translators: 1: %d; 2: %d. */
                __('Number of guests changed from %1$d to %2$d', 'mhm-rentiva'),
                $old_guests,
                $guests
            );
        }
        
        // Payment method change
        if ($old_payment_method !== $payment_method) {
            $changes[] = sprintf(
                /* translators: 1: %s; 2: %s. */
                __('Payment method changed from %1$s to %2$s', 'mhm-rentiva'),
                $old_payment_method,
                $payment_method
            );
        }
        
        // Status change note is automatically added by do_action (line 1207)
        // So we don't call it again
        
        // Add note for other changes
        if (!empty($changes)) {
            self::add_history_note(
                $booking_id,
                __('Booking updated: ', 'mhm-rentiva') . implode(', ', $changes),
                'note'
            );
        }
        
        // Update notes
        if ($notes !== get_post_field('post_content', $booking_id)) {
            wp_update_post([
                'ID' => $booking_id,
                'post_content' => $notes
            ]);
        }
        
        // ✅ Successful response - Include updated data
        wp_send_json_success([
            'message' => __('Booking updated successfully!', 'mhm-rentiva'),
            'updated_data' => [
                'rental_days' => (string) $updated_rental_days,
                'total_price' => $formatted_total_price,
                'deposit_amount' => $formatted_deposit_amount,
                'remaining_amount' => $formatted_remaining_amount
            ]
        ]);
    }
    
    /**
     * AJAX: Send email
     */
    public static function ajax_send_customer_email()
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['mhm_rentiva_email_nonce'] ?? '', 'mhm_rentiva_send_email')) {
            wp_send_json_error(__('Security check failed.', 'mhm-rentiva'));
            return;
        }
        
        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
            return;
        }
        
        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $email_type = self::sanitize_text_field_safe($_POST['email_type'] ?? '');
        $subject = self::sanitize_text_field_safe($_POST['email_subject'] ?? '');
        $message = sanitize_textarea_field((string) ($_POST['email_message'] ?? ''));
        
        if (!$booking_id || !$email_type) {
            wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
            return;
        }
        
        // Prepare email content
        $email_content = self::prepare_email_content($booking_id, $email_type, $subject, $message);
        
        // Get customer information
        $customer_email = get_post_meta($booking_id, '_mhm_customer_email', true);
        
        if (!$customer_email) {
            wp_send_json_error(__('Customer email not found.', 'mhm-rentiva'));
            return;
        }
        
        // Send email
        $sent = wp_mail(
            $customer_email,
            $email_content['subject'],
            $email_content['message'],
            [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
            ]
        );
        
        if ($sent) {
            wp_send_json_success(__('Email sent successfully!', 'mhm-rentiva'));
        } else {
            wp_send_json_error(__('Failed to send email.', 'mhm-rentiva'));
        }
    }
    
    /**
     * AJAX: Add history note
     */
    public static function ajax_add_booking_history_note()
    {
        // Nonce check
        if (!wp_verify_nonce($_POST['mhm_rentiva_history_nonce'] ?? '', 'mhm_rentiva_add_history_note')) {
            wp_send_json_error(__('Security check failed.', 'mhm-rentiva'));
            return;
        }

        // Permission check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'mhm-rentiva'));
            return;
        }

        $booking_id = (int) ($_POST['booking_id'] ?? 0);
        $note_type = self::sanitize_text_field_safe($_POST['note_type'] ?? 'manual');
        $note_content = sanitize_textarea_field((string) ($_POST['note_content'] ?? ''));

        if (!$booking_id || !$note_content) {
            wp_send_json_error(__('Missing required fields.', 'mhm-rentiva'));
            return;
        }

        // Add note
        $result = self::add_history_note($booking_id, $note_content, $note_type);

        if ($result) {
            wp_send_json_success(__('Note added successfully!', 'mhm-rentiva'));
        } else {
            wp_send_json_error(__('Failed to add note.', 'mhm-rentiva'));
        }
    }

    /**
     * Intercept booking update - override WordPress post update completely
     */
    public static function intercept_booking_update()
    {
        // Only run on admin post.php page
        if (!isset($_GET['post']) || !isset($_GET['action']) || $_GET['action'] !== 'edit') {
            return;
        }

        $post_id = (int) $_GET['post'];

        // Only for vehicle_booking post type
        if (get_post_type($post_id) !== 'vehicle_booking') {
            return;
        }

        // Only run on POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Check POST data
        if (!isset($_POST['post_type']) || $_POST['post_type'] !== 'vehicle_booking') {
            return;
        }

        if (!isset($_POST['post_ID']) || (int) $_POST['post_ID'] !== $post_id) {
            return;
        }

        // Permission check
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to edit this booking.', 'mhm-rentiva'));
        }

        // Nonce check
        if (!isset($_POST['mhm_rentiva_booking_meta_main_nonce']) ||
            !wp_verify_nonce($_POST['mhm_rentiva_booking_meta_main_nonce'], 'mhm_rentiva_booking_meta_action')) {
            wp_die(__('Security check failed.', 'mhm-rentiva'));
        }

        // Update meta data
        self::save_meta($post_id, get_post($post_id));

        // Redirect to booking edit page
        $redirect_url = add_query_arg([
            'post' => $post_id,
            'action' => 'edit',
            'message' => 'updated'
        ], admin_url('post.php'));

        wp_redirect($redirect_url);
        exit;
    }
}
