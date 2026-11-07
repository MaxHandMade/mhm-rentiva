/**
 * Booking Edit Meta Box JavaScript
 */

(function ($) {
    'use strict';

    const DATE_FIELDS = ['pickup_date', 'pickup_time', 'dropoff_date', 'dropoff_time'];
    // The range the server drew (window.mhmBookingEditDates, printed before
    // this script): the baseline, so input made before the page was ready
    // still counts as a change (Codex r4).
    const dateData = window.mhmBookingEditDates || {};
    const initialDates = dateData.initial || {};

    $(document).ready(function () {
        // The edit box is a <div> inside WordPress's own #post form, so a
        // submit handler on it never ran (function scan H4). Only the date
        // order is checked on #post: the server applies the same rule
        // (BookingEditDates::is_refused()), and the field checks below stay
        // advisory so they cannot block an Update the server accepts.
        $('#post').on('submit', function (e) {
            // A cancellation request writes no dates on the server (it keeps
            // the stored ones), so the date check must not block it (Codex
            // code audit 3b-2, Important 3).
            if (isCancellationRequest()) {
                return true;
            }
            if (!validateDateRange()) {
                e.preventDefault();
                return false;
            }
        });

        // Real-time validation
        $('.mhm-field-input, .mhm-field-select').on('blur', function () {
            validateField($(this));
        });

        // Date validation
        $('#mhmrentiva_booking_edit_pickup_date, #mhmrentiva_booking_edit_pickup_time, #mhmrentiva_booking_edit_dropoff_date, #mhmrentiva_booking_edit_dropoff_time').on('change', function () {
            validateDateRange();
        });

        // Status change notification.
        // Bound by id -- mhmrentiva_edit_status is the field's NAME (which is
        // what the server reads from $_POST). Bound to the name, this handler
        // matched nothing and the confirmation never appeared.
        $('#mhmrentiva_booking_edit_status').on('change', function () {
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
        $('#mhmrentiva_booking_edit_status').each(function () {
            $(this).data('previous-value', $(this).val());
        });

        // Update license plate display when vehicle is changed
        $('#mhmrentiva_booking_edit_vehicle_id').on('change', function () {
            const selectedOption = $(this).find('option:selected');
            const optionText = selectedOption.text();
            
            // Extract plate from option text (format: "Vehicle Name (PLATE)")
            const plateMatch = optionText.match(/\(([^)]+)\)$/);
            const plate = plateMatch ? plateMatch[1] : '';
            
            // Update or create description element
            let $description = $(this).siblings('p.description');
            if ($description.length === 0) {
                $description = $('<p class="description"></p>');
                $(this).after($description);
            }
            
            if (plate) {
                const strings = (window.mhmBookingEdit && window.mhmBookingEdit.text) || {};
                const label = strings.currentLicensePlate || 'Current License Plate:';
                // Option text is decoded; the plate is text, never markup.
                $description.text(label + ' ').append($('<strong>').text(plate));
            } else {
                $description.html('');
            }
        });
        
        // Trigger on page load to show current plate
        $('#mhmrentiva_booking_edit_vehicle_id').trigger('change');
    });

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
        if (fieldName === 'mhmrentiva_edit_customer_phone' && value && !isValidPhone(value)) {
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
     * The site's current date (Y-m-d), localized from PHP. The browser's own
     * date is only a fallback when the localized value is missing.
     */
    /**
     * "Cancelled" chosen on a booking that was not cancelled when the screen
     * was drawn: the server treats that Update as a cancellation request.
     */
    function isCancellationRequest() {
        const $status = $('#mhmrentiva_booking_edit_status');
        if (!$status.length || $status.val() !== 'cancelled') {
            return false;
        }
        const initial = $status.find('option').filter(function () {
            return this.defaultSelected;
        }).val();
        return initial !== 'cancelled';
    }

    /**
     * Validate date range: the return must come after the pickup (date and
     * time), the rule BookingEditDates::is_refused() applies on the server.
     * No "pickup in the past" check: an existing booking may lie in the past,
     * and its notes must still be editable.
     */
    function validateDateRange() {
        const $dropoff = $('#mhmrentiva_booking_edit_dropoff_date');
        const pickupDate = $('#mhmrentiva_booking_edit_pickup_date').val();
        const dropoffDate = $dropoff.val();

        $dropoff.removeClass('error').next('.error-message').remove();

        if (!pickupDate || !dropoffDate) {
            return true;
        }

        // Only a changed range is checked: a booking whose stored range is
        // already equal (a transfer may carry one) must still save its other
        // fields. Same rule as the server (BookingEditDates::is_refused()).
        const changed = DATE_FIELDS.some(function (id) {
            const el = document.getElementById('mhmrentiva_booking_edit_' + id);
            return el && el.value !== (initialDates[id] !== undefined ? initialDates[id] : el.value);
        });
        if (!changed) {
            return true;
        }

        // An empty time is the default Util::parse_datetimes() applies (its
        // filters, passed in by the server).
        const defaults = dateData.defaultTimes || {};
        const pickupTime = $('#mhmrentiva_booking_edit_pickup_time').val() || defaults.pickup || '10:00';
        const dropoffTime = $('#mhmrentiva_booking_edit_dropoff_time').val() || defaults.dropoff || '10:00';

        // 'YYYY-MM-DD HH:MM' compares correctly as text. The return must come
        // AFTER the pickup: an equal moment is no rental for pricing,
        // availability or the timestamps.
        if ((dropoffDate + ' ' + dropoffTime) <= (pickupDate + ' ' + pickupTime)) {
            const strings = (window.mhmRentivaAdmin && window.mhmRentivaAdmin.strings) || {};
            showFieldError($dropoff, strings.dropoff_after_pickup || 'Dropoff date must be after pickup date.');
            return false;
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
