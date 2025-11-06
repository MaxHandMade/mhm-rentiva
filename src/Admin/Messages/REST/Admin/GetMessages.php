<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Admin;

use MHMRentiva\Admin\Messages\REST\Helpers\MessageQuery;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

final class GetMessages
{
    /**
     * Admin message list
     */
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $status = $request->get_param('status');
        $category = $request->get_param('category');
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');

        $result = MessageQuery::getAdminMessages($status, $category, $per_page, $page);

        return new WP_REST_Response($result, 200);
    }
}
