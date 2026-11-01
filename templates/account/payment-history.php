<?php
/**
 * Payment History page (PR D, Task 6; drawings Hesabim list section and Hesabim-Mobil cards).
 *
 * One table, a raised card per row on phones. Each row is one booking: the
 * status tag is the payment's own (completed / pending / cancelled, money
 * received is completed), the amount is what moved as recorded, and the bank
 * receipt control follows the single rule (AccountController::receipt_upload_allowed).
 * On a WooCommerce endpoint this template writes the `rvs-` shell; inside the
 * [rentiva_payment_history] shortcode (block, widget) the shortcode wrote it already.
 *
 * @var array $data AccountRenderer::get_payment_history_data() / get_payment_history_preview_data().
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

$payments     = (array) ( $data['payments'] ?? array() );
$navigation   = $data['navigation'] ?? array();
$total        = (int) ( $data['total'] ?? 0 );
$total_pages  = (int) ( $data['total_pages'] ?? 1 );
$current_page = (int) ( $data['current_page'] ?? 1 );

// Spec 3A Task 7b: column and pager settings; missing means today's markup.
$show_date           = $data['show_date'] ?? true;
$show_payment_method = $data['show_payment_method'] ?? true;
$show_booking_link   = $data['show_booking_link'] ?? false;
$show_pagination     = $data['show_pagination'] ?? true;

// Sample rows (editor preview): no link to a booking, no receipt upload.
$is_sample     = ! empty($data['is_sample']);
$is_integrated = empty($navigation);

$vehicles_page_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public filter name kept for backward compatibility.
$vehicles_page_url = (string) apply_filters('mhmrentiva_vehicles_page_url', $vehicles_page_url);

// The receipt column exists when any row on the page has something to show or do.
$show_receipt = false;
foreach ($payments as $mhmrentiva_ph_probe) {
	if (! empty($mhmrentiva_ph_probe['receipt']['attachment_id']) || ! empty($mhmrentiva_ph_probe['can_upload_receipt'])) {
		$show_receipt = true;
		break;
	}
}

// The payment's own three tags -> the status tag's colour pairs.
$mhmrentiva_ph_modifiers = array(
	'completed' => 'done',
	'pending'   => 'payment',
	'cancelled' => 'cancelled',
);

$mhmrentiva_ph_uid = wp_unique_id('rvs-pay-');

$mhmrentiva_ph_col_date    = __('Date', 'mhm-rentiva');
$mhmrentiva_ph_col_booking = __('Booking', 'mhm-rentiva');
$mhmrentiva_ph_col_type    = __('Type', 'mhm-rentiva');
$mhmrentiva_ph_col_method  = __('Payment Method', 'mhm-rentiva');
$mhmrentiva_ph_col_amount  = __('Amount', 'mhm-rentiva');
$mhmrentiva_ph_col_status  = __('Status', 'mhm-rentiva');
$mhmrentiva_ph_col_receipt = __('Bank receipt', 'mhm-rentiva');

$own_shell  = ! AbstractShortcode::in_rvs_shell();
$root_class = 'rvs-account mhm-rentiva-account-page' . ( $is_integrated ? ' mhm-integrated' : '' );
?>
<?php if ($own_shell) : ?>
<div class="rvs-shell <?php echo esc_attr($root_class); ?>" data-rvs-shortcode="rentiva_payment_history">
<?php else : ?>
<div class="<?php echo esc_attr($root_class); ?>">
<?php endif; ?>
	<?php if (! $is_integrated) : ?>
		<?php echo wp_kses_post(Templates::render('account/navigation', array( 'navigation' => $navigation ), true)); ?>
	<?php endif; ?>

	<section class="rvs-account__section rvs-account-bookings rvs-account-payments" aria-labelledby="<?php echo esc_attr($mhmrentiva_ph_uid . '-title'); ?>" data-testid="payment-history-page">
		<div class="rvs-account-bookings__head">
			<h2 id="<?php echo esc_attr($mhmrentiva_ph_uid . '-title'); ?>"><?php esc_html_e('Payment History', 'mhm-rentiva'); ?></h2>
			<span class="rvs-account-bookings__page">
				<?php
				/* translators: %d: total payments count. */
				echo esc_html(sprintf(__('%d payments total', 'mhm-rentiva'), $total));
				?>
			</span>
		</div>

		<?php if (empty($payments)) : ?>
			<div class="rvs-account-empty" data-testid="payments-empty">
				<h3><?php esc_html_e('No payment records yet', 'mhm-rentiva'); ?></h3>
				<p><?php esc_html_e('Your payment history will appear here when you make a reservation.', 'mhm-rentiva'); ?></p>
				<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url($vehicles_page_url); ?>"><?php esc_html_e('View Vehicles', 'mhm-rentiva'); ?></a>
			</div>
		<?php else : ?>
			<table class="rvs-table rvs-account-bookings__table rvs-account-payments__table" role="table" data-testid="payment-history-list">
				<thead role="rowgroup">
					<tr role="row">
						<?php if ($show_date) : ?>
							<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_ph_col_date); ?></th>
						<?php endif; ?>
						<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_ph_col_booking); ?></th>
						<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_ph_col_type); ?></th>
						<?php if ($show_payment_method) : ?>
							<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_ph_col_method); ?></th>
						<?php endif; ?>
						<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_ph_col_amount); ?></th>
						<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_ph_col_status); ?></th>
						<?php if ($show_receipt) : ?>
							<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_ph_col_receipt); ?></th>
						<?php endif; ?>
					</tr>
				</thead>
				<tbody role="rowgroup">
					<?php foreach ($payments as $payment) : ?>
						<?php
						$mhmrentiva_ph_id      = (int) $payment['booking_id'];
						$mhmrentiva_ph_number  = (string) ( $payment['number'] ?? $mhmrentiva_ph_id );
						$mhmrentiva_ph_amount  = (string) ( $payment['amount_html'] ?? '' );
						$mhmrentiva_ph_receipt = (array) ( $payment['receipt'] ?? array() );
						$mhmrentiva_ph_has     = ! empty($mhmrentiva_ph_receipt['attachment_id']);
						$mhmrentiva_ph_can     = ! empty($payment['can_upload_receipt']);
						if ('' === $mhmrentiva_ph_amount && ! empty($payment['has_amount'])) {
							$mhmrentiva_ph_amount = esc_html(CurrencyHelper::format_price( (float) ( $payment['amount'] ?? 0 ), 2));
						}
						?>
						<tr role="row" data-testid="payment-item" data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_ph_id); ?>">
							<?php if ($show_date) : ?>
								<td role="cell" class="rvs-account-payments__date" data-label="<?php echo esc_attr($mhmrentiva_ph_col_date); ?>"><?php echo esc_html( (string) $payment['date']); ?></td>
							<?php endif; ?>
							<td role="cell" class="rvs-account-payments__booking" data-label="<?php echo esc_attr($mhmrentiva_ph_col_booking); ?>">
								<?php if ($show_booking_link && $is_sample) : ?>
									<a class="payment-booking-link"><strong><?php echo esc_html( (string) $payment['booking_title']); ?></strong></a>
								<?php elseif ($show_booking_link) : ?>
									<a class="payment-booking-link" href="<?php echo esc_url(AccountController::get_booking_view_url($mhmrentiva_ph_id)); ?>"><strong><?php echo esc_html( (string) $payment['booking_title']); ?></strong></a>
								<?php else : ?>
									<strong><?php echo esc_html( (string) $payment['booking_title']); ?></strong>
								<?php endif; ?>
								<span class="rvs-account-bookings__no">#<?php echo esc_html($mhmrentiva_ph_number); ?></span>
							</td>
							<td role="cell" class="rvs-account-payments__type" data-label="<?php echo esc_attr($mhmrentiva_ph_col_type); ?>"><?php echo 'deposit' === $payment['type'] ? esc_html__('Deposit', 'mhm-rentiva') : esc_html__('Full payment', 'mhm-rentiva'); ?></td>
							<?php if ($show_payment_method) : ?>
								<td role="cell" class="rvs-account-payments__method" data-label="<?php echo esc_attr($mhmrentiva_ph_col_method); ?>"><?php echo esc_html( (string) ( $payment['method'] ?? '' )); ?></td>
							<?php endif; ?>
							<td role="cell" class="rvs-account-bookings__amount rvs-account-payments__amount" data-label="<?php echo esc_attr($mhmrentiva_ph_col_amount); ?>"><?php echo '' !== $mhmrentiva_ph_amount ? wp_kses_post($mhmrentiva_ph_amount) : '&mdash;'; ?></td>
							<td role="cell" class="rvs-account-bookings__status rvs-account-payments__status" data-label="<?php echo esc_attr($mhmrentiva_ph_col_status); ?>">
								<?php
								Templates::render(
									'partials/rvs/status-tag',
									array(
										'status' => array(
											'modifier' => $mhmrentiva_ph_modifiers[ $payment['status'] ] ?? 'pending',
											'label'    => (string) ( $payment['status_label'] ?? ucfirst( (string) $payment['status'] ) ),
										),
									)
								);
								?>
							</td>
							<?php if ($show_receipt) : ?>
								<td role="cell" class="rvs-account-payments__receipt receipt-wrapper" data-label="<?php echo esc_attr($mhmrentiva_ph_col_receipt); ?>">
									<?php if ($mhmrentiva_ph_has) : ?>
										<span>
											<?php
											/* translators: %s: status of the uploaded bank receipt (Submitted, Approved, Rejected). */
											echo esc_html(sprintf(__('Bank receipt: %s', 'mhm-rentiva'), (string) ( $mhmrentiva_ph_receipt['status_label'] ?? '' )));
											?>
										</span>
										<?php if (! $is_sample && '' !== (string) ( $mhmrentiva_ph_receipt['url'] ?? '' )) : ?>
											<a href="<?php echo esc_url( (string) $mhmrentiva_ph_receipt['url']); ?>" target="_blank" rel="noopener"><?php esc_html_e('View', 'mhm-rentiva'); ?></a>
										<?php endif; ?>
										<?php if ($mhmrentiva_ph_can) : ?>
											<button type="button" class="rvs-btn rvs-btn--ghost remove-receipt-btn" data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_ph_id); ?>"><?php esc_html_e('Remove', 'mhm-rentiva'); ?></button>
										<?php endif; ?>
									<?php elseif ($mhmrentiva_ph_can) : ?>
										<label class="rvs-btn rvs-btn--secondary rvs-account-detail__upload">
											<?php esc_html_e('Upload bank receipt', 'mhm-rentiva'); ?>
											<input type="file" class="rvs-sr-only mhm-upload-receipt" data-booking-id="<?php echo esc_attr( (string) $mhmrentiva_ph_id); ?>" accept="image/*,.pdf"<?php disabled($is_sample); ?>>
										</label>
									<?php endif; ?>
								</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php if ($show_pagination && $total_pages > 1) : ?>
			<nav class="rvs-account-bookings__pager" aria-label="<?php esc_attr_e('Pagination', 'mhm-rentiva'); ?>" data-testid="payments-pager">
				<?php if ($current_page > 1) : ?>
					<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url(AccountRenderer::page_url(AccountRenderer::PAGE_VAR_PAYMENTS, $current_page - 1)); ?>" rel="prev"><?php esc_html_e('Previous', 'mhm-rentiva'); ?></a>
				<?php endif; ?>
				<span class="rvs-account-bookings__page">
					<?php
					/* translators: 1: current page, 2: total pages */
					echo esc_html(sprintf(__('Page %1$d / %2$d', 'mhm-rentiva'), $current_page, $total_pages));
					?>
				</span>
				<?php if ($current_page < $total_pages) : ?>
					<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url(AccountRenderer::page_url(AccountRenderer::PAGE_VAR_PAYMENTS, $current_page + 1)); ?>" rel="next"><?php esc_html_e('Next', 'mhm-rentiva'); ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</section>
</div>
