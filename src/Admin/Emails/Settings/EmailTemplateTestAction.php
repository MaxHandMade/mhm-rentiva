<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Settings;

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;

if (!defined('ABSPATH')) {
    exit;
}

final class EmailTemplateTestAction
{
    public static function register(): void
    {
        add_action('admin_post_mhm_rentiva_send_template_test', [self::class, 'handle']);
    }

    public static function handle(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mhm-rentiva'), 403);
        }

        $nonce = $_POST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'mhm_rentiva_send_template_test')) {
            wp_die(esc_html__('Security check failed.', 'mhm-rentiva'), 403);
        }

        $template_key = isset($_POST['template_key']) ? sanitize_text_field((string) $_POST['template_key']) : '';
        $to           = isset($_POST['to']) ? sanitize_email((string) $_POST['to']) : '';
        $booking_id   = isset($_POST['booking_id']) ? absint($_POST['booking_id']) : 0;
        $new_status   = isset($_POST['new_status']) ? sanitize_text_field((string) $_POST['new_status']) : '';

        if ($to === '') {
            // Default to test address if test mode, otherwise admin email
            $to = EmailSettings::is_test_mode() ? EmailSettings::get_test_address() : get_option('admin_email');
        }

        if ($template_key === '' || !is_email($to)) {
            self::redirect('failed');
        }

        // Build context
        $context = [];
        if ($booking_id > 0) {
            $context = self::buildBookingContext($booking_id);
            if ($template_key === 'booking_status_changed_customer' || $template_key === 'booking_status_changed_admin') {
                $context['status_change'] = [
                    'old_status' => $context['booking']['status'] ?? 'pending',
                    'new_status' => $new_status !== '' ? $new_status : 'confirmed',
                    'old_status_label' => $context['booking']['status'] ?? 'pending',
                    'new_status_label' => $new_status !== '' ? $new_status : 'confirmed',
                ];
            }
        }

        // Ensure site context exists
        if (!isset($context['site'])) {
            $context['site'] = [
                'name' => get_bloginfo('name'),
                'url'  => home_url('/'),
                'admin_email' => get_option('admin_email'),
            ];
        }

        $ok = Mailer::send($template_key, $to, $context);
        self::redirect($ok ? 'success' : 'failed');
    }

    private static function buildBookingContext(int $booking_id): array
    {
        $post = get_post($booking_id);
        if (!$post || $post->post_type !== 'vehicle_booking') {
            return [];
        }

        if (!class_exists('\\MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper')) {
            return [];
        }
        $customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo($booking_id);
        $vehicle_info  = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingVehicleInfo($booking_id);
        $date_info     = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingDateInfo($booking_id);
        $payment_status  = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingPaymentStatus($booking_id);
        $payment_gateway = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingPaymentGateway($booking_id);
        $total_price     = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingTotalPrice($booking_id);

        return [
            'booking' => [
                'id' => $booking_id,
                'title' => $post->post_title,
                'status' => $post->post_status,
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
        ];
    }

    private static function redirect(string $status): void
    {
        $url = add_query_arg([
            'page' => 'mhm-rentiva-settings',
            'tab'  => 'email-templates',
            'type' => sanitize_key($_GET['type'] ?? 'booking_notifications'),
            'mhm_template_test' => $status,
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}


