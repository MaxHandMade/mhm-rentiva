<?php declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Logs;

if (!defined('ABSPATH')) {
    exit;
}

final class PostType
{
    public const TYPE = 'mhm_app_log';

    public static function register(): void
    {
        add_action('init', [self::class, 'cpt']);
    }

    public static function cpt(): void
    {
        $labels = [
            'name'               => __('Logs', 'mhm-rentiva'),
            'singular_name'      => __('Log', 'mhm-rentiva'),
            'menu_name'          => __('Logs', 'mhm-rentiva'),
            'add_new'            => __('Add New', 'mhm-rentiva'),
            'add_new_item'       => __('Add New Log', 'mhm-rentiva'),
            'edit_item'          => __('Edit Log', 'mhm-rentiva'),
            'new_item'           => __('New Log', 'mhm-rentiva'),
            'view_item'          => __('View Log', 'mhm-rentiva'),
            'search_items'       => __('Search Logs', 'mhm-rentiva'),
            'not_found'          => __('No logs found.', 'mhm-rentiva'),
            'not_found_in_trash' => __('No logs found in Trash.', 'mhm-rentiva'),
        ];

        register_post_type(self::TYPE, [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // Manually added in Menu.php
            'supports'           => ['title', 'editor'],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
            'menu_position'      => null,
            'has_archive'        => false,
            'rewrite'            => false,
            'show_in_rest'       => false,
        ]);
    }
}
