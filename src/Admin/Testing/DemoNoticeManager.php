<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Testing;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * DemoNoticeManager
 *
 * Implements the 5-layer demo warning system:
 *   1. Global admin notice (orange, turns red after 7 days).
 *   2. Admin bar "DEMO ACTIVE" badge with quick-link to the test tab.
 *   3. Post-editor inline banner on demo records.
 *   4. List-table [DEMO] post-state badge on demo records.
 *   5. Seed summary card — rendered separately by the Setup Wizard / test tab.
 *
 * All layers are only active when the `mhm_rentiva_demo_active` option equals '1'.
 *
 * @package MHMRentiva\Admin\Testing
 * @since   4.25.1
 */
final class DemoNoticeManager
{
    /**
     * Registers all hooks. No-ops silently when demo is not active.
     *
     * @since 4.25.1
     */
    public static function register(): void
    {
        if (! self::is_demo_active()) {
            return;
        }

        add_action('admin_notices',  array( self::class, 'render_global_notice' ));
        add_action('admin_bar_menu', array( self::class, 'render_admin_bar_badge' ), 100);
        add_action('edit_form_top',  array( self::class, 'render_post_editor_banner' ));
        add_filter('display_post_states', array( self::class, 'add_demo_post_state' ), 10, 2);
    }

    /**
     * Returns true when the demo option is active.
     *
     * @since 4.25.1
     */
    public static function is_demo_active(): bool
    {
        return '1' === get_option('mhm_rentiva_demo_active');
    }

    /**
     * Returns true when demo data has been active for 7 or more days.
     *
     * @since 4.25.1
     */
    public static function is_expiry_warning(): bool
    {
        $seeded_at = (int) get_option('mhm_rentiva_demo_seeded_at', 0);

        if (0 === $seeded_at) {
            return false;
        }

        return ( time() - $seeded_at ) >= 7 * DAY_IN_SECONDS;
    }

    /**
     * Layer 1 — Global admin notice.
     *
     * Orange warning while fresh, red error after 7 days.
     *
     * @since 4.25.1
     */
    public static function render_global_notice(): void
    {
        $is_old   = self::is_expiry_warning();
        $class    = $is_old ? 'notice-error' : 'notice-warning';
        $message  = $is_old
            ? __( '⚠️ Demo data has been active for 7+ days. Clean up before using in production.', 'mhm-rentiva' )
            : __( 'ℹ️ Demo data is active. Remember to clean up before going live.', 'mhm-rentiva' );

        printf(
            '<div class="notice %s is-dismissible"><p><strong>MHM Rentiva Demo:</strong> %s</p></div>',
            esc_attr( $class ),
            esc_html( $message )
        );
    }

    /**
     * Layer 2 — Admin bar badge.
     *
     * Adds an orange "DEMO ACTIVE" node that links to the test settings tab.
     *
     * @since 4.25.1
     * @param \WP_Admin_Bar $wp_admin_bar WP admin bar instance.
     */
    public static function render_admin_bar_badge( \WP_Admin_Bar $wp_admin_bar ): void
    {
        $wp_admin_bar->add_node(
            array(
                'id'    => 'mhm-demo-active',
                'title' => '&#128992; DEMO ACTIVE',
                'href'  => admin_url( 'admin.php?page=mhm-rentiva-settings&tab=test' ),
                'meta'  => array( 'class' => 'mhm-demo-admin-bar-badge' ),
            )
        );
    }

    /**
     * Layer 3 — Post editor inline banner.
     *
     * Displays a warning inside the edit screen for demo records only.
     *
     * @since 4.25.1
     * @param \WP_Post $post Current post being edited.
     */
    public static function render_post_editor_banner( \WP_Post $post ): void
    {
        $demo_post_types = array( 'vehicle', 'vehicle_booking', 'vehicle_addon' );

        if ( ! in_array( $post->post_type, $demo_post_types, true ) ) {
            return;
        }

        if ( '1' !== get_post_meta( $post->ID, '_mhm_is_demo', true ) ) {
            return;
        }

        echo '<div class="notice notice-warning inline" style="margin:10px 0;">'
            . '<p>' . esc_html__( '⚠️ This is a demo record and will be removed during cleanup.', 'mhm-rentiva' ) . '</p>'
            . '</div>';
    }

    /**
     * Layer 4 — List-table [DEMO] post-state badge.
     *
     * Appended to the post title column in list tables for demo records.
     *
     * @since 4.25.1
     * @param  array<string,string> $post_states Existing post states.
     * @param  \WP_Post             $post        Current list-table row post.
     * @return array<string,string>
     */
    public static function add_demo_post_state( array $post_states, \WP_Post $post ): array
    {
        if ( '1' === get_post_meta( $post->ID, '_mhm_is_demo', true ) ) {
            $post_states['mhm_demo'] = __( '[DEMO]', 'mhm-rentiva' );
        }

        return $post_states;
    }
}
