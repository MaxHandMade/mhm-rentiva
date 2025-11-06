<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Admin;

use MHMRentiva\Admin\PostTypes\Message\Message;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class UpdateStatus
{
    /**
     * Update message status
     */
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $message_id = $request->get_param('id');
        $status = $request->get_param('status');

        if (!Message::update_message_status($message_id, $status)) {
            return new WP_REST_Response(['error' => __('Status could not be updated', 'mhm-rentiva')], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => __('Message status updated', 'mhm-rentiva'),
            'status' => $status,
        ], 200);
    }
}
