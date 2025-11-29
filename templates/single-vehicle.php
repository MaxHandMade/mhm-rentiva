<?php
/**
 * Single Vehicle Template
 * 
 * This template is automatically loaded by the plugin
 * Simple template for vehicle detail page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
    }
    mhm_rentiva_load_textdomain();
}

// ⭐ Asset management removed - VehicleRatingForm shortcode handles asset loading
// The shortcode [rentiva_vehicle_rating_form] will automatically enqueue assets via VehicleRatingForm::enqueue_assets()

get_header(); ?>

<div class="mhm-vehicle-single-page">
    <!-- Navigation -->
    <div class="mhm-vehicle-navigation">
        <div class="container">
            <nav class="mhm-breadcrumb">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Home', 'mhm-rentiva'); ?></a>
                <span class="separator">›</span>
                <a href="<?php echo esc_url(get_post_type_archive_link('vehicle')); ?>"><?php echo esc_html__('Vehicles', 'mhm-rentiva'); ?></a>
                <span class="separator">›</span>
                <span class="current"><?php echo esc_html(get_the_title()); ?></span>
            </nav>
            
            <div class="mhm-navigation-actions">
                <a href="<?php echo esc_url(get_post_type_archive_link('vehicle')); ?>" class="btn-back">
                    ← <?php echo esc_html__('Back to Vehicles', 'mhm-rentiva'); ?>
                </a>
                <button type="button" class="btn-back-history" onclick="history.back()">
                    ← <?php echo esc_html__('Back', 'mhm-rentiva'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Vehicle Details Shortcode -->
        <?php echo do_shortcode('[rentiva_vehicle_details]'); ?>
    </div>

    <!-- Rating Form Section - Full Width -->
    <div class="rv-vehicle-rating-section-full">
        <div class="container">
            <?php echo do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . get_the_ID() . '"]'); ?>
        </div>
    </div>

</div>

<?php get_footer(); ?>
