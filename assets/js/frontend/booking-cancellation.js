/**
 * Booking Cancellation JavaScript
 * 
 * Handles booking cancellation in My Account > Booking Detail
 */
jQuery(document).ready(function ($) {

    // Check if configuration exists
    if (typeof mhmRentivaCancellation === 'undefined') {
        return;
    }

    var modal = $('#cancel-booking-modal');
    var cancelBtn = $('#cancel-booking-btn');
    var closeBtn = $('#close-modal, #cancel-modal-close');
    var confirmBtn = $('#confirm-cancellation');
    var statusMsg = $('#cancel-status-message');

    // Open modal
    cancelBtn.on('click', function () {
        modal.fadeIn(200);
    });

    // Close modal
    closeBtn.on('click', function () {
        modal.fadeOut(200);
        statusMsg.hide();
        $('#cancellation-reason').val('');
    });

    // Close modal on outside click
    $(window).on('click', function (event) {
        if (event.target.id === 'cancel-booking-modal') {
            modal.fadeOut(200);
            statusMsg.hide();
            $('#cancellation-reason').val('');
        }
    });

    // Confirm cancellation
    confirmBtn.on('click', function () {
        var bookingId = cancelBtn.data('booking-id');
        var reason = $('#cancellation-reason').val();
        var originalText = confirmBtn.text();

        // Disable button
        confirmBtn.prop('disabled', true).text(mhmRentivaCancellation.i18n.cancelling);
        statusMsg.hide();

        // Send AJAX request
        $.ajax({
            url: mhmRentivaCancellation.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mhm_cancel_booking',
                booking_id: bookingId,
                reason: reason,
                nonce: mhmRentivaCancellation.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Show success message
                    statusMsg.removeClass('rv-error').addClass('rv-success')
                        .text('✅ ' + response.data.message)
                        .slideDown();

                    // Reload page after 2 seconds
                    setTimeout(function () {
                        location.reload();
                    }, 2000);
                } else {
                    // Show error message
                    statusMsg.removeClass('rv-success').addClass('rv-error')
                        .text('❌ ' + response.data.message)
                        .slideDown();

                    // Re-enable button
                    confirmBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function (xhr, status, error) {
                statusMsg.removeClass('rv-success').addClass('rv-error')
                    .text('❌ ' + mhmRentivaCancellation.i18n.error)
                    .slideDown();

                confirmBtn.prop('disabled', false).text(originalText);
            }
        });
    });
});
