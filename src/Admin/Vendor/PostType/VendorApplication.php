<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\PostType;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the mhm_vendor_app custom post type.
 * Stores vendor onboarding applications with document uploads and status tracking.
 */
final class VendorApplication
{
    public const POST_TYPE = 'mhm_vendor_app';

    public static function register(): void
    {
        add_action('init', array(self::class, 'register_post_type'));
    }

    public static function register_post_type(): void
    {
        $labels = array(
            'name'               => __('Vendor Applications', 'mhm-rentiva'),
            'singular_name'      => __('Vendor Application', 'mhm-rentiva'),
            'menu_name'          => __('Vendor Applications', 'mhm-rentiva'),
            'add_new'            => __('Add New', 'mhm-rentiva'),
            'add_new_item'       => __('Add New Application', 'mhm-rentiva'),
            'edit_item'          => __('Review Application', 'mhm-rentiva'),
            'view_item'          => __('View Application', 'mhm-rentiva'),
            'search_items'       => __('Search Applications', 'mhm-rentiva'),
            'not_found'          => __('No applications found.', 'mhm-rentiva'),
            'not_found_in_trash' => __('No applications found in trash.', 'mhm-rentiva'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => false,
            'show_in_menu'       => false,
            'query_var'          => false,
            'rewrite'            => false,
            'capabilities'       => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options',
            ),
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title', 'author'),
            'show_in_rest'       => false,
        );

        register_post_type(self::POST_TYPE, $args);
    }
}
