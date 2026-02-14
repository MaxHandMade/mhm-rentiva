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

$columns = (int) ($atts['columns'] ?? 3);
$layout  = $atts['layout'] ?? 'slider';
?>

<div class="mhm-rentiva-featured-wrapper mhm-layout-<?php echo esc_attr($layout); ?>"
    data-columns="<?php echo esc_attr($columns); ?>"
    data-autoplay="<?php echo esc_attr($atts['autoplay']); ?>"
    data-interval="<?php echo esc_attr($atts['interval']); ?>">

    <?php if (! empty($atts['title'])): ?>
        <h2 class="mhm-rentiva-featured-title"><?php echo esc_html($atts['title']); ?></h2>
    <?php endif; ?>

    <?php if (empty($vehicles)): ?>
        <p class="mhm-rentiva-no-vehicles"><?php esc_html_e('No featured vehicles found.', 'mhm-rentiva'); ?></p>
    <?php else: ?>

        <?php if ($layout === 'slider'): ?>
            <div class="swiper mhm-featured-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($vehicles as $vehicle): ?>
                        <div class="swiper-slide">
                            <?php
                            echo \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/vehicle-card', array(
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
        <?php else: ?>
            <!-- Grid Layout -->
            <div class="mhm-featured-grid" style="--mhm-columns: <?php echo esc_attr($columns); ?>">
                <?php foreach ($vehicles as $vehicle): ?>
                    <div class="mhm-featured-grid-item">
                        <?php
                        echo \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/vehicle-card', array(
                            'vehicle' => $vehicle,
                            'layout'  => 'grid',
                            'atts'    => $atts,
                        ));
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>