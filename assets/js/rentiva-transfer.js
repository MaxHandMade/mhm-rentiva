jQuery(document).ready(function ($) {
    var $origin = $('#mhm-origin');
    var $destination = $('#mhm-destination');

    // 🛑 STRICT CHECK FUNCTION
    function checkConflict(triggerElement, isSilent) {
        var originVal = $origin.val();
        var destVal = $destination.val();

        // If both are filled and equal
        if (originVal && destVal && originVal === destVal) {

            // If trigger uses 'origin', clear 'destination', otherwise clear other.
            // Default to clearing destination.
            var targetToClear = (triggerElement === $origin[0]) ? $destination : $origin;

            // Reset Target
            targetToClear.val('').trigger('change.select2'); // Select2 support

            // Only alert if NOT silent (User interaction)
            if (!isSilent) {
                // SAFE MESSAGE READING
                var errorMessage = "Pick-up and drop-off locations cannot be the same!"; // Fallback
                if (typeof rentiva_transfer_vars !== 'undefined' && rentiva_transfer_vars.i18n && rentiva_transfer_vars.i18n.same_location_error) {
                    errorMessage = rentiva_transfer_vars.i18n.same_location_error;
                }
                alert(errorMessage);
            }

            // Force update for UI libraries
            targetToClear.trigger('select2:select').trigger('update');
        }
    }

    // 1. ON PAGE LOAD: Check silently and fix
    // (If same default selection, clear one without alert)
    setTimeout(function () {
        checkConflict(null, true);
    }, 500); // Wait 500ms for UI libs

    // 2. ON USER CHANGE: Alert loudly & Filter
    $origin.on('change', function () {
        var originId = $(this).val();

        if (originId) {
            // FILTER DROPDOWN: Show only valid destinations
            if (typeof rentiva_transfer_vars !== 'undefined' && rentiva_transfer_vars.routes) {
                var validDestinations = [];

                // Find valid destinations for this origin
                $.each(rentiva_transfer_vars.routes, function (index, route) {
                    if (route.origin_id == originId) {
                        validDestinations.push(route.destination_id);
                    }
                });

                // Reset and filter Dropoff
                var currentDest = $destination.val();
                $destination.find('option').each(function () {
                    var optVal = $(this).attr('value');
                    if (optVal === "") return; // Skip placeholder

                    if (validDestinations.includes(optVal)) {
                        $(this).prop('disabled', false).show(); // Native show
                    } else {
                        $(this).prop('disabled', true).hide(); // Native hide
                    }
                });

                // If current selection is invalid, reset it
                if (currentDest && !validDestinations.includes(currentDest)) {
                    $destination.val('').trigger('change.select2');
                }
            }
        } else {
            // Reset: Enable all
            $destination.find('option').prop('disabled', false).show();
        }

        // Trigger UI updates
        $destination.trigger('update'); // For some plugins

        // Run standard conflict check
        checkConflict(this, false);
    });

    $destination.on('change', function () { checkConflict(this, false); });

    // --- AJAX Form Submit ---
    $('.js-unified-transfer-form').on('submit', function (e) {
        // Final check (on Submit)
        var originVal = $origin.val();
        var destVal = $destination.val();

        if (originVal === destVal) {
            e.preventDefault();
            checkConflict(null, false); // Alert
            return;
        }

        e.preventDefault();
        var formData = $(this).serialize();
        var searchingText = 'Searching...';
        if (typeof rentiva_transfer_vars !== 'undefined' && rentiva_transfer_vars.i18n && rentiva_transfer_vars.i18n.searching_text) {
            searchingText = rentiva_transfer_vars.i18n.searching_text;
        }
        $('#mhm-transfer-results').html('<div class="mhm-loading">' + (rentiva_transfer_vars.icons?.spinner || '') + ' ' + searchingText + '</div>');

        $.ajax({
            url: rentiva_transfer_vars.ajax_url,
            type: 'POST',
            data: formData + '&action=rentiva_transfer_search&security=' + rentiva_transfer_vars.nonce,
            success: function (response) {
                if (response.success) {
                    $('#mhm-transfer-results').html(response.data.html);
                } else {
                    $('#mhm-transfer-results').html('<div class="mhm-error">' + response.data.message + '</div>');
                }
            },
            error: function () {
                $('#mhm-transfer-results').html('<div class="mhm-error">' + (rentiva_transfer_vars.i18n.error_text || 'Error occurred.') + '</div>');
            }
        });
    });

    // Add to Cart
    $(document).on('click', '.mhm-transfer-book-btn', function (e) {
        e.preventDefault();
        var btn = $(this);
        var vehicleId = btn.data('vehicle-id');
        var transferData = btn.data('transfer-meta');

        var processingText = (typeof rentiva_transfer_vars.i18n.processing_text !== 'undefined') ? rentiva_transfer_vars.i18n.processing_text : 'Processing...';
        btn.prop('disabled', true).text(processingText);

        $.ajax({
            url: rentiva_transfer_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'rentiva_transfer_add_to_cart',
                vehicle_id: vehicleId,
                transfer_data: transferData,
                security: rentiva_transfer_vars.nonce
            },
            success: function (response) {
                if (response.success) {
                    window.location.href = rentiva_transfer_vars.cart_url;
                } else {
                    // Reset button state
                    var bookNowText = (typeof rentiva_transfer_vars.i18n.book_now_text !== 'undefined') ? rentiva_transfer_vars.i18n.book_now_text : 'Book Now';
                    btn.removeClass('loading').text(btn.data('original-text') || bookNowText);

                    // Safely read error message
                    var msg = (typeof rentiva_transfer_vars.i18n.default_error !== 'undefined') ? rentiva_transfer_vars.i18n.default_error : "An error occurred.";
                    if (response.data && response.data.message) {
                        msg = response.data.message;
                    }
                    alert(msg);
                }
            },
            error: function () {
                btn.removeClass('loading').text('Error');
                var serverError = (typeof rentiva_transfer_vars.i18n.server_error !== 'undefined') ? rentiva_transfer_vars.i18n.server_error : "Server communication error!";
                alert(serverError);
            }
        });
    });
});
