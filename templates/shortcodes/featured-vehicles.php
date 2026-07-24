<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Shortcode Template: Featured Vehicles
 *
 * @var array $vehicles
 * @var array $atts
 */

if (! defined('ABSPATH')) {
    exit;
}

$columns     = (int) ( $atts['columns'] ?? 3 );
$layout      = $atts['layout'] ?? 'slider';
$is_carousel = ( $layout === 'slider' || $layout === 'carousel' )
    && ! ( defined('REST_REQUEST') && REST_REQUEST ); // Show grid preview in block editor
?>

<div class="mhm-rentiva-featured-wrapper mhm-layout-<?php echo esc_attr($layout); ?>"
    data-columns="<?php echo esc_attr($columns); ?>"
    data-autoplay="<?php echo esc_attr($atts['autoplay'] ?? '1'); ?>"
    data-interval="<?php echo esc_attr($atts['interval'] ?? '5000'); ?>">

    <?php if (empty($vehicles)) : ?>
        <p class="mhm-rentiva-no-vehicles"><?php esc_html_e('No featured vehicles found.', 'mhm-rentiva'); ?></p>
    <?php else : ?>

        <?php if ($is_carousel) : ?>
            <?php
            $swiper_config = wp_json_encode(array(
                'columns'  => $columns,
                'autoplay' => ( $atts['autoplay'] ?? '1' ) !== '0',
                'interval' => (int) ( $atts['interval'] ?? 5000 ),
            ));
            ?>
            <div class="swiper mhm-featured-swiper" data-swiper='<?php echo esc_attr($swiper_config); ?>'
                style="--mhm-columns: <?php echo esc_attr($columns); ?>">
                <div class="swiper-wrapper">
                    <?php foreach ($vehicles as $vehicle) : ?>
                        <div class="swiper-slide">
                            <?php
                            // Rendered directly to output: the partial echoes its own markup and
                            // escapes each dynamic value at its own output site.
                            \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/vehicle-card', array(
                                'vehicle' => $vehicle,
                                'layout'  => 'grid', // Featured usually looks like grid cards
                                'atts'    => $atts,
                            ));
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        <?php else : ?>
            <!-- Grid Layout -->
            <div class="mhm-featured-grid" style="--mhm-columns: <?php echo esc_attr($columns); ?>">
                <?php foreach ($vehicles as $vehicle) : ?>
                    <div class="mhm-featured-grid-item">
                        <?php
                        // Rendered directly to output: the partial echoes its own markup and
                        // escapes each dynamic value at its own output site.
                        \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/vehicle-card', array(
                            'vehicle' => $vehicle,
                            'layout'  => 'grid',
                            'atts'    => $atts,
                        ));
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $atts['view_all_url'] ) ) : ?>
            <div class="rv-featured-vehicles__footer">
                <a href="<?php echo esc_url( $atts['view_all_url'] ); ?>" class="rv-featured-vehicles__view-all">
                    <?php echo esc_html( ! empty( $atts['view_all_text'] ) ? $atts['view_all_text'] : __( 'View All Vehicles', 'mhm-rentiva' ) ); ?>
                </a>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
