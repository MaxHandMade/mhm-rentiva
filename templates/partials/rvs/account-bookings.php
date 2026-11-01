<?php
/**
 * "My bookings" list (PR D, Task 2; drawings Hesabim + Hesabim-Mobil).
 *
 * Title, the section control (links, so it works without JS; R6), the table
 * (a raised card per row on phones), the section's empty sentence or the
 * general empty state, and the pager. The search box and status select of the
 * old list are not drawn; their query vars still narrow the list (R6).
 *
 * @var list<array{id:int, number:string, vehicle:string, image:string, dates:string, amount_html:string, status:array{modifier:string,label:string}, url:string}> $rows AccountBookingsView::rows().
 * @var array{upcoming:int, past:int, cancelled:int}      $counts       Section sizes (AccountBookingsQuery::counts()).
 * @var bool                                              $show_segments Section control drawn (off when the list is fixed to a status).
 * @var string                                            $segment      The section shown: 'upcoming'|'past'|'cancelled', '' for none.
 * @var bool                                              $has_bookings The customer has any booking at all.
 * @var bool                                              $show_image   Thumbnail in the car cell.
 * @var bool                                              $show_dates   Dates column.
 * @var bool                                              $show_status  Status column.
 * @var bool                                              $show_price   Amount column.
 * @var bool                                              $show_pager   Pager drawn.
 * @var int                                               $current_page Page shown.
 * @var int                                               $total_pages  Pages in the section.
 * @var string                                            $vehicles_url "Browse cars" target.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Frontend\Account\AccountBookingsQuery;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_bl_rows    = (array) ( $rows ?? array() );
$mhmrentiva_bl_counts  = (array) ( $counts ?? array() );
$mhmrentiva_bl_segment = (string) ( $segment ?? '' );
$mhmrentiva_bl_uid     = wp_unique_id('rvs-acc-');

$mhmrentiva_bl_labels = array(
	/* translators: %s: number of upcoming bookings */
	AccountBookingsQuery::UPCOMING  => __('Upcoming (%s)', 'mhm-rentiva'),
	/* translators: %s: number of past bookings */
	AccountBookingsQuery::PAST      => __('Past (%s)', 'mhm-rentiva'),
	/* translators: %s: number of cancelled bookings */
	AccountBookingsQuery::CANCELLED => __('Cancelled (%s)', 'mhm-rentiva'),
);
$mhmrentiva_bl_none   = array(
	AccountBookingsQuery::UPCOMING  => __('No upcoming bookings.', 'mhm-rentiva'),
	AccountBookingsQuery::PAST      => __('No past bookings.', 'mhm-rentiva'),
	AccountBookingsQuery::CANCELLED => __('No cancelled bookings.', 'mhm-rentiva'),
);

