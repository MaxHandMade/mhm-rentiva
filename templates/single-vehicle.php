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
						<?php echo esc_html__('Araçlara Geri Dön', 'mhm-rentiva'); ?>
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
				<?php echo do_shortcode('[rentiva_vehicle_details]'); ?>
			</div>

		</div>
	</div>
</div>

<style>
	/* Refined Layout for High-End Feel */
	.rv-premium-skin {
		padding-bottom: 4rem;
		background: #f8fafc;
	}

	.rv-single-vehicle-unified-card {
		background: #ffffff;
		border-radius: 20px;
		box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
		overflow: hidden;
		margin-top: 2rem;
		border: 1px solid #edf2f7;
	}

	/* Breadcrumb Styling */
	.mhm-vehicle-navigation {
		background: #fff;
		padding: 1.25rem 0;
		border-bottom: 1px solid #edf2f7;
	}

	.mhm-nav-wrapper {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.mhm-breadcrumb {
		font-size: 14px;
		color: #64748b;
	}

	.mhm-breadcrumb a {
		color: #3182ce;
		text-decoration: none;
		font-weight: 500;
	}

	.mhm-breadcrumb .separator {
		margin: 0 8px;
		color: #cbd5e1;
	}

	.btn-back {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		color: #64748b;
		text-decoration: none;
		font-size: 14px;
		font-weight: 600;
		transition: color 0.2s;
	}

	.btn-back:hover {
		color: #3182ce;
	}
</style>

<?php get_footer(); ?>