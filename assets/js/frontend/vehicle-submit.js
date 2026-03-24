/* global mhmVehicleSubmit, jQuery */
(function ($) {
    'use strict';

    $(document).on('submit', '#mhm-vehicle-submit-form', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#mhm-vehicle-submit-btn');
        var $spinner = $('#mhm-vehicle-submit-spinner');
        var $msg     = $('#mhm-vehicle-submit-msg');

        $btn.prop('disabled', true);
        $spinner.show();
        $msg.hide().removeClass('mhm-vendor-notice--success mhm-vendor-notice--error');

        var formData = new FormData(this);

        $.ajax({
            url: mhmVehicleSubmit.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    $form.fadeOut(300);
                    $msg.addClass('mhm-vendor-notice--success')
                        .text(mhmVehicleSubmit.successMsg)
                        .show();
                    // Notify the dashboard listings panel to refresh.
                    document.dispatchEvent(new Event('mhm_vehicle_submitted'));
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : mhmVehicleSubmit.errorMsg;
                    $msg.addClass('mhm-vendor-notice--error').text(msg).show();
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            },
            error: function () {
                $msg.addClass('mhm-vendor-notice--error').text(mhmVehicleSubmit.errorMsg).show();
                $btn.prop('disabled', false);
                $spinner.hide();
            }
        });
    });
}(jQuery));
