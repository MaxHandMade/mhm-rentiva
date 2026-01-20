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
                var errorMessage = "Teslim Alma ile Bırakma konumları aynı olamaz!"; // Fallback
                if (typeof mhm_transfer_vars !== 'undefined' && mhm_transfer_vars.i18n && mhm_transfer_vars.i18n.same_location_error) {
                    errorMessage = mhm_transfer_vars.i18n.same_location_error;
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

    // 2. ON USER CHANGE: Alert loudly
    $origin.on('change', function () { checkConflict(this, false); });
    $destination.on('change', function () { checkConflict(this, false); });

    // --- AJAX Form Submit ---
    $('#mhm-transfer-search-form').on('submit', function (e) {
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
        var searchingText = 'Araçlar aranıyor...';
        if (typeof mhm_transfer_vars !== 'undefined' && mhm_transfer_vars.i18n && mhm_transfer_vars.i18n.searching_text) {
            searchingText = mhm_transfer_vars.i18n.searching_text;
        }
        $('#mhm-transfer-results').html('<div class="mhm-loading"><span class="dashicons dashicons-update"></span> ' + searchingText + '</div>');

        $.ajax({
            url: mhm_transfer_vars.ajax_url,
            type: 'POST',
            data: formData + '&action=mhm_transfer_search&security=' + mhm_transfer_vars.nonce,
            success: function (response) {
                if (response.success) {
                    $('#mhm-transfer-results').html(response.data.html);
                } else {
                    $('#mhm-transfer-results').html('<div class="mhm-error">' + response.data.message + '</div>');
                }
            },
            error: function () {
                $('#mhm-transfer-results').html('<div class="mhm-error">' + (mhm_transfer_vars.i18n.error_text || 'Error occurred.') + '</div>');
            }
        });
    });

    // Add to Cart
    $(document).on('click', '.mhm-transfer-book-btn', function (e) {
        e.preventDefault();
        var btn = $(this);
        var vehicleId = btn.data('vehicle-id');
        var transferData = btn.data('transfer-meta');

        btn.prop('disabled', true).text('İşleniyor...');

        $.ajax({
            url: mhm_transfer_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'mhm_transfer_add_to_cart',
                vehicle_id: vehicleId,
                transfer_data: transferData,
                security: mhm_transfer_vars.nonce
            },
            success: function (response) {
                if (response.success) {
                    window.location.href = mhm_transfer_vars.cart_url;
                } else {
                    // Butonu eski haline getir
                    btn.removeClass('loading').text(btn.data('original-text') || 'Rezervasyon Yap');

                    // Hatayı güvenli oku
                    var msg = "Bir hata oluştu.";
                    if (response.data && response.data.message) {
                        msg = response.data.message;
                    }
                    alert(msg);
                }
            },
            error: function () {
                btn.removeClass('loading').text('Hata');
                alert("Sunucu ile iletişim hatası!");
            }
        });
    });
});
