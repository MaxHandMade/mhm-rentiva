/**
 * Booking list calendar popup.
 *
 * Extracted verbatim from the inline block previously echoed by
 * BookingColumns::add_booking_calendar(). Translatable labels are provided
 * through the localized `mhmBookingPopup.i18n` object (wp_localize_script).
 */
jQuery(document).ready(function($) {
	var i18n = (window.mhmBookingPopup && window.mhmBookingPopup.i18n) || {};

	var statusClasses = {
		'pending'     : 'status-badge--pending',
		'confirmed'   : 'status-badge--confirmed',
		'in_progress' : 'status-badge--in-progress',
		'completed'   : 'status-badge--completed',
		'cancelled'   : 'status-badge--cancelled',
		'refunded'    : 'status-badge--refunded',
		'no_show'     : 'status-badge--cancelled',
		'draft'       : 'status-badge--draft'
	};

	$('[data-booking-popup]').on('click', function(e) {
		e.preventDefault();

		var $this       = $(this);
		var bookingsRaw = $this.data('bookings');
		var bookings    = [];

		if (bookingsRaw) {
			try {
				bookings = typeof bookingsRaw === 'string' ? JSON.parse(bookingsRaw) : bookingsRaw;
			} catch (err) {
				console.error('Booking popup JSON parse error:', err);
			}
		}

		if ( ! bookings.length ) {
			bookings = [{
				booking_id    : $this.data('booking-id'),
				customer_name : $this.data('customer-name'),
				customer_email: $this.data('customer-email'),
				customer_phone: $this.data('customer-phone'),
				vehicle_title : $this.data('vehicle-title'),
				vehicle_plate : $this.data('vehicle-plate'),
				total_price   : $this.data('total-price'),
				status        : $this.data('status'),
				status_label  : $this.data('status-label') || $this.data('status'),
				start_date    : $this.data('start-date'),
				end_date      : $this.data('end-date'),
				created_date  : $this.data('created-date')
			}];
		}

		if (bookings.length === 1) {
			showSingleBooking(bookings[0]);
		} else {
			showMultiBooking(bookings);
		}

		$('#mhm-booking-popup').fadeIn(250);
	});

	function showSingleBooking(b) {
		$('#popup-customer-name').text(b.customer_name || '—');
		$('#popup-customer-email').text(b.customer_email || '—');
		$('#popup-customer-phone').text(b.customer_phone || '—');
		$('#popup-vehicle-title').text(b.vehicle_title || '—');
		$('#popup-vehicle-plate').text(b.vehicle_plate || '—');
		$('#popup-start-date').text(b.start_date || '—');
		$('#popup-end-date').text(b.end_date || '—');
		$('#popup-total-price').text(b.total_price ? b.total_price : '—');
		$('#popup-created-date').text(b.created_date || '—');
		$('.mhm-popup-booking-id').text(b.display_id ? '#' + b.display_id : (b.booking_id ? '#' + b.booking_id : ''));

		var $badge = $('#popup-status-badge');
		$badge.text(b.status_label || b.status || '—');
		$badge.attr('class', 'mhm-popup-status-badge ' + (statusClasses[b.status] || ''));

		$('#popup-edit-booking').attr('href', b.booking_id ? 'post.php?post=' + parseInt(b.booking_id, 10) + '&action=edit' : '#');

		$('#popup-single-view').show();
		$('#popup-multi-view').hide();
		$('#popup-single-footer').show();
	}

	function showMultiBooking(bookings) {
		$('#popup-multi-count').text(bookings.length + ' ' + (i18n.bookingsOnThisDay || ''));

		var html = '';
		bookings.forEach(function(b) {
			var safeCustomerName = $('<span>').text(b.customer_name || '—').html();
			var safeStartDate    = $('<span>').text(b.start_date || '—').html();
			var safeEndDate      = $('<span>').text(b.end_date || '—').html();
			var safeTotalPrice   = $('<span>').text(b.total_price || '—').html();
			var safeStatusLabel  = $('<span>').text(b.status_label || b.status || '—').html();
			var bookingId        = parseInt(b.booking_id, 10) || 0;

			html += '<div class="mhm-popup-booking-card">';
			html += '<div class="mhm-popup-booking-card-header">';
			html += '<span class="mhm-popup-status-badge ' + (statusClasses[b.status] || '') + '">' + safeStatusLabel + '</span>';
			var displayId = parseInt(b.display_id, 10) || bookingId;
			html += '<span class="mhm-popup-booking-card-id">' + (displayId ? '#' + displayId : '') + '</span>';
			html += '</div>';
			html += '<div class="booking-info-grid">';
			html += '<div class="info-item"><label>' + (i18n.customer || '') + '</label><span>' + safeCustomerName + '</span></div>';
			html += '<div class="info-item"><label>' + (i18n.pickup || '') + '</label><span>' + safeStartDate + '</span></div>';
			html += '<div class="info-item"><label>' + (i18n.returnLabel || '') + '</label><span>' + safeEndDate + '</span></div>';
			html += '<div class="info-item"><label>' + (i18n.total || '') + '</label><span>' + safeTotalPrice + '</span></div>';
			html += '</div>';
			if (bookingId) {
				html += '<div class="mhm-popup-booking-card-footer">';
				html += '<a href="post.php?post=' + bookingId + '&action=edit" class="button button-secondary">' + (i18n.editBooking || '') + '</a>';
				html += '</div>';
			}
			html += '</div>';
		});
		$('#popup-bookings-list').html(html);

		$('#popup-single-view').hide();
		$('#popup-multi-view').show();
		$('#popup-single-footer').hide();
	}

	$('.mhm-popup-close, .mhm-popup-overlay').on('click', function() {
		$('#mhm-booking-popup').fadeOut(200);
	});

	$(document).on('keydown', function(e) {
		if (e.key === 'Escape') {
			$('#mhm-booking-popup').fadeOut(200);
		}
	});
});
