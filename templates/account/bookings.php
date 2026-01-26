<?php

/**
 * My Account - Bookings Template
 *
 * @var WP_User $user
 * @var array $bookings
 * @var array $navigation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



// Filtering with proper sanitization
$status_filter = isset( $_GET['status_filter'] ) ? mhm_rentiva_sanitize_text_field_safe( wp_unslash( $_GET['status_filter'] ) ) : '';
$search_query  = isset( $_GET['search_booking'] ) ? mhm_rentiva_sanitize_text_field_safe( wp_unslash( $_GET['search_booking'] ) ) : '';

// Filter bookings
$filtered_bookings = $bookings;

if ( $status_filter && $status_filter !== 'all' ) {
	$filtered_bookings = array_filter(
		$filtered_bookings,
		function ( $booking ) use ( $status_filter ) {
			$status = get_post_meta( $booking->ID, '_mhm_status', true );
			return $status === $status_filter;
		}
	);
}

if ( $search_query ) {
	$filtered_bookings = array_filter(
		$filtered_bookings,
		function ( $booking ) use ( $search_query ) {
			return strpos( (string) $booking->ID, $search_query ) !== false;
		}
	);
}

// Separate upcoming and past bookings
$today             = current_time( 'Y-m-d' );
$upcoming_bookings = array();
$past_bookings     = array();

foreach ( $filtered_bookings as $booking ) {
	$dropoff_date = get_post_meta( $booking->ID, '_mhm_dropoff_date', true );
	if ( $dropoff_date >= $today ) {
		$upcoming_bookings[] = $booking;
	} else {
		$past_bookings[] = $booking;
	}
}

// Currency symbol (dynamic)
$currency_code   = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhm_rentiva_currency', 'USD' );
$currency_symbol = \MHMRentiva\Admin\Reports\Reports::get_currency_symbol();

// Booking form page URL
$booking_form_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url( 'rentiva_booking_form' );
$current_page_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url();

$wrapper_class = 'mhm-rentiva-account-page';
$is_integrated = empty( $navigation );
if ( $is_integrated ) {
	$wrapper_class .= ' mhm-integrated';
}
?>

<div class="<?php echo esc_attr( $wrapper_class ); ?>">

	<!-- Account Navigation (only show if not on WooCommerce My Account page) -->
	<?php if ( ! empty( $navigation ) ) : ?>
		<?php echo wp_kses_post( \MHMRentiva\Admin\Core\Utilities\Templates::render( 'account/navigation', array( 'navigation' => $navigation ), true ) ); ?>
	<?php endif; ?>

	<!-- Bookings Content -->
	<div class="mhm-account-content">
		<div class="rv-bookings-page">

			<!-- Header -->
			<div class="section-header">
				<h2><?php esc_html_e( 'My Reservations', 'mhm-rentiva' ); ?></h2>
				<a href="<?php echo esc_url( $booking_form_url ); ?>" class="btn btn-primary">
					<?php esc_html_e( 'New Reservation', 'mhm-rentiva' ); ?>
				</a>
			</div>

			<!-- Filter Section -->
			<div class="rv-filter-section">
				<h3><?php esc_html_e( 'Filter', 'mhm-rentiva' ); ?></h3>

				<form method="get" action="<?php echo esc_url( $current_page_url ); ?>" class="rv-filter-form">
					<?php wp_nonce_field( 'mhm_rentiva_filter_bookings', 'filter_nonce' ); ?>
					<input type="hidden" name="endpoint" value="bookings">

					<div class="rv-filter-row">
						<select name="status_filter" class="rv-filter-select" onchange="this.form.submit()">
							<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( 'All Status', 'mhm-rentiva' ); ?></option>
							<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'mhm-rentiva' ); ?></option>
							<option value="confirmed" <?php selected( $status_filter, 'confirmed' ); ?>><?php esc_html_e( 'Confirmed', 'mhm-rentiva' ); ?></option>
							<option value="in_progress" <?php selected( $status_filter, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'mhm-rentiva' ); ?></option>
							<option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php esc_html_e( 'Completed', 'mhm-rentiva' ); ?></option>
							<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'mhm-rentiva' ); ?></option>
						</select>
					</div>

					<div class="rv-filter-row">
						<input
							type="text"
							name="search_booking"
							class="rv-search-input"
							placeholder="<?php esc_attr_e( 'Search by Reservation ID', 'mhm-rentiva' ); ?>"
							value="<?php echo esc_attr( $search_query ); ?>">
					</div>
				</form>
			</div>

			<!-- Upcoming Reservations -->
			<?php if ( ! empty( $upcoming_bookings ) ) : ?>
				<div class="rv-bookings-section">
					<div class="section-header">
						<h3><?php esc_html_e( 'Upcoming Reservations', 'mhm-rentiva' ); ?></h3>
					</div>

					<div class="rv-table-wrapper">
						<table class="rv-bookings-table mhm-table">
							<thead>
								<tr>
									<th style="width: 50px;"><?php esc_html_e( 'ID', 'mhm-rentiva' ); ?></th>
									<?php if ( ! $is_integrated ) : ?>
										<th style="width: 60px;"><?php esc_html_e( 'Img', 'mhm-rentiva' ); ?></th>
									<?php endif; ?>
									<th><?php esc_html_e( 'Service', 'mhm-rentiva' ); ?></th>
									<th style="white-space: nowrap;"><?php esc_html_e( 'Date/Time', 'mhm-rentiva' ); ?></th>
									<th><?php esc_html_e( 'Status', 'mhm-rentiva' ); ?></th>
									<th><?php esc_html_e( 'Fee', 'mhm-rentiva' ); ?></th>
									<th style="text-align: center; width: 60px;"><?php esc_html_e( 'Act', 'mhm-rentiva' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $upcoming_bookings as $booking ) :
									$vehicle_id  = get_post_meta( $booking->ID, '_mhm_vehicle_id', true );
									$vehicle     = get_post( $vehicle_id );
									$status      = get_post_meta( $booking->ID, '_mhm_status', true );
									$pickup_date = get_post_meta( $booking->ID, '_mhm_pickup_date', true );
									// Get pickup time with fallbacks
									$pickup_time = get_post_meta( $booking->ID, '_mhm_start_time', true );
									if ( ! $pickup_time ) {
										$pickup_time = get_post_meta( $booking->ID, '_mhm_pickup_time', true );
									}
									if ( ! $pickup_time ) {
										$pickup_time = get_post_meta( $booking->ID, '_booking_pickup_time', true );
									}
									$total_price = get_post_meta( $booking->ID, '_mhm_total_price', true );

									// Vehicle image
									$vehicle_image = get_the_post_thumbnail_url( $vehicle_id, 'thumbnail' );
									if ( ! $vehicle_image ) {
										$vehicle_image = MHM_RENTIVA_PLUGIN_URL . 'assets/images/no-image.png';
									}

									// Status badge
									$status_labels = array(
										'pending'     => esc_html__( 'Pending', 'mhm-rentiva' ),
										'confirmed'   => esc_html__( 'Confirmed', 'mhm-rentiva' ),
										'in_progress' => esc_html__( 'In Progress', 'mhm-rentiva' ),
										'completed'   => esc_html__( 'Completed', 'mhm-rentiva' ),
										'cancelled'   => esc_html__( 'Cancelled', 'mhm-rentiva' ),
									);
									$status_label  = $status_labels[ $status ] ?? ucfirst( $status );
									$status_class  = 'status-' . $status;

									// Date format (site settings)
									$format         = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
									$pickup_ts      = strtotime( trim( $pickup_date . ' ' . ( $pickup_time ?: '' ) ) );
									$formatted_date = $pickup_ts ? date_i18n( $format, $pickup_ts ) : date_i18n( get_option( 'date_format' ), strtotime( $pickup_date ) );
									?>
									<tr>
										<td class="rv-booking-id">#<?php echo esc_html( $booking->ID ); ?></td>
										<?php if ( ! $is_integrated ) : ?>
											<td class="rv-vehicle-thumb">
												<img src="<?php echo esc_url( $vehicle_image ); ?>" alt="<?php echo esc_attr( $vehicle->post_title ?? '' ); ?>">
											</td>
										<?php endif; ?>
										<td class="rv-vehicle-name"><?php echo esc_html( $vehicle->post_title ?? esc_html__( 'N/A', 'mhm-rentiva' ) ); ?></td>
										<td class="rv-booking-date"><?php echo esc_html( $formatted_date ); ?></td>
										<td class="rv-booking-status">
											<span class="status-badge <?php echo esc_attr( $status_class ); ?>">
												<?php echo esc_html( $status_label ); ?>
											</span>
										</td>
										<td class="rv-booking-price">
											<span class="rv-price-badge">
												<?php
												if ( function_exists( 'wc_price' ) ) {
													echo wp_kses_post( wc_price( $total_price ) );
												} else {
													echo esc_html( number_format( (float) $total_price, 2 ) ) . ' ' . esc_html( $currency_symbol );
												}
												?>
											</span>
										</td>
										<td class="rv-booking-actions">
											<a href="<?php echo esc_url( \MHMRentiva\Admin\Frontend\Account\AccountController::get_booking_view_url( $booking->ID ) ); ?>"
												class="btn btn-secondary rv-btn-icon-only" title="<?php esc_attr_e( 'View Details', 'mhm-rentiva' ); ?>">
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
			<?php if ( ! empty( $past_bookings ) ) : ?>
				<div class="rv-bookings-section">
					<div class="section-header">
						<h3><?php esc_html_e( 'Past Reservations', 'mhm-rentiva' ); ?></h3>
					</div>

					<div class="rv-table-wrapper">
						<table class="rv-bookings-table mhm-table">
							<thead>
								<tr>
									<th style="width: 50px;"><?php esc_html_e( 'ID', 'mhm-rentiva' ); ?></th>
									<?php if ( ! $is_integrated ) : ?>
										<th style="width: 60px;"><?php esc_html_e( 'Img', 'mhm-rentiva' ); ?></th>
									<?php endif; ?>
									<th><?php esc_html_e( 'Service', 'mhm-rentiva' ); ?></th>
									<th style="white-space: nowrap;"><?php esc_html_e( 'Date/Time', 'mhm-rentiva' ); ?></th>
									<th><?php esc_html_e( 'Status', 'mhm-rentiva' ); ?></th>
									<th><?php esc_html_e( 'Fee', 'mhm-rentiva' ); ?></th>
									<th style="text-align: center; width: 60px;"><?php esc_html_e( 'Act', 'mhm-rentiva' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $past_bookings as $booking ) :
									$vehicle_id  = get_post_meta( $booking->ID, '_mhm_vehicle_id', true );
									$vehicle     = get_post( $vehicle_id );
									$status      = get_post_meta( $booking->ID, '_mhm_status', true );
									$pickup_date = get_post_meta( $booking->ID, '_mhm_pickup_date', true );
									// Get pickup time with fallbacks
									$pickup_time = get_post_meta( $booking->ID, '_mhm_start_time', true );
									if ( ! $pickup_time ) {
										$pickup_time = get_post_meta( $booking->ID, '_mhm_pickup_time', true );
									}
									if ( ! $pickup_time ) {
										$pickup_time = get_post_meta( $booking->ID, '_booking_pickup_time', true );
									}
									$total_price = get_post_meta( $booking->ID, '_mhm_total_price', true );

									// Vehicle image
									$vehicle_image = get_the_post_thumbnail_url( $vehicle_id, 'thumbnail' );
									if ( ! $vehicle_image ) {
										$vehicle_image = MHM_RENTIVA_PLUGIN_URL . 'assets/images/no-image.png';
									}

									// Status badge
									$status_labels = array(
										'pending'     => esc_html__( 'Pending', 'mhm-rentiva' ),
										'confirmed'   => esc_html__( 'Confirmed', 'mhm-rentiva' ),
										'in_progress' => esc_html__( 'In Progress', 'mhm-rentiva' ),
										'completed'   => esc_html__( 'Completed', 'mhm-rentiva' ),
										'cancelled'   => esc_html__( 'Cancelled', 'mhm-rentiva' ),
									);
									$status_label  = $status_labels[ $status ] ?? ucfirst( $status );
									$status_class  = 'status-' . $status;

									// Date format (site settings)
									$format         = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
									$pickup_ts      = strtotime( trim( $pickup_date . ' ' . ( $pickup_time ?: '' ) ) );
									$formatted_date = $pickup_ts ? date_i18n( $format, $pickup_ts ) : date_i18n( get_option( 'date_format' ), strtotime( $pickup_date ) );
									?>
									<tr>
										<td class="rv-booking-id">#<?php echo esc_html( $booking->ID ); ?></td>
										<?php if ( ! $is_integrated ) : ?>
											<td class="rv-vehicle-thumb">
												<img src="<?php echo esc_url( $vehicle_image ); ?>" alt="<?php echo esc_attr( $vehicle->post_title ?? '' ); ?>">
											</td>
										<?php endif; ?>
										<td class="rv-vehicle-name"><?php echo esc_html( $vehicle->post_title ?? esc_html__( 'N/A', 'mhm-rentiva' ) ); ?></td>
										<td class="rv-booking-date"><?php echo esc_html( $formatted_date ); ?></td>
										<td class="rv-booking-status">
											<span class="status-badge <?php echo esc_attr( $status_class ); ?>">
												<?php echo esc_html( $status_label ); ?>
											</span>
										</td>
										<td class="rv-booking-price">
											<span class="rv-price-badge">
												<?php
												if ( function_exists( 'wc_price' ) ) {
													echo wp_kses_post( wc_price( $total_price ) );
												} else {
													echo esc_html( number_format( (float) $total_price, 2 ) ) . ' ' . esc_html( $currency_symbol );
												}
												?>
											</span>
										</td>
										<td class="rv-booking-actions">
											<a href="<?php echo esc_url( \MHMRentiva\Admin\Frontend\Account\AccountController::get_booking_view_url( $booking->ID ) ); ?>"
												class="btn btn-secondary rv-btn-icon-only" title="<?php esc_attr_e( 'View Details', 'mhm-rentiva' ); ?>">
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

			<!-- No Bookings -->
			<?php if ( empty( $upcoming_bookings ) && empty( $past_bookings ) ) : ?>
				<div class="rv-no-bookings">
					<div class="rv-empty-state">
						<div class="rv-empty-icon">📅</div>
						<h3><?php esc_html_e( 'No Bookings Found', 'mhm-rentiva' ); ?></h3>
						<p><?php esc_html_e( 'You haven\'t made any vehicle bookings yet. Start exploring our fleet!', 'mhm-rentiva' ); ?></p>
						<a href="<?php echo esc_url( $booking_form_url ); ?>" class="rv-btn-primary">
							<?php esc_html_e( 'Browse Vehicles', 'mhm-rentiva' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

		</div><!-- .rv-bookings-page -->
	</div><!-- .mhm-account-content -->

</div><!-- .mhm-rentiva-account-page -->