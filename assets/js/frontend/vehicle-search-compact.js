/**
 * Vehicle Search Compact Form JavaScript
 * MHM Rentiva Plugin
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        initializeCompactSearch();
    });

    /**
     * Initialize compact search form
     */
    function initializeCompactSearch() {
        const $form = $('#rv-search-filters-compact');
        if ($form.length === 0) return;

        // Initialize date pickers
        initializeDatePickers();

        // Initialize advanced filters toggle
        initializeAdvancedFilters();

        // Initialize form validation
        initializeFormValidation();

        // Initialize auto-complete (if enabled)
        initializeAutoComplete();
    }

    /**
     * Initialize jQuery UI Date Pickers
     */
    function initializeDatePickers() {
        // Use localized datepicker options from PHP if available, otherwise fallback to defaults
        const defaultOptions = {
            dateFormat: 'yy-mm-dd',
            minDate: 0, // Today
            showButtonPanel: true,
            closeText: 'Close',
            currentText: 'Today',
            clearText: 'Clear',
            monthNames: ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'],
            monthNamesShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            dayNames: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            dayNamesShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            dayNamesMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            weekHeader: 'Wk',
            firstDay: 1, // Monday
            isRTL: false,
            showMonthAfterYear: false,
            yearSuffix: '',
            changeMonth: true,
            changeYear: true,
            yearRange: 'c-2:c+2'
        };

        // Merge with localized options from PHP (i18n support)
        const datePickerOptions = (typeof mhmRentivaSearch !== 'undefined' && mhmRentivaSearch.datepicker_options)
            ? { ...defaultOptions, ...mhmRentivaSearch.datepicker_options }
            : defaultOptions;

        // Initialize pickup date picker
        $('#rv-pickup-date').datepicker({
            ...datePickerOptions,
            appendTo: 'body', // Ensure datepicker is appended to body, not footer
            beforeShow: function (input, inst) {
                // Like booking form - for opening downwards
                setTimeout(function () {
                    const $input = $(input);
                    const inputOffset = $input.offset();
                    const inputHeight = $input.outerHeight();

                    // Position datepicker below input
                    inst.dpDiv.css({
                        'position': 'absolute',
                        'top': (inputOffset.top + inputHeight + 5) + 'px',
                        'left': inputOffset.left + 'px',
                        'z-index': 9999
                    });
                }, 10);
            },
            onSelect: function (selectedDate) {
                const datepickerOpts = $(this).datepicker('option', 'all');

                // Set minimum date for return date
                $('#rv-return-date').datepicker('option', 'minDate', selectedDate);

                // If return date is before pickup date, clear it
                const returnDateStr = $('#rv-return-date').val();
                if (returnDateStr) {
                    try {
                        const pDate = $.datepicker.parseDate(datepickerOpts.dateFormat, selectedDate);
                        const rDate = $.datepicker.parseDate(datepickerOpts.dateFormat, returnDateStr);
                        if (rDate <= pDate) {
                            $('#rv-return-date').val('');
                        }
                    } catch (e) {
                        console.error('Date parsing error', e);
                    }
                }
            }
        });

        // Initialize return date picker
        $('#rv-return-date').datepicker({
            ...datePickerOptions,
            appendTo: 'body', // Ensure datepicker is appended to body, not footer
            beforeShow: function (input, inst) {
                // Like booking form - for opening downwards
                setTimeout(function () {
                    const $input = $(input);
                    const inputOffset = $input.offset();
                    const inputHeight = $input.outerHeight();

                    // Position datepicker below input
                    inst.dpDiv.css({
                        'position': 'absolute',
                        'top': (inputOffset.top + inputHeight + 5) + 'px',
                        'left': inputOffset.left + 'px',
                        'z-index': 9999
                    });
                }, 10);
            },
            onSelect: function (selectedDate) {
                // Set maximum date for pickup date
                $('#rv-pickup-date').datepicker('option', 'maxDate', selectedDate);
            }
        });

        // Set initial minimum date for return date
        const pickupDate = $('#rv-pickup-date').val();
        if (pickupDate) {
            $('#rv-return-date').datepicker('option', 'minDate', pickupDate);
        }

        // Automatically update return time when pickup time changes
        // Return time is disabled and always matches pickup time
        $('#rv-pickup-time').on('change', function (e) {
            const pickupTime = $(e.target).val();
            if (pickupTime) {
                // Update both visible (disabled) select and hidden input
                $('#rv-return-time').val(pickupTime);
                $('#rv-return-time-hidden').val(pickupTime);
            } else {
                $('#rv-return-time').val('');
                $('#rv-return-time-hidden').val('');
            }
        });

        // Initialize return time on page load if pickup time is already selected
        const initialPickupTime = $('#rv-pickup-time').val();
        if (initialPickupTime) {
            $('#rv-return-time').val(initialPickupTime);
            $('#rv-return-time-hidden').val(initialPickupTime);
        }
    }

    /**
     * Initialize advanced filters toggle
     */
    function initializeAdvancedFilters() {
        const $toggle = $('#rv-toggle-filters-compact');
        const $content = $('#rv-advanced-content-compact');

        if ($toggle.length === 0 || $content.length === 0) return;

        // Initially hide the advanced filters
        $content.hide();

        $toggle.on('click', function (e) {
            e.preventDefault();

            if ($content.is(':visible')) {
                // Hide filters
                $content.slideUp(300, function () {
                    $toggle.removeClass('active');
                    $toggle.find('.rv-toggle-text').text('Advanced Filters');
                    $toggle.find('.rv-toggle-icon').text('▼');
                });
            } else {
                // Show filters
                $content.slideDown(300, function () {
                    $toggle.addClass('active');
                    $toggle.find('.rv-toggle-text').text('Hide Filters');
                    $toggle.find('.rv-toggle-icon').text('▲');
                });
            }
        });
    }

    /**
     * Initialize form validation
     */
    function initializeFormValidation() {
        const $form = $('#rv-search-filters-compact');

        if ($form.length === 0) return;

        // Handle form submission
        $form.on('submit', function (e) {
            // Get form values
            const pickupValue = $('#rv-pickup-date').val();
            const returnValue = $('#rv-return-date').val();

            const datepickerOpts = (typeof mhmRentivaSearch !== 'undefined' && mhmRentivaSearch.datepicker_options)
                ? mhmRentivaSearch.datepicker_options : defaultOptions;

            // Only validate date range if both dates are provided
            if (pickupValue && returnValue) {
                try {
                    const pickupDate = $.datepicker.parseDate(datepickerOpts.dateFormat, pickupValue);
                    const returnDate = $.datepicker.parseDate(datepickerOpts.dateFormat, returnValue);

                    if (returnDate <= pickupDate) {
                        e.preventDefault();
                        const $returnDate = $('#rv-return-date');
                        $returnDate.closest('.rv-search-field').addClass('error');
                        if (!$returnDate.next('.error-message').length) {
                            $returnDate.after('<div class="error-message">' + mhmRentivaSearch.i18n.return_after_pickup + '</div>');
                        }
                        return false;
                    }
                } catch (err) {
                    console.error('Validation parsing error', err);
                }
            }

            // Validate pickup date is not in the past (if provided)
            if (pickupValue) {
                try {
                    const pickupDate = $.datepicker.parseDate(datepickerOpts.dateFormat, pickupValue);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    if (pickupDate < today) {
                        e.preventDefault();
                        const $pickupDate = $('#rv-pickup-date');
                        $pickupDate.closest('.rv-search-field').addClass('error');
                        if (!$pickupDate.next('.error-message').length) {
                            $pickupDate.after('<div class="error-message">' + mhmRentivaSearch.i18n.pickup_past + '</div>');
                        }
                        return false;
                    }

                    // ⭐ CRITICAL: Convert to ISO before submission for server compatibility
                    const isoPickup = $.datepicker.formatDate('yy-mm-dd', pickupDate);
                    $('#rv-pickup-date').val(isoPickup);

                    if (returnValue) {
                        const returnDate = $.datepicker.parseDate(datepickerOpts.dateFormat, returnValue);
                        const isoReturn = $.datepicker.formatDate('yy-mm-dd', returnDate);
                        $('#rv-return-date').val(isoReturn);
                    }
                } catch (err) {
                    console.error('ISO conversion error', err);
                }
            }

            // Add loading state
            const $btn = $('.rv-search-btn');
            $btn.addClass('loading').prop('disabled', true);

            return true;
        });

        // Real-time validation (optional - for UX)
        $form.find('input[type="text"], select').on('blur', function () {
            const $field = $(this);
            const fieldName = $field.attr('name');
            const value = $field.val();

            // Only validate dates if they have values
            // Compare date strings directly to avoid timezone issues
            if (fieldName === 'pickup_date' && value) {
                const today = new Date();
                const todayStr = today.getFullYear() + '-' +
                    String(today.getMonth() + 1).padStart(2, '0') + '-' +
                    String(today.getDate()).padStart(2, '0');

                // value is already in YYYY-MM-DD format from datepicker
                if (pickupValue < todayStr) {
                    $field.closest('.rv-search-field').addClass('error');
                    if (!$field.next('.error-message').length) {
                        $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.pickup_past + '</div>');
                    }
                } else {
                    $field.closest('.rv-search-field').removeClass('error');
                    $field.next('.error-message').remove();
                }
            }
        });
    }

    /**
     * Validate individual field
     */
    function validateField($field) {
        const value = $field.val().trim();
        const fieldName = $field.attr('name');
        const $fieldContainer = $field.closest('.rv-search-field');

        // Clear previous states
        $fieldContainer.removeClass('error success');
        $fieldContainer.find('.error-message').remove();

        // Required field validation
        if ($field.prop('required') && !value) {
            $fieldContainer.addClass('error');
            $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.field_required + '</div>');
            return false;
        }

        // Date validation
        if (fieldName === 'pickup_date' && value) {
            try {
                const datepickerOpts = (typeof mhmRentivaSearch !== 'undefined' && mhmRentivaSearch.datepicker_options)
                    ? mhmRentivaSearch.datepicker_options : defaultOptions;
                const pickupDate = $.datepicker.parseDate(datepickerOpts.dateFormat, value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (pickupDate < today) {
                    $fieldContainer.addClass('error');
                    $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.pickup_past + '</div>');
                    return false;
                }
            } catch (e) {
                return false;
            }
        }

        if (fieldName === 'return_date' && value) {
            try {
                const datepickerOpts = (typeof mhmRentivaSearch !== 'undefined' && mhmRentivaSearch.datepicker_options)
                    ? mhmRentivaSearch.datepicker_options : defaultOptions;
                const returnDate = $.datepicker.parseDate(datepickerOpts.dateFormat, value);
                const pickupValue = $('#rv-pickup-date').val();
                if (pickupValue) {
                    const pickupDate = $.datepicker.parseDate(datepickerOpts.dateFormat, pickupValue);
                    if (returnDate <= pickupDate) {
                        $fieldContainer.addClass('error');
                        $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.return_after_pickup + '</div>');
                        return false;
                    }
                }
            } catch (e) {
                return false;
            }
        }

        // Price validation
        if (fieldName === 'min_price' && value) {
            const minPrice = parseFloat(value);
            const maxPrice = parseFloat($('#rv-max-price').val());

            if (isNaN(minPrice) || minPrice < 0) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.invalid_price + '</div>');
                return false;
            }

            if (maxPrice && minPrice > maxPrice) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.min_price_error + '</div>');
                return false;
            }
        }

        if (fieldName === 'max_price' && value) {
            const maxPrice = parseFloat(value);
            const minPrice = parseFloat($('#rv-min-price').val());

            if (isNaN(maxPrice) || maxPrice < 0) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.invalid_price + '</div>');
                return false;
            }

            if (minPrice && maxPrice < minPrice) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">' + mhmRentivaSearch.i18n.max_price_error + '</div>');
                return false;
            }
        }

        // If we get here, field is valid
        if (value) {
            $fieldContainer.addClass('success');
        }

        return true;
    }

    /**
     * Validate entire form
     */
    function validateForm() {
        const $form = $('#rv-search-filters-compact');
        const $requiredFields = $form.find('input[required], select[required]');
        let isValid = true;

        // Clear all previous states
        $form.find('.rv-search-field').removeClass('error success');
        $form.find('.error-message').remove();

        // Validate required fields only
        $requiredFields.each(function () {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        // Validate date range only if dates are provided
        // Allow form submission even without dates (show all available vehicles)
        const pickupValue = $('#rv-pickup-date').val();
        const returnValue = $('#rv-return-date').val();

        // Only validate date range if both dates are provided
        if (pickupValue && returnValue) {
            if (!validateDateRange()) {
                isValid = false;
            }
        }

        return isValid;
    }

    /**
     * Validate date range
     */
    function validateDateRange() {
        const $pickupDate = $('#rv-pickup-date');
        const $returnDate = $('#rv-return-date');

        const pickupValue = $pickupDate.val();
        const returnValue = $returnDate.val();

        if (pickupValue && returnValue) {
            try {
                const datepickerOpts = (typeof mhmRentivaSearch !== 'undefined' && mhmRentivaSearch.datepicker_options)
                    ? mhmRentivaSearch.datepicker_options : defaultOptions;
                const pickupDate = $.datepicker.parseDate(datepickerOpts.dateFormat, pickupValue);
                const returnDate = $.datepicker.parseDate(datepickerOpts.dateFormat, returnValue);

                if (returnDate <= pickupDate) {
                    $returnDate.closest('.rv-search-field').addClass('error');
                    $returnDate.after('<div class="error-message">' + mhmRentivaSearch.i18n.return_after_pickup + '</div>');
                    return false;
                }
            } catch (e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Initialize auto-complete for keyword search (if available)
     */
    function initializeAutoComplete() {
        const $keywordInput = $('#rv-keyword');

        if ($keywordInput.length === 0) return;

        // Simple auto-complete implementation
        let searchTimeout;
        $keywordInput.on('input', function () {
            const query = $(this).val();

            if (query.length < 2) return;

            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // TODO: Implement auto-complete suggestions
                // This would require an AJAX endpoint for suggestions
            }, 300);
        });
    }

    /**
     * Utility: Format date for display
     */
    function formatDate(date) {
        return $.datepicker.formatDate('yy-mm-dd', date);
    }

    /**
     * Utility: Get today's date
     */
    function getToday() {
        return new Date();
    }

    /**
     * Utility: Add days to date
     */
    function addDays(date, days) {
        const result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    }

})(jQuery);