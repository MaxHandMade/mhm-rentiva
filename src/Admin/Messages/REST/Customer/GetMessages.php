<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Customer;

use MHMRentiva\Admin\Messages\REST\Helpers\Auth;
use MHMRentiva\Admin\Messages\REST\Helpers\MessageQuery;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class GetMessages
{
    /**
     * Customer message list (WordPress User Auth)
     */
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        // WordPress user authentication check
        if (!is_user_logged_in()) {
            return new WP_REST_Response([
                'error' => __('Please login to access your messages.', 'mhm-rentiva')
            ], 401);
        }

        $user = wp_get_current_user();
        $customer_email = $user->user_email;

        $result = MessageQuery::getCustomerMessages($customer_email);

        return new WP_REST_Response($result, 200);
    }
}
