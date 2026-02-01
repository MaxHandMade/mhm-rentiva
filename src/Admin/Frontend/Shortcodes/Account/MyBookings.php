<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * My Bookings Shortcode
 */
final class MyBookings extends AbstractAccountShortcode
{

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_my_bookings';
    }

    protected static function get_template_path(): string
    {
        return 'account/bookings';
    }

    protected static function get_default_attributes(): array
    {
        return array(
            'limit'    => '10',
            'status'   => '',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'hide_nav' => false,
        );
    }

    protected static function prepare_template_data(array $atts): array
    {
        return AccountRenderer::get_bookings_data($atts);
    }

    protected static function enqueue_assets(): void
    {
        parent::enqueue_assets();

        wp_enqueue_style(
            'mhm-rentiva-bookings-page',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/bookings-page.css',
            array(),
            MHM_RENTIVA_VERSION
        );
    }
}
