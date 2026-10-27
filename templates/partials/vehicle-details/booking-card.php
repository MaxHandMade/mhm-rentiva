<?php
/**
 * Vehicle page booking card: period total, pickup / return, the car's own
 * pickup point ("Pickup and return: X", PR S -- no select of every branch),
 * price breakdown, "Continue" (to the booking form with the window), deposit
 * and cancellation lines. On a phone the card sits in the page and a fixed bar
 * repeats the total with the breakdown in a dialog (canvas `Arac-Detay-Mobil`).
 *
 * Date fields: the visible input shows the site's date format from the first
 * paint, the hidden one after it posts Y-m-d (datepicker-init.js adopts the pair).
 *
 * The page script (rvs-vehicle.js) replaces the [data-rvs-vd-*] parts with the quote endpoint's
 * answer when the window changes.
 *
 * @var array<string, mixed> $view       VehicleDetailsView::build().
 * @var array<string, mixed> $atts       Shortcode attributes.
 * @var bool                 $show_price show_pricing && show_price.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\Assets\DatepickerAssets;
use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_view    = (array) ( $view ?? array() );
$mhmrentiva_booking = (array) $mhmrentiva_view['booking'];
$mhmrentiva_window  = (array) $mhmrentiva_booking['window'];
$mhmrentiva_uid     = (string) $mhmrentiva_view['uid'];
$mhmrentiva_price   = ! empty($show_price);
$mhmrentiva_label   = trim( (string) ( ( $atts ?? array() )['booking_btn_text'] ?? '' ));
$mhmrentiva_label   = '' !== $mhmrentiva_label ? $mhmrentiva_label : __('Continue', 'mhm-rentiva');
$mhmrentiva_ok      = ! empty($mhmrentiva_booking['available']);
$mhmrentiva_priced  = ! empty($mhmrentiva_booking['priced']); // Wrong-order dates: the message alone, no total beside it.
$mhmrentiva_html    = PriceDisplay::allowed_html();
$mhmrentiva_days    = (int) $mhmrentiva_booking['days'];
/* translators: %s: number of rental days. */
$mhmrentiva_days_label = sprintf(_n('%s day total', '%s days total', $mhmrentiva_days, 'mhm-rentiva'), number_format_i18n($mhmrentiva_days));
$mhmrentiva_time       = (string) $mhmrentiva_window['pickup_time'];
$mhmrentiva_times      = static function (string $selected): void {
	for ($i = 0; $i < 24; $i++) {
		foreach (array( '00', '30' ) as $minute) {
			$time = sprintf('%02d:%s', $i, $minute);
			printf('<option value="%1$s"%2$s>%1$s</option>', esc_attr($time), selected($selected, $time, false));
		}
	}
};
$mhmrentiva_continue   = static function (string $extra_class) use ($mhmrentiva_ok, $mhmrentiva_booking, $mhmrentiva_label): void {
	?>
	<a class="rvs-btn rvs-btn--primary <?php echo esc_attr($extra_class); ?>" data-rvs-vd-continue
		href="<?php echo esc_url( (string) $mhmrentiva_booking['continue_url']); ?>"<?php echo $mhmrentiva_ok ? '' : ' aria-disabled="true" tabindex="-1"'; ?>><?php echo esc_html($mhmrentiva_label); ?></a>
	<?php
};
?>
<aside class="rvs-vd__booking" aria-label="<?php esc_attr_e('Booking', 'mhm-rentiva'); ?>">
	<div class="rvs-vd__card">
		<?php if ($mhmrentiva_price) : ?>
			<div class="rvs-vd__total" data-rvs-vd-priced<?php echo $mhmrentiva_priced ? '' : ' hidden'; ?>>
				<span class="rvs-vd__total-amount" data-rvs-vd-total><?php echo wp_kses( (string) $mhmrentiva_booking['breakdown']['total']['html'], $mhmrentiva_html); ?></span>
				<span class="rvs-vd__total-days" data-rvs-vd-days><?php echo esc_html($mhmrentiva_days_label); ?></span>
			</div>
		<?php endif; ?>

		<form class="rvs-vd__window" data-rvs-vd-window>
			<div class="rvs-field">
				<label for="<?php echo esc_attr($mhmrentiva_uid); ?>-pickup"><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></label>
				<div class="rvs-vd__when">
					<input id="<?php echo esc_attr($mhmrentiva_uid); ?>-pickup" type="text" class="rvs-input js-datepicker" value="<?php echo esc_attr(DatepickerAssets::display_date( (string) $mhmrentiva_window['pickup_date'])); ?>" autocomplete="off" data-rvs-pickup-date>
					<input type="hidden" name="pickup_date" value="<?php echo esc_attr( (string) $mhmrentiva_window['pickup_date']); ?>" data-rvs-date-value>
					<label class="rvs-sr-only" for="<?php echo esc_attr($mhmrentiva_uid); ?>-pickup-time"><?php esc_html_e('Pickup time', 'mhm-rentiva'); ?></label>
					<select id="<?php echo esc_attr($mhmrentiva_uid); ?>-pickup-time" class="rvs-input rvs-vd__time" name="pickup_time" data-rvs-pickup-time>
						<?php $mhmrentiva_times($mhmrentiva_time); ?>
					</select>
				</div>
			</div>
			<div class="rvs-field">
				<label for="<?php echo esc_attr($mhmrentiva_uid); ?>-return"><?php echo esc_html_x('Return', 'search strip: the return date field', 'mhm-rentiva'); ?></label>
				<div class="rvs-vd__when">
					<input id="<?php echo esc_attr($mhmrentiva_uid); ?>-return" type="text" class="rvs-input js-datepicker" value="<?php echo esc_attr(DatepickerAssets::display_date( (string) $mhmrentiva_window['return_date'])); ?>" autocomplete="off" data-rvs-return-date>
					<input type="hidden" name="return_date" value="<?php echo esc_attr( (string) $mhmrentiva_window['return_date']); ?>" data-rvs-date-value>
					<span class="rvs-input rvs-vd__time rvs-vd__time--locked" data-rvs-return-time-display title="<?php esc_attr_e('Return time follows the pickup time', 'mhm-rentiva'); ?>"><?php echo esc_html($mhmrentiva_time); ?></span>
				</div>
			</div>
		</form>

		<?php if ('' !== (string) ( $mhmrentiva_booking['pickup_return'] ?? '' )) : ?>
			<p class="rvs-vd__where" data-rvs-vd-where><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span>
			<?php
			/* translators: %s: the pickup point of this car. */
			printf(esc_html__('Pickup and return: %s', 'mhm-rentiva'), '<strong>' . esc_html( (string) $mhmrentiva_booking['pickup_return']) . '</strong>');
			?>
			</span></p>
		<?php endif; ?>

		<?php if ($mhmrentiva_price) : ?>
			<div data-rvs-vd-breakdown>
				<?php
				Templates::render(
					'partials/rvs/breakdown',
					array(
						'breakdown'   => $mhmrentiva_booking['breakdown'],
						'collapsible' => true,
					),
					false
				);
				?>
			</div>
		<?php endif; ?>

		<p class="rvs-vd__message" data-rvs-vd-message role="status"<?php echo $mhmrentiva_ok ? ' hidden' : ''; ?>><?php echo esc_html( (string) $mhmrentiva_booking['message']); ?></p>

		<?php $mhmrentiva_continue('rvs-btn--lg rvs-vd__continue'); ?>

		<ul class="rvs-vd__assurances">
			<li><?php echo esc_html( (string) $mhmrentiva_booking['deposit_text']); ?></li>
			<?php if ('' !== (string) $mhmrentiva_booking['cancel_text']) : ?>
				<li><?php echo esc_html( (string) $mhmrentiva_booking['cancel_text']); ?></li>
			<?php endif; ?>
		</ul>
	</div>

	<div class="rvs-vd__bar" role="region" aria-label="<?php esc_attr_e('Booking summary', 'mhm-rentiva'); ?>">
		<?php if ($mhmrentiva_price) : ?>
			<button type="button" class="rvs-vd__bar-total" data-rvs-vd-breakdown-open data-rvs-vd-priced<?php echo $mhmrentiva_priced ? '' : ' hidden'; ?>>
				<strong data-rvs-vd-total><?php echo wp_kses( (string) $mhmrentiva_booking['breakdown']['total']['html'], $mhmrentiva_html); ?></strong>
				<span>
					<span data-rvs-vd-days><?php echo esc_html($mhmrentiva_days_label); ?></span> · <?php esc_html_e('breakdown', 'mhm-rentiva'); ?>
				</span>
			</button>
		<?php endif; ?>
		<?php $mhmrentiva_continue('rvs-vd__bar-continue'); ?>
	</div>

	<?php if ($mhmrentiva_price) : ?>
		<div class="rvs-dialog" data-rvs-vd-breakdown-dialog hidden>
			<div class="rvs-dialog__panel rvs-vd__sheet">
				<div class="rvs-dialog__head">
					<h2 class="rvs-dialog__title" id="<?php echo esc_attr($mhmrentiva_uid); ?>-breakdown-title"><?php esc_html_e('Price breakdown', 'mhm-rentiva'); ?></h2>
					<button type="button" class="rvs-btn rvs-btn--icon rvs-dialog__close" data-rvs-dialog-close aria-label="<?php esc_attr_e('Close', 'mhm-rentiva'); ?>"><span aria-hidden="true">×</span></button>
				</div>
				<div class="rvs-dialog__body" data-rvs-vd-breakdown>
					<?php
					Templates::render(
						'partials/rvs/breakdown',
						array(
							'breakdown'   => $mhmrentiva_booking['breakdown'],
							'collapsible' => true,
						),
						false
					);
					?>
				</div>
			</div>
		</div>
	<?php endif; ?>
</aside>
