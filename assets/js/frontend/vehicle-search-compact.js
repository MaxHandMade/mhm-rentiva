/**
 * Vehicle Search Compact Form JavaScript
 * MHM Rentiva Plugin
 * 
 * Supports multiple form instances on the same page.
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        initializeCompactSearch();
    });

    /**
     * Initialize compact search form
     * @param {Document|HTMLElement} context Optional context to search within (useful for editor iframe)
     */
    function initializeCompactSearch(context) {
        // Use context if provided, otherwise default to entire document
        const $scope = context ? $(context) : $(document);

        // Iterate over all compact search forms within scope
        $scope.find('.rv-search-filters-compact').each(function () {
            const $form = $(this);
            const instanceId = $form.data('instance-id');

            // Initialize date pickers for this specific form
            initializeDatePickers($form);

            // Initialize advanced filters toggle for this specific form
            initializeAdvancedFilters($form);

            // Initialize form validation for this specific form
            initializeFormValidation($form);

            // Initialize auto-complete (if enabled)
            initializeAutoComplete($form);
        });
    }

    // Expose globally for Editor re-init
    window.mhmRentivaInitCompactSearch = initializeCompactSearch;

    /**
     * Initialize jQuery UI Date Pickers
     */
    function initializeDatePickers($form) {
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
            ? { ...defaultOptions, ...mhmRentivaSearch.datepicker_options, dateFormat: 'yy-mm-dd' }
            : { ...defaultOptions, dateFormat: 'yy-mm-dd' };

        const $pickupDate = $form.find('.js-pickup-date');
        const $returnDate = $form.find('.js-return-date');
        const $pickupTime = $form.find('.js-pickup-time');
        const $returnTime = $form.find('.js-return-time');
        const $returnTimeHidden = $form.find('.js-return-time-hidden');

        // Destroy existing instances to prevent "Missing instance data" errors
        $pickupDate.each(function () {
            const $input = $(this);
            if ($input.hasClass('hasDatepicker') || $input.data('datepicker')) {
                try { $input.datepicker('destroy'); } catch (e) { }
                $input.removeClass('hasDatepicker').removeData('datepicker').off('.datepicker');
            }
        });
        $returnDate.each(function () {
            const $input = $(this);
            if ($input.hasClass('hasDatepicker') || $input.data('datepicker')) {
                try { $input.datepicker('destroy'); } catch (e) { }
                $input.removeClass('hasDatepicker').removeData('datepicker').off('.datepicker');
            }
        });

        // Initialize pickup date picker
        $pickupDate.datepicker({
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
                $returnDate.datepicker('option', 'minDate', selectedDate);

                // If return date is before pickup date, clear it
                const returnDateStr = $returnDate.val();
                if (returnDateStr) {
                    try {
                        const pDate = $.datepicker.parseDate(datepickerOpts.dateFormat, selectedDate);
                        const rDate = $.datepicker.parseDate(datepickerOpts.dateFormat, returnDateStr);
                        if (rDate <= pDate) {
                            $returnDate.val('');
                        }
                    } catch (e) {
                        console.error('Date parsing error', e);
                    }
                }
            }
        });

        // Initialize return date picker
        $returnDate.datepicker({
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
                $pickupDate.datepicker('option', 'maxDate', selectedDate);
            }
        });

        // Set initial minimum date for return date
        const pickupDateVal = $pickupDate.val();
        if (pickupDateVal) {
            $returnDate.datepicker('option', 'minDate', pickupDateVal);
        }

        // Automatically update return time when pickup time changes
        // Return time is disabled and always matches pickup time
        $pickupTime.on('change', function (e) {
            const timeVal = $(e.target).val();
            if (timeVal) {
                // Update both visible (disabled) select and hidden input
                $returnTime.val(timeVal);
                $returnTimeHidden.val(timeVal);
            } else {
                $returnTime.val('');
                $returnTimeHidden.val('');
            }
        });

        // Initialize return time on page load if pickup time is already selected
        const initialPickupTime = $pickupTime.val();
        if (initialPickupTime) {
            $returnTime.val(initialPickupTime);
            $returnTimeHidden.val(initialPickupTime);
        }
    }

    /**
     * Initialize advanced filters toggle
     */
    function initializeAdvancedFilters($form) {
        const $toggle = $form.find('.js-toggle-filters');
        const $content = $form.find('.js-advanced-content');

        if ($toggle.length === 0 || $content.length === 0) return;

        // Initially hide the advanced filters
        $content.hide();

        $toggle.on('click', function (e) {
            e.preventDefault();

            if ($content.is(':visible')) {
                // Hide filters
                $content.slideUp(300, function () {
                    $toggle.removeClass('active');
                    $toggle.find('.rv-toggle-text').text('Advanced Filters'); // TODO: i18n
                    $toggle.find('.rv-toggle-icon').text('▼');
                });
            } else {
                // Show filters
                $content.slideDown(300, function () {
                    $toggle.addClass('active');
                    $toggle.find('.rv-toggle-text').text('Hide Filters'); // TODO: i18n
                    $toggle.find('.rv-toggle-icon').text('▲');
                });
            }
        });
    }

    /**
     * Initialize form validation
     */
    function initializeFormValidation($form) {
        if ($form.length === 0) return;

        const defaultOptions = (typeof mhmRentivaSearch !== 'undefined' && mhmRentivaSearch.datepicker_options)
            ? { ...mhmRentivaSearch.datepicker_options, dateFormat: 'yy-mm-dd' }
            : { dateFormat: 'yy-mm-dd' };

        // Global flag to prevent double submission
        let isSubmitting = false;

        // Handle form submission
        $form.on('submit', function (e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            // Get form values
            const $pickupDate = $form.find('.js-pickup-date');
            const $returnDate = $form.find('.js-return-date');
            const pickupValue = $pickupDate.val();
            const returnValue = $returnDate.val();
            const $wrapper = $form.closest('.rv-search-form-compact, .rv-search-form'); // Find wrapper

            // Remove existing errors
            $form.find('.error').removeClass('error');
            $form.find('.error-message').remove();

            const datepickerOpts = (typeof mhmRentivaSearch !== 'undefined' && mhmRentivaSearch.datepicker_options)
                ? mhmRentivaSearch.datepicker_options : defaultOptions;

            // Only validate date range if both dates are provided
            if (pickupValue && returnValue) {
                try {
                    const pickupDate = $.datepicker.parseDate(datepickerOpts.dateFormat, pickupValue);
                    const returnDate = $.datepicker.parseDate(datepickerOpts.dateFormat, returnValue);

                    if (returnDate <= pickupDate) {
                        e.preventDefault();
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
                        $pickupDate.closest('.rv-search-field').addClass('error');
                        if (!$pickupDate.next('.error-message').length) {
                            $pickupDate.after('<div class="error-message">' + mhmRentivaSearch.i18n.pickup_past + '</div>');
                        }
                        return false;
                    }

                    // ⭐ CRITICAL: Convert to ISO before submission for server compatibility
                    // Store original visual value to restore it later
                    $pickupDate.data('original-val', pickupValue);
                    const isoPickup = $.datepicker.formatDate('yy-mm-dd', pickupDate);
                    $pickupDate.val(isoPickup);

                    if (returnValue) {
                        $returnDate.data('original-val', returnValue);
                        const returnDate = $.datepicker.parseDate(datepickerOpts.dateFormat, returnValue);
                        const isoReturn = $.datepicker.formatDate('yy-mm-dd', returnDate);
                        $returnDate.val(isoReturn);
                    }
                } catch (err) {
                    console.error('ISO conversion error', err);
                }
            }

            isSubmitting = true;

            // Add loading state UI
            const $btn = $form.find('.js-search-btn');
            $btn.prop('disabled', true);
            $btn.find('.text').hide();
            $btn.find('.loading').css('display', 'flex');

            // ⭐ Priority Redirection: If redirect URL is provided
            const redirectUrl = $wrapper.find('.js-redirect-url').val();

            // If we have a redirect URL, we handle the redirection via JS to ensure clean params
            if (redirectUrl) {
                e.preventDefault();

                // Collect form data
                const formData = new FormData($form[0]);
                const params = new URLSearchParams();

                for (const pair of formData.entries()) {
                    if (pair[1]) { // Only add non-empty values
                        params.append(pair[0], pair[1]);
                    }
                }

                // Build final URL
                const separator = redirectUrl.includes('?') ? '&' : '?';
                const finalUrl = redirectUrl + separator + params.toString();

                // Redirect
                window.location.href = finalUrl;
                return false;
            }

            return true;
        });

        // Real-time validation (optional - for UX)
        $form.find('input[type="text"], select').on('blur', function () {
            const $field = $(this);
            const value = $field.val();

            // Additional validation could go here
        });
    }

    /**
     * Initialize auto-complete for keyword search (if available)
     */
    function initializeAutoComplete($form) {
        // Implement if keyword input exists in compact form
    }

})(jQuery);