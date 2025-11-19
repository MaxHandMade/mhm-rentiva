<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Customer;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\PostTypes\Message\Message;
use MHMRentiva\Admin\Messages\REST\Helpers\Auth;
use MHMRentiva\Admin\Messages\Settings\MessagesSettings;
use MHMRentiva\Admin\Messages\Core\MessageQueryHelper;
use MHMRentiva\Admin\Messages\Core\MessageCache;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class SendMessage
{
    /**
     * Customer message sending (WordPress User Auth)
     */
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        if (!Mode::featureEnabled(Mode::FEATURE_MESSAGES)) {
            return new WP_REST_Response(['error' => __('Messaging feature is not active', 'mhm-rentiva')], 403);
        }

        // WordPress user authentication check
        if (!is_user_logged_in()) {
            return new WP_REST_Response([
                'error' => __('Please login to send messages.', 'mhm-rentiva')
            ], 401);
        }

        $user = wp_get_current_user();
        $customer_email = $user->user_email;
        $customer_name = $user->display_name ?: $user->user_login;

        $category = $request->get_param('category');
        $subject = $request->get_param('subject');
        $message = $request->get_param('message');
        $booking_id = $request->get_param('booking_id');
        $priority = $request->get_param('priority');

        // License check
        if (!Mode::isPro()) {
            $monthly_limit = MessagesSettings::get_setting('lite_messages_per_month', 10);
            $daily_limit = MessagesSettings::get_setting('lite_messages_per_day', 3);
            
            // Optimized limit check
            $limits = MessageQueryHelper::check_customer_limits($customer_email);
            
            if ($limits['this_month_count'] >= $monthly_limit) {
                return new WP_REST_Response([
                    /* translators: %d placeholder. */
                    'error' => sprintf(__('Monthly message limit of %d in Lite version', 'mhm-rentiva'), $monthly_limit)
                ], 403);
            }
            
            if ($limits['today_count'] >= $daily_limit) {
                return new WP_REST_Response([
                    /* translators: %d placeholder. */
                    'error' => sprintf(__('Daily message limit of %d in Lite version', 'mhm-rentiva'), $daily_limit)
                ], 403);
            }
        }

        // Validate priority
        $priorities = MessagesSettings::get_priorities();
        $priority = sanitize_key($priority ?? 'normal');
        if (!array_key_exists($priority, $priorities)) {
            $priority = 'normal';
        }

        $message_data = [
            'subject' => $subject,
            'message' => $message,
            'message_type' => 'customer_to_admin',
            'customer_email' => $customer_email,
            'customer_name' => $customer_name,
            'category' => $category,
            'booking_id' => $booking_id,
            'priority' => $priority,
        ];

        $message_id = Message::create_message($message_data);

        if (!$message_id) {
            return new WP_REST_Response(['error' => __('Message could not be sent', 'mhm-rentiva')], 400);
        }

        // Clear cache
        MessageCache::clear_message_cache($message_id, $customer_email);

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Your message has been sent successfully', 'mhm-rentiva'),
            'message_id' => $message_id,
        ], 200);
    }
}
