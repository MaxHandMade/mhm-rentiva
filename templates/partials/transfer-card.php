<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;
use MHMRentiva\Admin\Core\CurrencyHelper;

$item     = isset($item) && is_array($item) ? $item : array();
$criteria = isset($criteria) && is_array($criteria) ? $criteria : array();
$atts     = isset($atts) && is_array($atts) ? $atts : array();

if (! isset($format_price) || ! is_callable($format_price)) {
	$format_price = static function (float $price, string $currency = ''): string {
		return CurrencyHelper::format_price($price, 0);
	};
}

$vehicle_id  = $item['id'] ?? 0;
$vehicle_title = $item['title'] ?? '';
$image_url   = $item['image'] ?? '';
$price       = (float) ( $item['price'] ?? 0 );
$currency    = $item['currency'] ?? '';
$category    = $item['category'] ?? '';
$max_pax     = $item['max_pax'] ?? '';
$luggage_cap = $item['luggage_capacity'] ?? '';
$duration    = $item['duration'] ?? '';
$distance    = $item['distance'] ?? '';

$show_title           = $atts['show_title'] ?? true;
$show_price           = $atts['show_price'] ?? true;
$show_booking_button  = $atts['show_booking_button'] ?? ( $atts['show_booking_btn'] ?? true );
$show_vehicle_details = $atts['show_vehicle_details'] ?? true;
$show_luggage_info    = $atts['show_luggage_info'] ?? true;
$show_passenger_count = $atts['show_passenger_count'] ?? true;
$show_route_info      = $atts['show_route_info'] ?? true;
$show_fav             = $atts['show_favorite_button'] ?? true;
$show_compare         = $atts['show_compare_button'] ?? true;
?>

<div class="mhm-transfer-card" data-vehicle-id="<?php echo esc_attr( (string) $vehicle_id); ?>">
	<div class="mhm-card-header">
		<?php if ($image_url) : ?>
			<img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($vehicle_title); ?>" class="mhm-transfer-card__image" loading="lazy">
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
					$current_user_id = get_current_user_id();
					if ($current_user_id) {
						$is_favorite = \MHMRentiva\Admin\Services\FavoritesService::is_favorite($current_user_id, $vehicle_id);
					}
				}
				?>
				<button class="mhm-card-favorite mhm-vehicle-favorite-btn <?php echo $is_favorite ? 'is-active' : ''; ?>"
					data-vehicle-id="<?php echo esc_attr( (string) $vehicle_id); ?>"
					data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_favorite')); ?>"
					title="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>"
					aria-label="<?php echo $is_favorite ? esc_attr__('Remove from Favorites', 'mhm-rentiva') : esc_attr__('Add to Favorites', 'mhm-rentiva'); ?>">
					<?php Icons::render('heart', array( 'class' => 'mhm-heart-icon' )); ?>
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
					data-vehicle-id="<?php echo esc_attr( (string) $vehicle_id); ?>"
					data-nonce="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_toggle_compare')); ?>"
					aria-label="<?php echo $is_in_compare ? esc_attr__('Remove Compare', 'mhm-rentiva') : esc_attr__('Compare', 'mhm-rentiva'); ?>">
					<?php Icons::render('compare'); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>

	<div class="mhm-transfer-card__info">
		<?php if ($show_title) : ?>
			<h3 class="mhm-transfer-card__title"><?php echo esc_html($vehicle_title); ?></h3>
		<?php endif; ?>

		<?php if ($show_passenger_count || $show_luggage_info || $show_route_info || $show_vehicle_details) : ?>
			<div class="mhm-transfer-card__meta">
				<?php if ($max_pax && $show_passenger_count) : ?>
					<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Max Passengers', 'mhm-rentiva'); ?>">
						<?php
                        Icons::render('users', array(
							'width'  => '14',
							'height' => '14',
						));
						?>
						<span><?php echo esc_html( (string) $max_pax); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?></span>
					</div>
				<?php endif; ?>
				<?php if ($luggage_cap && $show_luggage_info) : ?>
					<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Luggage Capacity', 'mhm-rentiva'); ?>">
						<?php
                        Icons::render('portfolio', array(
							'width'  => '14',
							'height' => '14',
						));
						?>
						<span><?php echo esc_html( (string) $luggage_cap); ?> <?php esc_html_e('Luggage', 'mhm-rentiva'); ?></span>
					</div>
				<?php endif; ?>
				<?php if ($distance && $show_route_info) : ?>
					<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Distance', 'mhm-rentiva'); ?>">
						<?php
                        Icons::render('location', array(
							'width'  => '14',
							'height' => '14',
						));
						?>
						<span><?php echo esc_html( (string) $distance); ?> km</span>
					</div>
				<?php endif; ?>
				<?php if ($duration && $show_vehicle_details) : ?>
					<div class="mhm-transfer-card__meta-item" title="<?php esc_attr_e('Duration', 'mhm-rentiva'); ?>">
						<?php
                        Icons::render('clock', array(
							'width'  => '14',
							'height' => '14',
						));
						?>
						<span><?php echo esc_html( (string) $duration); ?> <?php esc_html_e('min', 'mhm-rentiva'); ?></span>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="mhm-transfer-card__footer">
			<?php if ($show_price) : ?>
				<div class="mhm-transfer-card__price">
					<span class="mhm-transfer-card__price-amount"><?php echo wp_kses_post( (string) $format_price($price, $currency)); ?></span>
					<span class="mhm-transfer-card__price-period"><?php esc_html_e('/total', 'mhm-rentiva'); ?></span>
				</div>
			<?php endif; ?>

			<?php if ($show_booking_button) : ?>
				<button class="mhm-transfer-card__btn js-mhm-transfer-book mhm-transfer-book-btn"
					data-vehicle-id="<?php echo esc_attr( (string) $vehicle_id); ?>"
					data-price="<?php echo esc_attr( (string) $price); ?>"
					data-origin-id="<?php echo esc_attr( (string) ( $criteria['origin_id'] ?? '' )); ?>"
					data-destination-id="<?php echo esc_attr( (string) ( $criteria['destination_id'] ?? '' )); ?>"
					data-date="<?php echo esc_attr($criteria['date'] ?? ''); ?>"
					data-time="<?php echo esc_attr($criteria['time'] ?? ''); ?>"
					data-adults="<?php echo esc_attr( (string) ( $criteria['adults'] ?? 1 )); ?>"
					data-children="<?php echo esc_attr( (string) ( $criteria['children'] ?? 0 )); ?>"
					data-luggage-big="<?php echo esc_attr( (string) ( $criteria['luggage_big'] ?? 0 )); ?>"
					data-luggage-small="<?php echo esc_attr( (string) ( $criteria['luggage_small'] ?? 0 )); ?>">
					<?php esc_html_e('Book Now', 'mhm-rentiva'); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>
</div>
