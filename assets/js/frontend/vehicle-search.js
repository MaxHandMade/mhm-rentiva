/**
 * Vehicle Search Form JavaScript
 * Handles AJAX search functionality, form validation, and UI interactions
 */
(function ($) {
    'use strict';

    // Global variables
    let currentPage = 1;
    let isLoading = false;
    let searchTimeout = null;

    // Initialize when document is ready
    $(document).ready(function () {
        initializeSearchForm();
        initializeDatePickers();
        initializeEventHandlers();
    });

    /**
     * Initialize search form
     */
    function initializeSearchForm() {
        const $form = $('#rv-search-filters');
        if ($form.length === 0) return;

        // Set initial state
        updateFormState();
    }

    /**
     * Initialize date pickers
     */
    function initializeDatePickers() {
        const $startDate = $('#rv-start-date');
        const $endDate = $('#rv-end-date');

        if ($startDate.length === 0 || $endDate.length === 0) return;

        // Initialize jQuery UI datepicker
        $startDate.datepicker({
            ...mhmRentivaSearch.datepicker_options,
            onSelect: function (selectedDate) {
                $endDate.datepicker('option', 'minDate', selectedDate);
                validateDateRange();
            }
        });

        $endDate.datepicker({
            ...mhmRentivaSearch.datepicker_options,
            onSelect: function (selectedDate) {
                $startDate.datepicker('option', 'maxDate', selectedDate);
                validateDateRange();
            }
        });
    }

    /**
     * Initialize event handlers
     */
    function initializeEventHandlers() {
        // Form submission
        $('#rv-search-filters').on('submit', handleFormSubmit);

        // Reset button
        $('#rv-reset-btn, #rv-reset-from-no-results').on('click', handleReset);

        // Real-time search (if enabled)
        if ($('#rv-search-form').data('instant-search') === true) {
            $('#rv-keyword').on('input', debounceSearch);
            $('select').on('change', debounceSearch);
            $('input[type="number"]').on('input', debounceSearch);
        }

        // Pagination
        $(document).on('click', '.rv-pagination-btn', handlePagination);

        // Result card clicks
        $(document).on('click', '.rv-result-card', handleResultCardClick);

        // Form field changes
        $('input, select').on('change', updateFormState);
    }

    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();

        if (isLoading) return;

        // Validate form
        if (!validateForm()) {
            return;
        }

        // Perform search
        performSearch(1); // Reset to first page
    }

    /**
     * Handle reset button click
     */
    function handleReset() {
        const $form = $('#rv-search-filters');

        // Reset form fields
        $form[0].reset();

        // Clear date pickers
        $('#rv-start-date, #rv-end-date').datepicker('setDate', null);

        // Clear and hide results completely
        clearResults();
        hideResults();

        // Reset pagination
        currentPage = 1;
        $('#rv-current-page').val(1);

        // Update form state
        updateFormState();

        // Focus on keyword field
        $('#rv-keyword').focus();
    }

    /**
     * Handle pagination
     */
    function handlePagination(e) {
        e.preventDefault();

        if (isLoading) return;

        const $btn = $(this);
        const page = parseInt($btn.data('page'));

        if (page && page !== currentPage) {
            performSearch(page);
        }
    }

    /**
     * Handle result card click
     */
    function handleResultCardClick(e) {
        e.preventDefault();

        const $card = $(this);
        const url = $card.data('url');

        if (url) {
            // Check if redirect URL is set
            const redirectUrl = $('#rv-redirect-url').val();
            if (redirectUrl) {
                // Add search parameters to redirect URL
                const searchParams = getFormData();
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
    function debounceSearch() {
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(function () {
            if (validateForm()) {
                performSearch(1);
            }
        }, 500); // 500ms delay
    }

    /**
     * Perform AJAX search
     */
    function performSearch(page = 1) {
        if (isLoading) return;

        isLoading = true;
        currentPage = page;

        // Clear previous results before new search
        if (page === 1) {
            clearResults();
        }

        // Update UI
        setLoadingState(true);
        showResults();

        // Prepare data
        const formData = getFormData();
        formData.page = page;
        formData.per_page = $('#rv-per-page').val() || 12;

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
                    displayResults(response.data);
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
                setLoadingState(false);
            }
        });
    }

    /**
     * Get form data
     */
    function getFormData() {
        const $form = $('#rv-search-filters');
        const formData = {};
        const datepickerOpts = mhmRentivaSearch.datepicker_options || { dateFormat: 'yy-mm-dd' };

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
    function validateForm() {
        const $startDate = $('#rv-start-date');
        const $endDate = $('#rv-end-date');

        // Check if date fields are visible and validate them
        if ($startDate.is(':visible') && $endDate.is(':visible')) {
            const startDate = $startDate.val();
            const endDate = $endDate.val();

            if (startDate && endDate) {
                if (!validateDateRange()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate date range
     */
    function validateDateRange() {
        const $startDate = $('#rv-start-date');
        const $endDate = $('#rv-end-date');

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
    function displayResults(data) {
        const $resultsGrid = $('#rv-results-grid');
        const $resultsCount = $('#rv-results-count');
        const $pagination = $('#rv-results-pagination');
        const $noResults = $('#rv-no-results');

        // Update results count
        $resultsCount.text(data.total);

        if (data.vehicles && data.vehicles.length > 0) {
            // Show results
            $resultsGrid.show();
            $noResults.hide();

            // Render vehicle cards
            renderVehicleCards(data.vehicles);

            // Render pagination
            if (data.pages > 1) {
                renderPagination(data);
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
    function renderVehicleCards(vehicles) {
        const $grid = $('#rv-results-grid');
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
    function renderPagination(data) {
        const $pagination = $('#rv-results-pagination');
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
    function showResults() {
        $('#rv-search-results').show();
    }

    /**
     * Hide results section
     */
    function hideResults() {
        $('#rv-search-results, #rv-no-results').hide();
    }

    /**
     * Clear results content
     */
    function clearResults() {
        $('#rv-results-grid').empty();
        $('#rv-results-count').text('0');
        $('#rv-results-pagination').empty().hide();
        $('#rv-no-results').hide();
    }

    /**
     * Set loading state
     */
    function setLoadingState(loading) {
        const $form = $('#rv-search-form');
        const $btn = $('#rv-search-btn');

        if (loading) {
            $form.addClass('loading');
            $btn.prop('disabled', true);
        } else {
            $form.removeClass('loading');
            $btn.prop('disabled', false);
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
     * Update form state
     */
    function updateFormState() {
        // You can add form state management here
        // For example, enabling/disabling submit button based on form validity
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

})(jQuery);
