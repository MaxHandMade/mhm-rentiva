<?php

/**
 * Home POC Shortcode Handler.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HomePoc
 *
 * Minimal homepage composition reusing existing shortcodes.
 * Experimental version-scoped shortcode.
 *
 * Safety:
 * - Must not render in production by default.
 * - Enabled only when WP_DEBUG is true or MHM_RENTIVA_ENABLE_HOME_POC is true.
 */
class HomePoc
{


    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render(array $atts = []): string
    {
        if (! self::is_enabled_for_environment()) {
            return '';
        }

        // Entry-Guard: Check if the Home POC is enabled via filter.
        if (!apply_filters('mhm_rentiva_enable_home_poc', true)) {
            return '';
        }
        $template_path = MHM_RENTIVA_PLUGIN_PATH . 'templates/shortcodes/home-poc.php';

        if (!file_exists($template_path)) {
            return sprintf(
                '<div class="mhm-rentiva-error">%s</div>',
                esc_html__('Home POC template not found.', 'mhm-rentiva')
            );
        }

        ob_start();
        include $template_path;
        return (string) ob_get_clean();
    }

    /**
     * Guard POC rendering in production.
     */
    private static function is_enabled_for_environment(): bool
    {
        if (defined('MHM_RENTIVA_ENABLE_HOME_POC')) {
            return (bool) constant('MHM_RENTIVA_ENABLE_HOME_POC');
        }

        return defined('WP_DEBUG') && WP_DEBUG;
    }
}
