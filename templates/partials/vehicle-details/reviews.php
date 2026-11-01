<?php
/**
 * Vehicle page reviews: the one heading and the shared summary, then the rating
 * form's list and form, which draw no heading of their own inside this page.
 *
 * @var array<string, mixed> $view VehicleDetailsView::build().
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_view    = (array) ( $view ?? array() );
$mhmrentiva_uid     = (string) $mhmrentiva_view['uid'];
$mhmrentiva_reviews = $mhmrentiva_view['reviews'];
?>
<section class="rvs-vd__section rvs-vd__reviews" id="<?php echo esc_attr($mhmrentiva_uid); ?>-reviews" aria-labelledby="<?php echo esc_attr($mhmrentiva_uid); ?>-reviews-title">
	<div class="rvs-vd__section-head">
		<h2 class="rvs-vd__h2" id="<?php echo esc_attr($mhmrentiva_uid); ?>-reviews-title"><?php esc_html_e('Reviews', 'mhm-rentiva'); ?></h2>
	</div>

	<?php if (is_array($mhmrentiva_reviews)) : ?>
		<?php
		Templates::render(
			'partials/rvs/review-summary',
			array(
				'summary'    => $mhmrentiva_reviews,
				'show_stars' => false,
			)
		);
		?>
	<?php endif; ?>

	<div class="rvs-vd__review-form" id="<?php echo esc_attr($mhmrentiva_uid); ?>-review-form">
		<?php Templates::output_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . (int) $mhmrentiva_view['vehicle_id'] . '" show_rating_display="0"]'); ?>
	</div>
</section>
