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
		'color' => '#f59e0b',
		'bg'    => '#fef3cd',
	),
	'approved'       => array(
		'label' => __( 'Live', 'mhm-rentiva' ),
		'color' => '#16a34a',
		'bg'    => '#dcfce7',
	),
	'rejected'       => array(
		'label' => __( 'Rejected', 'mhm-rentiva' ),
		'color' => '#dc2626',
		'bg'    => '#fee2e2',
	),
	'partial_edit'   => array(
		'label' => __( 'Re-review Pending', 'mhm-rentiva' ),
		'color' => '#7c3aed',
		'bg'    => '#f3e8ff',
	),
);
?>

<div class="mhm-rentiva-dashboard__section">

	<div class="mhm-rentiva-dashboard__section-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
		<h3 style="margin:0"><?php esc_html_e( 'My Vehicles', 'mhm-rentiva' ); ?></h3>
		<button
			id="mhm-toggle-add-vehicle"
			class="button button-primary"
			style="background:#2271b1;border-color:#2271b1;color:#fff;padding:8px 18px;border-radius:6px;font-weight:600;cursor:pointer"
			aria-expanded="false"
		>
			+ <?php esc_html_e( 'Add Vehicle', 'mhm-rentiva' ); ?>
		</button>
	</div>

	<?php /* ── Inline vehicle submit form (hidden by default) ── */ ?>
	<div id="mhm-add-vehicle-panel" style="display:none;margin-bottom:32px">
		<div style="border:2px solid #2271b1;border-radius:10px;padding:0;overflow:hidden">
			<div style="background:#2271b1;color:#fff;padding:14px 20px;display:flex;justify-content:space-between;align-items:center">
				<strong><?php esc_html_e( 'New Vehicle', 'mhm-rentiva' ); ?></strong>
				<button id="mhm-close-add-vehicle" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;line-height:1" aria-label="<?php esc_attr_e( 'Close', 'mhm-rentiva' ); ?>">&times;</button>
			</div>
			<div style="padding:24px">
				<?php echo do_shortcode( '[rentiva_vehicle_submit]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>

	<?php /* ── Vehicle list ── */ ?>
	<?php if ( empty( $vehicles ) ) : ?>
		<div style="text-align:center;padding:48px 20px;background:#f9fafb;border-radius:10px;border:2px dashed #e5e7eb">
			<p style="color:#6b7280;margin:0 0 16px;font-size:1rem"><?php esc_html_e( 'You have not added any vehicles yet.', 'mhm-rentiva' ); ?></p>
			<p style="color:#9ca3af;margin:0;font-size:0.875rem"><?php esc_html_e( 'Click "Add Vehicle" to submit your first listing for review.', 'mhm-rentiva' ); ?></p>
		</div>
	<?php else : ?>
		<div class="mhm-vendor-listings">
			<?php foreach ( $vehicles as $vehicle ) : ?>
				<?php
				$review_status  = (string) get_post_meta( $vehicle->ID, '_vehicle_review_status', true );
				$rejection_note = (string) get_post_meta( $vehicle->ID, '_vehicle_rejection_note', true );
				$brand          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_brand', true );
				$model          = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_model', true );
				$year           = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_year', true );
				$price          = (float)  get_post_meta( $vehicle->ID, '_mhm_rentiva_price_per_day', true );
				$city           = (string) get_post_meta( $vehicle->ID, '_mhm_rentiva_vehicle_city', true );
				$status_info    = $review_status_labels[ $review_status ] ?? array(
					'label' => ucfirst( $review_status ?: __( 'Draft', 'mhm-rentiva' ) ),
					'color' => '#6b7280',
					'bg'    => '#f3f4f6',
				);
				$thumbnail_url  = get_the_post_thumbnail_url( $vehicle->ID, 'thumbnail' );
				?>
				<div class="mhm-vendor-listing-card" style="display:flex;gap:16px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:12px;align-items:flex-start">

					<?php /* Thumbnail */ ?>
					<div style="flex-shrink:0;width:80px;height:64px;background:#f3f4f6;border-radius:6px;overflow:hidden">
						<?php if ( $thumbnail_url ) : ?>
							<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="" style="width:100%;height:100%;object-fit:cover">
						<?php else : ?>
							<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#d1d5db;font-size:24px">&#128663;</div>
						<?php endif; ?>
					</div>

					<?php /* Info */ ?>
					<div style="flex:1;min-width:0">
						<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
							<strong style="font-size:0.9375rem;color:#111827">
								<?php echo esc_html( trim( $brand . ' ' . $model . ( $year ? ' (' . $year . ')' : '' ) ) ?: $vehicle->post_title ); ?>
							</strong>
							<span style="display:inline-block;padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:600;background:<?php echo esc_attr( $status_info['bg'] ); ?>;color:<?php echo esc_attr( $status_info['color'] ); ?>">
								<?php echo esc_html( $status_info['label'] ); ?>
							</span>
						</div>
						<p style="margin:0;font-size:0.8125rem;color:#6b7280">
							<?php if ( $price > 0 ) : ?>
								<span>&#8378;<?php echo esc_html( number_format( $price, 0 ) ); ?>/<?php esc_html_e( 'day', 'mhm-rentiva' ); ?></span>
							<?php endif; ?>
							<?php if ( $city ) : ?>
								<?php if ( $price > 0 ) : ?> &middot; <?php endif; ?>
								<span><?php echo esc_html( $city ); ?></span>
							<?php endif; ?>
						</p>
						<?php if ( $review_status === 'rejected' && $rejection_note ) : ?>
							<div style="margin-top:8px;padding:8px 12px;background:#fef2f2;border-left:3px solid #ef4444;border-radius:0 4px 4px 0;font-size:0.8125rem;color:#991b1b">
								<strong><?php esc_html_e( 'Rejection reason:', 'mhm-rentiva' ); ?></strong> <?php echo esc_html( $rejection_note ); ?>
							</div>
						<?php endif; ?>
					</div>

					<?php /* Actions */ ?>
					<div style="flex-shrink:0;display:flex;gap:8px;align-items:center">
						<?php if ( $review_status === 'approved' && $vehicle->post_status === 'publish' ) : ?>
							<a href="<?php echo esc_url( get_permalink( $vehicle->ID ) ); ?>" target="_blank" style="font-size:0.8125rem;color:#2271b1;text-decoration:none">
								<?php esc_html_e( 'View', 'mhm-rentiva' ); ?> &rarr;
							</a>
						<?php endif; ?>
					</div>

				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

</div>

<script>
(function () {
	var btn   = document.getElementById('mhm-toggle-add-vehicle');
	var panel = document.getElementById('mhm-add-vehicle-panel');
	var close = document.getElementById('mhm-close-add-vehicle');

	if (!btn || !panel) return;

	btn.addEventListener('click', function () {
		var open = panel.style.display !== 'none';
		panel.style.display = open ? 'none' : 'block';
		btn.setAttribute('aria-expanded', open ? 'false' : 'true');
		if (!open) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});

	if (close) {
		close.addEventListener('click', function () {
			panel.style.display = 'none';
			btn.setAttribute('aria-expanded', 'false');
		});
	}

	// Auto-open if URL has ?add_vehicle=1
	if (window.location.search.indexOf('add_vehicle=1') !== -1) {
		panel.style.display = 'block';
		btn.setAttribute('aria-expanded', 'true');
	}

	// After successful vehicle submit, hide the form and reload the listing
	document.addEventListener('mhm_vehicle_submitted', function () {
		panel.style.display = 'none';
		btn.setAttribute('aria-expanded', 'false');
		setTimeout(function () { window.location.reload(); }, 1500);
	});
}());
</script>
