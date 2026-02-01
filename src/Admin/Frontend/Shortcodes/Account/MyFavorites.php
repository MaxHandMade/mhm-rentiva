<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * My Favorites Shortcode
 */
final class MyFavorites extends AbstractAccountShortcode
{

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_my_favorites';
    }

    protected static function get_template_path(): string
    {
        return 'account/favorites';
    }

    protected static function get_default_attributes(): array
    {
        return array(
            'columns'  => '3',
            'limit'    => '12',
            'hide_nav' => false,
        );
    }

    protected static function prepare_template_data(array $atts): array
    {
        return AccountRenderer::get_favorites_data($atts);
    }
}
