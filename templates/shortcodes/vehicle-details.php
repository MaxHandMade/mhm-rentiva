<?php
/**
 * Vehicle page (`rentiva_vehicle_details`) on the front-end standard.
 *
 * Canvas `Arac-Detay` / `Arac-Detay-Mobil` (Aşama 2 PR B). Every value comes
 * from VehicleDetailsView::build() ($view); this file only prints it. The
 * booking card, the months and the gallery are partials the page's AJAX
 * endpoints render too.
 *
 * @var array<string, mixed> $view Built by VehicleDetailsView::build().
 * @var array<string, mixed> $atts Shortcode attributes.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Helpers\Icons;

if (! defined('ABSPATH')) {
	exit;
}

$view = isset($view) && is_array($view) ? $view : array();
$atts = isset($atts) && is_array($atts) ? $atts : array();
if (empty($view['vehicle_id'])) {
	return;
}

$flag = static function ($value, bool $default = true): bool {
	if ($value === null || $value === '') {
		return $default;
	}
	return in_array(strtolower( (string) $value), array( '1', 'true', 'yes', 'on' ), true);
};

$vehicle_id    = (int) $view['vehicle_id'];
$uid           = (string) $view['uid'];
$show_gallery  = $flag($atts['show_gallery'] ?? '1');
$show_features = $flag($atts['show_features'] ?? '1');
$show_price    = $flag($atts['show_pricing'] ?? '1') && $flag($atts['show_price'] ?? '1');
$show_booking  = $flag($atts['show_booking'] ?? '1') && $flag($atts['show_booking_button'] ?? '1') && ! empty($rental_bookable);
$show_calendar = $flag($atts['show_calendar'] ?? '1');
$show_terms    = $flag($atts['show_conditions'] ?? '1');
$show_rating   = $flag($atts['show_rating'] ?? '1');
$show_reviews  = $flag($atts['show_reviews'] ?? '1');
$show_similar  = $flag($atts['show_similar_vehicles'] ?? '1');
$show_favorite = $flag($atts['show_favorite_button'] ?? ( $atts['show_favorite_btn'] ?? '1' ));
$show_compare  = $flag($atts['show_compare_button'] ?? ( $atts['show_compare_btn'] ?? '1' ));
$is_preview    = RenderContext::current()->is_preview();
$booking       = (array) $view['booking'];
$rating        = $view['rating'];
?>
<div class="rvs-vd" id="<?php echo esc_attr($uid); ?>" data-rvs-vehicle="<?php echo esc_attr( (string) $vehicle_id); ?>">

	<nav class="rvs-vd__crumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'mhm-rentiva'); ?>">
		<ol>
			<?php foreach ( (array) $view['crumbs'] as $crumb) : ?>
				<li>
					<?php if ('' !== (string) $crumb['url']) : ?>
						<a href="<?php echo esc_url( (string) $crumb['url']); ?>"><?php echo esc_html( (string) $crumb['label']); ?></a>
					<?php else : ?>
						<span aria-current="page"><?php echo esc_html( (string) $crumb['label']); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>

	<header class="rvs-vd__head">
		<div class="rvs-vd__titles">
			<h1 class="rvs-vd__title"><?php echo esc_html( (string) $view['title']); ?></h1>
			<div class="rvs-vd__meta">
				<?php if (! empty($view['category'])) : ?>
					<span class="rvs-tag"><?php echo esc_html( (string) $view['category']['name']); ?></span>
				<?php endif; ?>
				<?php if ($show_rating && is_array($rating)) : ?>
					<span class="rvs-vd__rating">
						<span aria-hidden="true">★</span>
						<?php echo esc_html(number_format_i18n( (float) $rating['average'], 1)); ?> ·
						<?php if ($show_reviews) : ?>
							<a href="#<?php echo esc_attr($uid); ?>-reviews">
						<?php endif; ?>
						<?php
						/* translators: %s: number of reviews. */
						echo esc_html(sprintf(_n('%s review', '%s reviews', (int) $rating['count'], 'mhm-rentiva'), number_format_i18n( (int) $rating['count'])));
						?>
						<?php if ($show_reviews) : ?>
							</a>
						<?php endif; ?>
					</span>
				<?php endif; ?>
				<?php if ('' !== (string) $view['dealer_line']) : ?>
					<span class="rvs-vd__dealer"><?php echo esc_html( (string) $view['dealer_line']); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php if ($show_favorite || $show_compare) : ?>
			<div class="rvs-vd__actions">
				<?php if ($show_favorite) : ?>
					<button type="button" class="rvs-btn rvs-btn--secondary mhm-vehicle-favorite-btn<?php echo ! empty($is_favorite) ? ' is-active' : ''; ?>"
						data-vehicle-id="<?php echo esc_attr( (string) $vehicle_id); ?>"
						<?php RenderContext::the_nonce_attr_line('mhmrentiva_toggle_favorite'); ?>
						aria-pressed="<?php echo ! empty($is_favorite) ? 'true' : 'false'; ?>"<?php echo $is_preview ? ' disabled' : ''; ?>>
						<?php
						Icons::render(
							'heart',
							array(
								'class'  => 'mhm-heart-icon',
								'width'  => '18px',
								'height' => '18px',
							)
						);
						?>
						<span class="rvs-vd__action-label"><?php esc_html_e('Favorite', 'mhm-rentiva'); ?></span>
					</button>
				<?php endif; ?>
				<?php if ($show_compare) : ?>
					<button type="button" class="rvs-btn rvs-btn--secondary mhm-vehicle-compare-btn<?php echo ! empty($is_in_compare) ? ' is-active active' : ''; ?>"
						data-vehicle-id="<?php echo esc_attr( (string) $vehicle_id); ?>"
						<?php RenderContext::the_nonce_attr_line('mhmrentiva_toggle_compare'); ?>
						aria-pressed="<?php echo ! empty($is_in_compare) ? 'true' : 'false'; ?>"<?php echo $is_preview ? ' disabled' : ''; ?>>
						<?php
						Icons::render(
							'compare',
							array(
								'class'  => 'mhm-compare-icon',
								'width'  => '18px',
								'height' => '18px',
							)
						);
						?>
						<span class="rvs-vd__action-label"><?php esc_html_e('Compare', 'mhm-rentiva'); ?></span>
					</button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ($show_gallery && ! empty($view['gallery'])) : ?>
		<?php Templates::render('partials/vehicle-details/gallery', array( 'view' => $view ), false); ?>
	<?php endif; ?>

	<div class="rvs-vd__layout">
		<div class="rvs-vd__main">

			<?php if ($show_features && ! empty($view['highlights'])) : ?>
				<section class="rvs-vd__section" aria-labelledby="<?php echo esc_attr($uid); ?>-highlights">
					<h2 class="rvs-vd__h2" id="<?php echo esc_attr($uid); ?>-highlights"><?php esc_html_e('Highlights', 'mhm-rentiva'); ?></h2>
					<dl class="rvs-vd__specs">
						<?php foreach ( (array) $view['highlights'] as $spec) : ?>
							<div class="rvs-vd__spec">
								<dt><?php echo esc_html( (string) $spec['label']); ?></dt>
								<dd><?php echo esc_html( (string) $spec['value']); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</section>
			<?php endif; ?>

			<?php if ('' !== trim( (string) $view['content'])) : ?>
				<section class="rvs-vd__section rvs-vd__about" aria-labelledby="<?php echo esc_attr($uid); ?>-about">
					<h2 class="rvs-vd__h2" id="<?php echo esc_attr($uid); ?>-about"><?php esc_html_e('About this vehicle', 'mhm-rentiva'); ?></h2>
					<div class="rvs-vd__content"><?php echo wp_kses_post(wpautop( (string) $view['content'])); ?></div>
				</section>
			<?php endif; ?>

			<?php if ($show_terms && ! empty($view['conditions'])) : ?>
				<section class="rvs-vd__section" aria-labelledby="<?php echo esc_attr($uid); ?>-conditions">
					<h2 class="rvs-vd__h2" id="<?php echo esc_attr($uid); ?>-conditions"><?php esc_html_e('Rental conditions', 'mhm-rentiva'); ?></h2>
					<div class="rvs-vd__conditions">
						<?php foreach ( (array) $view['conditions'] as $condition) : ?>
							<div class="rvs-vd__condition">
								<strong><?php echo esc_html( (string) $condition['title']); ?></strong>
								<p><?php echo nl2br(esc_html( (string) $condition['text'])); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ($show_calendar) : ?>
				<section class="rvs-vd__section" aria-labelledby="<?php echo esc_attr($uid); ?>-availability">
					<div class="rvs-vd__section-head">
						<h2 class="rvs-vd__h2" id="<?php echo esc_attr($uid); ?>-availability"><?php esc_html_e('Availability', 'mhm-rentiva'); ?></h2>
						<div class="rvs-vd__month-nav">
							<button type="button" class="rvs-btn rvs-btn--icon" data-rvs-month-nav="-1" aria-label="<?php esc_attr_e('Previous month', 'mhm-rentiva'); ?>"><span aria-hidden="true">‹</span></button>
							<button type="button" class="rvs-btn rvs-btn--icon" data-rvs-month-nav="1" aria-label="<?php esc_attr_e('Next month', 'mhm-rentiva'); ?>"><span aria-hidden="true">›</span></button>
						</div>
					</div>
					<div class="rvs-vd__months" data-rvs-vd-months aria-live="polite">
						<?php foreach ( (array) $view['months'] as $month) : ?>
							<?php Templates::render('partials/rvs/calendar-month', array( 'calendar' => $month ), false); ?>
						<?php endforeach; ?>
					</div>
					<ul class="rvs-vd__legend">
						<li><span class="rvs-vd__swatch rvs-vd__swatch--selected" aria-hidden="true"></span><?php esc_html_e('Selected', 'mhm-rentiva'); ?></li>
						<li><span class="rvs-vd__swatch rvs-vd__swatch--booked" aria-hidden="true"></span><?php esc_html_e('Booked', 'mhm-rentiva'); ?></li>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ($show_reviews) : ?>
				<?php Templates::render('partials/vehicle-details/reviews', array( 'view' => $view ), false); ?>
			<?php endif; ?>
		</div>

		<?php if ($show_booking) : ?>
			<?php
			Templates::render(
				'partials/vehicle-details/booking-card',
				array(
					'view'       => $view,
					'atts'       => $atts,
					'show_price' => $show_price,
				),
				false
			);
			?>
		<?php endif; ?>
	</div>

	<?php
	if ($show_similar) :
		// An empty (or non-positive) similar_vehicles_limit keeps the old branch:
		// 6 in the mobile slider, 3 in the desktop grid; a set value replaces the
		// limit on both, capped at 12 (spec 3A §2.2).
		$is_mobile_view = wp_is_mobile();
		$similar_limit  = absint($atts['similar_vehicles_limit'] ?? 0);
		if ($similar_limit < 1) {
			$similar_limit = $is_mobile_view ? 6 : 3;
		}
		$similar_limit = min(12, $similar_limit);
		$similar_args  = '';
		if (! empty($view['category']['url'])) {
			$terms = get_the_terms($vehicle_id, 'mhmrentiva_vehicle_category');
			if (is_array($terms) && ! empty($terms[0]->slug)) {
				$similar_args = ' category="' . esc_attr(sanitize_title( (string) $terms[0]->slug)) . '"';
			}
		}
		// The vehicle on show is not "similar" to itself.
		$similar_args     .= ' exclude="' . $vehicle_id . '"';
		$related_shortcode = $is_mobile_view
			? '[rentiva_featured_vehicles layout="slider" limit="' . $similar_limit . '" columns="1"' . $similar_args . ' autoplay="0" interval="5000" show_features="1" show_favorite_button="1" show_compare_button="1" show_booking_button="1"]'
			: '[rentiva_featured_vehicles layout="grid" limit="' . $similar_limit . '" columns="3"' . $similar_args . ' show_features="1" show_favorite_button="1" show_compare_button="1" show_booking_button="1"]';
		// Render first: with no other featured vehicle the shortcode answers with
		// its empty-state paragraph, and a heading over it is noise.
		$related_html = Templates::render_shortcode($related_shortcode);
		if ('' !== trim($related_html) && false === strpos($related_html, 'class="mhm-rentiva-no-vehicles"')) :
			?>
			<section class="rvs-vd__similar" aria-labelledby="<?php echo esc_attr($uid); ?>-similar">
				<h2 class="rvs-vd__h2" id="<?php echo esc_attr($uid); ?>-similar"><?php esc_html_e('Similar vehicles', 'mhm-rentiva'); ?></h2>
				<?php Templates::output_rendered_html($related_html); ?>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
