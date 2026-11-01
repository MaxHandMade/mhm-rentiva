<?php
/**
 * Availability calendar (rentiva_availability_calendar), inside `.rvs-shell`.
 *
 * Prints CalendarView::build(). The script (availability-calendar.js) works
 * from the `data-rvs-cal-*` hooks; each calendar's root carries its own settings.
 *
 * @var array<string, mixed> $view CalendarView::build().
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_view = isset($view) && is_array($view) ? $view : array();
if (empty($mhmrentiva_view['vehicle'])) {
	return;
}

$mhmrentiva_cal_uid     = (string) $mhmrentiva_view['uid'];
$mhmrentiva_cal_vehicle = (array) $mhmrentiva_view['vehicle'];
$mhmrentiva_cal_classes = trim('rvs-availability rv-theme-' . sanitize_html_class( (string) $mhmrentiva_view['theme']) . ' ' . (string) $mhmrentiva_view['class']);
?>
<section class="<?php echo esc_attr($mhmrentiva_cal_classes); ?>"
	data-rvs-availability
	data-vehicle-id="<?php echo esc_attr( (string) $mhmrentiva_cal_vehicle['id']); ?>"
	data-start-month="<?php echo esc_attr( (string) $mhmrentiva_view['start_month']); ?>"
	data-months-to-show="<?php echo esc_attr( (string) $mhmrentiva_view['months_to_show']); ?>"
	data-week-start="<?php echo esc_attr( (string) $mhmrentiva_view['week_start']); ?>"
	data-show-pricing="<?php echo $mhmrentiva_view['show_pricing'] ? '1' : '0'; ?>"
	data-show-booking-button="<?php echo $mhmrentiva_view['show_booking'] ? '1' : '0'; ?>"
	aria-labelledby="<?php echo esc_attr($mhmrentiva_cal_uid); ?>-title">

	<div class="rvs-availability__vehicle">
		<?php if ('' !== (string) $mhmrentiva_cal_vehicle['image']) : ?>
			<img class="rvs-availability__image" data-rvs-cal-image src="<?php echo esc_url( (string) $mhmrentiva_cal_vehicle['image']); ?>" alt="<?php echo esc_attr( (string) $mhmrentiva_cal_vehicle['title']); ?>">
		<?php endif; ?>
		<div class="rvs-availability__vehicle-text">
			<p class="rvs-availability__name" data-rvs-cal-name><?php echo esc_html( (string) $mhmrentiva_cal_vehicle['title']); ?></p>
			<?php if ($mhmrentiva_view['show_pricing']) : ?>
				<p class="rvs-availability__price" data-rvs-cal-price><?php echo wp_kses( (string) $mhmrentiva_cal_vehicle['price_html'], PriceDisplay::allowed_html()); ?></p>
			<?php endif; ?>
		</div>
		<?php if ($mhmrentiva_view['show_selector']) : ?>
			<button type="button" class="rvs-btn rvs-btn--secondary rvs-availability__switch" data-rvs-cal-switch><?php esc_html_e('Change vehicle', 'mhm-rentiva'); ?></button>
		<?php endif; ?>
	</div>

	<div class="rvs-availability__head">
		<h2 class="rvs-availability__title" id="<?php echo esc_attr($mhmrentiva_cal_uid); ?>-title"><?php esc_html_e('Availability', 'mhm-rentiva'); ?></h2>
		<?php if ($mhmrentiva_view['show_nav']) : ?>
			<div class="rvs-availability__nav">
				<button type="button" class="rvs-btn rvs-btn--icon" data-rvs-cal-nav="-1" aria-label="<?php esc_attr_e('Previous month', 'mhm-rentiva'); ?>"<?php disabled(! $mhmrentiva_view['prev_allowed']); ?>><span aria-hidden="true">‹</span></button>
				<button type="button" class="rvs-btn rvs-btn--icon" data-rvs-cal-nav="1" aria-label="<?php esc_attr_e('Next month', 'mhm-rentiva'); ?>"><span aria-hidden="true">›</span></button>
			</div>
		<?php endif; ?>
	</div>

	<p class="rvs-availability__hint"><?php esc_html_e('Click on start and end dates to select your rental period.', 'mhm-rentiva'); ?></p>

	<div class="rvs-availability__months" data-rvs-cal-months aria-live="polite">
		<?php foreach ( (array) $mhmrentiva_view['months'] as $mhmrentiva_cal_month) : ?>
			<?php Templates::render('partials/rvs/calendar-month', array( 'calendar' => $mhmrentiva_cal_month ), false); ?>
		<?php endforeach; ?>
	</div>

	<?php if ($mhmrentiva_view['show_legend']) : ?>
		<ul class="rvs-availability__legend">
			<li><span class="rvs-availability__swatch rvs-availability__swatch--available" aria-hidden="true"></span><?php esc_html_e('Available', 'mhm-rentiva'); ?></li>
			<li><span class="rvs-availability__swatch rvs-availability__swatch--booked" aria-hidden="true"></span><?php esc_html_e('Booked', 'mhm-rentiva'); ?></li>
			<li><span class="rvs-availability__swatch rvs-availability__swatch--past" aria-hidden="true"></span><?php esc_html_e('Past', 'mhm-rentiva'); ?></li>
		</ul>
	<?php endif; ?>

	<?php Templates::render('partials/rvs/calendar-panel', array( 'view' => $mhmrentiva_view ), false); ?>

	<?php if ($mhmrentiva_view['show_selector']) : ?>
		<div class="rvs-dialog rvs-availability__dialog" data-rvs-cal-dialog hidden>
			<div class="rvs-dialog__panel">
				<div class="rvs-dialog__head">
					<h2 class="rvs-dialog__title" id="<?php echo esc_attr($mhmrentiva_cal_uid); ?>-dialog-title"><?php esc_html_e('Select vehicle', 'mhm-rentiva'); ?></h2>
					<button type="button" class="rvs-btn rvs-btn--icon rvs-dialog__close" data-rvs-dialog-close aria-label="<?php esc_attr_e('Close', 'mhm-rentiva'); ?>"><span aria-hidden="true">×</span></button>
				</div>
				<ul class="rvs-dialog__body rvs-availability__options">
					<?php foreach ( (array) $mhmrentiva_view['vehicles'] as $mhmrentiva_cal_option) : ?>
						<li>
							<button type="button" class="rvs-availability__option" data-rvs-cal-option="<?php echo esc_attr( (string) $mhmrentiva_cal_option['id']); ?>" aria-pressed="<?php echo (int) $mhmrentiva_cal_option['id'] === (int) $mhmrentiva_cal_vehicle['id'] ? 'true' : 'false'; ?>">
								<span class="rvs-availability__option-name"><?php echo esc_html( (string) $mhmrentiva_cal_option['title']); ?></span>
								<?php if ($mhmrentiva_view['show_pricing']) : ?>
									<span class="rvs-availability__option-price"><?php echo wp_kses( (string) $mhmrentiva_cal_option['price_html'], PriceDisplay::allowed_html()); ?></span>
								<?php endif; ?>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	<?php endif; ?>
</section>
