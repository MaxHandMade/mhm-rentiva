<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Transfer Search Results Template
 *
 * @var array $data {
 *     @var array $results Search results from TransferSearchEngine.
 *     @var array $criteria Search criteria used.
 *     @var string $origin_name Name of the origin location.
 *     @var string $destination_name Name of the destination location.
 * }
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;

// Variables are mapped into local scope from prepare_template_data() return array:
// $results, $criteria, $origin_name, $destination_name, $atts
// (Templates::render exposes template vars directly, no $data wrapper needed)
$results          = $results ?? array();
$criteria         = $criteria ?? array();
$origin_name      = $origin_name ?? '';
$destination_name = $destination_name ?? '';

/**
 * Local helper to format price
 */
$format_price = function (float $price, string $currency = '') {
	if (function_exists('wc_price')) {
		return wc_price($price);
	}
	return $currency . number_format($price, 2);
};

// Visibility Controls
$show_summary_route = $atts['show_summary_route'] ?? true;
$show_summary_date  = $atts['show_summary_date'] ?? true;
$show_summary_pax   = $atts['show_summary_pax'] ?? true;

$show_image           = $atts['show_image'] ?? true;
$show_category        = $atts['show_category'] ?? true;
$show_title           = $atts['show_title'] ?? true;
$show_price           = $atts['show_price'] ?? true;
$show_booking_btn     = $atts['show_booking_btn'] ?? true;
$show_vehicle_details = $atts['show_vehicle_details'] ?? true;
$show_luggage_info    = $atts['show_luggage_info'] ?? true;
$show_passenger_count = $atts['show_passenger_count'] ?? true;
$show_route_info      = $atts['show_route_info'] ?? true;

// v1.3.3 Visibility Bridges
$fav_val  = $atts['show_favorite_button'] ?? ($atts['show_favorite_btn'] ?? true);
$show_fav = ($fav_val !== '0' && $fav_val !== 'false' && $fav_val !== false);

$comp_val     = $atts['show_compare_button'] ?? ($atts['show_compare_btn'] ?? true);
$show_compare = ($comp_val !== '0' && $comp_val !== 'false' && $comp_val !== false);
?>

