<?php
/**
 * Addon Context Taxonomy.
 *
 * @package MHMRentiva\Admin\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the `addon_context` taxonomy on `vehicle_addon` and seeds the three default terms.
 * Schema lane only — runs on every `init` to ensure the taxonomy + terms exist.
 */
final class AddonContextTaxonomy {

    public const TAXONOMY = 'addon_context';

    public const TERM_RENTAL   = 'rental';
    public const TERM_TRANSFER = 'transfer';
    public const TERM_BOTH     = 'both';

    public static function register(): void {
        $labels = array(
            'name'          => __( 'Contexts', 'mhm-rentiva' ),
            'singular_name' => __( 'Context', 'mhm-rentiva' ),
            'menu_name'     => __( 'Context', 'mhm-rentiva' ),
            'all_items'     => __( 'All contexts', 'mhm-rentiva' ),
            'edit_item'     => __( 'Edit context', 'mhm-rentiva' ),
            'view_item'     => __( 'View context', 'mhm-rentiva' ),
            'update_item'   => __( 'Update context', 'mhm-rentiva' ),
            'add_new_item'  => __( 'Add new context', 'mhm-rentiva' ),
            'new_item_name' => __( 'New context name', 'mhm-rentiva' ),
        );

        register_taxonomy(
            self::TAXONOMY,
            array( AddonPostType::POST_TYPE ),
            array(
                'labels'             => $labels,
                'public'             => false,
                'publicly_queryable' => false,
                'show_ui'            => true,
                'show_in_menu'       => false,
                'show_in_rest'       => true,
                'hierarchical'       => false,
                'meta_box_cb'        => array( AddonContextMetaBox::class, 'render' ),
                'rewrite'            => false,
                'show_admin_column'  => false, // custom badge column rendered by AddonManager (Task 9)
                'default_term'       => array(
                    'name' => self::TERM_RENTAL,
                    'slug' => self::TERM_RENTAL,
                ),
            )
        );
    }

    public static function seed_default_terms(): void {
        $terms = array(
            self::TERM_RENTAL   => __( 'Rental', 'mhm-rentiva' ),
            self::TERM_TRANSFER => __( 'Transfer', 'mhm-rentiva' ),
            self::TERM_BOTH     => __( 'Both', 'mhm-rentiva' ),
        );

        foreach ( $terms as $slug => $name ) {
            if ( ! term_exists( $slug, self::TAXONOMY ) ) {
                wp_insert_term( $name, self::TAXONOMY, array( 'slug' => $slug ) );
            }
        }
    }
}
