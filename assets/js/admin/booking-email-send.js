/**
 * Booking Email Send Handler
 * Handles AJAX email sending from booking edit page
 */
(function ($) {
	'use strict';

	// Function to handle form submission
	function handleEmailFormSubmit(e) {
		e.preventDefault();
		e.stopPropagation();

		var container = $(this);
		var bookingId = container.data('booking-id');

		if (!bookingId) {
			bookingId = $('#post_ID').val();
		}

		if (!bookingId) {
			alert('Booking ID not found.');
			return;
		}

		// Get form values manually
		var emailType = container.find('#email_type').val() || '';
		var emailSubject = container.find('#email_subject').val() || '';
		var emailMessage = container.find('#email_message').val() || '';
		var emailNonce = container.find('input[name="mhm_rentiva_email_nonce"]').val() || '';

		if (!emailNonce) {
			alert('Security check failed. Please refresh the page.');
			return;
		}

		var submitBtn = container.find('.mhm-email-send-btn');
		var originalText = submitBtn.text();
		var sendingText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.sending)
			? window.mhmBookingEmail.strings.sending
			: 'Sending...';

		submitBtn.prop('disabled', true).text(sendingText);

		// Prepare AJAX data
		var ajaxData = {
			action: 'mhm_rentiva_send_customer_email',
			booking_id: bookingId,
			email_type: emailType,
			email_subject: emailSubject,
			email_message: emailMessage,
			mhm_rentiva_email_nonce: emailNonce
		};

		// Get AJAX URL
		var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl :
			(window.mhmBookingEmail && window.mhmBookingEmail.ajaxUrl ? window.mhmBookingEmail.ajaxUrl :
				(window.mhm_rentiva_config && window.mhm_rentiva_config.ajax_url ? window.mhm_rentiva_config.ajax_url :
					'/wp-admin/admin-ajax.php'));

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: ajaxData,
			dataType: 'json',
			success: function (response) {
				var successText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.success)
					? window.mhmBookingEmail.strings.success
					: 'Email sent successfully!';

				if (response && response.success) {
					alert(successText);
					// Clear fields if possible (not a real form but we can clear inputs)
					container.find('#email_subject, #email_message').val('');
				} else {
					var errorMsg = (response && response.data) ? response.data : 'Unknown error';
					alert('Error: ' + errorMsg);
				}
				submitBtn.prop('disabled', false).text(originalText);
			},
			error: function (xhr, status, error) {
				alert('An error occurred: ' + error);
				submitBtn.prop('disabled', false).text(originalText);
			}
		});
	}

	// Function to load email template
	function loadEmailTemplate(emailType, bookingId) {
		if (!emailType || !bookingId) {
			return;
		}

		var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl :
			(window.mhmBookingEmail && window.mhmBookingEmail.ajaxUrl ? window.mhmBookingEmail.ajaxUrl :
				'/wp-admin/admin-ajax.php');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'mhm_rentiva_get_email_template',
				booking_id: bookingId,
				email_type: emailType
			},
			dataType: 'json',
			success: function (response) {
				if (response && response.success && response.data) {
					$('#email_subject').val(response.data.subject || '');
					$('#email_message').val(response.data.message || '');
				}
			}
		});
	}

	// Initialize
	$(document).ready(function () {
		// Email Send Click Handler
		$(document).off('click', '.mhm-email-send-btn').on('click', '.mhm-email-send-btn', function (e) {
			var $container = $(this).closest('.mhm-customer-email-box');
			if ($container.length === 0) {
				$container = $(this).closest('.mhm-email-form-wrap');
			}
			handleEmailFormSubmit.call($container[0], e);
		});

		// History Note Click Handler
		$(document).off('click', '.mhm-add-history-btn').on('click', '.mhm-add-history-btn', function (e) {
			e.preventDefault();
			var $container = $(this).closest('.mhm-add-history-note');
			var bookingId = $container.find('form').data('booking-id') || $('#post_ID').val();
			var noteContent = $container.find('#note_content').val();
			var noteType = $container.find('#note_type').val();
			var nonce = $container.find('input[name="mhm_rentiva_history_nonce"]').val();

			if (!noteContent) {
				alert('Please enter a note.');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_add_booking_history_note',
					booking_id: bookingId,
					note_content: noteContent,
					note_type: noteType,
					mhm_rentiva_history_nonce: nonce
				},
				success: function (response) {
					if (response.success) {
						alert('Note added successfully!');
						location.reload();
					} else {
						alert('Error: ' + response.data);
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					alert('An error occurred!');
					$btn.prop('disabled', false);
				}
			});
		});

		// Template Loader change handler
		$(document).off('change', '#email_type').on('change', '#email_type', function () {
			var emailType = $(this).val();
			var bookingId = $('#post_ID').val();
			if (emailType && bookingId) {
				loadEmailTemplate(emailType, bookingId);
			}
		});
	});

})(jQuery);