<div class="mhm-transfer-results-page">
	<?php if ($show_summary_route) : ?>
		<div class="mhm-transfer-results__summary">
			<div class="mhm-transfer-results__summary-info">
				<h2 class="mhm-transfer-results__summary-route">
					<?php echo esc_html($origin_name); ?>
					<span class="mhm-transfer-card__route-arrow">&rarr;</span>
					<?php echo esc_html($destination_name); ?>
				</h2>
				<?php if ($show_summary_date || $show_summary_pax) : ?>
					<div class="mhm-transfer-results__summary-date">
						<?php if ($show_summary_date) : ?>
							<span class="rv-info-item">
								<?php Icons::render('calendar', ['width' => '14', 'height' => '14']); ?>
								<?php echo esc_html($criteria['date'] ?? ''); ?>
							</span>
							<span class="rv-info-item" style="margin-left: 15px;">
								<?php Icons::render('clock', ['width' => '14', 'height' => '14']); ?>
								<?php echo esc_html($criteria['time'] ?? ''); ?>
							</span>
						<?php endif; ?>
						<?php if ($show_summary_pax) : ?>
							<span class="rv-info-item" style="margin-left: 15px;">
								<?php Icons::render('users', ['width' => '14', 'height' => '14']); ?>
								<?php echo esc_html((string) (($criteria['adults'] ?? 0) + ($criteria['children'] ?? 0))); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if (empty($results)) : ?>
		<div class="mhm-transfer-results__empty">
			<div class="mhm-transfer-results__empty-icon"><?php Icons::render('info'); ?></div>
			<h3 class="mhm-transfer-results__empty-title"><?php esc_html_e('No transfers found', 'mhm-rentiva'); ?></h3>
			<p class="mhm-transfer-results__empty-text"><?php esc_html_e('No transfers found for the selected criteria. Please try different options.', 'mhm-rentiva'); ?></p>
		</div>
	<?php else : ?>
		<div class="mhm-transfer-results rv-unified-search-results">
			<?php
			foreach ($results as $item) :
				$vehicle_id  = $item['id'] ?? 0;
				$title       = $item['title'] ?? '';
				$image_url   = $item['image'] ?? '';
				$price       = (float) ($item['price'] ?? 0);
				$currency    = $item['currency'] ?? '';
				$category    = $item['category'] ?? '';
				$max_pax     = $item['max_pax'] ?? '';
				$luggage_cap = $item['luggage_capacity'] ?? '';
				$duration    = $item['duration'] ?? '';
				$distance    = $item['distance'] ?? '';
			?>
				<div class="mhm-transfer-card" data-vehicle-id="<?php echo esc_attr((string) $vehicle_id); ?>">
					<div class="mhm-card-header">
						<?php if ($image_url) : ?>
							<img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" class="mhm-transfer-card__image" loading="lazy">
						<?php else : ?>
							<div class="rv-no-image">
								<?php Icons::render('image'); ?>
							</div>
						<?php endif; ?>

						<?php if ($category) : ?>
							<span class="mhm-transfer-card__category"><?php echo esc_html($category); ?></span>
						<?php endif; ?>

						<div class="mhm-card-actions-overlay">
							<?php if ($show_fav) : ?>
								<?php
								$is_favorite = false;
								if (class_exists('\MHMRentiva\Admin\Services\FavoritesService')) {
									$current_user = get_current_user_id();
									if ($current_user) {
										$is_favorite = \MHMRentiva\Admin\Services\FavoritesService::is_favorite($current_user, $vehicle_id);
									}
								}
								?>
								<button class="mhm-card-favorite mhm-vehicle-favorite-btn <?php echo $is_favorite ? 'is-active' : ''; ?>"
									data-vehicle-id="<?php echo esc_attr((string) $vehicle_id); ?>"
									data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>"
									title="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>"
									aria-label="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>">
									<?php Icons::render('heart', ['class' => 'mhm-heart-icon']); ?>
								</button>
							<?php endif; ?>

							<?php if ($show_compare) : ?>
								<?php
								$is_in_compare = false;
								if (class_exists('\MHMRentiva\Admin\Services\CompareService')) {
									$is_in_compare = \MHMRentiva\Admin\Services\CompareService::is_in_compare($vehicle_id);
								}
								?>
								<button class="mhm-card-compare mhm-vehicle-compare-btn <?php echo $is_in_compare ? 'is-active active' : ''; ?>"
									data-vehicle-id="<?php echo esc_attr((string) $vehicle_id); ?>"
									data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_compare')); ?>"
									aria-label="<?php echo $is_in_compare ? esc_attr__('Remove Compare', 'mhm-rentiva') : esc_attr__('Compare', 'mhm-rentiva'); ?>">
									<?php Icons::render('compare'); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>

					<div class="mhm-transfer-card__info">
						<?php if ($show_title) : ?>
							<h3 class="mhm-transfer-card__title"><?php echo esc_html($title); ?></h3>
						<?php endif; ?>

						<?php if ($show_passenger_count || $show_luggage_info || $show_route_info || $show_vehicle_details) : ?>
							<div class="mhm-transfer-card__meta">
								<?php if ($max_pax && $show_passenger_count) : ?>
									<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Max Passengers', 'mhm-rentiva'); ?>">
										<?php Icons::render('users', ['width' => '14', 'height' => '14']); ?>
										<span><?php echo esc_html((string) $max_pax); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?></span>
									</div>
								<?php endif; ?>
								<?php if ($luggage_cap && $show_luggage_info) : ?>
									<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Luggage Capacity', 'mhm-rentiva'); ?>">
										<?php Icons::render('portfolio', ['width' => '14', 'height' => '14']); ?>
										<span><?php echo esc_html((string) $luggage_cap); ?> <?php esc_html_e('Luggage', 'mhm-rentiva'); ?></span>
									</div>
								<?php endif; ?>
								<?php if ($distance && $show_route_info) : ?>
									<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Distance', 'mhm-rentiva'); ?>">
										<?php Icons::render('location', ['width' => '14', 'height' => '14']); ?>
										<span><?php echo esc_html((string) $distance); ?> km</span>
									</div>
								<?php endif; ?>
								<?php if ($duration && $show_vehicle_details) : ?>
									<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Duration', 'mhm-rentiva'); ?>">
										<?php Icons::render('clock', ['width' => '14', 'height' => '14']); ?>
										<span><?php echo esc_html((string) $duration); ?> <?php esc_html_e('min', 'mhm-rentiva'); ?></span>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div class="mhm-transfer-card__footer">
							<?php if ($show_price) : ?>
								<div class="mhm-transfer-card__price">
									<span class="mhm-transfer-card__price-amount"><?php echo wp_kses_post((string) $format_price($price, $currency)); ?></span>
									<span class="mhm-transfer-card__price-period"><?php esc_html_e('/total', 'mhm-rentiva'); ?></span>
								</div>
							<?php endif; ?>

							<?php if ($show_booking_btn) : ?>
								<button class="mhm-transfer-card__btn js-mhm-transfer-book mhm-transfer-book-btn"
									data-vehicle-id="<?php echo esc_attr((string) $vehicle_id); ?>"
									data-price="<?php echo esc_attr((string) $price); ?>"
									data-origin-id="<?php echo esc_attr((string) ($criteria['origin_id'] ?? '')); ?>"
									data-destination-id="<?php echo esc_attr((string) ($criteria['destination_id'] ?? '')); ?>"
									data-date="<?php echo esc_attr($criteria['date'] ?? ''); ?>"
									data-time="<?php echo esc_attr($criteria['time'] ?? ''); ?>"
									data-adults="<?php echo esc_attr((string) ($criteria['adults'] ?? 1)); ?>"
									data-children="<?php echo esc_attr((string) ($criteria['children'] ?? 0)); ?>"
									data-luggage-big="<?php echo esc_attr((string) ($criteria['luggage_big'] ?? 0)); ?>"
									data-luggage-small="<?php echo esc_attr((string) ($criteria['luggage_small'] ?? 0)); ?>">
									<?php esc_html_e('Book Now', 'mhm-rentiva'); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>