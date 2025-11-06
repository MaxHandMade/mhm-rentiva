<?php
/**
 * Vehicle Archive Template
 * 
 * Template for vehicle archive page
 * URL: /vehicles/
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

// CSS file is now automatically loaded by shortcode

get_header(); ?>

<div class="mhm-vehicles-archive-page">
    <!-- Main Content -->
    <div class="container">
        <?php 
        // Automatically run vehicle grid shortcode
        echo do_shortcode('[rentiva_vehicles_grid columns="3" show_price="1" show_features="1"]'); 
        ?>
    </div>
</div>

<?php get_footer(); ?>
