/**
 * Search Results JavaScript
 * Handles filtering, sorting, view toggles, and AJAX updates
 */
(function ($) {
    'use strict';

    // Global variables
    let isLoading = false;
    let currentUrl = window.location.href;
    let filterTimeout = null;

    // Initialize when document is ready
    $(document).ready(function () {
        initializeResultsPage();
        initializeFilters();
        initializeSorting();
        initializeViewToggle();
        initializeFavorites();
        initializePagination();
    });

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }


    /**
     * Initialize results page
     */
    function initializeResultsPage() {
        const $container = $('.rv-search-results');
        if ($container.length === 0) return;

        // Show active filters count
        updateActiveFiltersCount();

        // Initialize price sliders if available
        initializePriceSliders();

        // Initialize year sliders if available
        initializeYearSliders();
    }

    /**
     * Initialize filter functionality
     */
    function initializeFilters() {
        const $filtersForm = $('.rv-filters-form');
        const $clearBtn = $('.rv-clear-filters');

        if ($filtersForm.length === 0) return;

        // Handle filter changes
        $filtersForm.find('input, select').on('change', function () {
            handleFilterChange();
        });

        // Handle checkbox changes specifically
        $filtersForm.find('input[type="checkbox"]').on('change', function () {
            handleFilterChange();
        });

        // Handle clear filters
        $clearBtn.on('click', function (e) {
            e.preventDefault();
            clearAllFilters();
        });

        // Handle real-time input changes
        $filtersForm.find('input[type="number"]').on('input', function () {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(handleFilterChange, 500);
        });
    }

    /**
     * Initialize sorting functionality
     */
    function initializeSorting() {
        const $sortSelect = $('.rv-sort-select');

        if ($sortSelect.length === 0) return;

        $sortSelect.on('change', function () {
            const sortValue = $(this).val();
            updateUrlParameter('sort', sortValue);
            reloadResults();
        });
    }

    /**
     * Initialize view toggle
     */
    function initializeViewToggle() {
        const $viewBtns = $('.rv-view-btn');
        if ($viewBtns.length === 0) return;

        // Resolve current view from storage or default
        const viewToUse = localStorage.getItem('mhm_rentiva_view_mode') || 'grid';
        updateLayout(viewToUse, false); // Initialize layout without force-saving

        // Handle button clicks
        $viewBtns.on('click', function () {
            const view = $(this).data('view');

            if (!view || (view !== 'grid' && view !== 'list')) {
                return;
            }

            // Update active state
            $viewBtns.removeClass('active');
            $(this).addClass('active');

            // Update layout
            updateLayout(view, true); // true = save to localStorage
        });
    }

    /**
     * Update layout based on view mode
     * @param {string} view - 'grid' or 'list'
     * @param {boolean} saveToStorage - Whether to save to localStorage
     */
    function updateLayout(view, saveToStorage = true) {
        if (view !== 'grid' && view !== 'list') {
            return;
        }

        // TARGET THE NEW PERMANENT WRAPPER
        const $layoutContainer = $('.rv-results-content');
        const $wrapper = $('.rv-vehicle-grid-wrapper');

        if ($layoutContainer.length > 0) {
            // Remove all layout classes first
            $layoutContainer.removeClass('rv-layout-grid rv-layout-list');
            // Add the correct layout class for wrapper
            $layoutContainer.addClass(`rv-layout-${view}`);

            // Update cards class
            if ($wrapper.length > 0) {
                const $cards = $wrapper.find('.mhm-vehicle-card');

                // Reset card classes
                $cards.removeClass('mhm-card--grid mhm-card--list');
                // Add new layout class
                $cards.addClass(`mhm-card--${view}`);
            }
        }

        // Save preference to localStorage if requested
        if (saveToStorage) {
            localStorage.setItem('mhm_rentiva_view_mode', view);
        }

        // Update active state of buttons
        $('.rv-view-btn').removeClass('active');
        $(`.rv-view-btn[data-view="${view}"]`).addClass('active');
    }


    /**
     * Initialize favorites functionality
     */
    function initializeFavorites() {
        $(document).on('click', '.mhm-card-favorite', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');

            if (!vehicleId) return;

            // Toggle favorite via AJAX
            toggleFavorite(vehicleId, $btn);
        });
    }

    /**
     * Initialize pagination
     */
    function initializePagination() {
        $(document).on('click', '.rv-pagination .page-numbers a', function (e) {
            e.preventDefault();

            const url = $(this).attr('href');
            if (url) {
                window.location.href = url;
            }
        });
    }

    /**
     * Initialize price range sliders
     */
    function initializePriceSliders() {
        const $slider = $('#rv-price-slider');
        if ($slider.length === 0) return;

        // Simple price slider implementation
        // In a real implementation, you'd use a proper slider library like noUiSlider
        $slider.on('click', function (e) {
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const maxPrice = parseFloat($(this).data('max-price') || 10000);
            const price = Math.round(percent * maxPrice);

            $('input[name="max_price"]').val(price);
            handleFilterChange();
        });
    }

    /**
     * Initialize year range sliders
     */
    function initializeYearSliders() {
        const $slider = $('#rv-year-slider');
        if ($slider.length === 0) return;

        // Simple year slider implementation
        $slider.on('click', function (e) {
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const minYear = parseInt($(this).data('min-year') || 1990);
            const maxYear = parseInt($(this).data('max-year') || new Date().getFullYear());
            const year = Math.round(minYear + (percent * (maxYear - minYear)));

            $('input[name="year_max"]').val(year);
            handleFilterChange();
        });
    }

    /**
     * Handle filter changes
     */
    function handleFilterChange() {
        if (isLoading) return;


        const $form = $('#rv-filters-form');
        const formData = $form.serializeArray();


        // Update URL parameters
        updateUrlFromFormData(formData);

        // Reload results
        reloadResults();

        // Update active filters count
        updateActiveFiltersCount();
    }

    /**
     * Clear all filters
     */
    function clearAllFilters() {
        const $form = $('#rv-filters-form');

        // Clear all form inputs
        $form.find('input[type="checkbox"]').prop('checked', false);
        $form.find('input[type="number"]').val('');

        // Remove all filter parameters from URL
        const url = new URL(window.location.href);
        const filterParams = [
            'min_price', 'max_price', 'brand', 'fuel_type', 'transmission',
            'seats', 'year_min', 'year_max', 'mileage_max'
        ];

        filterParams.forEach(param => {
            url.searchParams.delete(param);
        });

        // Update URL and reload
        window.history.replaceState({}, '', url.toString());
        reloadResults();

        // Update active filters count
        updateActiveFiltersCount();
    }

    /**
     * Update URL from form data
     */
    function updateUrlFromFormData(formData) {
        const url = new URL(window.location.href);

        // Clear existing filter parameters
        const filterParams = [
            'min_price', 'max_price', 'brand', 'fuel_type', 'transmission',
            'seats', 'year_min', 'year_max', 'mileage_max'
        ];

        filterParams.forEach(param => {
            url.searchParams.delete(param);
        });

        // Add new parameters
        formData.forEach(item => {
            if (item.value && item.value.trim() !== '') {
                if (item.name.endsWith('[]')) {
                    // Handle array parameters
                    const paramName = item.name.replace('[]', '');
                    url.searchParams.append(paramName, item.value);
                } else {
                    url.searchParams.set(item.name, item.value);
                }
            }
        });

        // Update URL without reloading
        window.history.replaceState({}, '', url.toString());
    }

    /**
     * Update URL parameter
     */
    function updateUrlParameter(param, value) {
        const url = new URL(window.location.href);

        if (value && value !== '') {
            url.searchParams.set(param, value);
        } else {
            url.searchParams.delete(param);
        }

        window.history.replaceState({}, '', url.toString());
    }

    /**
     * Reload results via AJAX
     */
    function reloadResults() {
        if (isLoading) return;

        isLoading = true;
        showLoadingIndicator();

        // View mode'u localStorage'dan al, yoksa grid kullan
        const currentLayout = localStorage.getItem('mhm_rentiva_view_mode') ||
            $('.rv-view-btn.active').data('view') ||
            'grid';

        // Get sort value from select (it's outside the form)
        const sortValue = $('.rv-sort-select').val() || '';

        const data = {
            action: 'mhm_rentiva_filter_results',
            nonce: mhmRentivaSearchResults.nonce,
            layout: currentLayout,
            per_page: 12,
            sort: sortValue,
            ...getCurrentFilters()
        };

        $.ajax({
            url: mhmRentivaSearchResults.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success && response.data) {
                    // Update main content (HTML from server)
                    updateResultsContainer(response.data);

                    // Update pagination (HTML from server)
                    if (response.data.pagination) {
                        $('.rv-pagination').html(response.data.pagination);
                    }

                    // Update count (from meta)
                    if (response.data.meta && response.data.meta.total !== undefined) {
                        updateResultsCount(response.data.meta.total);
                    }
                } else {
                    showError(response.data?.message || 'An error occurred');
                }
            },
            error: function () {
                showError('Network error. Please try again.');
            },
            complete: function () {
                isLoading = false;
                hideLoadingIndicator();
            }
        });
    }

    /**
     * Get current filter values from form
     */
    function getCurrentFilters() {
        const $form = $('.rv-filters-form');
        const filters = {};

        if ($form.length === 0) return {};

        // Get form data
        const formData = $form.serializeArray();

        formData.forEach(function (item) {
            if (item.name.endsWith('[]')) {
                // Array fields (brand[], fuel_type[], etc.)
                const key = item.name.replace('[]', '');
                if (!filters[key]) {
                    filters[key] = [];
                }
                filters[key].push(item.value);
            } else {
                // Single fields
                filters[item.name] = item.value;
            }
        });
        return filters;
    }

    function updateResultsContainer(data) {
        const $resultsContent = $('.rv-vehicle-grid-wrapper');
        const currentLayout = localStorage.getItem('mhm_rentiva_view_mode') || 'grid';

        // Update the content (Strictly server-side HTML)
        if (data.html) {
            $resultsContent.html(data.html);

            // Ensure layout wrapper state is correct
            setTimeout(function () {
                updateLayout(currentLayout, false);
            }, 50);
        } else {
            // Empty state (already handled by server HTML usually, but fallback here)
            const emptyHtml = `
                <div class="rv-no-results">
                    <div class="rv-no-results-icon">🚗</div>
                    <h3>${mhmRentivaSearchResults.i18n.no_results || 'No vehicles found'}</h3>
                    <p>${mhmRentivaSearchResults.i18n.try_adjusting || 'Try adjusting your search criteria or filters.'}</p>
                    <a href="${mhmRentivaSearchResults.search_page_url || '#'}" class="rv-back-to-search">
                        ${mhmRentivaSearchResults.i18n.back_to_search || 'Back to Search'}
                    </a>
                </div>
             `;
            $resultsContent.html(emptyHtml);
        }
    }


    /**
     * Update results count
     */
    function updateResultsCount(total) {
        const $title = $('.rv-results-title');
        if ($title.length === 0) return;

        const text = total === 1 ?
            (mhmRentivaSearchResults.i18n.vehicle_found || '%d vehicle found').replace('%d', '1') :
            (mhmRentivaSearchResults.i18n.vehicles_found || '%d vehicles found').replace('%d', total);

        $title.text(text);
    }

    /**
     * Toggle favorite
     */
    /**
     * Toggle favorite
     */
    function toggleFavorite(vehicleId, $btn) {
        // Disable button during request
        $btn.prop('disabled', true);

        $.ajax({
            url: mhmRentivaSearchResults.ajax_url,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_toggle_favorite',
                vehicle_id: vehicleId,
                nonce: mhmRentivaSearchResults.favorite_nonce
            },
            success: function (response) {
                if (response.success) {
                    const { action } = response.data;

                    // Toggle .is-active class - CSS handles the visual state
                    if (action === 'added') {
                        $btn.addClass('is-active');
                        MHMRentivaToast.show(mhmRentivaSearchResults.i18n.added_to_favorites || 'Added to favorites', { type: 'success' });
                    } else {
                        $btn.removeClass('is-active');
                        MHMRentivaToast.show(mhmRentivaSearchResults.i18n.removed_from_favorites || 'Removed from favorites', { type: 'success' });
                    }
                } else {
                    MHMRentivaToast.show(response.data.message || 'An error occurred', { type: 'error' });
                }
            },
            error: function () {
                MHMRentivaToast.show('Network error. Please try again.', { type: 'error' });
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    }

    /**
     * Update active filters count
     */
    function updateActiveFiltersCount() {
        const $form = $('#rv-filters-form');
        if ($form.length === 0) return;

        let activeFilters = 0;

        // Count checked checkboxes
        activeFilters += $form.find('input[type="checkbox"]:checked').length;

        // Count number inputs with actual values (not empty, not 0)
        $form.find('input[type="number"]').each(function () {
            const value = $(this).val();
            if (value && value !== '' && parseFloat(value) > 0) {
                activeFilters++;
            }
        });

        // Update clear button text
        const $clearBtn = $('#rv-clear-filters');
        const clearAllText = mhmRentivaSearchResults.i18n.clear_all || 'Clear All';

        if (activeFilters > 0) {
            const clearAllWithCount = (mhmRentivaSearchResults.i18n.clear_all_with_count || 'Clear All (%d)').replace('%d', activeFilters);
            $clearBtn.text(clearAllWithCount);
            $clearBtn.show();
        } else {
            $clearBtn.text(clearAllText);
            $clearBtn.hide();
        }
    }

    /**
     * Show loading indicator
     */
    function showLoadingIndicator() {
        $('.rv-loading-indicator').show();
    }

    function hideLoadingIndicator() {
        $('.rv-loading-indicator').hide();
    }

    /**
     * Show error message
     */
    function showError(message) {
        const $container = $('#rv-results-grid-content');

        const errorText = mhmRentivaSearchResults.i18n.error || 'Error';
        const tryAgainText = mhmRentivaSearchResults.i18n.try_again || 'Try Again';

        const html = `
            <div class="rv-error-message">
                <div class="rv-error-icon">⚠️</div>
                <h3>${errorText}</h3>
                <p>${escapeHtml(message)}</p>
                <button type="button" class="rv-btn rv-btn-primary" onclick="location.reload()">
                    ${tryAgainText}
                </button>
            </div>
        `;

        if ($container.length > 0) {
            $container.html(html);
        } else {
            $('#rv-results-container').html(html);
        }
    }

    /**
     * Legacy notification system removed in favor of centralized MHMRentivaToast.
     * Maintained for backward compatibility or internal calls.
     */
    function showNotification(message, type = 'info') {
        MHMRentivaToast.show(message, { type: type });
    }

})(jQuery);
