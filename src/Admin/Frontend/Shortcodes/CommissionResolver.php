<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\PolicyService;



/**
 * Shortcode to resolve and display the real-time commission rate.
 *
 * @package MHMRentiva\Admin\Frontend\Shortcodes
 */
class CommissionResolver {

    /**
     * Renders the shortcode output.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string
     */
    public static function render($atts = []): string
    {
        $atts      = shortcode_atts([ 'vendor_id' => 0 ], $atts);
        $vendor_id = absint($atts['vendor_id']);

        if ($vendor_id === 0) {
            return '';
        }

        try {
            $now    = current_time('mysql', true);
            $policy = PolicyService::resolve_policy_at($vendor_id, $now);

            // Return string float. e.g "15" or "15.5"
            return (string) $policy->get_global_rate();
        } catch (\Exception $e) {
            // Log error silently and return empty string on failure
            if (class_exists('\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger')) {
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
                    'CommissionResolver Shortcode Error',
                    [
                        'vendor_id' => $vendor_id,
                        'message'   => $e->getMessage(),
                    ]
                );
            }
            return '';
        }
    }
}
