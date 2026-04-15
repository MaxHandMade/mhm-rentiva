<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables from dashboard context.

$vendor_id = get_current_user_id();

// Query vendor's vehicles.
$vehicles = get_posts( array(
	'post_type'      => 'vehicle',
	'author'         => $vendor_id,
	'post_status'    => array( 'publish', 'pending', 'draft' ),
	'posts_per_page' => 50,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

$review_status_labels = array(
	'pending_review' => array(
		'label' => __( 'Awaiting Review', 'mhm-rentiva' ),
		'class' => 'is-pending',
	),
	'approved' => array(
		'label' => __( 'Live', 'mhm-rentiva' ),
		'class' => 'is-confirmed',
	),
	'rejected' => array(
		'label' => __( 'Rejected', 'mhm-rentiva' ),
		'class' => 'is-cancelled',
	),
	'partial_edit' => array(
		'label' => __( 'Re-review Pending', 'mhm-rentiva' ),
		'class' => 'is-progress',
	),
);

$lifecycle_status_labels = array(
	'active'         => array( 'label' => __( 'Active', 'mhm-rentiva' ), 'class' => 'is-confirmed' ),
	'paused'         => array( 'label' => __( 'Paused', 'mhm-rentiva' ), 'class' => 'is-progress' ),
	'expired'        => array( 'label' => __( 'Expired', 'mhm-rentiva' ), 'class' => 'is-cancelled' ),
	'withdrawn'      => array( 'label' => __( 'Withdrawn', 'mhm-rentiva' ), 'class' => 'is-cancelled' ),
	'pending_review' => array( 'label' => __( 'Awaiting Review', 'mhm-rentiva' ), 'class' => 'is-pending' ),
);

$format_currency = static function ( float $amount ): string {
	if ( function_exists( 'wc_price' ) ) {
		return (string) wc_price( $amount );
	}
	return '₺' . number_format( $amount, 0, ',', '.' );
};

/**
 * Resolve a vehicle's current operational state based on active bookings.
 *
 * Returns one of: 'rented' | 'on_transfer' | 'maintenance' | 'idle'.
 * A vehicle is "active" when a booking exists whose date range covers today
 * and whose status is pending_payment/confirmed/in_progress.
 */
$resolve_operational_state = static function ( int $vehicle_id ): string {
	global $wpdb;
	$today = gmdate( 'Y-m-d' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$active = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT p.ID,
				stm.meta_value AS service_type,
				trm.meta_value AS transfer_origin
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} vm ON vm.post_id = p.ID AND vm.meta_key = '_mhm_vehicle_id'
			 INNER JOIN {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = '_mhm_status'
			   AND sm.meta_value IN ('pending_payment','confirmed','in_progress')
			 INNER JOIN {$wpdb->postmeta} dm ON dm.post_id = p.ID AND dm.meta_key = '_mhm_pickup_date'
			 INNER JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_mhm_dropoff_date'
			 LEFT JOIN  {$wpdb->postmeta} stm ON stm.post_id = p.ID AND stm.meta_key = '_mhm_service_type'
			 LEFT JOIN  {$wpdb->postmeta} trm ON trm.post_id = p.ID AND trm.meta_key = '_mhm_transfer_origin_id'
			 WHERE p.post_type = 'vehicle_booking'
			 AND p.post_status NOT IN ('trash','auto-draft')
			 AND CAST(vm.meta_value AS UNSIGNED) = %d
			 AND dm.meta_value <= %s
			 AND em.meta_value >= %s
			 ORDER BY p.ID DESC
			 LIMIT 1",
			$vehicle_id,
			$today,
			$today
		)
	);

	if ( ! $active ) {
		return 'idle';
	}
	$service_type = (string) ( $active->service_type ?? '' );
	if ( $service_type === 'maintenance' ) {
		return 'maintenance';
	}
	if ( $service_type === 'transfer' || (int) ( $active->transfer_origin ?? 0 ) > 0 ) {
		return 'on_transfer';
	}
	return 'rented';
};

$operational_labels = array(
	'idle'        => array( 'label' => __( 'Idle', 'mhm-rentiva' ),            'class' => 'is-idle' ),
	'rented'      => array( 'label' => __( 'Rented', 'mhm-rentiva' ),          'class' => 'is-rented' ),
	'on_transfer' => array( 'label' => __( 'On Transfer', 'mhm-rentiva' ),     'class' => 'is-transfer' ),
	'maintenance' => array( 'label' => __( 'In Maintenance', 'mhm-rentiva' ),  'class' => 'is-maintenance' ),
);

$vehicle_count = count( $vehicles );
?>

<div class="mhm-vendor-listings-page">

	<!-- Header -->
	<div class="mhm-vendor-listings-page__header">
		<div class="mhm-vendor-listings-page__header-info">
			<h3 class="mhm-vendor-listings-page__title"><?php esc_html_e( 'My Vehicles', 'mhm-rentiva' ); ?></h3>
			<?php if ( $vehicle_count > 0 ) : ?>
				<p class="mhm-vendor-listings-page__subtitle">
					<?php
					printf(
						/* translators: %d: number of vehicles */
						esc_html__( '%d vehicles in your portfolio.', 'mhm-rentiva' ),
						$vehicle_count
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<button
			id="mhm-toggle-add-vehicle"
			class="mhm-vendor-listings-page__add-btn"
			aria-expanded="false"
		>
			<svg viewBox="0 0 24 24" fill="none" width="18" height="18" focusable="false"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
			<?php esc_html_e( 'Add Vehicle', 'mhm-rentiva' ); ?>
		</button>
	</div>

	<!-- Inline Add Form (hidden by default) -->
	<div id="mhm-add-vehicle-panel" class="mhm-vendor-listings-page__add-panel" style="display:none">
		<div class="mhm-vendor-listings-page__add-panel-inner">
			<div class="mhm-vendor-listings-page__add-panel-head">
				<div class="mhm-vendor-listings-page__add-panel-accent"></div>
				<strong><?php esc_html_e( 'New Vehicle Details', 'mhm-rentiva' ); ?></strong>
				<button id="mhm-close-add-vehicle" class="mhm-vendor-listings-page__add-panel-close" aria-label="<?php esc_attr_e( 'Close', 'mhm-rentiva' ); ?>">&times;</button>
			</div>
			<div class="mhm-vendor-listings-page__add-panel-body">
				<?php echo do_shortcode( '[rentiva_vehicle_submit]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>

	<!-- Vehicle Cards -->
	<?php if ( empty( $vehicles ) ) : ?>
		<div class="mhm-vendor-listings-page__empty">
			<div class="mhm-vendor-listings-page__empty-icon">
				<svg viewBox="0 0 24 24" fill="none" width="40" height="40" focusable="false">
					<path d="M4 14.25L5.8 9.75C6.09 9.02 6.79 8.55 7.58 8.55H16.42C17.21 8.55 17.91 9.02 18.2 9.75L20 14.25M5.25 14.25H18.75C19.44 14.25 20 14.81 20 15.5V17.25C20 17.66 19.66 18 19.25 18H18.5M5.5 18H4.75C4.34 18 4 17.66 4 17.25V15.5C4 14.81 4.56 14.25 5.25 14.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
					<circle cx="7.5" cy="16.5" r="1" fill="currentColor" />
					<circle cx="16.5" cy="16.5" r="1" fill="currentColor" />
				</svg>
			</div>
			<h4 class="mhm-vendor-listings-page__empty-title"><?php esc_html_e( 'You have not added any vehicles yet.', 'mhm-rentiva' ); ?></h4>
			<p class="mhm-vendor-listings-page__empty-text"><?php esc_html_e( 'Click "Add Vehicle" to submit your first listing for review.', 'mhm-rentiva' ); ?></p>
		</div>
	<?php else : ?>
		<div class="mhm-vendor-listings-page__list">
			<?php foreach ( $vehicles as $vehicle ) : ?>
				<?php
				$review_status    = (string) get_post_meta( $vehicle->ID, '_vehicle_review_status', true );
				$lifecycle_status = (string) get_post_meta( $vehicle->ID, '_mhm_vehicle_lifecycle_status', true );
				// Auto-correct: published vehicle with stale pending_review meta (e.g. published directly via admin).
				if ( $vehicle->post_status === 'publish' && $review_status === 'pending_review' ) {
					update_post_meta( $vehicle->ID, '_vehicle_review_status', 'approved' );
					$review_status = 'approved';
				}
				// Default lifecycle status for approved vehicles with no lifecycle meta yet.
				if ( $lifecycle_status === '' && $review_status === 'approved' ) {
					$lifecycle_status = 'active';
				}
				$rejection_note = (string) get_post_meta( $vehicle->ID, '_vehicle_rejection_note', true );
				$brand          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_brand', true );
				$model          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_model', true );
				$year           = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_year', true );
				$price          = (float)  get_post_meta( $vehicle->ID, '_mhm_rentiva_price_per_day', true );
				$city           = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_vehicle_city', true );
				$plate          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_plate', true );
				// For approved vehicles, show lifecycle status badge instead of review status.
				if ( $review_status === 'approved' && $lifecycle_status !== '' ) {
					$status_info = $lifecycle_status_labels[ $lifecycle_status ] ?? array(
						'label' => ucfirst( $lifecycle_status ),
						'class' => 'is-confirmed',
					);
				} else {
					$status_info = $review_status_labels[ $review_status ] ?? array(
						'label' => ucfirst( $review_status ?: __( 'Draft', 'mhm-rentiva' ) ),
						'class' => '',
					);
				}
				$thumbnail_url  = get_the_post_thumbnail_url( $vehicle->ID, 'medium' );
				$vehicle_name   = trim( $brand . ' ' . $model . ( $year ? ' (' . $year . ')' : '' ) );
				if ( $vehicle_name === '' ) {
					$vehicle_name = $vehicle->post_title;
				}
				$is_rejected = $review_status === 'rejected';

				// Operational state: only meaningful for approved + active vehicles.
				$operational_state = null;
				$operational_info  = null;
				if ( $review_status === 'approved' && in_array( $lifecycle_status, array( 'active', 'paused' ), true ) ) {
					$operational_state = $resolve_operational_state( (int) $vehicle->ID );
					$operational_info  = $operational_labels[ $operational_state ] ?? null;
				}
				?>
				<div class="mhm-vendor-listing-card <?php echo $is_rejected ? 'is-rejected' : ''; ?>">

					<!-- Thumbnail -->
					<div class="mhm-vendor-listing-card__thumb">
						<?php if ( $thumbnail_url ) : ?>
							<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $vehicle_name ); ?>" loading="lazy">
						<?php else : ?>
							<div class="mhm-vendor-listing-card__thumb-placeholder">
								<svg viewBox="0 0 24 24" fill="none" width="32" height="32"><path d="M4 14.25L5.8 9.75C6.09 9.02 6.79 8.55 7.58 8.55H16.42C17.21 8.55 17.91 9.02 18.2 9.75L20 14.25M5.25 14.25H18.75C19.44 14.25 20 14.81 20 15.5V17.25C20 17.66 19.66 18 19.25 18H18.5M5.5 18H4.75C4.34 18 4 17.66 4 17.25V15.5C4 14.81 4.56 14.25 5.25 14.25Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="7.5" cy="16.5" r="1" fill="currentColor"/><circle cx="16.5" cy="16.5" r="1" fill="currentColor"/></svg>
							</div>
						<?php endif; ?>
					</div>

					<!-- Info -->
					<div class="mhm-vendor-listing-card__info">
						<div class="mhm-vendor-listing-card__top">
							<div class="mhm-vendor-listing-card__meta">
								<h4 class="mhm-vendor-listing-card__name"><?php echo esc_html( $vehicle_name ); ?></h4>
								<span class="mhm-rentiva-dashboard__status <?php echo esc_attr( $status_info['class'] ); ?>">
									<?php echo esc_html( $status_info['label'] ); ?>
								</span>
								<?php if ( $operational_info ) : ?>
									<span class="mhm-vendor-listing-card__op-state <?php echo esc_attr( $operational_info['class'] ); ?>">
										<?php if ( $operational_state === 'on_transfer' ) : ?>
											<svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M3 12h13m0 0l-4-4m4 4l-4 4M21 6v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										<?php elseif ( $operational_state === 'rented' ) : ?>
											<svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M5 17h14M6 14l1.5-4.5A2 2 0 019.4 8h5.2a2 2 0 011.9 1.5L18 14M7 17v2M17 17v2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										<?php elseif ( $operational_state === 'maintenance' ) : ?>
											<svg viewBox="0 0 24 24" fill="none" width="12" height="12"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										<?php else : ?>
											<svg viewBox="0 0 24 24" fill="none" width="12" height="12"><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.5"/></svg>
										<?php endif; ?>
										<?php echo esc_html( $operational_info['label'] ); ?>
									</span>
								<?php endif; ?>
							</div>
							<?php if ( $price > 0 ) : ?>
								<div class="mhm-vendor-listing-card__price">
									<span class="mhm-vendor-listing-card__price-label"><?php esc_html_e( 'Daily Price', 'mhm-rentiva' ); ?></span>
									<span class="mhm-vendor-listing-card__price-value"><?php echo wp_kses_post( $format_currency( $price ) ); ?><small>/<?php esc_html_e( 'day', 'mhm-rentiva' ); ?></small></span>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( $plate || $city ) : ?>
							<p class="mhm-vendor-listing-card__details">
								<?php if ( $plate ) : ?>
									<span><?php esc_html_e( 'Plate:', 'mhm-rentiva' ); ?> <strong><?php echo esc_html( $plate ); ?></strong></span>
								<?php endif; ?>
								<?php if ( $plate && $city ) : ?>
									<span class="mhm-vendor-listing-card__sep">&middot;</span>
								<?php endif; ?>
								<?php if ( $city ) : ?>
									<span><?php echo esc_html( $city ); ?></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<?php if ( $review_status === 'pending_review' ) : ?>
							<div class="mhm-vendor-listing-card__notice is-warning">
								<svg viewBox="0 0 24 24" fill="none" width="16" height="16"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
								<?php esc_html_e( 'Your listing will be published after operator approval.', 'mhm-rentiva' ); ?>
							</div>
						<?php endif; ?>

						<?php if ( $is_rejected && $rejection_note ) : ?>
							<div class="mhm-vendor-listing-card__notice is-error">
								<svg viewBox="0 0 24 24" fill="none" width="16" height="16"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M15 9l-6 6M9 9l6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
								<strong><?php esc_html_e( 'Rejection reason:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $rejection_note ); ?>
							</div>
						<?php endif; ?>

						<?php
						// Withdrawal cooldown badge (shown on the withdrawn vehicle card).
						if ( $lifecycle_status === 'withdrawn' ) :
							$cooldown_ends_at = (string) get_post_meta( $vehicle->ID, '_mhm_vehicle_cooldown_ends_at', true );
							if ( $cooldown_ends_at ) :
								$cooldown_ts        = strtotime( $cooldown_ends_at );
								$cooldown_remaining = (int) ceil( ( $cooldown_ts - time() ) / DAY_IN_SECONDS );
								if ( $cooldown_remaining > 0 ) :
									?>
									<div class="mhm-vendor-listing-card__remaining is-red">
										<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
										<?php
										printf(
											/* translators: %d: number of days until new listing ban is lifted */
											esc_html( _n( 'New listing ban: %d more day', 'New listing ban: %d more days', $cooldown_remaining, 'mhm-rentiva' ) ),
											$cooldown_remaining
										);
										?>
									</div>
									<?php
								endif;
							endif;
						endif;

						// Remaining listing time display
						if ( in_array( $lifecycle_status, array( 'active', 'paused' ), true ) ) :
							$expires_at = get_post_meta( $vehicle->ID, '_mhm_vehicle_listing_expires_at', true );
							$started_at = get_post_meta( $vehicle->ID, '_mhm_vehicle_listing_started_at', true );

							if ( $expires_at ) :
								$now            = time();
								$expires_ts     = strtotime( $expires_at );
								$started_ts     = $started_at ? strtotime( $started_at ) : $now;
								$total_days     = max( 1, (int) round( ( $expires_ts - $started_ts ) / DAY_IN_SECONDS ) );
								$remaining_days = max( 0, (int) ceil( ( $expires_ts - $now ) / DAY_IN_SECONDS ) );
								$pct            = ( $remaining_days / $total_days ) * 100;

								if ( $pct > 50 ) {
									$color_class = 'is-green';
								} elseif ( $pct > 20 ) {
									$color_class = 'is-yellow';
								} else {
									$color_class = 'is-red';
								}
								?>
								<div class="mhm-vendor-listing-card__remaining <?php echo esc_attr( $color_class ); ?>">
									<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M12 7v5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
									<?php
									printf(
										/* translators: %d: number of remaining days */
										esc_html__( 'Remaining: %d days', 'mhm-rentiva' ),
										$remaining_days
									);
									?>
								</div>
							<?php endif; ?>
						<?php endif; ?>

						<!-- Actions -->
						<div class="mhm-vendor-listing-card__actions">
							<?php if ( $review_status === 'approved' && $vehicle->post_status === 'publish' && $lifecycle_status === 'active' ) : ?>
								<a href="<?php echo esc_url( get_permalink( $vehicle->ID ) ); ?>" target="_blank" class="mhm-vendor-listing-card__action is-outline">
									<?php esc_html_e( 'View', 'mhm-rentiva' ); ?> &rarr;
								</a>
							<?php endif; ?>
							<?php if ( $review_status !== 'pending_review' && in_array( $lifecycle_status, array( 'active', 'paused' ), true ) ) : ?>
								<button type="button" class="mhm-vendor-listing-card__action is-outline mhm-edit-vehicle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>">
									<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
									<?php esc_html_e( 'Edit', 'mhm-rentiva' ); ?>
								</button>
							<?php endif; ?>
							<?php if ( $lifecycle_status === 'active' ) : ?>
								<button type="button" class="mhm-vendor-listing-card__action is-caution mhm-lifecycle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>" data-action="pause">
									<?php esc_html_e( 'Pause', 'mhm-rentiva' ); ?>
								</button>
								<button type="button" class="mhm-vendor-listing-card__action is-danger mhm-lifecycle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>" data-action="withdraw">
									<?php esc_html_e( 'Withdraw', 'mhm-rentiva' ); ?>
								</button>
							<?php elseif ( $lifecycle_status === 'paused' ) : ?>
								<button type="button" class="mhm-vendor-listing-card__action is-success mhm-lifecycle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>" data-action="resume">
									<?php esc_html_e( 'Resume', 'mhm-rentiva' ); ?>
								</button>
								<button type="button" class="mhm-vendor-listing-card__action is-danger mhm-lifecycle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>" data-action="withdraw">
									<?php esc_html_e( 'Withdraw', 'mhm-rentiva' ); ?>
								</button>
							<?php elseif ( $lifecycle_status === 'expired' ) : ?>
								<button type="button" class="mhm-vendor-listing-card__action is-outline mhm-lifecycle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>" data-action="renew">
									<?php esc_html_e( 'Renew', 'mhm-rentiva' ); ?>
								</button>
							<?php elseif ( $lifecycle_status === 'withdrawn' ) : ?>
								<button type="button" class="mhm-vendor-listing-card__action is-outline mhm-lifecycle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>" data-action="relist">
									<?php esc_html_e( 'Relist', 'mhm-rentiva' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>

				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<!-- Edit Vehicle Panel (hidden, populated via AJAX) -->
	<div id="mhm-edit-vehicle-panel" class="mhm-vendor-listings-page__add-panel" style="display:none">
		<div class="mhm-vendor-listings-page__add-panel-inner">
			<div class="mhm-vendor-listings-page__add-panel-head">
				<div class="mhm-vendor-listings-page__add-panel-accent" style="background:#f59e0b"></div>
				<strong><?php esc_html_e( 'Edit Vehicle', 'mhm-rentiva' ); ?></strong>
				<button id="mhm-close-edit-vehicle" class="mhm-vendor-listings-page__add-panel-close" aria-label="<?php esc_attr_e( 'Close', 'mhm-rentiva' ); ?>">&times;</button>
			</div>
			<div class="mhm-vendor-listings-page__add-panel-body" id="mhm-edit-vehicle-body">
				<p class="mhm-vendor-form__hint"><?php esc_html_e( 'Loading...', 'mhm-rentiva' ); ?></p>
			</div>
		</div>
	</div>

</div>
