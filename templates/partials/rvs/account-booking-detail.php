<?php
/**
 * Booking detail (PR D, Task 4; drawing Rezervasyon-Detayi).
 *
 * Back link, title with status tag and payment line, an actions row, the
 * vehicle card, the progress list, the cancellation section and the payment
 * box. Desktop: content beside the payment box; phones: one column, in the
 * order head, vehicle, payment, progress, cancellation (rvs-account.css).
 * The cancel dialog is templates/partials/rvs/account-cancel-dialog.php.
 *
 * Contracts kept from the old template: the balance button
 * (`.rv-pay-remaining-btn`, data-booking-id, data-nonce
 * `mhmrentiva_pay_remaining_{id}`; account-pay-remaining.js) and the receipt
 * controls (`.mhm-upload-receipt` / `.remove-receipt-btn` with
 * data-booking-id; my-account.js).
 *
 * @var int                  $booking_id
 * @var string               $number       Display number (R4).
 * @var string               $back_url     "My bookings".
 * @var string               $summary_url  BookingSummaryPage::url().
 * @var array{modifier: string, label: string} $status RvsStatus::for().
 * @var string|null          $payment_line
 * @var array<string, string> $vehicle     name, image, pickup, return, location.
 * @var list<array{label: string, meta: string, done: bool, n: int}> $steps
 * @var array<string, mixed> $cancel       CancellationView::for_booking().
 * @var array<string, mixed> $breakdown    RvsBreakdown::from_booking().
 * @var bool                 $deposit_paid
 * @var list<array{kind: string, label: string, html: string}> $paid_rows
 * @var array<string, mixed>|null $pay     Balance control: null, or hybrid note / button nonce.
 * @var array<string, mixed> $receipt      can_upload, has, status, url.
 * @var string               $uid          Id prefix.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_bd_uid     = (string) ( $uid ?? wp_unique_id('rvs-acc-') );
$mhmrentiva_bd_id      = (int) ( $booking_id ?? 0 );
$mhmrentiva_bd_vehicle = (array) ( $vehicle ?? array() );
$mhmrentiva_bd_cancel  = (array) ( $cancel ?? array() );
$mhmrentiva_bd_receipt = (array) ( $receipt ?? array() );
$mhmrentiva_bd_kses    = PriceDisplay::allowed_html();
$mhmrentiva_bd_state   = (string) ( $mhmrentiva_bd_cancel['state'] ?? 'none' );
?>
<div class="rvs-account-detail" data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_bd_id); ?>">
	<header class="rvs-account-detail__head">
		<?php if ('' !== (string) ( $back_url ?? '' )) : ?>
			<a class="rvs-account-detail__back" href="<?php echo esc_url( (string) $back_url); ?>">&lsaquo; <?php esc_html_e('My bookings', 'mhm-rentiva'); ?></a>
		<?php endif; ?>
		<div class="rvs-account-detail__title-row">
			<div class="rvs-account__head">
				<span class="rvs-kicker"><?php esc_html_e('Booking details', 'mhm-rentiva'); ?></span>
				<h1 class="rvs-account__title">
					<?php
					/* translators: %s: booking number shown to the customer (the order number when there is one). */
					echo esc_html(sprintf(__('Booking #%s', 'mhm-rentiva'), (string) ( $number ?? '' )));
					?>
				</h1>
				<div class="rvs-account-detail__state">
					<?php Templates::render('partials/rvs/status-tag', array( 'status' => (array) ( $status ?? array() ) )); ?>
					<?php if (! empty($payment_line)) : ?>
						<span class="rvs-account-detail__payline"><?php echo esc_html( (string) $payment_line); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="rvs-account__actions rvs-account-detail__actions" data-rvs-detail-actions>
				<?php if ('' !== (string) ( $summary_url ?? '' )) : ?>
					<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url( (string) $summary_url); ?>"><?php esc_html_e('Booking summary', 'mhm-rentiva'); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<section class="rvs-account-detail__vehicle" aria-labelledby="<?php echo esc_attr($mhmrentiva_bd_uid . '-car'); ?>">
		<div class="rvs-account-detail__media">
			<img src="<?php echo esc_url( (string) ( $mhmrentiva_bd_vehicle['image'] ?? '' )); ?>" alt="" loading="lazy">
		</div>
		<div class="rvs-account-detail__car">
			<h2 id="<?php echo esc_attr($mhmrentiva_bd_uid . '-car'); ?>"><?php echo esc_html( (string) ( $mhmrentiva_bd_vehicle['name'] ?? '' )); ?></h2>
			<dl class="rvs-account-detail__when">
				<div>
					<dt><?php esc_html_e('Pickup', 'mhm-rentiva'); ?></dt>
					<dd>
						<strong><?php echo esc_html( (string) ( $mhmrentiva_bd_vehicle['pickup'] ?? '' )); ?></strong>
						<?php if ('' !== (string) ( $mhmrentiva_bd_vehicle['location'] ?? '' )) : ?>
							<span><?php echo esc_html( (string) $mhmrentiva_bd_vehicle['location']); ?></span>
						<?php endif; ?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e('Return', 'mhm-rentiva'); ?></dt>
					<dd>
						<strong><?php echo esc_html( (string) ( $mhmrentiva_bd_vehicle['return'] ?? '' )); ?></strong>
						<?php if ('' !== (string) ( $mhmrentiva_bd_vehicle['location'] ?? '' )) : ?>
							<span><?php echo esc_html( (string) $mhmrentiva_bd_vehicle['location']); ?></span>
						<?php endif; ?>
					</dd>
				</div>
			</dl>
		</div>
	</section>

	<aside class="rvs-account-detail__payment" aria-labelledby="<?php echo esc_attr($mhmrentiva_bd_uid . '-pay'); ?>">
		<h2 class="rvs-kicker" id="<?php echo esc_attr($mhmrentiva_bd_uid . '-pay'); ?>"><?php esc_html_e('Payment', 'mhm-rentiva'); ?></h2>
		<?php
		Templates::render(
			'partials/rvs/breakdown',
			array(
				'breakdown'    => (array) ( $breakdown ?? array() ),
				'deposit_paid' => ! empty($deposit_paid),
			)
		);
		?>
		<?php foreach ( (array) ( $paid_rows ?? array() ) as $mhmrentiva_bd_row) : ?>
			<div class="rvs-row rvs-account-detail__money rvs-account-detail__money--<?php echo esc_attr( (string) $mhmrentiva_bd_row['kind']); ?>">
				<span><?php echo esc_html( (string) $mhmrentiva_bd_row['label']); ?></span>
				<span><?php echo wp_kses( (string) $mhmrentiva_bd_row['html'], $mhmrentiva_bd_kses); ?></span>
			</div>
		<?php endforeach; ?>

		<?php if (! empty($pay)) : ?>
			<?php if (! empty($pay['hybrid'])) : ?>
				<p class="rvs-help rv-pay-remaining-note"><?php esc_html_e('Your deposit was paid outside our website, so we will contact you directly to collect the remaining balance.', 'mhm-rentiva'); ?></p>
			<?php else : ?>
				<button
					type="button"
					class="rvs-btn rvs-btn--primary rv-pay-remaining-btn"
					data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_bd_id); ?>"
					data-nonce="<?php echo esc_attr( (string) $pay['nonce']); ?>"
				><?php esc_html_e('Pay Remaining Amount', 'mhm-rentiva'); ?></button>
			<?php endif; ?>
		<?php endif; ?>

		<?php if (! empty($mhmrentiva_bd_receipt['has']) || ! empty($mhmrentiva_bd_receipt['can_upload'])) : ?>
			<div class="rvs-account-detail__receipt receipt-wrapper">
				<?php if (! empty($mhmrentiva_bd_receipt['has'])) : ?>
					<div class="rvs-row">
						<span>
							<?php
							/* translators: %s: status of the uploaded bank receipt (Submitted, Approved, Rejected). */
							echo esc_html(sprintf(__('Bank receipt: %s', 'mhm-rentiva'), (string) $mhmrentiva_bd_receipt['status']));
							?>
						</span>
						<a href="<?php echo esc_url( (string) $mhmrentiva_bd_receipt['url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'mhm-rentiva'); ?></a>
					</div>
					<?php if (! empty($mhmrentiva_bd_receipt['can_upload'])) : ?>
						<button type="button" class="rvs-btn rvs-btn--ghost remove-receipt-btn" data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_bd_id); ?>"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
					<?php endif; ?>
				<?php elseif (! empty($mhmrentiva_bd_receipt['can_upload'])) : ?>
					<label class="rvs-btn rvs-btn--secondary rvs-account-detail__upload">
						<?php esc_html_e('Upload bank receipt', 'mhm-rentiva'); ?>
						<input type="file" class="rvs-sr-only mhm-upload-receipt" data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_bd_id); ?>" accept="image/*,.pdf">
					</label>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</aside>

	<?php if (array() !== (array) ( $steps ?? array() )) : ?>
		<section class="rvs-account__section rvs-account-detail__timeline" aria-labelledby="<?php echo esc_attr($mhmrentiva_bd_uid . '-tl'); ?>">
			<h2 id="<?php echo esc_attr($mhmrentiva_bd_uid . '-tl'); ?>"><?php esc_html_e('Progress', 'mhm-rentiva'); ?></h2>
			<ol class="rvs-timeline">
				<?php foreach ( (array) $steps as $mhmrentiva_bd_step) : ?>
					<li class="<?php echo ! empty($mhmrentiva_bd_step['done']) ? 'is-done' : 'is-pending'; ?>">
						<span class="rvs-timeline__dot" aria-hidden="true"><?php echo ! empty($mhmrentiva_bd_step['done']) ? '&#10003;' : esc_html(number_format_i18n( (int) $mhmrentiva_bd_step['n'])); ?></span>
						<span>
							<strong><?php echo esc_html( (string) $mhmrentiva_bd_step['label']); ?></strong>
							<?php if (empty($mhmrentiva_bd_step['done'])) : ?>
								<span class="rvs-sr-only"><?php esc_html_e('(upcoming)', 'mhm-rentiva'); ?></span>
							<?php endif; ?>
							<?php if ('' !== (string) $mhmrentiva_bd_step['meta']) : ?>
								<br><span class="rvs-timeline__meta"><?php echo esc_html( (string) $mhmrentiva_bd_step['meta']); ?></span>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endif; ?>

	<?php if ('none' !== $mhmrentiva_bd_state) : ?>
		<section class="rvs-account-detail__cancel" aria-labelledby="<?php echo esc_attr($mhmrentiva_bd_uid . '-cancel'); ?>">
			<div>
				<h2 id="<?php echo esc_attr($mhmrentiva_bd_uid . '-cancel'); ?>"><?php esc_html_e('Cancellation', 'mhm-rentiva'); ?></h2>
				<p><?php echo wp_kses( (string) $mhmrentiva_bd_cancel['message_html'], $mhmrentiva_bd_kses); ?></p>
				<?php if (! empty($mhmrentiva_bd_cancel['note'])) : ?>
					<p class="rvs-help"><?php echo esc_html( (string) $mhmrentiva_bd_cancel['note']); ?></p>
				<?php endif; ?>
			</div>
			<?php if ('open' === $mhmrentiva_bd_state && ! empty($mhmrentiva_bd_cancel['can_cancel'])) : ?>
				<button
					type="button"
					class="rvs-btn rvs-account-detail__cancel-btn"
					data-rvs-cancel-open
					data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_bd_id); ?>"
					aria-haspopup="dialog"
					aria-controls="<?php echo esc_attr($mhmrentiva_bd_uid . '-dlg'); ?>"
				><?php esc_html_e('Cancel booking', 'mhm-rentiva'); ?></button>
			<?php endif; ?>
		</section>
		<?php if ('open' === $mhmrentiva_bd_state && ! empty($mhmrentiva_bd_cancel['can_cancel'])) : ?>
			<?php
			Templates::render(
				'partials/rvs/account-cancel-dialog',
				array(
					'booking_id'  => $mhmrentiva_bd_id,
					'number'      => (string) ( $number ?? '' ),
					'dialog_html' => (string) ( $mhmrentiva_bd_cancel['dialog_html'] ?? '' ),
					'dialog_id'   => $mhmrentiva_bd_uid . '-dlg',
				)
			);
			?>
		<?php endif; ?>
	<?php endif; ?>
</div>
