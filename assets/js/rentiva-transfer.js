jQuery(document).ready(function ($) {
    'use strict';

    // 🛑 CENTRALIZED CONFLICT CHECK
    function checkConflict($form, triggerName, isSilent) {
        var $origin = $form.find('[name="origin_id"]');
        var $destination = $form.find('[name="destination_id"]');
        var originVal = $origin.val();
        var destVal = $destination.val();

        if (originVal && destVal && originVal === destVal) {
            // Reset the other one
            var $targetToClear = (triggerName === 'origin_id') ? $destination : $origin;
            $targetToClear.val('').trigger('change');

            if (!isSilent) {
                var errorMessage = "Pick-up and drop-off locations cannot be the same!";
                if (typeof rentiva_transfer_vars !== 'undefined' && rentiva_transfer_vars.i18n && rentiva_transfer_vars.i18n.same_location_error) {
                    errorMessage = rentiva_transfer_vars.i18n.same_location_error;
                }

                if (typeof MHMRentivaToast !== 'undefined') {
                    MHMRentivaToast.show(errorMessage, { type: 'error' });
                } else {
                    alert(errorMessage);
                }
            }
        }
    }

    // 1. DYNAMIC ROUTE FILTERING & CONFLICT MGT
    $(document).on('change', '.js-unified-transfer-form [name="origin_id"]', function () {
        var $select = $(this);
        var $form = $select.closest('.js-unified-transfer-form');
        var originId = $select.val();
        var $destination = $form.find('[name="destination_id"]');

        if (originId && typeof rentiva_transfer_vars !== 'undefined' && rentiva_transfer_vars.routes) {
            var validDestinations = [];
            $.each(rentiva_transfer_vars.routes, function (i, route) {
                if (route.origin_id == originId) validDestinations.push(route.destination_id);
            });

            var currentDest = $destination.val();
            $destination.find('option').each(function () {
                var optVal = $(this).attr('value');
                if (!optVal) return;
                if (validDestinations.includes(optVal)) {
                    $(this).prop('disabled', false).show();
                } else {
                    $(this).prop('disabled', true).hide();
                }
            });

            if (currentDest && !validDestinations.includes(currentDest)) {
                $destination.val('').trigger('change');
            }
        } else {
            $destination.find('option').prop('disabled', false).show();
        }

        checkConflict($form, 'origin_id', false);
    });

    $(document).on('change', '.js-unified-transfer-form [name="destination_id"]', function () {
        var $form = $(this).closest('.js-unified-transfer-form');
        checkConflict($form, 'destination_id', false);
    });

    // 2. AJAX Form Submit
    $(document).on('submit', '.js-unified-transfer-form', function (e) {
        var $form = $(this);
        var $results = $('#mhm-transfer-results');

        // Validation
        var originVal = $form.find('[name="origin_id"]').val();
        var destVal = $form.find('[name="destination_id"]').val();

        if (!originVal || !destVal) {
            e.preventDefault();
            var errorMsg = (typeof rentiva_transfer_vars !== 'undefined' && rentiva_transfer_vars.i18n.error_text) ? rentiva_transfer_vars.i18n.error_text : "Please select locations.";
            if (typeof MHMRentivaToast !== 'undefined') {
                MHMRentivaToast.show(errorMsg, { type: 'error' });
            } else {
                alert(errorMsg);
            }
            return;
        }

        if (originVal === destVal) {
            e.preventDefault();
            checkConflict($form, null, false);
            return;
        }

        // AJAX logic
        if ($results.length > 0) {
            e.preventDefault();
            var formData = $form.serialize();
            var searchingText = (typeof rentiva_transfer_vars !== 'undefined' && rentiva_transfer_vars.i18n.searching_text) ? rentiva_transfer_vars.i18n.searching_text : 'Searching...';

            $results.html('<div class="mhm-loading">' + (rentiva_transfer_vars.icons?.spinner || '⏳') + ' ' + searchingText + '</div>');

            $.ajax({
                url: rentiva_transfer_vars.ajax_url,
                type: 'POST',
                data: formData + '&action=rentiva_transfer_search&security=' + rentiva_transfer_vars.nonce,
                success: function (response) {
                    if (response.success) {
                        $results.html(response.data.html);
                    } else {
                        $results.html('<div class="mhm-error">' + response.data.message + '</div>');
                    }
                },
                error: function () {
                    $results.html('<div class="mhm-error">' + (rentiva_transfer_vars.i18n.error_text || 'Error occurred.') + '</div>');
                }
            });
        }
    });

    // Add to Cart
    $(document).on('click', '.mhm-transfer-book-btn, .js-mhm-transfer-book', function (e) {
        e.preventDefault();
        var btn = $(this);
        var vehicleId = btn.data('vehicle-id');
        var transferData = btn.data('transfer-meta');

        // Fallback for individual data attributes (from static results template)
        if (!transferData) {
            transferData = {
                origin_id: btn.data('origin-id'),
                destination_id: btn.data('destination-id'),
                date: btn.data('date'),
                time: btn.data('time'),
                price: btn.data('price'),
                adults: btn.data('adults') || 1,
                children: btn.data('children') || 0,
                luggage_big: btn.data('luggage-big') || 0,
                luggage_small: btn.data('luggage-small') || 0
            };
        }

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
                    window.location.href = response.data.redirect_url || rentiva_transfer_vars.cart_url;
                } else {
                    // Reset button state
                    var bookNowText = (typeof rentiva_transfer_vars.i18n.book_now_text !== 'undefined') ? rentiva_transfer_vars.i18n.book_now_text : 'Book Now';
                    btn.prop('disabled', false).text(btn.data('original-text') || bookNowText);

                    // Safely read error message
                    var msg = (typeof rentiva_transfer_vars.i18n.default_error !== 'undefined') ? rentiva_transfer_vars.i18n.default_error : "An error occurred.";
                    if (response.data && response.data.message) {
                        msg = response.data.message;
                    }
                    alert(msg);
                }
            },
            error: function () {
                btn.prop('disabled', false).text('Error');
                var serverError = (typeof rentiva_transfer_vars.i18n.server_error !== 'undefined') ? rentiva_transfer_vars.i18n.server_error : "Server communication error!";
                alert(serverError);
            }
        });
    });
});
