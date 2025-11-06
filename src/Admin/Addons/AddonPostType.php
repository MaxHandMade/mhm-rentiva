<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

use MHMRentiva\Admin\Core\PostTypes\AbstractPostType;

if (!defined('ABSPATH')) {
    exit;
}

final class AddonPostType extends AbstractPostType
{
    public const POST_TYPE = 'vehicle_addon';

    protected static function get_post_type(): string
    {
        return self::POST_TYPE;
    }

    protected static function get_singular_name(): string
    {
        return __('Additional Service', 'mhm-rentiva');
    }

    protected static function get_plural_name(): string
    {
        return __('Additional Services', 'mhm-rentiva');
    }

    protected static function get_menu_icon(): string
    {
        return 'dashicons-plus-alt';
    }

    protected static function get_custom_args(): array
    {
        return array_merge(
            self::get_admin_only_args(),
            [
                'supports' => self::get_supports_array(['editor', 'excerpt', 'thumbnail', 'page-attributes']),
            ]
        );
    }

    public static function register_taxonomies(): void
    {
        // Addon categories taxonomy (optional)
        $labels = self::get_taxonomy_labels(__('Addon Category', 'mhm-rentiva'), __('Addon Categories', 'mhm-rentiva'));
        $args = array_merge(
            self::get_default_taxonomy_args(),
            ['labels' => $labels]
        );

        register_taxonomy('addon_category', [self::POST_TYPE], $args);
    }

}
