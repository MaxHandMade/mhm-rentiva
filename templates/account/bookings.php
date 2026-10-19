<?php
declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * My Account - Bookings Template
 *
 * @var WP_User $user
 * @var array $bookings
 * @var array $navigation
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Frontend\RenderContext;



// The list's filter form values, with proper sanitization.
// Both params are read off WordPress's `query_vars` whitelist, where
// AccountController::PUBLIC_QUERY_VARS registers them; this is a front-end
// screen, so wp() runs WP::parse_request() and fills them. No superglobal is
// touched, and the filters keep working from a bookmarked or shared URL.
// WP::parse_request() preserves arrays for registered query vars, so the
// is_array() guard keeps `?mhmrentiva_status_filter[]=x` from raising a live
// "Array to string conversion" on the cast.
$status_filter_raw = get_query_var('mhmrentiva_status_filter', null);
$search_query_raw  = get_query_var('mhmrentiva_search_booking', null);
$status_filter     = ( null === $status_filter_raw || is_array($status_filter_raw) ) ? '' : sanitize_text_field(wp_unslash( (string) $status_filter_raw));
$search_query      = ( null === $search_query_raw || is_array($search_query_raw) ) ? '' : sanitize_text_field(wp_unslash( (string) $search_query_raw));

$bookings      = $data['bookings'] ?? array();
$navigation    = $data['navigation'] ?? array();
$is_integrated = empty($navigation);

// Column toggles and pager (spec 3A Task 7a). The renderer resolves them; a
// caller that hands no flags gets today's table: every column, no pager.
$show_image         = ! $is_integrated && (bool) ( $data['show_image'] ?? true );
$show_booking_dates = (bool) ( $data['show_booking_dates'] ?? true );
$show_status        = (bool) ( $data['show_status'] ?? true );
$show_price         = (bool) ( $data['show_price'] ?? true );
$current_list_page  = (int) ( $data['current_page'] ?? 1 );
$total_list_pages   = (int) ( $data['total_pages'] ?? 1 );
$show_pager         = ! empty($data['show_pagination']) && $total_list_pages > 1;

// The status and search filters narrow the query itself
// (AccountRenderer::get_bookings_data()), so the rows and the pager agree;
// the values above only refill the form.
$filtered_bookings = $bookings;

// Separate upcoming and past bookings
$today             = current_time('Y-m-d');
$upcoming_bookings = array();
$past_bookings     = array();

// One view row per booking (AccountRenderer::booking_view_row()). An editor
// preview hands its sample rows ready-made in `booking_rows`; otherwise they are
// built here from the booking posts, with the values this template used to read.
$booking_rows = isset($data['booking_rows']) && is_array($data['booking_rows'])
	? $data['booking_rows']
	: array_map(array( \MHMRentiva\Admin\Frontend\Account\AccountRenderer::class, 'booking_view_row' ), $filtered_bookings);

foreach ($booking_rows as $booking_row) {
	if ($booking_row['dropoff_date'] >= $today) {
		$upcoming_bookings[] = $booking_row;
	} else {
		$past_bookings[] = $booking_row;
	}
}

// Dynamic currency symbol (plugin settings)
// Currency: prices below are rendered with CurrencyHelper::format_price(), which
// carries the symbol and the WooCommerce placement itself.

// Booking form page URL
$booking_form_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_booking_form');
$current_page_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();

