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

$format_currency = static function ( float $amount ): string {
	if ( function_exists( 'wc_price' ) ) {
		return (string) wc_price( $amount );
	}
	return '₺' . number_format( $amount, 0, ',', '.' );
};

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
				$review_status  = (string) get_post_meta( $vehicle->ID, '_vehicle_review_status', true );
				// Auto-correct: published vehicle with stale pending_review meta (e.g. published directly via admin).
				if ( $vehicle->post_status === 'publish' && $review_status === 'pending_review' ) {
					update_post_meta( $vehicle->ID, '_vehicle_review_status', 'approved' );
					$review_status = 'approved';
				}
				$rejection_note = (string) get_post_meta( $vehicle->ID, '_vehicle_rejection_note', true );
				$brand          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_brand', true );
				$model          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_model', true );
				$year           = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_year', true );
				$price          = (float)  get_post_meta( $vehicle->ID, '_mhm_rentiva_price_per_day', true );
				$city           = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_vehicle_city', true );
				$plate          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_plate', true );
				$status_info    = $review_status_labels[ $review_status ] ?? array(
					'label' => ucfirst( $review_status ?: __( 'Draft', 'mhm-rentiva' ) ),
					'class' => '',
				);
				$thumbnail_url  = get_the_post_thumbnail_url( $vehicle->ID, 'medium' );
				$vehicle_name   = trim( $brand . ' ' . $model . ( $year ? ' (' . $year . ')' : '' ) );
				if ( $vehicle_name === '' ) {
					$vehicle_name = $vehicle->post_title;
				}
				$is_rejected = $review_status === 'rejected';
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

						<!-- Actions -->
						<div class="mhm-vendor-listing-card__actions">
							<?php if ( $review_status === 'approved' && $vehicle->post_status === 'publish' ) : ?>
								<a href="<?php echo esc_url( get_permalink( $vehicle->ID ) ); ?>" target="_blank" class="mhm-vendor-listing-card__action is-outline">
									<?php esc_html_e( 'View', 'mhm-rentiva' ); ?> &rarr;
								</a>
							<?php endif; ?>
							<?php if ( $review_status !== 'pending_review' ) : ?>
								<button type="button" class="mhm-vendor-listing-card__action is-outline mhm-edit-vehicle-btn" data-vehicle-id="<?php echo esc_attr( (string) $vehicle->ID ); ?>">
									<svg viewBox="0 0 24 24" fill="none" width="14" height="14"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
									<?php esc_html_e( 'Edit', 'mhm-rentiva' ); ?>
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
