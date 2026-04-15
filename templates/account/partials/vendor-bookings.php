<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables from dashboard context.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded vendor-scoped query.

/**
 * Vendor Bookings partial — rendered under the Booking Requests tab.
 *
 * @since 4.22.3
 */

$vendor_id   = get_current_user_id();
$date_format = get_option( 'date_format' );

// Fetch vendor's vehicle IDs.
$vehicle_ids = get_posts( array(
	'post_type'      => 'vehicle',
	'author'         => $vendor_id,
	'post_status'    => array( 'publish', 'pending' ),
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
) );

$status_labels = array(
	'pending'         => array( 'label' => __( 'Pending', 'mhm-rentiva' ),         'class' => 'is-pending' ),
	'pending_payment' => array( 'label' => __( 'Awaiting Payment', 'mhm-rentiva' ), 'class' => 'is-pending' ),
	'confirmed'       => array( 'label' => __( 'Confirmed', 'mhm-rentiva' ),        'class' => 'is-confirmed' ),
	'in_progress'     => array( 'label' => __( 'In Progress', 'mhm-rentiva' ),      'class' => 'is-progress' ),
	'completed'       => array( 'label' => __( 'Completed', 'mhm-rentiva' ),        'class' => 'is-completed' ),
	'cancelled'       => array( 'label' => __( 'Cancelled', 'mhm-rentiva' ),        'class' => 'is-cancelled' ),
	'refunded'        => array( 'label' => __( 'Refunded', 'mhm-rentiva' ),         'class' => 'is-refunded' ),
	'no_show'         => array( 'label' => __( 'No Show', 'mhm-rentiva' ),          'class' => 'is-cancelled' ),
);

$format_currency = static function ( float $amount ): string {
	if ( function_exists( 'wc_price' ) ) {
		return (string) wc_price( $amount );
	}
	return '₺' . number_format( $amount, 0, ',', '.' );
};

