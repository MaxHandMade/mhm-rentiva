<?php

/**
 * Single Vehicle Template - Premium Unified Skin
 *
 * This template blends the Vehicle Details and Rating Form into a single
 * premium container with shared styling and consistent design tokens.
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;

get_header(); ?>

<div class="mhm-vehicle-single-page rv-premium-skin">
	<!-- Navigation / Breadcrumb -->
	<div class="mhm-vehicle-navigation">
		<div class="container">
			<div class="mhm-nav-wrapper">
				<nav class="mhm-breadcrumb">
					<a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html__('Home', 'mhm-rentiva'); ?></a>
					<span class="separator">/</span>
					<a href="<?php echo esc_url(\MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list')); ?>"><?php echo esc_html__('Vehicles', 'mhm-rentiva'); ?></a>
					<span class="separator">/</span>
					<span class="current"><?php echo esc_html(get_the_title()); ?></span>
				</nav>

				<div class="mhm-navigation-actions">
					<a href="<?php echo esc_url(\MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list')); ?>" class="btn-back">
						<?php Icons::render('back-arrow'); ?>
						<?php echo esc_html__('Back to Vehicles', 'mhm-rentiva'); ?>
					</a>
				</div>
			</div>
		</div>
	</div>

	<!-- Unified Premium Container -->
	<div class="container">
		<div class="rv-single-vehicle-unified-card">

			<!-- Section 1: All-in-One Highlights -->
			<div class="rv-unified-details-section">
				<?php \MHMRentiva\Admin\Core\Utilities\Templates::output_shortcode('[rentiva_vehicle_details]'); ?>
			</div>

		</div>
	</div>
</div>

<?php get_footer(); ?>
