<?php

/**
 * Vehicle Archive Template
 *
 * Template for vehicle archive page
 * URL: /vehicles/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



// CSS file is now automatically loaded by shortcode

get_header(); ?>

<div class="mhm-vehicles-archive-page">
	<!-- Main Content -->
	<div class="container">
		<?php
		// Automatically run vehicle grid shortcode
		echo do_shortcode( '[rentiva_vehicles_grid columns="3" show_price="1" show_features="1"]' );
		?>
	</div>
</div>

<?php get_footer(); ?>
