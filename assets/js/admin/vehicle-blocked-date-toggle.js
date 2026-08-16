/**
 * Vehicles list Calendar face: quick block/unblock day toggle.
 *
 * Split out of the retired vehicle-calendar-popup.js. That file carried a
 * SECOND, weaker copy of the booking popup — it read only the winner-cell's
 * flat data-* attributes, never the `data-bookings` JSON, so a day holding
 * several bookings showed exactly one of them on this screen while the
 * Bookings screen (booking-popup.js) listed them all. The popup is now
 * booking-popup.js on BOTH screens; what stays here is the part that is
 * genuinely Vehicles-only, because it is the only face rendered with
 * `enable_block_toggle => true`.
 *
 * The endpoint, its action name and its nonce are unchanged
 * (`mhmrentiva_toggle_blocked_date`, localized as `mhmVehicleCalendar`).
 */
jQuery(document).ready(function($) {
	var config         = window.mhmVehicleCalendar || {};
	var i18n           = config.i18n || {};
	var mhmToggleNonce = config.nonce || '';

	// Quick block/unblock: explicit confirm, then repaint from the server's
	// confirmed result. The native confirm gives instant feedback that the
	// click registered (the admin-ajax round-trip itself is slow because the
	// whole plugin bootstraps on every request), and repainting from the
	// server response — not optimistically — keeps the cell and the DB in sync.
	var mhmBlockedTitle = i18n.blockedTitle || '';
	var mhmAvailTitle   = i18n.availTitle || '';
	var mhmToggleError  = i18n.toggleError || '';
	var mhmConfirmClose = i18n.confirmClose || '';
	var mhmConfirmOpen  = i18n.confirmOpen || '';

	$('.calendar-table').on('click', '.day-cell.available, .day-cell.blocked-day', function() {
		var $cell = $(this);
		var vehicleId = $cell.data('vehicle-id');
		var date      = $cell.data('date');
		if (!vehicleId || !date) { return; }

		var isBlocked = $cell.hasClass('blocked-day');
		if (!window.confirm(isBlocked ? mhmConfirmOpen : mhmConfirmClose)) { return; }

		$.post(ajaxurl, {
			action: 'mhmrentiva_toggle_blocked_date',
			vehicle_id: vehicleId,
			date: date,
			nonce: mhmToggleNonce
		}).done(function(resp) {
			if (resp && resp.success && resp.data) {
				if (resp.data.blocked) {
					$cell.removeClass('available').addClass('blocked-day').attr('title', mhmBlockedTitle);
				} else {
					$cell.removeClass('blocked-day').addClass('available').attr('title', mhmAvailTitle);
				}
			} else {
				window.alert((resp && typeof resp.data === 'string') ? resp.data : mhmToggleError);
			}
		}).fail(function() {
			window.alert(mhmToggleError);
		});
	});
});