$mhmrentiva_bl_col_no     = _x('No', 'booking number column', 'mhm-rentiva');
$mhmrentiva_bl_col_car    = __('Car', 'mhm-rentiva');
$mhmrentiva_bl_col_dates  = __('Dates', 'mhm-rentiva');
$mhmrentiva_bl_col_amount = __('Amount', 'mhm-rentiva');
$mhmrentiva_bl_col_status = __('Status', 'mhm-rentiva');
$mhmrentiva_bl_col_action = __('Action', 'mhm-rentiva');
?>
<section class="rvs-account__section rvs-account-bookings" aria-labelledby="<?php echo esc_attr($mhmrentiva_bl_uid . '-title'); ?>" data-testid="account-bookings-page">
	<div class="rvs-account-bookings__head">
		<h2 id="<?php echo esc_attr($mhmrentiva_bl_uid . '-title'); ?>"><?php esc_html_e('My bookings', 'mhm-rentiva'); ?></h2>
		<?php if (! empty($show_segments) && ! empty($has_bookings)) : ?>
			<nav class="rvs-seg" aria-label="<?php esc_attr_e('Booking sections', 'mhm-rentiva'); ?>" data-testid="bookings-sections">
				<?php foreach ($mhmrentiva_bl_labels as $mhmrentiva_bl_key => $mhmrentiva_bl_label) : ?>
					<a href="<?php echo esc_url(AccountRenderer::segment_url($mhmrentiva_bl_key)); ?>" data-rvs-segment="<?php echo esc_attr($mhmrentiva_bl_key); ?>"<?php echo $mhmrentiva_bl_key === $mhmrentiva_bl_segment ? ' aria-current="page"' : ''; ?>><?php echo esc_html(sprintf($mhmrentiva_bl_label, number_format_i18n( (int) ( $mhmrentiva_bl_counts[ $mhmrentiva_bl_key ] ?? 0 )))); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>

	<?php if (empty($has_bookings)) : ?>
		<div class="rvs-account-empty" data-testid="bookings-empty">
			<h3><?php esc_html_e('No bookings yet', 'mhm-rentiva'); ?></h3>
			<p><?php esc_html_e('Your first rental will appear here, with its summary and cancellation.', 'mhm-rentiva'); ?></p>
			<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url( (string) ( $vehicles_url ?? '' )); ?>"><?php esc_html_e('Browse cars', 'mhm-rentiva'); ?></a>
		</div>
	<?php elseif (array() === $mhmrentiva_bl_rows) : ?>
		<p class="rvs-account-bookings__none" data-testid="bookings-section-empty"><?php echo esc_html($mhmrentiva_bl_none[ $mhmrentiva_bl_segment ] ?? __('No bookings found.', 'mhm-rentiva')); ?></p>
	<?php else : ?>
		<table class="rvs-table rvs-account-bookings__table" role="table" data-testid="bookings-table">
			<thead role="rowgroup">
				<tr role="row">
					<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_bl_col_no); ?></th>
					<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_bl_col_car); ?></th>
					<?php if (! empty($show_dates)) : ?>
						<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_bl_col_dates); ?></th>
					<?php endif; ?>
					<?php if (! empty($show_price)) : ?>
						<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_bl_col_amount); ?></th>
					<?php endif; ?>
					<?php if (! empty($show_status)) : ?>
						<th scope="col" role="columnheader"><?php echo esc_html($mhmrentiva_bl_col_status); ?></th>
					<?php endif; ?>
					<th scope="col" role="columnheader"><span class="rvs-sr-only"><?php echo esc_html($mhmrentiva_bl_col_action); ?></span></th>
				</tr>
			</thead>
			<tbody role="rowgroup">
				<?php foreach ($mhmrentiva_bl_rows as $mhmrentiva_bl_row) : ?>
					<tr role="row">
						<td role="cell" class="rvs-account-bookings__no" data-label="<?php echo esc_attr($mhmrentiva_bl_col_no); ?>">#<?php echo esc_html($mhmrentiva_bl_row['number']); ?></td>
						<td role="cell" class="rvs-account-bookings__car" data-label="<?php echo esc_attr($mhmrentiva_bl_col_car); ?>"><span class="rvs-account-bookings__vehicle">
							<?php if (! empty($show_image)) : ?>
								<img class="rvs-account-bookings__thumb" src="<?php echo esc_url('' !== $mhmrentiva_bl_row['image'] ? $mhmrentiva_bl_row['image'] : \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_placeholder_image_url()); ?>" alt="" width="48" height="32" loading="lazy">
							<?php endif; ?>
							<strong><?php echo esc_html($mhmrentiva_bl_row['vehicle']); ?></strong></span>
						</td>
						<?php if (! empty($show_dates)) : ?>
							<td role="cell" class="rvs-account-bookings__dates" data-label="<?php echo esc_attr($mhmrentiva_bl_col_dates); ?>"><?php echo esc_html($mhmrentiva_bl_row['dates']); ?></td>
						<?php endif; ?>
						<?php if (! empty($show_price)) : ?>
							<td role="cell" class="rvs-account-bookings__amount" data-label="<?php echo esc_attr($mhmrentiva_bl_col_amount); ?>"><?php echo wp_kses($mhmrentiva_bl_row['amount_html'], \MHMRentiva\Admin\Booking\Pricing\PriceDisplay::allowed_html()); ?></td>
						<?php endif; ?>
						<?php if (! empty($show_status)) : ?>
							<td role="cell" class="rvs-account-bookings__status" data-label="<?php echo esc_attr($mhmrentiva_bl_col_status); ?>"><?php Templates::render('partials/rvs/status-tag', array( 'status' => $mhmrentiva_bl_row['status'] )); ?></td>
						<?php endif; ?>
						<td role="cell" class="rvs-account-bookings__action" data-label="<?php echo esc_attr($mhmrentiva_bl_col_action); ?>">
							<?php if ('' !== $mhmrentiva_bl_row['url']) : ?>
								<a class="rvs-account-bookings__view" href="<?php echo esc_url($mhmrentiva_bl_row['url']); ?>"><span class="rvs-account-bookings__view-text"><?php esc_html_e('View', 'mhm-rentiva'); ?></span></a>
							<?php else : ?>
								<span class="rvs-account-bookings__view" aria-disabled="true"><?php esc_html_e('View', 'mhm-rentiva'); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if (! empty($show_pager)) : ?>
		<?php
		$mhmrentiva_bl_page  = (int) ( $current_page ?? 1 );
		$mhmrentiva_bl_pages = (int) ( $total_pages ?? 1 );
		?>
		<nav class="rvs-account-bookings__pager" aria-label="<?php esc_attr_e('Pagination', 'mhm-rentiva'); ?>" data-testid="bookings-pager">
			<?php if ($mhmrentiva_bl_page > 1) : ?>
				<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url(AccountRenderer::page_url(AccountRenderer::PAGE_VAR_BOOKINGS, $mhmrentiva_bl_page - 1)); ?>" rel="prev"><?php esc_html_e('Previous', 'mhm-rentiva'); ?></a>
			<?php endif; ?>
			<span class="rvs-account-bookings__page">
				<?php
				/* translators: 1: current page, 2: total pages */
				echo esc_html(sprintf(__('Page %1$s / %2$s', 'mhm-rentiva'), number_format_i18n($mhmrentiva_bl_page), number_format_i18n($mhmrentiva_bl_pages)));
				?>
			</span>
			<?php if ($mhmrentiva_bl_page < $mhmrentiva_bl_pages) : ?>
				<a class="rvs-btn rvs-btn--secondary" href="<?php echo esc_url(AccountRenderer::page_url(AccountRenderer::PAGE_VAR_BOOKINGS, $mhmrentiva_bl_page + 1)); ?>" rel="next"><?php esc_html_e('Next', 'mhm-rentiva'); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</section>
