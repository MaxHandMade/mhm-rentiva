<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\PostTypes\Payouts;

use MHMRentiva\Admin\PostTypes\Taxonomies;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Registers the 'mhm_payout' Custom Post Type resolving Model B workflow states off-ledger.
 */
final class PostType
{
    public const POST_TYPE = 'mhm_payout';

    /**
     * Register the CPT
     */
    public static function register(): void
    {
        add_action('init', array(self::class, 'register_post_type'));
    }

    /**
     * Run registration logic natively cleanly.
     */
    public static function register_post_type(): void
    {
        $labels = array(
            'name'                  => __('Payout Requests', 'mhm-rentiva'),
            'singular_name'         => __('Payout Request', 'mhm-rentiva'),
            'menu_name'             => __('Payouts', 'mhm-rentiva'),
            'name_admin_bar'        => __('Payout Request', 'mhm-rentiva'),
            'add_new'               => __('Add New', 'mhm-rentiva'),
            'add_new_item'          => __('Add New Payout Request', 'mhm-rentiva'),
            'new_item'              => __('New Payout Request', 'mhm-rentiva'),
            'edit_item'             => __('Edit Payout Request', 'mhm-rentiva'),
            'view_item'             => __('View Payout Request', 'mhm-rentiva'),
            'all_items'             => __('All Payout Requests', 'mhm-rentiva'),
            'search_items'          => __('Search Payout Requests', 'mhm-rentiva'),
            'not_found'             => __('No payout requests found.', 'mhm-rentiva'),
            'not_found_in_trash'    => __('No payout requests found in Trash.', 'mhm-rentiva'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false, // Internal workflow only
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'mhm-rentiva',
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array('title', 'author'),
            'map_meta_cap'        => true,
        );

        register_post_type(self::POST_TYPE, $args);
    }
}
