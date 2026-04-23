<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;

$vehicle_id              = isset($vehicle_id) ? intval($vehicle_id) : 0;
$title                   = isset($title) ? (string) $title : '';
$content                 = isset($content) ? (string) $content : '';
$excerpt                 = isset($excerpt) ? (string) $excerpt : '';
$booking_url             = isset($booking_url) ? (string) $booking_url : '';
$transmission            = isset($transmission) ? (string) $transmission : '';
$fuel_type               = isset($fuel_type) ? (string) $fuel_type : '';
$seats                   = isset($seats) ? (string) $seats : '';
$price_per_day           = isset($price_per_day) ? floatval($price_per_day) : 0;
$price_per_day_formatted = isset($price_per_day_formatted) ? (string) $price_per_day_formatted : '';
$currency_symbol         = isset($currency_symbol) ? (string) $currency_symbol : '$';
$rating                  = isset($rating) && is_array($rating) ? $rating : array();
$categories              = isset($categories) && is_array($categories) ? $categories : array();
$card_features           = isset($card_features) && is_array($card_features) ? $card_features : array();
$gallery                 = isset($gallery) && is_array($gallery) ? $gallery : array();
$featured_image          = isset($featured_image) && is_array($featured_image) ? $featured_image : array(
	'url' => '',
	'alt' => '',
);
$atts                    = isset($atts) && is_array($atts) ? $atts : array();
$is_favorite             = isset($is_favorite) ? (bool) $is_favorite : false;
$is_in_compare           = isset($is_in_compare) ? (bool) $is_in_compare : false;

$flag = static function ($value, bool $default = true): bool {
	if ($value === null || $value === '') {
		return $default;
	}
	return in_array(strtolower( (string) $value), array( '1', 'true', 'yes', 'on' ), true);
};

// Service type check: transfer-only vehicles cannot be rented.
$service_type     = $vehicle_id ? get_post_meta($vehicle_id, '_rentiva_vehicle_service_type', true) : '';
$is_transfer_only = ( $service_type === 'transfer' );

$show_gallery_section = $flag($atts['show_gallery'] ?? '1', true);
$show_features        = $flag($atts['show_features'] ?? '1', true);
$show_pricing         = $flag($atts['show_pricing'] ?? '1', true);
$show_price           = $flag($atts['show_price'] ?? '1', true);
$show_booking         = $flag($atts['show_booking'] ?? '1', true);
$show_booking_button  = $flag($atts['show_booking_button'] ?? '1', true);
$show_booking_form    = $flag($atts['show_booking_form'] ?? '1', true);
$show_calendar        = $flag($atts['show_calendar'] ?? '1', true);
$show_favorite_button = $flag($atts['show_favorite_button'] ?? ( $atts['show_favorite_btn'] ?? '1' ), true);
$show_compare_button  = $flag($atts['show_compare_button'] ?? ( $atts['show_compare_btn'] ?? '1' ), true);

$primary_category   = ! empty($categories[0]) ? $categories[0] : array();
$category_name      = (string) ( $primary_category['name'] ?? __('Premium', 'mhm-rentiva') );
$category_url       = (string) ( $primary_category['url'] ?? '#' );
$category_slug      = (string) ( $primary_category['slug'] ?? '' );
$safe_category_slug = sanitize_title($category_slug);

$rating_average = isset($rating['average']) ? floatval($rating['average']) : 0;
$rating_count   = isset($rating['count']) ? intval($rating['count']) : 0;

$features_for_showcase        = is_array($card_features) ? array_slice($card_features, 0, 6) : array();
$detail_features_for_showcase = isset($detail_features) && is_array($detail_features)
	? array_slice($detail_features, 0, 6)
	: $features_for_showcase;
$meta_chips                   = is_array($card_features) ? array_slice($card_features, 0, 3) : array();

$allowed_svg_tags = array(
	'svg'      => array(
		'xmlns'           => true,
		'width'           => true,
		'height'          => true,
		'viewbox'         => true,
		'viewBox'         => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
		'class'           => true,
		'aria-hidden'     => true,
		'role'            => true,
		'focusable'       => true,
		'overflow'        => true,
		'style'           => true,
	),
	'path'     => array(
		'd'               => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
	),
	'circle'   => array(
		'cx'           => true,
		'cy'           => true,
		'r'            => true,
		'fill'         => true,
		'stroke'       => true,
		'stroke-width' => true,
	),
	'line'     => array(
		'x1'             => true,
		'y1'             => true,
		'x2'             => true,
		'y2'             => true,
		'stroke'         => true,
		'stroke-width'   => true,
		'stroke-linecap' => true,
	),
	'polyline' => array(
		'points'          => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
	),
	'polygon'  => array(
		'points'          => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linejoin' => true,
	),
	'rect'     => array(
		'x'            => true,
		'y'            => true,
		'width'        => true,
		'height'       => true,
		'rx'           => true,
		'ry'           => true,
		'fill'         => true,
		'stroke'       => true,
		'stroke-width' => true,
	),
	'g'        => array(
		'fill'   => true,
		'stroke' => true,
		'class'  => true,
	),
);
?>

