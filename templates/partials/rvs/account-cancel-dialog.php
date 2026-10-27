<?php
/**
 * Cancel-booking dialog (PR D, Task 4; drawing Rezervasyon-Detayi, 460px).
 *
 * The shared rvs dialog (rvs-overlay.css + rvs-dialog.js), opened by
 * booking-cancellation.js with focus on "Keep booking". JS contract:
 * [data-rvs-cancel-dialog], [data-rvs-cancel-reason], [data-rvs-cancel-other]
 * (the "Tell us more" field, hidden until "Other" is chosen),
 * [data-rvs-cancel-confirm], [data-rvs-cancel-keep], [data-rvs-cancel-message].
 * The request keeps its contract: action mhmrentiva_cancel_booking, nonce
 * mhmrentiva_cancel_booking_nonce, booking_id, reason.
 *
 * @var int    $booking_id
 * @var string $number      Display number (R4).
 * @var string $dialog_html Refund sentence, escaped (CancellationView), may be ''.
 * @var string $dialog_id
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_cd_id   = (string) ( $dialog_id ?? wp_unique_id('rvs-acc-') );
$mhmrentiva_cd_body = (string) ( $dialog_html ?? '' );
?>
<div
	class="rvs-dialog rvs-account-cancel"
	id="<?php echo esc_attr($mhmrentiva_cd_id); ?>"
	data-rvs-cancel-dialog
	data-booking-id="<?php echo esc_attr( (string) (int) ( $booking_id ?? 0 )); ?>"
	<?php if ('' !== $mhmrentiva_cd_body) : ?>
		aria-describedby="<?php echo esc_attr($mhmrentiva_cd_id . '-desc'); ?>"
	<?php endif; ?>
	hidden
>
	<div class="rvs-dialog__panel rvs-account-cancel__panel">
		<div class="rvs-dialog__body rvs-account-cancel__body">
			<h2 class="rvs-dialog__title" id="<?php echo esc_attr($mhmrentiva_cd_id . '-title'); ?>">
				<?php
				/* translators: %s: booking number shown to the customer. */
				echo esc_html(sprintf(__('Cancel booking #%s?', 'mhm-rentiva'), (string) ( $number ?? '' )));
				?>
			</h2>
			<?php if ('' !== $mhmrentiva_cd_body) : ?>
				<p id="<?php echo esc_attr($mhmrentiva_cd_id . '-desc'); ?>"><?php echo wp_kses($mhmrentiva_cd_body, PriceDisplay::allowed_html()); ?></p>
			<?php endif; ?>
			<div class="rvs-field">
				<label for="<?php echo esc_attr($mhmrentiva_cd_id . '-reason'); ?>"><?php esc_html_e('Reason (optional)', 'mhm-rentiva'); ?></label>
				<select class="rvs-input" id="<?php echo esc_attr($mhmrentiva_cd_id . '-reason'); ?>" data-rvs-cancel-reason>
					<option value="">&mdash;</option>
					<option value="plans"><?php esc_html_e('My plans changed', 'mhm-rentiva'); ?></option>
					<option value="other_car"><?php esc_html_e('I found another car', 'mhm-rentiva'); ?></option>
					<option value="other"><?php esc_html_e('Other', 'mhm-rentiva'); ?></option>
				</select>
			</div>
			<div class="rvs-field" data-rvs-cancel-other hidden>
				<label for="<?php echo esc_attr($mhmrentiva_cd_id . '-other'); ?>"><?php esc_html_e('Tell us more', 'mhm-rentiva'); ?></label>
				<textarea class="rvs-input" id="<?php echo esc_attr($mhmrentiva_cd_id . '-other'); ?>" rows="3" maxlength="500"></textarea>
			</div>
			<p class="rvs-error" data-rvs-cancel-message role="alert" hidden></p>
			<div class="rvs-account-cancel__actions">
				<button type="button" class="rvs-btn rvs-btn--secondary" data-rvs-cancel-keep><?php esc_html_e('Keep booking', 'mhm-rentiva'); ?></button>
				<button type="button" class="rvs-btn rvs-account-cancel__confirm" data-rvs-cancel-confirm><?php esc_html_e('Cancel booking', 'mhm-rentiva'); ?></button>
			</div>
		</div>
	</div>
</div>
