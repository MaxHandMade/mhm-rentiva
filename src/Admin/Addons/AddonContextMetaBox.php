<?php
/**
 * Addon Context Metabox.
 *
 * @package MHMRentiva\Admin\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single-select radio metabox replacing the default checkbox UI for `addon_context`.
 * Uses radio inputs to keep tax_query semantics simple — exactly one term per addon.
 */
final class AddonContextMetaBox {

    public static function render( \WP_Post $post ): void {
        $current = wp_get_object_terms(
            $post->ID,
            AddonContextTaxonomy::TAXONOMY,
            array( 'fields' => 'slugs' )
        );
        $current = is_wp_error( $current ) || empty( $current )
            ? AddonContextTaxonomy::TERM_RENTAL
            : (string) $current[0];

        $options = array(
            AddonContextTaxonomy::TERM_RENTAL   => __( 'Rental only', 'mhm-rentiva' ),
            AddonContextTaxonomy::TERM_TRANSFER => __( 'Transfer only', 'mhm-rentiva' ),
            AddonContextTaxonomy::TERM_BOTH     => __( 'Both', 'mhm-rentiva' ),
        );

        wp_nonce_field( 'mhmrentiva_addon_context_save', 'mhmrentiva_addon_context_nonce' );

        echo '<div class="mhm-addon-context-radio">';
        echo '<p class="description">' . esc_html__(
            'Choose which booking type this add-on applies to.',
            'mhm-rentiva'
        ) . '</p>';

        foreach ( $options as $slug => $label ) {
            printf(
                '<label class="mhm-addon-context-option"><input type="radio" name="mhmrentiva_addon_context" value="%s" %s> %s</label><br>',
                esc_attr( $slug ),
                checked( $current, $slug, false ),
                esc_html( $label )
            );
        }

        echo '</div>';
    }

    /**
     * Persist the radio selection. Hooked on `save_post_mhmrentiva_addon`.
     */
    public static function save( int $post_id ): void {
        if ( ! isset( $_POST['mhmrentiva_addon_context_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( (string) $_POST['mhmrentiva_addon_context_nonce'] ) ),
            'mhmrentiva_addon_context_save'
        ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $value = isset( $_POST['mhmrentiva_addon_context'] )
            ? sanitize_key( wp_unslash( (string) $_POST['mhmrentiva_addon_context'] ) )
            : AddonContextTaxonomy::TERM_RENTAL;

        $allowed = array(
            AddonContextTaxonomy::TERM_RENTAL,
            AddonContextTaxonomy::TERM_TRANSFER,
            AddonContextTaxonomy::TERM_BOTH,
        );
        if ( ! in_array( $value, $allowed, true ) ) {
            $value = AddonContextTaxonomy::TERM_RENTAL;
        }

        wp_set_object_terms( $post_id, $value, AddonContextTaxonomy::TAXONOMY, false );
    }

    public static function register(): void {
        add_action( 'save_post_' . AddonPostType::POST_TYPE, array( self::class, 'save' ) );
    }
}
