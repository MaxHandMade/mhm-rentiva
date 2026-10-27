<?php
/**
 * One extra section of the results (PR S): "other pickup points in the same
 * city" or "booked for these dates". A labelled region with its own h2, so a
 * screen reader can list and jump to it; page 1 only, outside the totals.
 *
 * Rendered by the results page and by the AJAX re-render (same markup).
 *
 * @var string $kind   'nearby' or 'booked'.
 * @var string $id     The heading's id (unique on the page).
 * @var string $title  Section heading.
 * @var string $sub    Line under the heading ('' = none).
 * @var string $layout 'grid' or 'list' (the page's current view).
 * @var string $cards  Rendered cards (rvs-vehicle-card partials).
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_kind   = 'booked' === ( $kind ?? '' ) ? 'booked' : 'nearby';
$mhmrentiva_id     = (string) ( $id ?? 'rvsr-' . $mhmrentiva_kind );
$mhmrentiva_sub    = (string) ( $sub ?? '' );
$mhmrentiva_layout = 'list' === ( $layout ?? 'grid' ) ? 'rvs-card-list' : 'rvs-card-grid';
?>
<section class="rvs-results__section rvs-results__section--<?php echo esc_attr($mhmrentiva_kind); ?>" aria-labelledby="<?php echo esc_attr($mhmrentiva_id); ?>" data-rvs-section="<?php echo esc_attr($mhmrentiva_kind); ?>">
	<div class="rvs-results__section-head">
		<h2 class="rvs-results__section-title" id="<?php echo esc_attr($mhmrentiva_id); ?>"><?php echo esc_html( (string) ( $title ?? '' )); ?></h2>
		<?php if ('' !== $mhmrentiva_sub) : ?>
			<p class="rvs-results__section-sub"><?php echo esc_html($mhmrentiva_sub); ?></p>
		<?php endif; ?>
	</div>
	<div class="<?php echo esc_attr($mhmrentiva_layout); ?> rvs-results__section-grid" data-rvs-section-grid><?php \MHMRentiva\Helpers\Html::echo_markup( (string) ( $cards ?? '' )); ?></div>
</section>
