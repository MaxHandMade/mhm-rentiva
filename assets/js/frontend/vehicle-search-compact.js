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
        const datePickerOptions = {
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

        // Initialize pickup date picker
        $('#rv-pickup-date').datepicker({
            ...datePickerOptions,
            beforeShow: function (input, inst) {
                // Booking form'daki gibi - aşağıya açılması için
                setTimeout(function () {
                    const $input = $(input);
                    const inputOffset = $input.offset();
                    const inputHeight = $input.outerHeight();

                    // Datepicker'ı input'un altına konumlandır
                    inst.dpDiv.css({
                        'position': 'absolute',
                        'top': (inputOffset.top + inputHeight + 5) + 'px',
                        'left': inputOffset.left + 'px',
                        'z-index': 9999
                    });
                }, 10);
            },
            onSelect: function (selectedDate) {
                // Set minimum date for return date
                $('#rv-return-date').datepicker('option', 'minDate', selectedDate);

                // If return date is before pickup date, clear it
                const returnDate = $('#rv-return-date').val();
                if (returnDate && new Date(returnDate) <= new Date(selectedDate)) {
                    $('#rv-return-date').val('');
                }
            }
        });

        // Initialize return date picker
        $('#rv-return-date').datepicker({
            ...datePickerOptions,
            beforeShow: function (input, inst) {
                // Booking form'daki gibi - aşağıya açılması için
                setTimeout(function () {
                    const $input = $(input);
                    const inputOffset = $input.offset();
                    const inputHeight = $input.outerHeight();

                    // Datepicker'ı input'un altına konumlandır
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

        // Handle form submission
        $form.on('submit', function (e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }

            // Add loading state
            const $btn = $('.rv-search-btn');
            $btn.addClass('loading').prop('disabled', true);

            // Remove loading state after 3 seconds (fallback)
            setTimeout(() => {
                $btn.removeClass('loading').prop('disabled', false);
            }, 3000);
        });

        // Real-time validation
        $form.find('input, select').on('blur', function () {
            validateField($(this));
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
            $field.after('<div class="error-message">This field is required</div>');
            return false;
        }

        // Date validation
        if (fieldName === 'pickup_date' && value) {
            const pickupDate = new Date(value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (pickupDate < today) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">Pickup date cannot be in the past</div>');
                return false;
            }
        }

        if (fieldName === 'return_date' && value) {
            const returnDate = new Date(value);
            const pickupDate = new Date($('#rv-pickup-date').val());

            if ($('#rv-pickup-date').val() && returnDate <= pickupDate) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">Return date must be after pickup date</div>');
                return false;
            }
        }

        // Price validation
        if (fieldName === 'min_price' && value) {
            const minPrice = parseFloat(value);
            const maxPrice = parseFloat($('#rv-max-price').val());

            if (isNaN(minPrice) || minPrice < 0) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">Please enter a valid price</div>');
                return false;
            }

            if (maxPrice && minPrice > maxPrice) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">Min price cannot be greater than max price</div>');
                return false;
            }
        }

        if (fieldName === 'max_price' && value) {
            const maxPrice = parseFloat(value);
            const minPrice = parseFloat($('#rv-min-price').val());

            if (isNaN(maxPrice) || maxPrice < 0) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">Please enter a valid price</div>');
                return false;
            }

            if (minPrice && maxPrice < minPrice) {
                $fieldContainer.addClass('error');
                $field.after('<div class="error-message">Max price cannot be less than min price</div>');
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

        // Validate required fields
        $requiredFields.each(function () {
            if (!validateField($(this))) {
                isValid = false;
            }
        });

        // Validate date range
        if (!validateDateRange()) {
            isValid = false;
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
            const pickupDate = new Date(pickupValue);
            const returnDate = new Date(returnValue);

            if (returnDate <= pickupDate) {
                $returnDate.closest('.rv-search-field').addClass('error');
                $returnDate.after('<div class="error-message">Return date must be after pickup date</div>');
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