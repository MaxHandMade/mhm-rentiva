/* global mhmVendorApply, jQuery */
(function ($) {
    'use strict';

    $(document).on('submit', '#mhm-vendor-apply-form', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#mhm-vendor-apply-submit');
        var $spinner = $('#mhm-vendor-apply-spinner');
        var $msg     = $('#mhm-vendor-apply-msg');

        $btn.prop('disabled', true);
        $spinner.show();
        $msg.hide().removeClass('mhm-vendor-notice--success mhm-vendor-notice--error');

        var formData = new FormData(this);

        $.ajax({
            url: mhmVendorApply.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (typeof response !== 'object' || response === null) {
                    $btn.prop('disabled', false);
                    $spinner.hide();
                    $msg.addClass('mhm-vendor-notice--error')
                        .text(
                            (typeof mhmVendorApply !== 'undefined' && mhmVendorApply.i18n && mhmVendorApply.i18n.sessionExpired)
                                ? mhmVendorApply.i18n.sessionExpired
                                : 'Your session has expired. Please refresh the page and try again.'
                        )
                        .show();
                    return;
                }
                if (response.success) {
                    // If server returned a redirect URL (WC My Account endpoint), go there.
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                        return;
                    }
                    // Fallback: show inline success message.
                    $form.fadeOut(300);
                    $msg.addClass('mhm-vendor-notice--success')
                        .text(mhmVendorApply.successMsg)
                        .show();
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : mhmVendorApply.errorMsg;
                    $msg.addClass('mhm-vendor-notice--error').text(msg).show();
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                $spinner.hide();
                var msg = (xhr.status === 0 || xhr.status === 403)
                    ? 'Your session has expired. Please refresh and try again.'
                    : 'Server error. Please try again.';
                if (typeof mhmVendorApply !== 'undefined' && mhmVendorApply.i18n) {
                    msg = mhmVendorApply.i18n.serverError || msg;
                }
                $msg.addClass('mhm-vendor-notice--error').text(msg).show();
            }
        });
    });
}(jQuery));
