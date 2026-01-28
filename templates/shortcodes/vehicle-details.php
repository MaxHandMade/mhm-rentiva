<?php

/**
 * Vehicle Details Template - Premium Unified
 *
 * @var int $vehicle_id Vehicle ID
 * @var object $vehicle WP_Post object
 * @var array $atts Shortcode parameters
 * @var string $title Vehicle title
 * @var string $content Vehicle content
 * @var array $featured_image Featured image
 * @var array $gallery Gallery images
 * @var array $card_features Features from helper
 * @var float $price_per_day Daily price
 * @var string $currency_symbol Currency symbol
 * @var array $categories Categories
 * @var string $booking_url Booking URL
 * @var array $rating Rating information
 */

if (! defined('ABSPATH')) {
	exit;
}

?>

<div class="rv-vehicle-details-wrapper">
	<div class="rv-vehicle-details">

		<!-- PART 1: Gallery Section (Images Only) -->
		<div class="rv-vehicle-gallery-section">

			<!-- Gallery Container -->
			<div class="rv-gallery-container <?php echo empty($gallery) ? 'no-thumbnails' : ''; ?>">
				<!-- Main Image -->
				<div class="rv-main-image-wrapper">
					<img src="<?php echo esc_url($featured_image['url'] ?? ''); ?>"
						alt="<?php echo esc_attr($title ?? ''); ?>"
						class="rv-featured-image">

					<?php if (! empty($categories)) : ?>
						<div class="rv-category-badge">
							<a href="<?php echo esc_url($categories[0]['url'] ?? '#'); ?>">
								<?php echo esc_html($categories[0]['name']); ?>
							</a>
						</div>
					<?php endif; ?>

					<?php if (isset($is_available) && ! $is_available) : ?>
						<div class="rv-status-badge rv-status-badge--unavailable">
							<?php echo esc_html(! empty($status_text) ? $status_text : __('Out of Order', 'mhm-rentiva')); ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Thumbnail Gallery -->
				<?php if (! empty($gallery)) : ?>
					<div class="rv-gallery-thumbnails">
						<!-- Main image thumbnail -->
						<div class="rv-thumbnail-item active" data-index="main">
							<img src="<?php echo esc_url($featured_image['url'] ?? ''); ?>"
								alt="<?php echo esc_attr($title ?? ''); ?>"
								data-large="<?php echo esc_url($featured_image['url'] ?? ''); ?>">
						</div>

						<?php foreach ($gallery as $index => $image) : ?>
							<div class="rv-thumbnail-item" data-index="<?php echo esc_attr($index); ?>">
								<img src="<?php echo esc_url($image['url']); ?>"
									alt="<?php echo esc_attr($image['alt']); ?>"
									data-large="<?php echo esc_url($image['url_large']); ?>"
									data-full="<?php echo esc_url($image['url_full']); ?>">
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Short Description (Excerpt) - Stays with Gallery -->
			<?php if (! empty($excerpt)) : ?>
				<div class="rv-vehicle-short-description">
					<?php echo wp_kses_post($excerpt); ?>
				</div>
			<?php endif; ?>

			<!-- Quick Vehicle Features (Chips) - Stays with Gallery -->
			<?php if (! empty($card_features)) : ?>
				<div class="rv-vehicle-meta-chips">
					<?php foreach ($card_features as $feature) : ?>
						<div class="rv-feature-item">
							<?php echo $feature['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
							?>
							<span class="rv-feature-text"><?php echo esc_html($feature['text']); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>

		<!-- PART 2: Content Section (Description & Reviews) - Separate for mobile reordering -->
		<div class="rv-vehicle-content-section">

			<!-- Vehicle Description -->
			<?php if (! empty($content)) : ?>
				<div class="rv-vehicle-description">
					<h3 class="rv-section-title"><?php esc_html_e('Vehicle Description', 'mhm-rentiva'); ?></h3>
					<div class="rv-description-text">
						<?php echo wp_kses_post($content); ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Ratings & Reviews -->
			<div class="rv-integrated-reviews-section">
				<?php echo do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $vehicle_id . '"]'); ?>
			</div>

		</div>

		<!-- Right Column: Booking, Pricing & Calendar (Sticky) -->
		<div class="rv-vehicle-info-section">
			<div class="rv-booking-card-sticky">

				<!-- Header (Title & Price) -->
				<div class="rv-header-main">
					<h1 class="rv-vehicle-title"><?php echo esc_html($title ?? ''); ?></h1>

					<?php if (($price_per_day ?? 0)) : ?>
						<div class="rv-price-tag">
							<div class="rv-price-val">
								<span class="rv-symbol"><?php echo esc_html($currency_symbol ?? '$'); ?></span>
								<span class="rv-amount"><?php echo esc_html(number_format(floatval($price_per_day))); ?></span>
							</div>
							<span class="rv-period"><?php esc_html_e('/day', 'mhm-rentiva'); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<!-- Star Rating Summary -->
				<div class="rv-stats-bar">
					<?php if (($rating ?? array()) && isset($rating['average']) && $rating['average'] > 0) : ?>
						<div class="rv-mini-stars">
							<?php
							$avg_rating = (float) ($rating['average'] ?? 0);
							for ($i = 1; $i <= 5; $i++) : ?>
								<span class="rv-star <?php echo $i <= $avg_rating ? 'filled' : ''; ?>">★</span>
							<?php endfor; ?>
						</div>
						<span class="rv-stat-text">
							<strong><?php echo esc_html(number_format(floatval($rating['average']), 1)); ?></strong>
							(<?php echo esc_html($rating['count'] ?? 0); ?> <?php esc_html_e('reviews', 'mhm-rentiva'); ?>)
						</span>
					<?php else : ?>
						<div class="rv-mini-stars">
							<span class="rv-star">★</span><span class="rv-star">★</span><span class="rv-star">★</span><span class="rv-star">★</span><span class="rv-star">★</span>
						</div>
						<span class="rv-stat-text"><?php esc_html_e('Not yet rated', 'mhm-rentiva'); ?></span>
					<?php endif; ?>
				</div>

				<!-- Book Now CTA -->
				<div class="rv-cta-container">
					<?php
					$btn_text = $atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva');
					if (isset($is_available) && ! $is_available) : ?>
						<button class="rv-btn-primary disabled" disabled>
							<span><?php echo esc_html($btn_text); ?></span>
						</button>
					<?php else : ?>
						<a href="<?php echo esc_url($booking_url ?? ''); ?>" class="rv-btn-primary">
							<span><?php echo esc_html($btn_text); ?></span>
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
								<line x1="5" y1="12" x2="19" y2="12"></line>
								<polyline points="12 5 19 12 12 19"></polyline>
							</svg>
						</a>
					<?php endif; ?>
				</div>

				<!-- Availability Calendar -->
				<?php if ((intval($vehicle_id ?? 0)) > 0) : ?>
					<div class="rv-mini-calendar-widget" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>">
						<div class="rv-cal-header">
							<h4 class="rv-cal-title"><?php esc_html_e('Availability', 'mhm-rentiva'); ?></h4>
							<div class="rv-cal-nav">
								<button class="rv-calendar-nav-btn" data-direction="prev">‹</button>
								<span id="rv-current-month-year"><?php echo esc_html(date_i18n('F Y')); ?></span>
								<button class="rv-calendar-nav-btn" data-direction="next">›</button>
							</div>
						</div>
						<div id="rv-calendar-container" class="rv-cal-body">
							<?php echo wp_kses_post(\MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::render_monthly_calendar(intval($vehicle_id))); ?>
						</div>
					</div>
				<?php endif; ?>

			</div>
		</div>

	</div>
</div>