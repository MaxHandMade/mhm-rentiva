<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Templates;

use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;

if (!defined('ABSPATH')) {
    exit;
}

final class EmailPreview
{
    public static function register(): void
    {
        // EmailPreview class only uses render method, no register needed
    }

    public static function render(): void
    {
        echo '<h2>' . esc_html__('Email Preview', 'mhm-rentiva') . '</h2>';
        echo '<p class="description">' . esc_html__('Send a test and see a live preview side-by-side.', 'mhm-rentiva') . '</p>';

        $registry = \MHMRentiva\Admin\Emails\Core\Templates::registry();
        $send_nonce = wp_create_nonce('mhm_rentiva_send_template_test');
        $preview_nonce = wp_create_nonce('mhm_email_preview_action');
        $admin_post = admin_url('admin-post.php');
        $current_url = esc_url_raw(add_query_arg([]));
        $default_to = \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_test_mode() ? \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_test_address() : get_option('admin_email');

        echo '<div style="display:flex; gap:16px; align-items:flex-start;">';
        echo '<div style="flex:0 0 420px; max-width:420px;">';
        echo '<div class="card" style="padding:12px;">';
        echo '<h3>' . esc_html__('Send Template to Email (Test Mode friendly)', 'mhm-rentiva') . '</h3>';
        echo '<div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">';
        echo '<div style="flex:1 1 100%"><label>' . esc_html__('Template', 'mhm-rentiva') . '<br/>';
        echo '<select id="mhm-preview-template-key" style="width:100%; min-width:260px;">';

        // Extended Friendly Names Map
        $friendly_names = [
            'booking_created_customer' => esc_html__('Booking Confirmation (Customer)', 'mhm-rentiva'),
            'booking_created_admin'    => esc_html__('New Booking Alert (Admin)', 'mhm-rentiva'),
            'booking_status_changed_admin' => esc_html__('Status Change: (Admin)', 'mhm-rentiva'),
            'booking_status_changed_customer' => esc_html__('Status Change: Confirmed (Customer)', 'mhm-rentiva'),
            'booking_cancelled'        => esc_html__('Booking Cancelled (Customer)', 'mhm-rentiva'),
            'auto_cancel'              => esc_html__('Auto Cancel Notification (Customer)', 'mhm-rentiva'),
            'booking_reminder_customer' => esc_html__('Booking Reminder (Customer)', 'mhm-rentiva'),
            'welcome_customer'         => esc_html__('Welcome Email (Customer)', 'mhm-rentiva'),
            'refund_customer'          => esc_html__('Refund Notification (Customer)', 'mhm-rentiva'),
            'refund_admin'             => esc_html__('Refund Alert (Admin)', 'mhm-rentiva'),

            'message_received_admin' => esc_html__('New Message (Admin)', 'mhm-rentiva'),
            'message_replied_customer' => esc_html__('Message Reply (Customer)', 'mhm-rentiva'),
        ];

        foreach ($registry as $key => $def) {
            $label = $friendly_names[$key] ?? ucfirst(str_replace('_', ' ', $key));
            echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></label></div>';

        echo '<div style="flex:1 1 100%"><label>' . esc_html__('Send To (optional)', 'mhm-rentiva') . '<br/>';
        echo '<input type="email" id="mhm-preview-send-to" class="regular-text" value="' . esc_attr($default_to) . '" style="width:100%" /></label></div>';
        echo '<div>';
        echo '<button type="button" id="mhm-preview-send-btn" class="button button-secondary" data-post="' . esc_url($admin_post) . '" data-nonce="' . esc_attr($send_nonce) . '">' . esc_html__('Send Test Email', 'mhm-rentiva') . '</button> ';
        echo '<button type="button" id="mhm-preview-btn" class="button button-primary" data-self="' . esc_url($current_url) . '" data-nonce="' . esc_attr($preview_nonce) . '">' . esc_html__('Preview', 'mhm-rentiva') . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        if (isset($_GET['mhm_template_test'])) {
            $st = sanitize_text_field((string) ($_GET['mhm_template_test'] ?? ''));
            if ($st === 'success') {
                echo '<div class="notice notice-success inline" style="margin-top:8px;"><p>' . esc_html__('Template email sent.', 'mhm-rentiva') . '</p></div>';
            } elseif ($st === 'failed') {
                echo '<div class="notice notice-error inline" style="margin-top:8px;"><p>' . esc_html__('Failed to send template email.', 'mhm-rentiva') . '</p></div>';
            }
        }

        echo '</div>';

        echo '<div style="flex:1 1 auto; min-width:420px;">';
        echo '<div id="mhm-preview-result-container">';

        // Initial state or placeholder
        echo '<div class="notice notice-info inline"><p>' . esc_html__('Enter a Booking ID and click Preview to see the email.', 'mhm-rentiva') . '</p></div>';

        echo '</div>'; // End container
        echo '</div>';
        echo '</div>';

        // Inline JS removed - handled by assets/js/admin/email-templates.js
    }

    private static function show_preview_result(): void
    {
        $booking_id = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $template_key = isset($_POST['template_key']) ? sanitize_text_field((string) ($_POST['template_key'] ?? '')) : '';
        $status = isset($_POST['new_status']) ? sanitize_text_field((string) ($_POST['new_status'] ?? '')) : '';

        if (!$booking_id) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Please enter a valid booking ID.', 'mhm-rentiva') . '</p></div>';
            return;
        }

        // Rezervasyon verilerini al
        $booking_data = self::get_booking_data($booking_id);
        if (!$booking_data) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Booking not found.', 'mhm-rentiva') . '</p></div>';
            return;
        }

