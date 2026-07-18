/**
 * Vehicle list monthly calendar: booking popup + quick block/unblock day toggle.
 *
 * Extracted verbatim from the inline block previously echoed by
 * VehicleColumns::add_monthly_calendar(). The per-request nonce and the
 * translatable strings are provided through mhmVehicleCalendar (wp_localize_script).
 */
jQuery(document).ready(function($) {
	var config         = window.mhmVehicleCalendar || {};
	var i18n           = config.i18n || {};
	var mhmToggleNonce = config.nonce || '';

	var statusClasses = {
		'pending'     : 'status-badge--pending',
		'confirmed'   : 'status-badge--confirmed',
		'in-progress' : 'status-badge--in-progress',
		'completed'   : 'status-badge--completed',
		'cancelled'   : 'status-badge--cancelled'
	};

	// Open popup
	$('[data-booking-popup]').on('click', function(e) {
		e.preventDefault();

		var $this       = $(this);
		var bookingId   = $this.data('booking-id');
		var status      = $this.data('status');
		var statusLabel = $this.data('status-label') || status;
		var startDate   = $this.data('start-date');
		var endDate     = $this.data('end-date');
		var startTime   = $this.data('start-time');
		var endTime     = $this.data('end-time');
		var createdDate = $this.data('created-date');
		var totalPrice  = $this.data('total-price');

		// Customer
		$('#popup-customer-name').text($this.data('customer-name') || '—');
		$('#popup-customer-email').text($this.data('customer-email') || '—');
		$('#popup-customer-phone').text($this.data('customer-phone') || '—');

		// Dates & times
		$('#popup-start-date').text(startDate || '—');
		$('#popup-start-time').text(startTime || '');
		$('#popup-end-date').text(endDate || '—');
		$('#popup-end-time').text(endTime || '');

		// Booking info
		$('#popup-total-price').text(totalPrice ? totalPrice + ' €' : '—');
		$('#popup-created-date').text(createdDate || '—');

		// Booking ID sub-label
		$('.mhm-popup-booking-id').text(bookingId ? '#' + bookingId : '');

		// Status badge
		var $badge = $('#popup-status-badge');
		$badge.text(statusLabel || '—');
		$badge.attr('class', 'mhm-popup-status-badge ' + (statusClasses[status] || ''));

		// Edit button
		$('#popup-edit-booking').off('click').on('click', function(e) {
			e.preventDefault();
			if (bookingId) {
				window.location.href = 'post.php?post=' + bookingId + '&action=edit';
			}
		});

		$('#mhm-booking-popup').fadeIn(250);
	});

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
			action: 'mhm_toggle_blocked_date',
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

	// Close popup
	$('.mhm-popup-close, .mhm-popup-overlay').on('click', function() {
		$('#mhm-booking-popup').fadeOut(200);
	});

	$(document).on('keydown', function(e) {
		if (e.keyCode === 27) {
			$('#mhm-booking-popup').fadeOut(200);
		}
	});
});
