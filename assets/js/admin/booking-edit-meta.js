/**
 * Booking Edit Meta Box JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Form validation
        $('.mhm-booking-edit-form').on('submit', function (e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });

        // Real-time validation
        $('.mhm-field-input, .mhm-field-select').on('blur', function () {
            validateField($(this));
        });

        // Date validation
        $('#mhm_edit_pickup_date, #mhm_edit_dropoff_date').on('change', function () {
            validateDateRange();
        });

        // Status change notification
        $('#mhm_edit_status').on('change', function () {
            const status = $(this).val();
            const statusLabels = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.statusLabels) || {
                'pending': 'Pending',
                'confirmed': 'Confirmed',
                'in_progress': 'In Progress',
                'completed': 'Completed',
                'cancelled': 'Cancelled'
            };

            const confirmText = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings && window.mhmRentivaAdmin.strings.confirmStatusChange) ||
                `Are you sure you want to change the booking status to "${statusLabels[status]}"?`;

            if (confirm(confirmText.replace('%s', statusLabels[status]))) {
                // Status change confirmed
                const changingText = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings && window.mhmRentivaAdmin.strings.changing) || 'Changing status...';
                showNotification(changingText, 'info');
            } else {
                // Revert to previous value
                $(this).val($(this).data('previous-value'));
            }
        });

        // Store previous value for status select
        $('#mhm_edit_status').each(function () {
            $(this).data('previous-value', $(this).val());
        });
    });

    /**
     * Validate the entire form
     */
    function validateForm() {
        let isValid = true;

        $('.mhm-field-input[required], .mhm-field-select[required]').each(function () {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        if (!validateDateRange()) {
            isValid = false;
        }

        return isValid;
    }

    /**
     * Validate individual field
     */
    function validateField($field) {
        const value = $field.val().trim();
        const fieldType = $field.attr('type');
        const fieldName = $field.attr('name');

        // Remove previous error styling
        $field.removeClass('error');
        $field.siblings('.error-message').remove();

        const strings = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings) || {};

        // Required field validation
        if ($field.prop('required') && !value) {
            showFieldError($field, strings.required_field || 'This field is required.');
            return false;
        }

        // Email validation
        if (fieldType === 'email' && value && !isValidEmail(value)) {
            showFieldError($field, strings.invalid_email || 'Please enter a valid email address.');
            return false;
        }

        // Phone validation
        if (fieldName === 'mhm_edit_customer_phone' && value && !isValidPhone(value)) {
            showFieldError($field, strings.invalid_phone || 'Please enter a valid phone number.');
            return false;
        }

        // Number validation
        if (fieldType === 'number' && value) {
            const min = parseInt($field.attr('min'));
            const max = parseInt($field.attr('max'));
            const numValue = parseInt(value);

            if (numValue < min || numValue > max) {
                const rangeText = strings.value_range || `Value must be between ${min} and ${max}.`;
                showFieldError($field, rangeText.replace('%min', min).replace('%max', max));
                return false;
            }
        }

        return true;
    }

    /**
     * Validate date range
     */
    function validateDateRange() {
        const pickupDate = $('#mhm_edit_pickup_date').val();
        const dropoffDate = $('#mhm_edit_dropoff_date').val();

        if (pickupDate && dropoffDate) {
            const pickup = new Date(pickupDate);
            const dropoff = new Date(dropoffDate);

            const strings = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings) || {};

            if (dropoff <= pickup) {
                showFieldError($('#mhm_edit_dropoff_date'), strings.dropoff_after_pickup || 'Dropoff date must be after pickup date.');
                return false;
            }

            // Check if dates are not in the past (except for existing bookings)
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (pickup < today) {
                showFieldError($('#mhm_edit_pickup_date'), strings.pickup_not_past || 'Pickup date cannot be in the past.');
                return false;
            }
        }

        return true;
    }

    /**
     * Show field error
     */
    function showFieldError($field, message) {
        $field.addClass('error');
        $field.after(`<div class="error-message" style="color: #dc2626; font-size: 12px; margin-top: 4px;">${message}</div>`);
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate phone format
     */
    function isValidPhone(phone) {
        const phoneRegex = /^[\d\s\-\+\(\)]+$/;
        return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 10;
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="notice notice-${type} is-dismissible" style="margin: 5px 0;">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">${(window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings && window.mhmRentivaAdmin.strings.dismiss) || 'Dismiss this notice'}</span>
                </button>
            </div>
        `);

        $('.mhm-booking-edit-form').prepend(notification);

        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            notification.fadeOut();
        }, 3000);
    }

})(jQuery);
