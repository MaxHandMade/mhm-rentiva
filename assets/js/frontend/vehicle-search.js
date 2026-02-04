/**
 * Vehicle Search Form JavaScript
 * Handles AJAX search functionality, form validation, and UI interactions
 * 
 * Supports multiple form instances.
 */
(function ($) {
    'use strict';

    // Global variables
    let isLoading = false;
    let searchTimeout = null;

    // Initialize when document is ready
    $(document).ready(function () {
        initializeSearchForms();
    });

    /**
     * Initialize search forms
     * @param {Document|HTMLElement} context Optional context to search within (useful for editor iframe)
     */
    function initializeSearchForms(context) {
        // Use context if provided, otherwise default to entire document
        const $scope = context ? $(context) : $(document);

        // Find all search forms (full and compact) within scope
        $scope.find('.rv-search-form .js-rv-search-form, .rv-search-form-compact .js-rv-search-form').each(function () {
            const $form = $(this);
            const $wrapper = $form.closest('.rv-search-form, .rv-search-form-compact');

            initializeDatePickers($form);
            initializeEventHandlers($form, $wrapper);

            // Set initial state
            updateFormState($form);
        });
    }

    // Expose globally for Editor re-init
    window.mhmRentivaInitSearch = initializeSearchForms;

    /**
     * Initialize date pickers
     */
    function initializeDatePickers($form) {
        const $startDate = $form.find('.js-start-date');
        const $endDate = $form.find('.js-end-date');

        if ($startDate.length === 0 || $endDate.length === 0) return;

        // Destroy existing instances to prevent "Missing instance data" errors
        $startDate.each(function () {
            const $input = $(this);
            if ($input.hasClass('hasDatepicker') || $input.data('datepicker')) {
                try { $input.datepicker('destroy'); } catch (e) { }
                $input.removeClass('hasDatepicker').removeData('datepicker').off('.datepicker');
            }
        });
        $endDate.each(function () {
            const $input = $(this);
            if ($input.hasClass('hasDatepicker') || $input.data('datepicker')) {
                try { $input.datepicker('destroy'); } catch (e) { }
                $input.removeClass('hasDatepicker').removeData('datepicker').off('.datepicker');
            }
        });

        // Common options for both pickers
        const datePickerOptions = {
            ...mhmRentivaSearch.datepicker_options,
            dateFormat: 'yy-mm-dd',
            appendTo: 'body', // Fix for z-index/overflow issues
            beforeShow: function (input, inst) {
                setTimeout(function () {
                    const $input = $(input);
                    const inputOffset = $input.offset();
                    const inputHeight = $input.outerHeight();

                    // Position datepicker below input
                    inst.dpDiv.css({
                        'position': 'absolute',
                        'top': (inputOffset.top + inputHeight + 5) + 'px',
                        'left': inputOffset.left + 'px',
                        'z-index': 99999 // High z-index to ensure visibility
                    });
                }, 10);
            }
        };

        // Initialize jQuery UI datepicker
        $startDate.datepicker({
            ...datePickerOptions,
            onSelect: function (selectedDate) {
                // Set min date for end date
                $endDate.datepicker('option', 'minDate', selectedDate);

                // If end date is before new start date, clear it
                const endDateVal = $endDate.val();
                if (endDateVal) {
                    try {
                        const dateFormat = datePickerOptions.dateFormat || 'yy-mm-dd';
                        const start = $.datepicker.parseDate(dateFormat, selectedDate);
                        const end = $.datepicker.parseDate(dateFormat, endDateVal);

                        if (end < start) {
                            $endDate.val('');
                        }
                    } catch (e) { }
                }

                validateDateRange($form);
            }
        });

        $endDate.datepicker({
            ...datePickerOptions,
            onSelect: function (selectedDate) {
                // Set max date for start date
                $startDate.datepicker('option', 'maxDate', selectedDate);
                validateDateRange($form);
            }
        });
    }

    /**
     * Initialize event handlers
     */
    function initializeEventHandlers($form, $wrapper) {
        // Form submission
        $form.on('submit', function (e) { handleFormSubmit(e, $form, $wrapper); });

        // Reset button
        $form.find('.js-reset-btn').on('click', function () { handleReset($form, $wrapper); });

        // No results reset button
        $wrapper.find('.js-reset-from-no-results').on('click', function () { handleReset($form, $wrapper); });

        // Real-time search (if enabled)
        if ($wrapper.data('instant-search') === true) {
            $form.find('.js-keyword').on('input', function () { debounceSearch($form, $wrapper); });
            $form.find('select').on('change', function () { debounceSearch($form, $wrapper); });
            $form.find('input[type="number"]').on('input', function () { debounceSearch($form, $wrapper); });
        }

        // Pagination
        $wrapper.on('click', '.rv-pagination-btn', function (e) { handlePagination(e, $(this), $form, $wrapper); });

        // Result card clicks
        $wrapper.on('click', '.rv-result-card', function (e) { handleResultCardClick(e, $(this), $wrapper); });

        // Form field changes
        $form.find('input, select').on('change', function () { updateFormState($form); });
    }

    /**
     * Handle form submission
     */
    function handleFormSubmit(e, $form, $wrapper) {
        e.preventDefault();

        if (isLoading) return;

        // Validate form
        if (!validateForm($form)) {
            return;
        }

        // ⭐ Priority Redirection: If redirect URL is provided and it's a compact layout OR no results container exists
        const redirectUrl = $wrapper.find('.js-redirect-url').val();
        const isCompact = $wrapper.hasClass('rv-search-form-compact');
        const hasResultsContainer = $wrapper.find('.js-rv-search-results').length > 0;

        if (redirectUrl && (isCompact || !hasResultsContainer)) {
            const searchParams = getFormData($form);
            const queryString = new URLSearchParams(searchParams).toString();
            const separator = redirectUrl.includes('?') ? '&' : '?';
            window.location.href = redirectUrl + separator + queryString;
            return;
        }

        // Perform AJAX search
        performSearch($form, $wrapper, 1); // Reset to first page
    }

    /**
     * Handle reset button click
     */
    function handleReset($form, $wrapper) {
        // Reset form fields
        $form[0].reset();

        // Clear date pickers
        $form.find('.js-start-date, .js-end-date').datepicker('setDate', null);

        // Clear and hide results completely
        clearResults($wrapper);
        hideResults($wrapper);

        // Reset pagination
        $wrapper.find('.js-current-page').val(1);

        // Update form state
        updateFormState($form);

        // Focus on keyword field
        $form.find('.js-keyword').focus();
    }

    /**
     * Handle pagination
     */
    function handlePagination(e, $btn, $form, $wrapper) {
        e.preventDefault();

        if (isLoading) return;

        const page = parseInt($btn.data('page'));
        const currentPage = parseInt($wrapper.find('.js-current-page').val());

        if (page && page !== currentPage) {
            performSearch($form, $wrapper, page);
        }
    }

    /**
     * Handle result card click
     */
    function handleResultCardClick(e, $card, $wrapper) {
        e.preventDefault();

        const url = $card.data('url');

        if (url) {
            // Check if redirect URL is set
            const redirectUrl = $wrapper.find('.js-redirect-url').val();
            if (redirectUrl) {
                // Add search parameters to redirect URL
                // Note: We need to get form data from the wrapper's form
                const $form = $wrapper.find('.js-rv-search-form');
                const searchParams = getFormData($form);
                const queryString = new URLSearchParams(searchParams).toString();
                const separator = redirectUrl.includes('?') ? '&' : '?';
                window.location.href = redirectUrl + separator + queryString;
            } else {
                // Direct navigation to vehicle page
                window.location.href = url;
            }
        }
    }

    /**
     * Debounced search for real-time functionality
     */
    function debounceSearch($form, $wrapper) {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(function () {
            if (validateForm($form)) {
                performSearch($form, $wrapper, 1);
            }
        }, 500); // 500ms delay
    }

    /**
     * Perform AJAX search
     */
    function performSearch($form, $wrapper, page = 1) {
        if (isLoading) return;

        isLoading = true;

        // Update current page hidden input
        $wrapper.find('.js-current-page').val(page);

        // Clear previous results before new search
        if (page === 1) {
            clearResults($wrapper);
        }

        // Update UI
        setLoadingState($form, true);
        showResults($wrapper);

        // Prepare data
        const formData = getFormData($form);
        formData.page = page;
        formData.per_page = $wrapper.find('.js-per-page').val() || 12;

        // AJAX request
        $.ajax({
            url: mhmRentivaSearch.ajax_url,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_search_vehicles',
                nonce: mhmRentivaSearch.nonce,
                ...formData
            },
            success: function (response) {
                if (response.success) {
                    displayResults($wrapper, response.data);
                } else {
                    showError(response.data.message || mhmRentivaSearch.i18n.error);
                }
            },
            error: function (xhr, status, error) {
                console.error('Search error:', error);
                showError(mhmRentivaSearch.i18n.error);
            },
            complete: function () {
                isLoading = false;
                setLoadingState($form, false);
            }
        });
    }

    /**
     * Get form data
     */
    function getFormData($form) {
        const formData = {};
        const datepickerOpts = { ...(mhmRentivaSearch.datepicker_options || {}), dateFormat: 'yy-mm-dd' };

        $form.find('input, select').each(function () {
            const $field = $(this);
            const name = $field.attr('name');
            let value = $field.val();

            if (name && value) {
                // ⭐ Convert dates to ISO for server compatibility
                if (name === 'start_date' || name === 'end_date' || name === 'pickup_date' || name === 'return_date') {
                    try {
                        const date = $.datepicker.parseDate(datepickerOpts.dateFormat, value);
                        value = $.datepicker.formatDate('yy-mm-dd', date);
                    } catch (e) {
                        // Keep original if parsing fails
                    }
                }
                formData[name] = value;
            }
        });

        return formData;
    }

    /**
     * Validate form
     */
    function validateForm($form) {
        const $startDate = $form.find('.js-start-date');
        const $endDate = $form.find('.js-end-date');

        // Check if date fields are visible and validate them
        if ($startDate.is(':visible') && $endDate.is(':visible')) {
            const startDate = $startDate.val();
            const endDate = $endDate.val();

            if (startDate && endDate) {
                if (!validateDateRange($form)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate date range
     */
    function validateDateRange($form) {
        const $startDate = $form.find('.js-start-date');
        const $endDate = $form.find('.js-end-date');

        const startDateStr = $startDate.val();
        const endDateStr = $endDate.val();

        if (startDateStr && endDateStr) {
            try {
                const datepickerOpts = mhmRentivaSearch.datepicker_options || { dateFormat: 'yy-mm-dd' };
                const start = $.datepicker.parseDate(datepickerOpts.dateFormat, startDateStr);
                const end = $.datepicker.parseDate(datepickerOpts.dateFormat, endDateStr);

                if (start >= end) {
                    showFieldError($endDate, mhmRentivaSearch.i18n.invalid_dates);
                    return false;
                }
            } catch (e) {
                return false;
            }
        }

        clearFieldError($endDate);
        return true;
    }

    /**
     * Display search results
     */
    function displayResults($wrapper, data) {
        const $resultsGrid = $wrapper.find('.js-rv-results-grid');
        const $resultsCount = $wrapper.find('.js-rv-results-count');
        const $pagination = $wrapper.find('.js-rv-results-pagination');
        const $noResults = $wrapper.find('.js-rv-no-results');

        // Update results count
        $resultsCount.text(data.total);

        if (data.vehicles && data.vehicles.length > 0) {
            // Show results
            $resultsGrid.show();
            $noResults.hide();

            // Render vehicle cards
            renderVehicleCards($resultsGrid, data.vehicles);

            // Render pagination
            if (data.pages > 1) {
                renderPagination($pagination, data);
                $pagination.show();
            } else {
                $pagination.hide();
            }
        } else {
            // Show no results
            $resultsGrid.hide();
            $pagination.hide();
            $noResults.show();
        }
    }

    /**
     * Render vehicle cards
     */
    function renderVehicleCards($grid, vehicles) {
        $grid.empty();

        vehicles.forEach(function (vehicle) {
            const $card = createVehicleCard(vehicle);
            $grid.append($card);
        });
    }

    /**
     * Create vehicle card element
     */
    function createVehicleCard(vehicle) {
        const imageHtml = vehicle.featured_image
            ? `<img src="${escapeHtml(vehicle.featured_image)}" alt="${escapeHtml(vehicle.title)}" loading="lazy">`
            : '<div class="rv-no-image">🚗</div>';

        const specs = [];
        if (vehicle.fuel_type) specs.push({ label: 'Yakıt', value: vehicle.fuel_type });
        if (vehicle.transmission) specs.push({ label: 'Vites', value: vehicle.transmission });
        if (vehicle.seats) specs.push({ label: 'Koltuk', value: vehicle.seats });
        if (vehicle.engine_size) specs.push({ label: 'Motor', value: vehicle.engine_size });

        const specsHtml = specs.map(spec =>
            `<div class="rv-result-card-spec">
                <span class="rv-result-card-spec-label">${escapeHtml(spec.label)}:</span>
                <span class="rv-result-card-spec-value">${escapeHtml(spec.value)}</span>
            </div>`
        ).join('');

        return $(`
            <div class="rv-result-card" data-url="${escapeHtml(vehicle.permalink)}">
                <div class="rv-result-card-image">
                    ${imageHtml}
                </div>
                <div class="rv-result-card-content">
                    <h4 class="rv-result-card-title">${escapeHtml(vehicle.title)}</h4>
                    <p class="rv-result-card-excerpt">${escapeHtml(vehicle.excerpt)}</p>
                    <div class="rv-result-card-specs">
                        ${specsHtml}
                    </div>
                    <div class="rv-result-card-price">
                        ${escapeHtml(vehicle.price_per_day)} TL/gün
                    </div>
                </div>
            </div>
        `);
    }

    /**
     * Render pagination
     */
    function renderPagination($pagination, data) {
        $pagination.empty();

        const currentPage = data.current_page;
        const totalPages = data.pages;
        const maxVisible = 5;

        // Previous button
        if (currentPage > 1) {
            $pagination.append(createPaginationButton('«', currentPage - 1, false));
        }

        // Page numbers
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        for (let i = startPage; i <= endPage; i++) {
            $pagination.append(createPaginationButton(i, i, i === currentPage));
        }

        // Next button
        if (currentPage < totalPages) {
            $pagination.append(createPaginationButton('»', currentPage + 1, false));
        }
    }

    /**
     * Create pagination button
     */
    function createPaginationButton(text, page, isActive) {
        const activeClass = isActive ? 'active' : '';
        return $(`<button class="rv-pagination-btn ${activeClass}" data-page="${page}">${text}</button>`);
    }

    /**
     * Show results section
     */
    function showResults($wrapper) {
        $wrapper.find('.js-rv-search-results').show();
    }

    /**
     * Hide results section
     */
    function hideResults($wrapper) {
        $wrapper.find('.js-rv-search-results, .js-rv-no-results').hide();
    }

    /**
     * Clear results content
     */
    function clearResults($wrapper) {
        $wrapper.find('.js-rv-results-grid').empty();
        $wrapper.find('.js-rv-results-count').text('0');
        $wrapper.find('.js-rv-results-pagination').empty().hide();
        $wrapper.find('.js-rv-no-results').hide();
    }

    /**
     * Set loading state
     */
    function setLoadingState($form, loading) {
        const $btn = $form.find('.js-search-btn');

        if (loading) {
            $form.addClass('loading');
            $btn.prop('disabled', true);
            $btn.find('.text').hide();
            $btn.find('.loading').css('display', 'flex'); // Ensure flex for alignment
        } else {
            $form.removeClass('loading');
            $btn.prop('disabled', false);
            $btn.find('.text').show();
            $btn.find('.loading').hide();
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        // console.error('Search error:', message); // Removed console.error for production clean-up
        showToast(message, 'error');
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'success') {
        // Create toast element if it doesn't exist
        if ($('#rv-toast-notification').length === 0) {
            $('body').append('<div id="rv-toast-notification" class="rv-frontend-toast"></div>');
        }

        const $toast = $('#rv-toast-notification');

        // Reset classes and content
        $toast.removeClass('error success').addClass(type).text(message);

        // Show
        $toast.fadeIn(300);

        // Auto hide
        setTimeout(() => {
            $toast.fadeOut(300);
        }, 5000);
    }

    /**
     * Show field error
     */
    function showFieldError($field, message) {
        $field.addClass('error');
        // You can add error styling and message display here
    }

    /**
     * Clear field error
     */
    function clearFieldError($field) {
        $field.removeClass('error');
    }

    /**
     * Update form state (scopeless helper, mostly for event binding structure)
     */
    function updateFormState($form) {
        // You can add form state management here
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }

})(jQuery);
