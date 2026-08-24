<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared `#mhm-booking-popup` markup.
 *
 * Faz 2 Task 4: extracted from the near-duplicate copies previously echoed
 * inline by VehicleColumns::add_monthly_calendar() (retired) and
 * BookingColumns::add_booking_calendar() (retired in Task 5). The one
 * remaining consumer, FleetOccupancyMatrix::render_markup(), `include`s
 * this file verbatim rather than keeping its own copy — both the Vehicles
 * and Bookings Calendar faces paint through that one renderer now.
 *
 * DOM ids are the real contract, and ONE script binds to them on both
 * screens: assets/js/admin/booking-popup.js. (Vehicles used to load its own
 * vehicle-calendar-popup.js, which never read the `data-bookings` payload
 * and so could not show #popup-multi-view at all — a day with several
 * bookings listed one of them there and all of them on Bookings. That copy
 * is gone; only the Vehicles-only click-to-block behaviour survived it, in
 * assets/js/admin/vehicle-blocked-date-toggle.js.)
 */
?>
<div id="mhm-booking-popup" class="mhm-popup-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="mhm-popup-title">
	<div class="mhm-popup-overlay"></div>
	<div class="mhm-popup-content">
		<div class="mhm-popup-header">
			<div class="mhm-popup-header-left">
				<span class="dashicons dashicons-calendar-alt mhm-popup-header-icon"></span>
				<div>
					<h3 id="mhm-popup-title"><?php esc_html_e( 'Booking Details', 'mhm-rentiva' ); ?></h3>
					<span class="mhm-popup-booking-id"></span>
				</div>
			</div>
			<div class="mhm-popup-header-right">
				<span id="popup-status-badge" class="mhm-popup-status-badge"></span>
				<button class="mhm-popup-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'mhm-rentiva' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		</div>

		<div class="mhm-popup-body">
			<!-- Single booking view (default; shown when the cell holds exactly one booking) -->
			<div id="popup-single-view">
				<div class="mhm-popup-section">
					<div class="mhm-popup-section-title">
						<span class="dashicons dashicons-admin-users"></span>
						<?php esc_html_e( 'Customer', 'mhm-rentiva' ); ?>
					</div>
					<div class="booking-info-grid">
						<div class="info-item">
							<label><?php esc_html_e( 'Name', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-name">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Email', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-email">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Phone', 'mhm-rentiva' ); ?></label>
							<span id="popup-customer-phone">—</span>
						</div>
					</div>
				</div>

				<div class="mhm-popup-section">
					<div class="mhm-popup-section-title">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php esc_html_e( 'Vehicle & Dates', 'mhm-rentiva' ); ?>
					</div>
					<div class="booking-info-grid booking-info-grid--dates">
						<div class="info-item">
							<label><?php esc_html_e( 'Vehicle', 'mhm-rentiva' ); ?></label>
							<span id="popup-vehicle-title">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Plate', 'mhm-rentiva' ); ?></label>
							<span id="popup-vehicle-plate">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Pickup', 'mhm-rentiva' ); ?></label>
							<span id="popup-start-date" class="info-date">—</span>
							<span id="popup-start-time" class="info-time">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Return', 'mhm-rentiva' ); ?></label>
							<span id="popup-end-date" class="info-date">—</span>
							<span id="popup-end-time" class="info-time">—</span>
						</div>
					</div>
				</div>

				<div class="mhm-popup-section mhm-popup-section--last">
					<div class="mhm-popup-section-title">
						<span class="dashicons dashicons-tickets-alt"></span>
						<?php esc_html_e( 'Booking Info', 'mhm-rentiva' ); ?>
					</div>
					<div class="booking-info-grid">
						<div class="info-item">
							<label><?php esc_html_e( 'Total Price', 'mhm-rentiva' ); ?></label>
							<span id="popup-total-price" class="info-price">—</span>
						</div>
						<div class="info-item">
							<label><?php esc_html_e( 'Created', 'mhm-rentiva' ); ?></label>
							<span id="popup-created-date">—</span>
						</div>
					</div>
				</div>
			</div>

			<!-- Multiple bookings view (shown on any day whose cell holds more than one) -->
			<div id="popup-multi-view" style="display: none;">
				<div class="mhm-popup-multi-header">
					<span class="dashicons dashicons-calendar-alt"></span>
					<span id="popup-multi-count"></span>
				</div>
				<div id="popup-bookings-list"></div>
			</div>
		</div>

		<div class="mhm-popup-footer" id="popup-single-footer">
			<a id="popup-edit-booking" href="#" class="button button-primary mhm-popup-edit-btn">
				<span class="dashicons dashicons-edit"></span>
				<?php esc_html_e( 'Edit Booking', 'mhm-rentiva' ); ?>
			</a>
		</div>
	</div>
</div>
