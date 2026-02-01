<?php

/**
 * Vehicles List Template - Premium Chip Style Layout
 *
 * This template implements a high-end design with feature chips and soft shadows.
 *
 * @var array $atts Shortcode attributes
 * @var array $vehicles Vehicle data array
 * @var int $total_vehicles Total vehicle count
 * @var bool $has_vehicles Whether vehicles exist
 * @var string $layout_class Layout CSS class
 * @var string $columns_class Columns CSS class
 * @var array $context Global settings context
 */

if (! defined('ABSPATH')) {
	exit;
}

// Data Preparation & Defaults
$atts           = $atts ?? array();
$vehicles       = $vehicles ?? array();
$total_vehicles = $total_vehicles ?? 0;
$has_vehicles   = $has_vehicles ?? false;
$layout_class   = $layout_class ?? 'rv-vehicles-list';
$columns_class  = $columns_class ?? 'rv-vehicles-list--columns-1';
$wrapper_class  = $wrapper_class ?? '';
$booking_url    = $booking_url ?? \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_booking_form');
$context        = $context ?? array();

?>

<div class="rv-vehicles-list-container <?php echo esc_attr($wrapper_class); ?>">

	<?php if ($has_vehicles) : ?>

		<div class="rv-vehicles-list__wrapper <?php echo esc_attr($layout_class . ' ' . $columns_class); ?>" data-columns="<?php echo esc_attr($atts['columns']); ?>">

			<?php foreach ($vehicles as $vehicle) : ?>
				<div class="rv-vehicle-card rv-vehicle-card--list" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">

					<!-- 1. Visual Section: Image & Star Rating Overlay -->
					<?php
					$show_images_shortcode = ($atts['show_image'] ?? null);
					$show_images_final     = $show_images_shortcode !== null ? ($show_images_shortcode === '1') : ($context['show_images'] ?? true);
					?>

					<?php if ($show_images_final) : ?>
						<div class="rv-vehicle-card__image">
							<a href="<?php echo esc_url($vehicle['permalink']); ?>" class="rv-vehicle-card__image-link">
								<img src="<?php echo esc_url($vehicle['image_url']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" loading="lazy" class="rv-vehicle-card__img">
							</a>

							<?php if (($atts['show_rating'] ?? '1') === '1' && $vehicle['rating']['count'] > 0) : ?>
								<div class="rv-vehicle-card__rating-overlay">
									<span class="rv-stars"><?php echo esc_html($vehicle['rating']['stars']); ?></span>
									<span class="rv-rating-count">(<?php echo esc_html($vehicle['rating']['count']); ?>)</span>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- 2. Content Section: Chips & Specs -->
					<div class="rv-vehicle-card__content">

						<!-- Favorite Button - Positioned Absolute via CSS -->
						<?php if (($atts['show_favorite_btn'] ?? '1') === '1') : ?>
							<?php
							$is_favorite    = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::is_favorite($vehicle['id']);
							$favorite_class = $is_favorite ? 'is-favorited' : '';
							?>
							<button class="rv-vehicle-card__favorite <?php echo esc_attr($favorite_class); ?>" data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>" aria-label="<?php esc_attr_e('Add to favorites', 'mhm-rentiva'); ?>">
								<svg class="rv-heart-icon" width="24" height="24" viewBox="0 0 24 24" fill="<?php echo esc_attr($is_favorite ? 'currentColor' : 'none'); ?>" stroke="currentColor" stroke-width="2">
									<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
								</svg>
							</button>
						<?php endif; ?>

						<!-- Title & Category Information -->
						<div class="rv-vehicle-card__header">
							<div class="rv-vehicle-card__title-section">
								<?php if (($atts['show_title'] ?? '1') === '1') : ?>
									<h3 class="rv-vehicle-card__title">
										<a href="<?php echo esc_url($vehicle['permalink']); ?>">
											<?php echo esc_html($vehicle['title']); ?>
										</a>
									</h3>
								<?php endif; ?>

								<?php if (($atts['show_category'] ?? '1') === '1' && ! empty($vehicle['category'])) : ?>
									<span class="rv-vehicle-card__category"><?php echo esc_html($vehicle['category']); ?></span>
								<?php endif; ?>
							</div>

							<div class="rv-vehicle-card__header-actions">
								<?php
								// Status Badges
								$show_availability_shortcode = ($atts['show_availability'] ?? null);
								$show_availability_final     = $show_availability_shortcode !== null ? ($show_availability_shortcode === '1') : ($context['show_availability'] ?? true);

								$is_available = $vehicle['availability']['is_available'] ?? true;
								$status_text  = $vehicle['availability']['text'] ?? ($is_available ? __('Available', 'mhm-rentiva') : __('Unavailable', 'mhm-rentiva'));

								if (! $is_available || $show_availability_final) :
									$status_class = $is_available ? 'available' : 'unavailable';
								?>
									<span class="rv-badge rv-badge--<?php echo esc_attr($status_class); ?>">
										<?php echo esc_html($status_text); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>

						<!-- Description (Brief) -->
						<?php if (($atts['show_description'] ?? '0') === '1' && ! empty($vehicle['excerpt'])) : ?>
							<div class="rv-vehicle-card__description">
								<?php echo wp_kses_post(wp_trim_words($vehicle['excerpt'], 20)); ?>
							</div>
						<?php endif; ?>

						<!-- Feature Chips Grid -->
						<?php
						$show_features_shortcode = ($atts['show_features'] ?? null);
						$show_features_final     = $show_features_shortcode !== null ? ($show_features_shortcode === '1') : ($context['show_features'] ?? true);
						?>

						<?php if ($show_features_final && ! empty($vehicle['features'])) : ?>
							<div class="rv-vehicle-card__features">
								<?php foreach ($vehicle['features'] as $feature) : ?>
									<div class="rv-feature-item">
										<?php echo $feature['svg']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
										?>
										<span class="rv-feature-text"><?php echo esc_html($feature['value'] ?? $feature['text'] ?? ''); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<!-- 3. Conversion Row: Pricing & Primary Action -->
						<?php if (($atts['show_price'] ?? '1') === '1' || ($atts['show_booking_btn'] ?? '1') === '1') : ?>
							<div class="rv-vehicle-card__footer">
								<?php if (($atts['show_price'] ?? '1') === '1') : ?>
									<div class="rv-vehicle-card__price">
										<span class="rv-price-amount"><?php echo esc_html($vehicle['price']['formatted']); ?></span>
										<span class="rv-price-period"><?php echo esc_html__('/day', 'mhm-rentiva'); ?></span>
									</div>
								<?php endif; ?>

								<?php if (($atts['show_booking_btn'] ?? '1') === '1') : ?>
									<div class="rv-vehicle-card__actions">
										<?php
										$btn_class = 'rv-btn-booking has-primary-background-color has-text-color';
										$btn_href  = esc_url(add_query_arg('vehicle_id', $vehicle['id'], $booking_url));

										if (! $is_available) {
											$btn_class .= ' rv-btn-disabled';
											$btn_href   = 'javascript:void(0);';
										}
										?>
										<a href="<?php echo esc_url($btn_href); ?>"
											class="<?php echo esc_attr($btn_class); ?>"
											style="background-color: var(--mhm-btn-bg); color: var(--mhm-btn-color);"
											data-vehicle-id="<?php echo esc_attr($vehicle['id']); ?>">
											<?php echo esc_html($atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva')); ?>
										</a>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

					</div>
				</div>
			<?php endforeach; ?>
		</div>

	<?php else : ?>

		<div class="rv-vehicles-list__empty">
			<p><?php echo esc_html__('No vehicles found yet.', 'mhm-rentiva'); ?></p>
		</div>

	<?php endif; ?>

</div>