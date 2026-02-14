<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Vehicle Archive Template
 *
 * Template for vehicle archive page
 * URL: /vehicles/
 */

if (! defined('ABSPATH')) {
    exit;
}



// CSS file is now automatically loaded by shortcode

get_header(); ?>

<div class="mhm-vehicles-archive-page">
    <!-- Main Content -->
    <div class="container">
        <?php
        // Enqueue styles manually since we are not using the shortcode
        wp_enqueue_style('mhm-rentiva-vehicles-grid');
        wp_enqueue_style('mhm-vehicle-card-css');

        if (have_posts()) : ?>
            <div class="rv-vehicles-grid-wrapper rv-vehicles-grid rv-vehicles-grid--columns-3">
                <?php while (have_posts()) : the_post();
                    // Prepare data using the standard helper
                    // We rely on VehiclesList helper as it has the robust data preparation logic
                    $vehicle_data = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_data_for_shortcode(get_the_ID(), [
                        'price_format' => 'daily',
                        'max_features' => 4,
                        'image_size'   => 'medium'
                    ]);

                    if ($vehicle_data) :
                        // Attributes for the card toggles
                        $card_atts = [
                            'show_image'       => true,
                            'show_category'    => true,
                            'show_brand'       => false,
                            'show_features'    => true,
                            'show_price'       => true,
                            'show_rating'      => true,
                            'show_booking_btn' => true,
                            'show_favorite_btn' => true,
                            'show_badges'      => true,
                            'show_availability' => false,
                            'show_compare_btn' => false,
                            'booking_btn_text' => esc_html__('Book Now', 'mhm-rentiva')
                        ];

                        // Load the partial
                        set_query_var('vehicle', $vehicle_data);
                        set_query_var('layout', 'grid');
                        set_query_var('atts', $card_atts);
                        load_template(MHM_RENTIVA_PLUGIN_DIR . 'templates/partials/vehicle-card.php', false);
                    endif;
                endwhile; ?>
            </div>

            <!-- Pagination -->
            <div class="mhm-pagination">
                <?php
                echo wp_kses_post((string) paginate_links([
                    'prev_text' => __('&laquo; Previous', 'mhm-rentiva'),
                    'next_text' => __('Next &raquo;', 'mhm-rentiva'),
                ]));
                ?>
            </div>

        <?php else : ?>
            <p><?php esc_html_e('No vehicles found.', 'mhm-rentiva'); ?></p>
        <?php endif; ?>

    </div>
</div>

<?php get_footer(); ?>