        $key = $template_key !== '' ? $template_key : 'booking_created_customer';
        $context = self::build_context($booking_id, ($status !== '' ? 'booking_status_changed' : 'booking_created'), $status, $key);
        $subject = Templates::compile_subject($key, $context);
        $html    = Templates::render_body($key, $context);

        echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px;">';
        echo '<h3>' . esc_html__('Subject:', 'mhm-rentiva') . ' ' . esc_html($subject) . '</h3>';
        echo '<hr>';
        echo '<div style="background: white; border: 1px solid #ccc; padding: 15px;">';
        echo $html; // Body already escaped in template or rendered as HTML intentionally
        echo '</div>';
        echo '</div>';
    }

    private static function get_booking_data(int $booking_id): ?array
    {
        $booking = get_post($booking_id);
        if (!$booking || $booking->post_type !== 'vehicle_booking') {
            return null;
        }

        $meta = get_post_meta($booking_id);

        return [
            'id' => $booking_id,
            'title' => $booking->post_title,
            'status' => $booking->post_status,
            'contact_name' => $meta['contact_name'][0] ?? '',
            'contact_email' => $meta['contact_email'][0] ?? '',
            'contact_phone' => $meta['contact_phone'][0] ?? '',
            'pickup_date' => $meta['pickup_date'][0] ?? '',
            'dropoff_date' => $meta['dropoff_date'][0] ?? '',
            'total_price' => $meta['total_price'][0] ?? '',
            'vehicle_title' => get_the_title($meta['vehicle_id'][0] ?? 0),
        ];
    }

    // mapping method no longer used

    private static function build_context(int $booking_id, string $template_type, string $new_status, string $template_key = ''): array
    {
        // Build context similar to Mailer::getBookingContext
        $post = get_post($booking_id);
        $customer_info = BookingQueryHelper::getBookingCustomerInfo($booking_id);
        $vehicle_info  = BookingQueryHelper::getBookingVehicleInfo($booking_id);
        $date_info     = BookingQueryHelper::getBookingDateInfo($booking_id);
        $payment_status   = BookingQueryHelper::getBookingPaymentStatus($booking_id);
        $payment_gateway  = BookingQueryHelper::getBookingPaymentGateway($booking_id);
        $total_price      = BookingQueryHelper::getBookingTotalPrice($booking_id);

        $context = [
            'booking' => [
                'id' => $booking_id,
                'title' => $post ? $post->post_title : '',
                'status' => $post ? $post->post_status : '',
                'payment_status' => $payment_status,
                'payment_gateway' => $payment_gateway,
                'total_price' => $total_price,
                'pickup_date' => $date_info['pickup_date'] ?? '',
                'return_date' => $date_info['return_date'] ?? '',
                'rental_days' => $date_info['rental_days'] ?? 0,
            ],
            'customer' => [
                'name' => trim(($customer_info['first_name'] ?? '') . ' ' . ($customer_info['last_name'] ?? '')),
                'first_name' => $customer_info['first_name'] ?? '',
                'last_name' => $customer_info['last_name'] ?? '',
                'email' => $customer_info['email'] ?? '',
                'phone' => $customer_info['phone'] ?? '',
            ],
            'vehicle' => [
                'id' => $vehicle_info['id'] ?? 0,
                'title' => $vehicle_info['title'] ?? '',
                'price_per_day' => $vehicle_info['price_per_day'] ?? 0,
                'featured_image' => $vehicle_info['featured_image'] ?? '',
            ],
            'site' => [
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'admin_email' => get_option('admin_email'),
            ],
        ];

        if ($template_type === 'booking_status_changed') {
            $context['status_change'] = [
                'old_status' => $context['booking']['status'] ?? 'pending',
                'new_status' => $new_status,
                'old_status_label' => $context['booking']['status'] ?? 'pending',
                'new_status_label' => $new_status,
            ];
        }

        // Enrich context for specific template keys (preview convenience)
        if ($template_key === 'refund_customer' || $template_key === 'refund_admin') {
            $amount = 0.0;
            if (isset($context['booking']['total_price']) && is_numeric($context['booking']['total_price'])) {
                $amount = (float) $context['booking']['total_price'];
            }
            // Force custom logic for preview to guarantee symbol display
            // if (function_exists('wc_price')) { ... } 

            $symbol = \MHMRentiva\Admin\Core\CurrencyHelper::get_currency_symbol();

            $formatted_amount = number_format($amount, 2);
            $pos = get_option('mhm_rentiva_currency_position', 'right_space');

            switch ($pos) {
                case 'left':
                    $context['amount'] = $symbol . $formatted_amount;
                    break;
                case 'left_space':
                    $context['amount'] = $symbol . ' ' . $formatted_amount;
                    break;
                case 'right':
                    $context['amount'] = $formatted_amount . $symbol;
                    break;
                case 'right_space':
                default:
                    $context['amount'] = $formatted_amount . ' ' . $symbol;
                    break;
            }
            $context['status'] = 'completed';
            $context['reason'] = '';
        }

        if ($template_key === 'message_received_admin' || $template_key === 'message_replied_customer') {
            $context['message'] = [
                'subject' => __('Sample Subject', 'mhm-rentiva'),
                'from_name' => $context['customer']['name'] ?? 'John Doe',
                'from_email' => $context['customer']['email'] ?? 'john@example.com',
                'body' => __('This is a sample message body for preview.', 'mhm-rentiva'),
                'reply' => __('This is a sample reply for preview.', 'mhm-rentiva'),
            ];
        }

        // Mock data for Manual Cancellation
        if ($template_key === 'booking_cancelled') {
            // Force these values to ensure preview looks good even without real DB data
            $context['booking_id']    = '9999';
            $context['order_id']      = '9999';
            $context['contact_name']  = 'John Doe';
            $context['status']        = __('Cancelled', 'mhm-rentiva');

            // Ensure nested array exists if template uses {{booking.id}}
            $context['booking']['id'] = '9999';
            $context['booking']['status'] = 'cancelled';

            // Ensure sites info
            $context['site_url'] = site_url();
        }

        // Generic fallback for phone if missing (crucial for Admin alerts)
        if (empty($context['contact_phone'])) {
            $context['contact_phone'] = '555-000-0000';
        }
        if (empty($context['customer']['phone'])) {
            $context['customer']['phone'] = '555-000-0000';
        }

        return $context;
    }
}
