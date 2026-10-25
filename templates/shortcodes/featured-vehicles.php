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
        <?php // Contract: vehicle-details hides its "Similar Premium Vehicles" section by looking for class="mhm-rentiva-no-vehicles" in this output. Theme overrides must keep this exact class on the empty state. ?>
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
                            \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/rvs-vehicle-card', array(
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
            <div class="rvs-card-grid" style="--rvs-cols: <?php echo esc_attr( (string) max(1, min(6, $columns))); ?>">
                <?php foreach ($vehicles as $vehicle) : ?>
                    <?php
                    // The partial escapes each value at its own output site.
                    \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/rvs-vehicle-card', array(
                        'vehicle' => $vehicle,
                        'layout'  => 'grid',
                        'atts'    => $atts,
                    ));
                    ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $atts['view_all_url'] ) ) : ?>
            <div class="rvs-listing__footer">
                <a href="<?php echo esc_url( $atts['view_all_url'] ); ?>" class="rvs-btn rvs-btn--secondary">
                    <?php echo esc_html( ! empty( $atts['view_all_text'] ) ? $atts['view_all_text'] : __( 'View All Vehicles', 'mhm-rentiva' ) ); ?>
                </a>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