$wrapper_class = 'mhm-rentiva-account-page';
if ($is_integrated) { // Use the already determined $is_integrated
	$wrapper_class .= ' mhm-integrated';
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">

	<!-- Account Navigation (only show if not on WooCommerce My Account page) -->
	<?php if (! empty($navigation)) : ?>
		<?php echo wp_kses_post(\MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', array( 'navigation' => $navigation ), true)); ?>
	<?php endif; ?>

	<!-- Bookings Content -->
	<div class="mhm-account-content">
		<div class="rv-bookings-page" data-testid="account-bookings-page">

			<!-- Header -->
			<div class="section-header">
				<h2><?php esc_html_e('My Reservations', 'mhm-rentiva'); ?></h2>
				<a href="<?php echo esc_url($booking_form_url); ?>" class="btn btn-primary">
					<?php esc_html_e('New Reservation', 'mhm-rentiva'); ?>
				</a>
			</div>

			<!-- Filter Section -->
			<div class="rv-filter-section">
				<h3><?php echo esc_html_x('Filter', 'noun: filter panel heading', 'mhm-rentiva'); ?></h3>

				<form method="get"<?php echo RenderContext::current()->is_preview() ? '' : ' action="' . esc_url($current_page_url) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped with esc_url(). ?> class="rv-filter-form" data-testid="bookings-filter-form">
					<input type="hidden" name="endpoint" value="bookings">

					<div class="rv-filter-row">
						<select name="mhmrentiva_status_filter" class="rv-filter-select" data-testid="bookings-status-filter">
							<option value="all" <?php selected($status_filter, 'all'); ?>><?php esc_html_e('All Status', 'mhm-rentiva'); ?></option>
							<option value="pending" <?php selected($status_filter, 'pending'); ?>><?php esc_html_e('Pending', 'mhm-rentiva'); ?></option>
							<option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php esc_html_e('Confirmed', 'mhm-rentiva'); ?></option>
							<option value="in_progress" <?php selected($status_filter, 'in_progress'); ?>><?php esc_html_e('In Progress', 'mhm-rentiva'); ?></option>
							<option value="completed" <?php selected($status_filter, 'completed'); ?>><?php esc_html_e('Completed', 'mhm-rentiva'); ?></option>
							<option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'mhm-rentiva'); ?></option>
						</select>
					</div>

					<div class="rv-filter-row">
						<input
							type="text"
							name="mhmrentiva_search_booking"
							class="rv-search-input"
							placeholder="<?php esc_attr_e('Search by Reservation ID', 'mhm-rentiva'); ?>"
							value="<?php echo esc_attr($search_query); ?>">
					</div>

					<div class="rv-filter-row">
						<?php
						// This form used to auto-submit on <select> change via an inline
						// `onchange="this.form.submit()"` handler. wp_kses() -- now wrapping
						// this template's output at the dispatcher (see
						// ShortcodeServiceProvider::handle_shortcode_execution()) -- strips
						// every `on*` attribute unconditionally, same as it must for any
						// inline event handler; the fix is a real submit control, not an
						// allowlist exception (inline JS execution is exactly what that
						// escaping closes off). Without this button the form had no other
						// way to submit at all.
						?>
						<button type="submit" class="rv-filter-submit button"<?php echo RenderContext::current()->is_preview() ? ' disabled' : ''; ?>><?php echo esc_html_x('Filter', 'verb: apply filter', 'mhm-rentiva'); ?></button>
					</div>
				</form>
			</div>

			<!-- Upcoming Reservations -->
			<?php if (! empty($upcoming_bookings)) : ?>
				<div class="rv-bookings-section">
					<div class="section-header">
						<h3><?php esc_html_e('Upcoming Reservations', 'mhm-rentiva'); ?></h3>
					</div>

					<div class="rv-table-wrapper">
						<table class="rv-bookings-table mhm-table" data-testid="bookings-table-upcoming">
							<thead>
								<tr>
									<th style="width: 50px;"><?php esc_html_e('ID', 'mhm-rentiva'); ?></th>
									<?php if ($show_image) : ?>
										<th style="width: 60px;"><?php esc_html_e('Img', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<th><?php esc_html_e('Service', 'mhm-rentiva'); ?></th>
									<?php if ($show_booking_dates) : ?>
										<th style="white-space: nowrap;"><?php esc_html_e('Date/Time', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<?php if ($show_status) : ?>
										<th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<?php if ($show_price) : ?>
										<th><?php esc_html_e('Fee', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<th style="text-align: center; width: 60px;"><?php esc_html_e('Act', 'mhm-rentiva'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ($upcoming_bookings as $booking_row) :
									$vehicle_title  = $booking_row['vehicle_title'];
									$booking_status = $booking_row['status'];
									$pickup_date    = $booking_row['pickup_date'];
									$pickup_time    = $booking_row['pickup_time'];
									$total_price    = $booking_row['total_price'];
									$view_url       = $booking_row['view_url'];

									// Vehicle image. The fallback used to hardcode
									// assets/images/no-image.png, a file this plugin has never
									// shipped -- a broken <img> for every vehicle with no
									// featured image. VehicleDataHelper owns the same
									// placeholder the vehicle grid and list already used.
									$vehicle_image = $booking_row['vehicle_image'];
									if (! $vehicle_image) {
										$vehicle_image = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_placeholder_image_url();
									}

									// Status badge
									$status_labels = array(
										'pending'     => esc_html__('Pending', 'mhm-rentiva'),
										'confirmed'   => esc_html__('Confirmed', 'mhm-rentiva'),
										'in_progress' => esc_html__('In Progress', 'mhm-rentiva'),
										'completed'   => esc_html__('Completed', 'mhm-rentiva'),
										'cancelled'   => esc_html__('Cancelled', 'mhm-rentiva'),
									);
									if (isset($status_labels[ $booking_status ])) {
										$status_label = $status_labels[ $booking_status ];
									} else {
										$status_label = ucfirst($booking_status);
									}
									$status_class = 'status-' . $booking_status;

									// Date format (site settings)
									$format          = get_option('date_format') . ' ' . get_option('time_format');
									$pickup_ts_input = trim($pickup_date . ' ' . $pickup_time);
									$pickup_ts       = strtotime($pickup_ts_input);
									$formatted_date  = $pickup_ts ? date_i18n($format, $pickup_ts) : date_i18n(get_option('date_format'), strtotime($pickup_date));
									?>
									<tr>
										<td class="rv-booking-id">#<?php echo esc_html($booking_row['display_id'] ?? $booking_row['id']); ?></td>
										<?php if ($show_image) : ?>
											<td class="rv-vehicle-thumb">
												<img src="<?php echo esc_url($vehicle_image); ?>" alt="<?php echo esc_attr($vehicle_title ?? ''); ?>">
											</td>
										<?php endif; ?>
										<td class="rv-vehicle-name"><?php echo esc_html($vehicle_title ?? esc_html__('N/A', 'mhm-rentiva')); ?></td>
										<?php if ($show_booking_dates) : ?>
											<td class="rv-booking-date"><?php echo esc_html($formatted_date); ?></td>
										<?php endif; ?>
										<?php if ($show_status) : ?>
										<td class="rv-booking-status">
											<span class="status-badge <?php echo esc_attr($status_class); ?>">
												<?php echo esc_html($status_label); ?>
											</span>
										</td>
										<?php endif; ?>
										<?php if ($show_price) : ?>
										<td class="rv-booking-price">
											<span class="rv-price-badge">
												<?php
												// Canonical formatting: uses wc_price() when WooCommerce is
												// active and falls back to the same placement rule when it is
												// not. The old fallback pinned the symbol to the right.
												echo esc_html(\MHMRentiva\Admin\Core\CurrencyHelper::format_price( (float) $total_price, \MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals()));
												?>
											</span>
										</td>
										<?php endif; ?>
										<td class="rv-booking-actions">
											<a<?php echo '' === $view_url ? '' : ' href="' . esc_url($view_url) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped with esc_url(); a sample row has no link. The empty line below is deliberate: PHP drops the newline after this closing tag, the blank line restores it (front bytes unchanged). ?>

												class="btn btn-secondary rv-btn-icon-only" title="<?php esc_attr_e('View Details', 'mhm-rentiva'); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
													<circle cx="12" cy="12" r="3"></circle>
												</svg>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<!-- Past Reservations -->
			<?php if (! empty($past_bookings)) : ?>
				<div class="rv-bookings-section">
					<div class="section-header">
						<h3><?php esc_html_e('Past Reservations', 'mhm-rentiva'); ?></h3>
					</div>

					<div class="rv-table-wrapper">
						<table class="rv-bookings-table mhm-table" data-testid="bookings-table-past">
							<thead>
								<tr>
									<th style="width: 50px;"><?php esc_html_e('ID', 'mhm-rentiva'); ?></th>
									<?php if ($show_image) : ?>
										<th style="width: 60px;"><?php esc_html_e('Img', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<th><?php esc_html_e('Service', 'mhm-rentiva'); ?></th>
									<?php if ($show_booking_dates) : ?>
										<th style="white-space: nowrap;"><?php esc_html_e('Date/Time', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<?php if ($show_status) : ?>
										<th><?php esc_html_e('Status', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<?php if ($show_price) : ?>
										<th><?php esc_html_e('Fee', 'mhm-rentiva'); ?></th>
									<?php endif; ?>
									<th style="text-align: center; width: 60px;"><?php esc_html_e('Act', 'mhm-rentiva'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ($past_bookings as $booking_row) :
									$vehicle_title  = $booking_row['vehicle_title'];
									$booking_status = $booking_row['status'];
									$pickup_date    = $booking_row['pickup_date'];
									$pickup_time    = $booking_row['pickup_time'];
									$total_price    = $booking_row['total_price'];
									$view_url       = $booking_row['view_url'];

									// Vehicle image. The fallback used to hardcode
									// assets/images/no-image.png, a file this plugin has never
									// shipped -- a broken <img> for every vehicle with no
									// featured image. VehicleDataHelper owns the same
									// placeholder the vehicle grid and list already used.
									$vehicle_image = $booking_row['vehicle_image'];
									if (! $vehicle_image) {
										$vehicle_image = \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::get_placeholder_image_url();
									}

									// Status badge
									$status_labels = array(
										'pending'     => esc_html__('Pending', 'mhm-rentiva'),
										'confirmed'   => esc_html__('Confirmed', 'mhm-rentiva'),
										'in_progress' => esc_html__('In Progress', 'mhm-rentiva'),
										'completed'   => esc_html__('Completed', 'mhm-rentiva'),
										'cancelled'   => esc_html__('Cancelled', 'mhm-rentiva'),
									);
									if (isset($status_labels[ $booking_status ])) {
										$status_label = $status_labels[ $booking_status ];
									} else {
										$status_label = ucfirst($booking_status);
									}
									$status_class = 'status-' . $booking_status;

									// Date format (site settings)
									$format          = get_option('date_format') . ' ' . get_option('time_format');
									$pickup_ts_input = trim($pickup_date . ' ' . $pickup_time);
									$pickup_ts       = strtotime($pickup_ts_input);
									$formatted_date  = $pickup_ts ? date_i18n($format, $pickup_ts) : date_i18n(get_option('date_format'), strtotime($pickup_date));
									?>
									<tr>
										<td class="rv-booking-id">#<?php echo esc_html($booking_row['display_id'] ?? $booking_row['id']); ?></td>
										<?php if ($show_image) : ?>
											<td class="rv-vehicle-thumb">
												<img src="<?php echo esc_url($vehicle_image); ?>" alt="<?php echo esc_attr($vehicle_title ?? ''); ?>">
											</td>
										<?php endif; ?>
										<td class="rv-vehicle-name"><?php echo esc_html($vehicle_title ?? esc_html__('N/A', 'mhm-rentiva')); ?></td>
										<?php if ($show_booking_dates) : ?>
											<td class="rv-booking-date"><?php echo esc_html($formatted_date); ?></td>
										<?php endif; ?>
										<?php if ($show_status) : ?>
										<td class="rv-booking-status">
											<span class="status-badge <?php echo esc_attr($status_class); ?>">
												<?php echo esc_html($status_label); ?>
											</span>
										</td>
										<?php endif; ?>
										<?php if ($show_price) : ?>
										<td class="rv-booking-price">
											<span class="rv-price-badge">
												<?php
												// Canonical formatting: uses wc_price() when WooCommerce is
												// active and falls back to the same placement rule when it is
												// not. The old fallback pinned the symbol to the right.
												echo esc_html(\MHMRentiva\Admin\Core\CurrencyHelper::format_price( (float) $total_price, \MHMRentiva\Admin\Core\CurrencyHelper::get_price_decimals()));
												?>
											</span>
										</td>
										<?php endif; ?>
										<td class="rv-booking-actions">
											<a<?php echo '' === $view_url ? '' : ' href="' . esc_url($view_url) . '"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped with esc_url(); a sample row has no link. The empty line below is deliberate: PHP drops the newline after this closing tag, the blank line restores it (front bytes unchanged). ?>

												class="btn btn-secondary rv-btn-icon-only" title="<?php esc_attr_e('View Details', 'mhm-rentiva'); ?>">
												<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
													<circle cx="12" cy="12" r="3"></circle>
												</svg>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<?php if ($show_pager) : ?>
				<?php
				// Pager over `limit` (spec 3A Task 7a): the payment history pager's
				// markup, on the public query var mhmrentiva_page.
				?>
				<div class="mhm-pagination" style="display:flex;align-items:center;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid #eee;">
					<?php if ($current_list_page > 1) : ?>
						<a href="<?php echo esc_url(\MHMRentiva\Admin\Frontend\Account\AccountRenderer::page_url(\MHMRentiva\Admin\Frontend\Account\AccountRenderer::PAGE_VAR_BOOKINGS, $current_list_page - 1)); ?>" class="btn btn-secondary btn-sm">
							&larr; <?php esc_html_e('Previous', 'mhm-rentiva'); ?>
						</a>
					<?php else : ?>
						<span class="btn btn-secondary btn-sm" style="opacity:.4;pointer-events:none;">&larr; <?php esc_html_e('Previous', 'mhm-rentiva'); ?></span>
					<?php endif; ?>

					<span style="font-size:14px;color:#666;">
						<?php
						/* translators: 1: current page, 2: total pages */
						echo esc_html(sprintf(__('Page %1$d / %2$d', 'mhm-rentiva'), $current_list_page, $total_list_pages));
						?>
					</span>

					<?php if ($current_list_page < $total_list_pages) : ?>
						<a href="<?php echo esc_url(\MHMRentiva\Admin\Frontend\Account\AccountRenderer::page_url(\MHMRentiva\Admin\Frontend\Account\AccountRenderer::PAGE_VAR_BOOKINGS, $current_list_page + 1)); ?>" class="btn btn-secondary btn-sm">
							<?php esc_html_e('Next', 'mhm-rentiva'); ?> &rarr;
						</a>
					<?php else : ?>
						<span class="btn btn-secondary btn-sm" style="opacity:.4;pointer-events:none;"><?php esc_html_e('Next', 'mhm-rentiva'); ?> &rarr;</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- No Bookings -->
			<?php if (empty($upcoming_bookings) && empty($past_bookings)) : ?>
				<div class="rv-no-bookings">
					<div class="rv-empty-state">
						<div class="rv-empty-icon">&#x1F4C5;</div>
						<h3><?php esc_html_e('No Bookings Found', 'mhm-rentiva'); ?></h3>
						<p><?php esc_html_e('You haven\'t made any vehicle bookings yet. Start exploring our fleet!', 'mhm-rentiva'); ?></p>
						<a href="<?php echo esc_url($booking_form_url); ?>" class="rv-btn-primary">
							<?php esc_html_e('Browse Vehicles', 'mhm-rentiva'); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

		</div><!-- .rv-bookings-page -->
	</div><!-- .mhm-account-content -->

</div><!-- .mhm-rentiva-account-page -->