<div class="rv-vehicle-details-wrapper rv-vd2">
	<div class="rv-vehicle-details rv-vd2-layout">
		<div class="rv-vd2-main">
			<?php if ($show_gallery_section) : ?>
				<section class="rv-vd2-card rv-vd2-gallery-card">
					<div class="rv-main-image-wrapper">
						<div class="rv-vd2-image-actions" aria-label="<?php esc_attr_e('Vehicle actions', 'mhm-rentiva'); ?>">
							<?php if ($show_favorite_button) : ?>
								<button class="rv-vd2-action-btn rv-vd2-favorite mhm-card-favorite mhm-vehicle-favorite-btn <?php echo esc_attr($is_favorite ? 'is-active' : ''); ?>"
									data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
									data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>"
									title="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>"
									aria-label="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>"
									aria-pressed="<?php echo $is_favorite ? 'true' : 'false'; ?>">
									<?php Icons::render('heart', array( 'class' => 'mhm-heart-icon' )); ?>
									<span class="text-label sr-only"><?php echo $is_favorite ? esc_html__('Remove from Favorites', 'mhm-rentiva') : esc_html__('Add to Favorites', 'mhm-rentiva'); ?></span>
								</button>
							<?php endif; ?>
							<?php if ($show_compare_button) : ?>
								<button class="rv-vd2-action-btn rv-vd2-compare mhm-card-compare mhm-vehicle-compare-btn <?php echo esc_attr($is_in_compare ? 'is-active active' : ''); ?>"
									data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>"
									data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_compare')); ?>"
									title="<?php esc_attr_e('Compare', 'mhm-rentiva'); ?>"
									aria-label="<?php esc_attr_e('Compare', 'mhm-rentiva'); ?>"
									aria-pressed="<?php echo $is_in_compare ? 'true' : 'false'; ?>">
									<?php Icons::render('compare', array( 'class' => 'mhm-compare-icon' )); ?>
									<span class="text-label sr-only"><?php esc_html_e('Compare', 'mhm-rentiva'); ?></span>
								</button>
							<?php endif; ?>
						</div>
						<img src="<?php echo esc_url($featured_image['url'] ?? ''); ?>"
							alt="<?php echo esc_attr($title ?? ''); ?>"
							class="rv-featured-image">
						<?php if (! empty($gallery) && count($gallery) > 3) : ?>
							<button type="button" class="rv-vd2-gallery-btn" aria-label="<?php echo esc_attr__('View all photos', 'mhm-rentiva'); ?>">
								<span aria-hidden="true">▦</span>
								<?php esc_html_e('All Photos', 'mhm-rentiva'); ?>
							</button>
						<?php endif; ?>
					</div>

					<?php if (! empty($gallery)) : ?>
						<div class="rv-gallery-thumbnails" data-total="<?php echo esc_attr(count($gallery) + 1); ?>">
							<div class="rv-thumbnail-item active" data-index="main">
								<img src="<?php echo esc_url($featured_image['url'] ?? ''); ?>"
									alt="<?php echo esc_attr($title ?? ''); ?>"
									data-large="<?php echo esc_url($featured_image['url'] ?? ''); ?>">
							</div>
							<?php foreach ($gallery as $index => $image) : ?>
								<div class="rv-thumbnail-item<?php echo $index >= 3 ? ' rv-thumb-hidden' : ''; ?>" data-index="<?php echo esc_attr($index); ?>">
									<img src="<?php echo esc_url($image['url']); ?>"
										alt="<?php echo esc_attr($image['alt']); ?>"
										data-large="<?php echo esc_url($image['url_large']); ?>"
										data-full="<?php echo esc_url($image['url_full']); ?>">
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section class="rv-vd2-card">
				<h3 class="rv-vd2-section-title"><?php esc_html_e('About Vehicle', 'mhm-rentiva'); ?></h3>
				<div class="rv-vd2-about">
					<?php if (! empty($content)) : ?>
						<?php echo wp_kses_post($content); ?>
					<?php elseif (! empty($excerpt)) : ?>
						<?php echo wp_kses_post($excerpt); ?>
					<?php else : ?>
						<p><?php esc_html_e('Detailed vehicle information will be available soon.', 'mhm-rentiva'); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<?php if ($show_features && ! empty($detail_features_for_showcase)) : ?>
				<section class="rv-vd2-card">
					<h3 class="rv-vd2-section-title"><?php esc_html_e('Highlighted Features', 'mhm-rentiva'); ?></h3>
					<div class="rv-vd2-feature-grid">
						<?php foreach ($detail_features_for_showcase as $feature) : ?>
							<div class="rv-vd2-feature-item">
								<span class="rv-vd2-feature-icon">
									<?php echo wp_kses( (string) ( $feature['svg'] ?? '' ), $allowed_svg_tags); ?>
								</span>
								<span class="rv-vd2-feature-label"><?php echo esc_html( (string) ( $feature['text'] ?? '' )); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<section class="rv-vd2-card">
				<h3 class="rv-vd2-section-title"><?php esc_html_e('Rental Conditions', 'mhm-rentiva'); ?></h3>
				<ul class="rv-vd2-policy-list">
					<li>
						<strong><?php esc_html_e('Age & Driver License', 'mhm-rentiva'); ?></strong>
						<span><?php esc_html_e('Minimum 25 years old and a valid driving license is required.', 'mhm-rentiva'); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e('Fuel Policy', 'mhm-rentiva'); ?></strong>
						<span><?php esc_html_e('Vehicle is delivered with full tank and must be returned full tank.', 'mhm-rentiva'); ?></span>
					</li>
					<li>
						<strong><?php esc_html_e('Mileage Limit', 'mhm-rentiva'); ?></strong>
						<span><?php esc_html_e('Daily mileage limit applies. Extra mileage can be billed.', 'mhm-rentiva'); ?></span>
					</li>
				</ul>
			</section>

			<?php if ($show_booking_form) : ?>
				<section class="rv-vd2-card rv-vd2-ratings-card">
					<h3 class="rv-vd2-section-title"><?php esc_html_e('Ratings & Reviews', 'mhm-rentiva'); ?></h3>
					<div class="rv-integrated-reviews-section">
						<?php echo do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . intval($vehicle_id) . '"]'); ?>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<aside class="rv-vd2-sidebar">
			<div class="rv-vd2-booking-card">
				<div class="rv-vd2-booking-head">
					<span class="rv-vd2-pill"><?php echo esc_html(strtoupper($category_name)); ?></span>
					<h1 class="rv-vehicle-title"><?php echo esc_html($title ?? ''); ?></h1>
				</div>

				<div class="rv-vd2-rating-line">
					<span class="rv-vd2-star" aria-hidden="true">★</span>
					<span class="rv-vd2-rating-value"><?php echo esc_html($rating_average > 0 ? number_format($rating_average, 1) : '0.0'); ?></span>
					<span class="rv-vd2-rating-count">(<?php echo esc_html($rating_count); ?> <?php esc_html_e('reviews', 'mhm-rentiva'); ?>)</span>
				</div>

				<div class="rv-vd2-meta-row">
					<?php if (! empty($meta_chips)) : ?>
						<?php foreach ($meta_chips as $chip) : ?>
							<?php $chip_text = isset($chip['text']) ? (string) $chip['text'] : ''; ?>
							<?php if ($chip_text === '') : ?>
								<?php continue; ?>
							<?php endif; ?>
							<span>
								<span class="rv-vd2-meta-icon"><?php echo wp_kses( (string) ( $chip['svg'] ?? '' ), $allowed_svg_tags); ?></span>
								<?php echo esc_html($chip_text); ?>
							</span>
						<?php endforeach; ?>
					<?php else : ?>
						<?php if (! empty($transmission)) : ?>
							<span><?php echo esc_html($transmission); ?></span>
						<?php endif; ?>
						<?php if (! empty($fuel_type)) : ?>
							<span><?php echo esc_html($fuel_type); ?></span>
						<?php endif; ?>
						<?php if (! empty($seats)) : ?>
							<span><?php echo esc_html($seats . ' ' . __('Seats', 'mhm-rentiva')); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php if ($is_transfer_only) : ?>
				<div class="rv-transfer-only-notice" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; text-align: center;">
					<p style="margin: 0 0 4px; font-weight: 600; color: #856404;">
						<?php
                        Icons::render('info', array(
							'width'  => '16',
							'height' => '16',
						));
						?>
						<?php esc_html_e('This vehicle is for transfer service only.', 'mhm-rentiva'); ?>
					</p>
					<p style="margin: 0; font-size: 13px; color: #856404;">
						<?php esc_html_e('This vehicle cannot be rented. You can book it through VIP Transfer service.', 'mhm-rentiva'); ?>
					</p>
					<a href="<?php echo esc_url(home_url('/transfer-hizmeti/')); ?>" class="rv-btn-primary" style="margin-top: 12px; display: inline-block;">
						<span><?php esc_html_e('Search Transfer', 'mhm-rentiva'); ?></span>
					</a>
				</div>
			<?php else : ?>
				<?php if ($show_pricing && $show_price && ( $price_per_day ?? 0 )) : ?>
					<div class="rv-vd2-price-block">
						<p class="rv-vd2-price-label"><?php esc_html_e('Daily Rate', 'mhm-rentiva'); ?></p>
						<p class="rv-vd2-price-main"><?php echo esc_html($price_per_day_formatted !== '' ? $price_per_day_formatted : \MHMRentiva\Admin\Core\CurrencyHelper::format_price( (float) $price_per_day, 0)); ?></p>
					</div>
				<?php endif; ?>

				<?php if ($show_booking && $show_booking_button) : ?>
					<div class="rv-cta-container">
						<?php $btn_text = $atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva'); ?>
						<?php if (isset($is_available) && ! $is_available) : ?>
							<button class="rv-btn-primary disabled" disabled><span><?php echo esc_html($btn_text); ?></span></button>
						<?php else : ?>
							<a href="<?php echo esc_url($booking_url ?? ''); ?>" class="rv-btn-primary"><span><?php echo esc_html($btn_text); ?></span></a>
						<?php endif; ?>
						<p class="rv-vd2-cancel-note"><?php esc_html_e('Free cancellation according to conditions.', 'mhm-rentiva'); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

				<?php if ($show_calendar && ( intval($vehicle_id ?? 0) ) > 0) : ?>
					<div class="rv-mini-calendar-widget" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>">
						<div class="rv-cal-header">
							<h4 class="rv-cal-title"><?php esc_html_e('Availability', 'mhm-rentiva'); ?></h4>
							<div class="rv-cal-nav">
								<button class="rv-calendar-nav-btn" data-direction="prev" aria-label="<?php echo esc_attr__('Previous month', 'mhm-rentiva'); ?>">‹</button>
								<span id="rv-current-month-year"><?php echo esc_html(date_i18n('F Y')); ?></span>
								<button class="rv-calendar-nav-btn" data-direction="next" aria-label="<?php echo esc_attr__('Next month', 'mhm-rentiva'); ?>">›</button>
							</div>
						</div>
						<div id="rv-calendar-container" class="rv-cal-body">
							<?php echo wp_kses_post(\MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::render_monthly_calendar(intval($vehicle_id))); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</aside>
	</div>

	<section class="rv-vd2-related">
		<h3 class="rv-vd2-related-title"><?php esc_html_e('Similar Premium Vehicles', 'mhm-rentiva'); ?></h3>
		<p class="rv-vd2-related-subtitle"><?php esc_html_e('Users who viewed this vehicle also checked these.', 'mhm-rentiva'); ?></p>
		<?php
		$is_mobile_view    = wp_is_mobile();
		$related_shortcode = $is_mobile_view
			? '[rentiva_featured_vehicles title="" layout="slider" limit="6" columns="1" autoplay="0" interval="5000" show_features="1" show_favorite_button="1" show_compare_button="1" show_booking_button="1"]'
			: '[rentiva_featured_vehicles title="" layout="grid" limit="3" columns="3" show_features="1" show_favorite_button="1" show_compare_button="1" show_booking_button="1"]';
		if (! empty($safe_category_slug)) {
			$related_shortcode = $is_mobile_view
				? '[rentiva_featured_vehicles title="" layout="slider" limit="6" columns="1" category="' . esc_attr($safe_category_slug) . '" autoplay="0" interval="5000" show_features="1" show_favorite_button="1" show_compare_button="1" show_booking_button="1"]'
				: '[rentiva_featured_vehicles title="" layout="grid" limit="3" columns="3" category="' . esc_attr($safe_category_slug) . '" show_features="1" show_favorite_button="1" show_compare_button="1" show_booking_button="1"]';
		}
		?>
		<?php echo do_shortcode($related_shortcode); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</section>
</div>
