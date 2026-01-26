<?php

/**
 * Single Vehicle Template
 *
 * This template is automatically loaded by the plugin
 * Simple template for vehicle detail page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



// ⭐ Asset management removed - VehicleRatingForm shortcode handles asset loading
// The shortcode [rentiva_vehicle_rating_form] will automatically enqueue assets via VehicleRatingForm::enqueue_assets()

get_header(); ?>

<div class="mhm-vehicle-single-page">
	<!-- Navigation -->
	<div class="mhm-vehicle-navigation">
		<div class="container">
			<div class="mhm-nav-wrapper">
				<nav class="mhm-breadcrumb">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Home', 'mhm-rentiva' ); ?></a>
					<span class="separator">/</span>
					<a href="<?php echo esc_url( \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url( 'rentiva_vehicles_list' ) ); ?>"><?php echo esc_html__( 'Vehicles', 'mhm-rentiva' ); ?></a>
					<span class="separator">/</span>
					<span class="current"><?php echo esc_html( get_the_title() ); ?></span>
				</nav>

				<div class="mhm-navigation-actions">
					<a href="<?php echo esc_url( \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url( 'rentiva_vehicles_list' ) ); ?>" class="btn-back">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
							<path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z" />
						</svg>
						<?php echo esc_html__( 'Back to Vehicles', 'mhm-rentiva' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<div class="container">
		<!-- Vehicle Details Shortcode -->
		<?php echo do_shortcode( '[rentiva_vehicle_details]' ); ?>
	</div>

	<!-- Rating Form Section - Full Width -->
	<div class="rv-vehicle-rating-section-full">
		<div class="container">
			<?php echo do_shortcode( '[rentiva_vehicle_rating_form vehicle_id="' . get_the_ID() . '"]' ); ?>
		</div>
	</div>

</div>

<?php get_footer(); ?>
