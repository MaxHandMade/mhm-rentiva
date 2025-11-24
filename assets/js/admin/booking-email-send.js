/**
 * Booking Email Send Handler
 * Handles AJAX email sending from booking edit page
 */
(function ($) {
    'use strict';

    // Script loaded

    // Function to handle form submission
    function handleEmailFormSubmit(e) {
        e.preventDefault();
        e.stopPropagation();

        var form = $(this);
        var bookingId = form.data('booking-id');

        if (!bookingId) {
            alert('Booking ID bulunamadı.');
            return;
        }

        // Get form values manually
        var emailType = form.find('#email_type').val() || '';
        var emailSubject = form.find('#email_subject').val() || '';
        var emailMessage = form.find('#email_message').val() || '';
        var emailNonce = form.find('input[name="mhm_rentiva_email_nonce"]').val() || '';

        if (!emailNonce) {
            alert('Güvenlik kontrolü başarısız. Sayfayı yenileyin.');
            return;
        }

        var submitBtn = form.find('button[type="submit"]');
        var originalText = submitBtn.text();
        var sendingText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.sending)
            ? window.mhmBookingEmail.strings.sending
            : 'Gönderiliyor...';
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
            timeout: 30000,
            success: function (response) {
                var successText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.success)
                    ? window.mhmBookingEmail.strings.success
                    : 'E-posta başarıyla gönderildi!';
                var errorText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.error)
                    ? window.mhmBookingEmail.strings.error
                    : 'Hata:';
                var unknownErrorText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.unknownError)
                    ? window.mhmBookingEmail.strings.unknownError
                    : 'Bilinmeyen hata';

                if (response && response.success) {
                    alert(successText);
                    form[0].reset();
                } else {
                    var errorMsg = (response && response.data) ? response.data : unknownErrorText;
                    alert(errorText + ' ' + errorMsg);
                }
                submitBtn.prop('disabled', false).text(originalText);
            },
            error: function (xhr, status, error) {
                var errorOccurredText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.errorOccurred)
                    ? window.mhmBookingEmail.strings.errorOccurred
                    : 'Bir hata oluştu:';
                var errorMsg = errorOccurredText + ' ' + error;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg += ' - ' + xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    try {
                        var parsed = JSON.parse(xhr.responseText);
                        if (parsed.data) {
                            errorMsg += ' - ' + parsed.data;
                        }
                    } catch (e) {
                        errorMsg += ' - ' + xhr.responseText.substring(0, 100);
                    }
                }

                alert(errorMsg);
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
                (window.mhm_rentiva_config && window.mhm_rentiva_config.ajax_url ? window.mhm_rentiva_config.ajax_url :
                    '/wp-admin/admin-ajax.php'));

        var loadingText = (window.mhmBookingEmail && window.mhmBookingEmail.strings && window.mhmBookingEmail.strings.loadingTemplate)
            ? window.mhmBookingEmail.strings.loadingTemplate
            : 'Şablon yükleniyor...';

        // Show loading state
        var $subjectField = $('#email_subject');
        var $messageField = $('#email_message');
        var originalSubject = $subjectField.val();
        var originalMessage = $messageField.val();

        // Only load template if fields are empty or user hasn't customized them
        // For now, we'll always load template when type changes (user can still edit)

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
                    // Load template into fields
                    $subjectField.val(response.data.subject || '');
                    $messageField.val(response.data.message || '');
                }
            },
            error: function () {
                // Silently fail - user can still type manually
            }
        });
    }

    // Function to initialize form handlers
    function initFormHandlers() {
        // Find all email forms
        var $forms = $('.mhm-email-form');

        // Check if form exists in customer email box (meta box ID)
        var $emailBox = $('#mhm_rentiva_customer_email_box');
        if ($emailBox.length === 0) {
            $emailBox = $('div[id*="customer_email"]');
        }

        if ($emailBox.length > 0) {
            var $formInBox = $emailBox.find('form');

            // If form not found, try finding via #email_type select
            if ($formInBox.length === 0) {
                var $emailTypeSelect = $('#email_type');
                if ($emailTypeSelect.length > 0) {
                    $formInBox = $emailTypeSelect.closest('form');
                }
            }

            if ($formInBox.length > 0) {
                if (!$formInBox.hasClass('mhm-email-form')) {
                    $formInBox.addClass('mhm-email-form');
                }
                // Also ensure data-booking-id is set
                if (!$formInBox.data('booking-id')) {
                    var postId = $('#post_ID').val();
                    if (postId) {
                        $formInBox.attr('data-booking-id', postId);
                    }
                }
                // Attach handler directly
                $formInBox.off('submit').on('submit', handleEmailFormSubmit);
            }
        }

        // Re-check after potential class addition
        $forms = $('.mhm-email-form');

        if ($forms.length > 0) {
            // Attach event handler to each form
            $forms.each(function () {
                var $form = $(this);
                $form.off('submit').on('submit', handleEmailFormSubmit);
            });
        }

        // Always use event delegation as fallback (works even if form is added later)
        $(document).off('submit', '.mhm-email-form').on('submit', '.mhm-email-form', handleEmailFormSubmit);
        $(document).off('submit', '#mhm_rentiva_customer_email_box form').on('submit', '#mhm_rentiva_customer_email_box form', handleEmailFormSubmit);
        $(document).off('submit', 'form:has(#email_type)').on('submit', 'form:has(#email_type)', handleEmailFormSubmit);

        // Handle email type change to load template
        $(document).off('change', '#email_type').on('change', '#email_type', function () {
            var emailType = $(this).val();
            var bookingId = $(this).closest('form').data('booking-id') ||
                (window.mhmBookingEmail && window.mhmBookingEmail.bookingId ? window.mhmBookingEmail.bookingId :
                    $('#post_ID').val());

            if (emailType && bookingId) {
                loadEmailTemplate(emailType, bookingId);
            }
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function () {
        initFormHandlers();
    });

    // Try multiple times with increasing delays (meta boxes may load at different times)
    var delays = [500, 1000, 2000, 3000, 5000];
    delays.forEach(function (delay) {
        setTimeout(function () {
            initFormHandlers();
        }, delay);
    });

    // Also listen for WordPress postbox toggles (when meta boxes are expanded/collapsed)
    $(document).on('postbox-toggled', function () {
        setTimeout(initFormHandlers, 100);
    });
})(jQuery);
