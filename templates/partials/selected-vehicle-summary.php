<?php
/**
 * The booking form's summary: vehicle, window, "Change" link and breakdown.
 *
 * Prints BookingForm::summary_view() and nothing else. Case A (a vehicle is
 * known) arrives filled; in Case B the vehicle part is an empty frame the
 * script fills from the chosen <option>, and the Change link waits, hidden.
 * Until the first quote the breakdown slot holds the daily price.
 *
 * @var array<string, mixed> $summary BookingForm::summary_view().
 * @var string               $uid     The form instance's id prefix (rvs-bf-N).
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_sum         = (array) ( $summary ?? array() );
$mhmrentiva_sum_uid     = (string) ( $uid ?? '' );
$mhmrentiva_sum_vehicle = $mhmrentiva_sum['vehicle'] ?? null;
$mhmrentiva_sum_html    = PriceDisplay::allowed_html();
$mhmrentiva_sum_change  = (string) ( $mhmrentiva_sum['change_url'] ?? '' );
?>
<div class="rvs-booking__summary" id="<?php echo esc_attr($mhmrentiva_sum_uid . '-summary'); ?>" data-rvs-summary>
	<?php if (is_array($mhmrentiva_sum_vehicle)) : ?>
		<div class="rvs-booking__vehicle" data-rvs-summary-vehicle>
			<div class="rvs-booking__media">
				<?php
				$mhmrentiva_sum_image = (array) ( $mhmrentiva_sum_vehicle['image'] ?? array() );
				if (! empty($mhmrentiva_sum_image['id'])) {
					echo wp_get_attachment_image(
						(int) $mhmrentiva_sum_image['id'],
						'medium_large',
						false,
						array(
							'class'   => 'rvs-booking__img',
							'alt'     => (string) ( $mhmrentiva_sum_image['alt'] ?? '' ),
							'loading' => 'eager',
						)
					);
				} elseif (! empty($mhmrentiva_sum_image['url'])) {
					echo '<img class="rvs-booking__img" src="' . esc_url( (string) $mhmrentiva_sum_image['url']) . '" alt="' . esc_attr( (string) ( $mhmrentiva_sum_image['alt'] ?? '' )) . '" loading="eager">';
				}
				?>
			</div>
			<h3 class="rvs-booking__vehicle-title"><?php echo esc_html( (string) ( $mhmrentiva_sum_vehicle['title'] ?? '' )); ?></h3>
			<?php if ('' !== (string) ( $mhmrentiva_sum_vehicle['specs'] ?? '' )) : ?>
				<p class="rvs-booking__specs"><?php echo esc_html( (string) $mhmrentiva_sum_vehicle['specs']); ?></p>
			<?php endif; ?>
		</div>
	<?php elseif (! empty($mhmrentiva_sum['pick'])) : ?>
		<?php // Case B: filled from the chosen <option> (data-image, data-features); hidden until then. ?>
		<div class="rvs-booking__vehicle" data-rvs-summary-vehicle hidden>
			<div class="rvs-booking__media">
				<img class="rvs-booking__img" alt="" data-rvs-summary-image hidden>
			</div>
			<h3 class="rvs-booking__vehicle-title" data-rvs-summary-title></h3>
			<p class="rvs-booking__specs" data-rvs-summary-specs hidden></p>
		</div>
	<?php endif; ?>

	<div class="rvs-booking__when">
		<?php if ('' !== (string) ( $mhmrentiva_sum['location'] ?? '' )) : ?>
			<p class="rvs-booking__where" data-rvs-summary-location><?php echo esc_html( (string) $mhmrentiva_sum['location']); ?></p>
		<?php endif; ?>
		<p class="rvs-booking__dates" data-rvs-summary-dates><?php echo esc_html( (string) ( $mhmrentiva_sum['dates'] ?? '' )); ?></p>
		<?php if ('' !== $mhmrentiva_sum_change) : ?>
			<a class="rvs-booking__change" href="<?php echo esc_url($mhmrentiva_sum_change); ?>" data-rvs-change><?php esc_html_e('Change', 'mhm-rentiva'); ?></a>
		<?php else : ?>
			<a class="rvs-booking__change" data-rvs-change hidden><?php esc_html_e('Change', 'mhm-rentiva'); ?></a>
		<?php endif; ?>
	</div>

	<div class="rvs-booking__breakdown" data-rvs-breakdown>
		<div class="rvs-booking__daily">
			<span><?php esc_html_e('Daily Price', 'mhm-rentiva'); ?></span>
			<span data-rvs-daily><?php echo '' !== (string) ( $mhmrentiva_sum['daily_html'] ?? '' ) ? wp_kses( (string) $mhmrentiva_sum['daily_html'], $mhmrentiva_sum_html) : '-'; ?></span>
		</div>
	</div>
</div>