// Status filter from URL.
$filter_status = sanitize_key( (string) ( $_GET['booking_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>

<div class="mhm-vendor-bookings-page">

	<!-- Header + Tab Filter -->
	<div class="mhm-vendor-bookings-page__header">
		<h3 class="mhm-vendor-bookings-page__title"><?php esc_html_e( 'Booking Requests', 'mhm-rentiva' ); ?></h3>
	</div>
	<?php
	$filter_tabs = array(
		''            => __( 'All', 'mhm-rentiva' ),
		'pending'     => __( 'Pending', 'mhm-rentiva' ),
		'confirmed'   => __( 'Confirmed', 'mhm-rentiva' ),
		'in_progress' => __( 'In Progress', 'mhm-rentiva' ),
		'completed'   => __( 'Completed', 'mhm-rentiva' ),
		'cancelled'   => __( 'Cancelled', 'mhm-rentiva' ),
	);
	$dashboard_url = $dashboard['dashboard_url'] ?? home_url( '/panel/' );
	?>
	<nav class="mhm-vendor-bookings-page__tabs">
		<?php foreach ( $filter_tabs as $tab_key => $tab_label ) : ?>
			<a
				href="<?php echo esc_url( add_query_arg( array_filter( array( 'tab' => 'bookings', 'booking_status' => $tab_key ) ), $dashboard_url ) ); ?>"
				class="mhm-vendor-bookings-page__tab <?php echo $filter_status === $tab_key ? 'is-active' : ''; ?>"
			><?php echo esc_html( $tab_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<?php if ( empty( $vehicle_ids ) ) : ?>
		<!-- No vehicles yet -->
		<div class="mhm-vendor-bookings-page__empty">
			<div class="mhm-vendor-bookings-page__empty-icon">
				<svg viewBox="0 0 24 24" fill="none" width="40" height="40" focusable="false">
					<rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/>
					<path d="M3 10h18" stroke="currentColor" stroke-width="1.5"/>
					<path d="M8 4v-1M16 4v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
				</svg>
			</div>
			<h4 class="mhm-vendor-bookings-page__empty-title"><?php esc_html_e( 'No vehicles listed yet.', 'mhm-rentiva' ); ?></h4>
			<p class="mhm-vendor-bookings-page__empty-text"><?php esc_html_e( 'Add a vehicle first to start receiving booking requests.', 'mhm-rentiva' ); ?></p>
		</div>
	<?php else : ?>
		<?php
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $vehicle_ids ), '%d' ) );

		// Build optional status filter.
		$status_where = '';
		$query_params = $vehicle_ids;
		if ( $filter_status !== '' && isset( $status_labels[ $filter_status ] ) ) {
			$status_where = ' AND sm.meta_value = %s';
			$query_params[] = $filter_status;
		}

		$query_params[] = 50; // LIMIT

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders/$status_where are safe.
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID,
						vm.meta_value AS vehicle_id,
						sm.meta_value AS booking_status,
						dm.meta_value AS pickup_date,
						em.meta_value AS dropoff_date,
						tm.meta_value AS total_price
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} vm ON vm.post_id = p.ID AND vm.meta_key = '_mhm_vehicle_id'
				 LEFT JOIN  {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = '_mhm_status'
				 LEFT JOIN  {$wpdb->postmeta} dm ON dm.post_id = p.ID AND dm.meta_key = '_mhm_pickup_date'
				 LEFT JOIN  {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_mhm_dropoff_date'
				 LEFT JOIN  {$wpdb->postmeta} tm ON tm.post_id = p.ID AND tm.meta_key = '_mhm_total_price'
				 WHERE p.post_type = 'vehicle_booking'
				 AND p.post_status NOT IN ('trash','auto-draft')
				 AND CAST(vm.meta_value AS UNSIGNED) IN ($placeholders)
				 {$status_where}
				 ORDER BY p.ID DESC
				 LIMIT %d",
				...$query_params
			)
		);

		// Count stats (use PHP since we already have results — avoids extra queries).
		$stats = array(
			'pending'     => 0,
			'in_progress' => 0,
			'completed'   => 0,
			'total_revenue' => 0.0,
		);

		// For stats, we need unfiltered counts if a filter is active.
		if ( $filter_status !== '' ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$all_statuses = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT sm.meta_value AS booking_status, tm.meta_value AS total_price
					 FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} vm ON vm.post_id = p.ID AND vm.meta_key = '_mhm_vehicle_id'
					 LEFT JOIN  {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = '_mhm_status'
					 LEFT JOIN  {$wpdb->postmeta} tm ON tm.post_id = p.ID AND tm.meta_key = '_mhm_total_price'
					 WHERE p.post_type = 'vehicle_booking'
					 AND p.post_status NOT IN ('trash','auto-draft')
					 AND CAST(vm.meta_value AS UNSIGNED) IN ($placeholders)
					 LIMIT 500",
					...$vehicle_ids
				)
			);
		} else {
			$all_statuses = $bookings;
		}

		$this_month_start = wp_date( 'Y-m-01' );
		foreach ( $all_statuses as $b ) {
			$s = (string) ( $b->booking_status ?? '' );
			if ( $s === 'pending' || $s === 'pending_payment' ) {
				$stats['pending']++;
			} elseif ( $s === 'in_progress' || $s === 'confirmed' ) {
				$stats['in_progress']++;
			} elseif ( $s === 'completed' ) {
				$stats['completed']++;
			}
			if ( in_array( $s, array( 'completed', 'confirmed', 'in_progress' ), true ) ) {
				$stats['total_revenue'] += (float) ( $b->total_price ?? 0 );
			}
		}
		?>

		<!-- Stats Summary -->
		<div class="mhm-vendor-bookings-page__stats">
			<div class="mhm-vendor-bookings-page__stat is-pending">
				<div class="mhm-vendor-bookings-page__stat-icon">
					<svg viewBox="0 0 24 24" fill="none" width="20" height="20"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
				</div>
				<div class="mhm-vendor-bookings-page__stat-info">
					<span class="mhm-vendor-bookings-page__stat-value"><?php echo esc_html( (string) $stats['pending'] ); ?></span>
					<span class="mhm-vendor-bookings-page__stat-label"><?php esc_html_e( 'Pending', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
			<div class="mhm-vendor-bookings-page__stat is-active">
				<div class="mhm-vendor-bookings-page__stat-icon">
					<svg viewBox="0 0 24 24" fill="none" width="20" height="20"><path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<div class="mhm-vendor-bookings-page__stat-info">
					<span class="mhm-vendor-bookings-page__stat-value"><?php echo esc_html( (string) $stats['in_progress'] ); ?></span>
					<span class="mhm-vendor-bookings-page__stat-label"><?php esc_html_e( 'Active', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
			<div class="mhm-vendor-bookings-page__stat is-revenue">
				<div class="mhm-vendor-bookings-page__stat-icon">
					<svg viewBox="0 0 24 24" fill="none" width="20" height="20"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<div class="mhm-vendor-bookings-page__stat-info">
					<span class="mhm-vendor-bookings-page__stat-value"><?php echo wp_kses_post( $format_currency( $stats['total_revenue'] ) ); ?></span>
					<span class="mhm-vendor-bookings-page__stat-label"><?php esc_html_e( 'Total Revenue', 'mhm-rentiva' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Booking Cards -->
		<?php if ( empty( $bookings ) ) : ?>
			<div class="mhm-vendor-bookings-page__empty">
				<div class="mhm-vendor-bookings-page__empty-icon">
					<svg viewBox="0 0 24 24" fill="none" width="40" height="40" focusable="false">
						<rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/>
						<path d="M3 10h18" stroke="currentColor" stroke-width="1.5"/>
						<path d="M8 4v-1M16 4v-1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
					</svg>
				</div>
				<h4 class="mhm-vendor-bookings-page__empty-title"><?php esc_html_e( 'No booking requests found.', 'mhm-rentiva' ); ?></h4>
				<p class="mhm-vendor-bookings-page__empty-text"><?php esc_html_e( 'When customers book your vehicles, their requests will appear here.', 'mhm-rentiva' ); ?></p>
			</div>
		<?php else : ?>
			<div class="mhm-vendor-bookings-page__list">
				<?php foreach ( $bookings as $booking ) : ?>
					<?php
					$b_status      = (string) ( $booking->booking_status ?? '' );
					$b_vehicle_id  = (int) ( $booking->vehicle_id ?? 0 );
					$b_price       = (float) ( $booking->total_price ?? 0 );
					$b_pickup      = (string) ( $booking->pickup_date ?? '' );
					$b_dropoff     = (string) ( $booking->dropoff_date ?? '' );

					// Resolve customer name via centralized helper (meta → WC order → WP user fallback chain).
					$customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( (int) $booking->ID );
					$customer_name = trim( ( $customer_info['first_name'] ?? '' ) . ' ' . ( $customer_info['last_name'] ?? '' ) );
					if ( $customer_name === '' ) {
						$customer_name = __( 'Unknown', 'mhm-rentiva' );
					}

					// Service type detection (transfer vs rental).
					$service_type = (string) get_post_meta( $booking->ID, '_mhm_service_type', true );
					if ( $service_type === '' && (string) get_post_meta( $booking->ID, '_mhm_is_transfer', true ) === 'yes' ) {
						$service_type = 'transfer';
					}
					if ( $service_type === '' && (int) get_post_meta( $booking->ID, '_mhm_transfer_origin_id', true ) > 0 ) {
						$service_type = 'transfer';
					}
					if ( $service_type === '' ) {
						$service_type = 'rental';
					}
					$service_label = $service_type === 'transfer'
						? __( 'Transfer', 'mhm-rentiva' )
						: __( 'Car Rental', 'mhm-rentiva' );

					$pickup_time  = (string) get_post_meta( $booking->ID, '_mhm_start_time', true );
					if ( $pickup_time === '' ) {
						$pickup_time = (string) get_post_meta( $booking->ID, '_mhm_pickup_time', true );
					}
					$dropoff_time = (string) get_post_meta( $booking->ID, '_mhm_end_time', true );
					if ( $dropoff_time === '' ) {
						$dropoff_time = (string) get_post_meta( $booking->ID, '_mhm_dropoff_time', true );
					}

					// Vehicle info.
					$vehicle_brand = (string) get_post_meta( $b_vehicle_id, '_mhm_rentiva_brand', true );
					$vehicle_model = (string) get_post_meta( $b_vehicle_id, '_mhm_rentiva_model', true );
					$vehicle_name  = trim( $vehicle_brand . ' ' . $vehicle_model );
					if ( $vehicle_name === '' ) {
						$vehicle_name = get_the_title( $b_vehicle_id ) ?: __( 'Unknown Vehicle', 'mhm-rentiva' );
					}
					$vehicle_thumb = get_the_post_thumbnail_url( $b_vehicle_id, 'thumbnail' );

					// Status info.
					$status_info = $status_labels[ $b_status ] ?? array(
						'label' => ucfirst( $b_status ?: __( 'Unknown', 'mhm-rentiva' ) ),
						'class' => '',
					);

					// Date formatting.
					$pickup_fmt  = $b_pickup  ? date_i18n( $date_format, strtotime( $b_pickup ) )  : '—';
					$dropoff_fmt = $b_dropoff ? date_i18n( $date_format, strtotime( $b_dropoff ) ) : '—';

					// Duration in days.
					$duration_days = 0;
					if ( $b_pickup && $b_dropoff ) {
						$diff = strtotime( $b_dropoff ) - strtotime( $b_pickup );
						$duration_days = max( 1, (int) ceil( $diff / 86400 ) );
					}

					// Display ID.
					$display_id = function_exists( 'mhm_rentiva_get_display_id' )
						? mhm_rentiva_get_display_id( (int) $booking->ID )
						: '#' . $booking->ID;

					$is_pending = in_array( $b_status, array( 'pending', 'pending_payment' ), true );
					?>
					<div class="mhm-vendor-booking-card <?php echo $is_pending ? 'is-pending-highlight' : ''; ?>">

						<!-- Vehicle Thumbnail -->
						<div class="mhm-vendor-booking-card__thumb">
							<?php if ( $vehicle_thumb ) : ?>
								<img src="<?php echo esc_url( $vehicle_thumb ); ?>" alt="<?php echo esc_attr( $vehicle_name ); ?>" loading="lazy">
							<?php else : ?>
								<div class="mhm-vendor-booking-card__thumb-placeholder">
									<svg viewBox="0 0 24 24" fill="none" width="24" height="24"><path d="M4 14.25L5.8 9.75C6.09 9.02 6.79 8.55 7.58 8.55H16.42C17.21 8.55 17.91 9.02 18.2 9.75L20 14.25M5.25 14.25H18.75C19.44 14.25 20 14.81 20 15.5V17.25C20 17.66 19.66 18 19.25 18H18.5M5.5 18H4.75C4.34 18 4 17.66 4 17.25V15.5C4 14.81 4.56 14.25 5.25 14.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="7.5" cy="16.5" r="1" fill="currentColor"/><circle cx="16.5" cy="16.5" r="1" fill="currentColor"/></svg>
								</div>
							<?php endif; ?>
						</div>

						<!-- Identity: Vehicle + Customer + Status + Service -->
						<div class="mhm-vendor-booking-card__identity">
							<span class="mhm-vendor-booking-card__vehicle-name"><?php echo esc_html( $vehicle_name ); ?></span>
							<span class="mhm-vendor-booking-card__customer-name">
								<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.5"/></svg>
								<?php echo esc_html( $customer_name ); ?>
							</span>
							<span class="mhm-vendor-booking-card__service is-<?php echo esc_attr( $service_type ); ?>">
								<?php if ( $service_type === 'transfer' ) : ?>
									<svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M3 12h13m0 0l-4-4m4 4l-4 4M21 6v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<?php else : ?>
									<svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M5 17h14M6 14l1.5-4.5A2 2 0 019.4 8h5.2a2 2 0 011.9 1.5L18 14M7 17v2M17 17v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<?php endif; ?>
								<?php echo esc_html( $service_label ); ?>
							</span>
							<span class="mhm-rentiva-dashboard__status <?php echo esc_attr( $status_info['class'] ); ?>">
								<?php echo esc_html( $status_info['label'] ); ?>
							</span>
							<?php
							$booking_view_url = \MHMRentiva\Admin\Frontend\Account\AccountController::get_booking_view_url( (int) $booking->ID );
							?>
							<a href="<?php echo esc_url( $booking_view_url ); ?>" class="mhm-vendor-booking-card__ref" title="<?php esc_attr_e( 'View booking details', 'mhm-rentiva' ); ?>">
								#<?php echo esc_html( ltrim( (string) $display_id, '#' ) ); ?>
							</a>
						</div>

						<!-- Date Range -->
						<div class="mhm-vendor-booking-card__dates">
							<span class="mhm-vendor-booking-card__dates-label"><?php esc_html_e( 'Date Range', 'mhm-rentiva' ); ?></span>
							<span class="mhm-vendor-booking-card__dates-value">
								<?php echo esc_html( $pickup_fmt ); ?> &rarr; <?php echo esc_html( $dropoff_fmt ); ?>
							</span>
							<span class="mhm-vendor-booking-card__dates-meta">
								<?php if ( $pickup_time || $dropoff_time ) : ?>
									<?php echo esc_html( $pickup_time ?: '—' ); ?> - <?php echo esc_html( $dropoff_time ?: '—' ); ?>
								<?php endif; ?>
							</span>
						</div>

						<!-- Price & Duration -->
						<div class="mhm-vendor-booking-card__pricing">
							<span class="mhm-vendor-booking-card__dates-label"><?php esc_html_e( 'Duration & Price', 'mhm-rentiva' ); ?></span>
							<?php if ( $b_price > 0 ) : ?>
								<span class="mhm-vendor-booking-card__price"><?php echo wp_kses_post( $format_currency( $b_price ) ); ?></span>
							<?php endif; ?>
							<?php if ( $duration_days > 0 ) : ?>
								<span class="mhm-vendor-booking-card__dates-meta">
									<?php
									printf(
										/* translators: %d: number of days */
										esc_html( _n( '%d day total', '%d days total', $duration_days, 'mhm-rentiva' ) ),
										$duration_days
									);
									?>
								</span>
							<?php endif; ?>
						</div>

						<!-- Actions: View detail + Report issue -->
						<div class="mhm-vendor-booking-card__actions">
							<a href="<?php echo esc_url( $booking_view_url ); ?>" class="mhm-vendor-booking-card__action is-view">
								<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/></svg>
								<?php esc_html_e( 'View Details', 'mhm-rentiva' ); ?>
							</a>
							<button type="button" class="mhm-vendor-booking-card__action is-report" data-booking-id="<?php echo esc_attr( (string) $booking->ID ); ?>">
								<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<?php esc_html_e( 'Report Issue', 'mhm-rentiva' ); ?>
							</button>
						</div>

					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

</div>
