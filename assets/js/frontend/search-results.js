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
     * Initialize results page
     */
    function initializeResultsPage() {
        const $container = $('#rv-search-results');
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
        const $filtersForm = $('#rv-filters-form');
        const $clearBtn = $('#rv-clear-filters');

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
        const $sortSelect = $('#rv-sort-select');

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

        $viewBtns.on('click', function () {
            const view = $(this).data('view');

            // Update active state
            $viewBtns.removeClass('active');
            $(this).addClass('active');

            // Update layout
            updateLayout(view);

            // Store preference
            localStorage.setItem('mhm_rentiva_view_mode', view);
        });

        // Load saved preference
        const savedView = localStorage.getItem('mhm_rentiva_view_mode');
        if (savedView) {
            $viewBtns.removeClass('active');
            $(`.rv-view-btn[data-view="${savedView}"]`).addClass('active');
            updateLayout(savedView);
        }
    }

    /**
     * Update layout based on view mode
     */
    function updateLayout(view) {
        const $container = $('#rv-results-container');
        const $vehiclesList = $container.find('#rv-vehicles-list');

        if ($vehiclesList.length === 0) return;

        // Remove existing layout classes
        $vehiclesList.removeClass('rv-vehicles-grid rv-vehicles-list');
        $vehiclesList.find('.rv-vehicle-card').removeClass('rv-vehicle-card-grid rv-vehicle-card-list');

        if (view === 'list') {
            $vehiclesList.addClass('rv-vehicles-list');
            $vehiclesList.find('.rv-vehicle-card').addClass('rv-vehicle-card-list');
        } else {
            $vehiclesList.addClass('rv-vehicles-grid');
            $vehiclesList.find('.rv-vehicle-card').addClass('rv-vehicle-card-grid');
        }

        // Force CSS update
        $vehiclesList[0].offsetHeight; // Trigger reflow

        // Force layout update with timeout
        setTimeout(() => {
            if (view === 'list') {
                $vehiclesList.css({
                    'display': 'flex',
                    'flex-direction': 'column',
                    'gap': '16px'
                });
            } else {
                $vehiclesList.css({
                    'display': 'grid',
                    'grid-template-columns': 'repeat(auto-fill, minmax(300px, 1fr))',
                    'gap': '24px'
                });
            }
        }, 10);

    }

    /**
     * Apply layout to existing elements
     */
    function applyLayout(view) {
        const $container = $('#rv-results-container');
        const $vehiclesList = $container.find('#rv-vehicles-list');

        if ($vehiclesList.length === 0) return;

        // Remove existing layout classes
        $vehiclesList.removeClass('rv-vehicles-grid rv-vehicles-list');
        $vehiclesList.find('.rv-vehicle-card').removeClass('rv-vehicle-card-grid rv-vehicle-card-list');

        if (view === 'list') {
            $vehiclesList.addClass('rv-vehicles-list');
            $vehiclesList.find('.rv-vehicle-card').addClass('rv-vehicle-card-list');
        } else {
            $vehiclesList.addClass('rv-vehicles-grid');
            $vehiclesList.find('.rv-vehicle-card').addClass('rv-vehicle-card-grid');
        }

    }

    /**
     * Initialize favorites functionality
     */
    function initializeFavorites() {
        $(document).on('click', '.rv-add-to-favorites', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');

            if (!vehicleId) return;

            // Toggle favorite
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

        const $container = $('#rv-results-container');
        // View mode'u localStorage'dan al, yoksa grid kullan
        const currentLayout = localStorage.getItem('mhm_rentiva_view_mode') ||
            $('.rv-view-btn.active').data('view') ||
            'grid';

        const data = {
            action: 'mhm_rentiva_filter_results',
            nonce: mhmRentivaSearchResults.nonce,
            layout: currentLayout,
            per_page: 12,
            ...getCurrentFilters()
        };

        $.ajax({
            url: mhmRentivaSearchResults.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    updateResultsContainer(response.data);
                    updatePagination(response.data.pagination);
                    updateResultsCount(response.data.total);
                } else {
                    showError(response.data.message || 'An error occurred');
                }
            },
            error: function (xhr, status, error) {
                showError('Network error. Please try again.');
                console.error('AJAX Error:', error);
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
        const $form = $('#rv-filters-form');
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

    /**
     * Update results container
     */
    function updateResultsContainer(data) {
        const $container = $('#rv-results-container');
        const currentLayout = localStorage.getItem('mhm_rentiva_view_mode') ||
            $('.rv-view-btn.active').data('view') ||
            'grid';


        if (data.html) {
            $container.html(data.html);
            // Apply layout after HTML update
            setTimeout(() => {
                applyLayout(currentLayout);
            }, 100);
        } else if (data.vehicles && data.vehicles.length > 0) {
            // Generate HTML from vehicle data
            const html = generateVehiclesHtml(data.vehicles, currentLayout);
            $container.html(html);
        } else {
            $container.html(`
                <div class="rv-no-results">
                    <div class="rv-no-results-icon">🚗</div>
                    <h3>${mhmRentivaSearchResults.i18n.no_results}</h3>
                    <p>Try adjusting your search criteria or filters.</p>
                    <a href="${mhmRentivaSearchResults.search_page_url || mhmRentivaSearchResults.current_url.replace('/results/', '/')}" class="rv-back-to-search">
                        Back to Search
                    </a>
                </div>
            `);
        }
    }

    /**
     * Generate vehicles HTML
     */
    function generateVehiclesHtml(vehicles, layout) {
        const containerClass = layout === 'list' ? 'rv-vehicles-list' : 'rv-vehicles-grid';

        let html = `<div class="${containerClass}" id="rv-vehicles-list">`;

        vehicles.forEach(vehicle => {
            html += generateVehicleCardHtml(vehicle, layout);
        });

        html += '</div>';
        return html;
    }

    /**
     * Generate individual vehicle card HTML
     */
    function generateVehicleCardHtml(vehicle, layout) {
        const cardClass = layout === 'list' ? 'rv-vehicle-card-list' : 'rv-vehicle-card-grid';

        return `
            <div class="rv-vehicle-card ${cardClass}" data-vehicle-id="${vehicle.id}">
                <div class="rv-vehicle-image">
                    ${vehicle.featured_image.url ?
                `<img src="${vehicle.featured_image.url}" alt="${vehicle.featured_image.alt}" loading="lazy">` :
                `<div class="rv-no-image"><svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM5 19V5h14v14H5z"/><path d="M7 7h10v6H7z"/></svg></div>`
            }
                    <div class="rv-price-badge">
                        <span class="rv-price-amount">${vehicle.currency_symbol}${parseFloat(vehicle.price_per_day).toLocaleString()}</span>
                        <span class="rv-price-period">/day</span>
                    </div>
                </div>
                <div class="rv-vehicle-info">
                    <h3 class="rv-vehicle-title">
                        <a href="${vehicle.url}">${vehicle.title}</a>
                    </h3>
                    ${vehicle.brand || vehicle.model || vehicle.year ? `
                        <p class="rv-vehicle-meta">
                            ${vehicle.brand ? `<span class="rv-brand">${vehicle.brand}</span>` : ''}
                            ${vehicle.model ? `<span class="rv-model">${vehicle.model}</span>` : ''}
                            ${vehicle.year ? `<span class="rv-year">${vehicle.year}</span>` : ''}
                        </p>
                    ` : ''}
                    <div class="rv-vehicle-features">
                        ${vehicle.fuel_type ? `
                            <span class="rv-feature">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                                ${vehicle.fuel_type}
                            </span>
                        ` : ''}
                        ${vehicle.transmission ? `
                            <span class="rv-feature">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                ${vehicle.transmission}
                            </span>
                        ` : ''}
                        ${vehicle.seats ? `
                            <span class="rv-feature">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                ${vehicle.seats} seats
                            </span>
                        ` : ''}
                    </div>
                    ${vehicle.rating && vehicle.rating.average > 0 ? `
                        <div class="rv-vehicle-rating">
                            <div class="rv-stars">
                                ${Array(5).fill().map((_, i) =>
                `<span class="rv-star ${i < vehicle.rating.average ? 'filled' : ''}">★</span>`
            ).join('')}
                            </div>
                            <span class="rv-rating-count">
                                (${vehicle.rating.count} ${vehicle.rating.count === 1 ? 'review' : 'reviews'})
                            </span>
                        </div>
                    ` : ''}
                    <div class="rv-vehicle-actions">
                        <a href="${vehicle.url}" class="rv-btn rv-btn-primary">View Details</a>
                        <button type="button" class="rv-btn rv-btn-secondary rv-add-to-favorites" data-vehicle-id="${vehicle.id}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Update pagination
     */
    function updatePagination(pagination) {
        const $pagination = $('#rv-pagination');
        if ($pagination.length === 0 || !pagination) return;

        // Generate pagination HTML
        let html = '<div class="page-numbers">';

        // Previous button
        if (pagination.has_prev) {
            html += `<a href="?page=${pagination.prev_page}" class="prev page-numbers">← Previous</a>`;
        }

        // Page numbers
        const startPage = Math.max(1, pagination.current - 2);
        const endPage = Math.min(pagination.total, pagination.current + 2);

        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current ? 'current' : '';
            html += `<a href="?page=${i}" class="page-numbers ${activeClass}">${i}</a>`;
        }

        // Next button
        if (pagination.has_next) {
            html += `<a href="?page=${pagination.next_page}" class="next page-numbers">Next →</a>`;
        }

        html += '</div>';
        $pagination.html(html);
    }

    /**
     * Update results count
     */
    function updateResultsCount(total) {
        const $title = $('.rv-results-title');
        if ($title.length === 0) return;

        const text = total === 1 ?
            '1 vehicle found' :
            `${total} vehicles found`;

        $title.text(text);
    }

    /**
     * Update layout
     */
    function updateLayout(layout) {
        const $container = $('#rv-vehicles-list');
        if ($container.length === 0) return;

        $container.removeClass('rv-vehicles-grid rv-vehicles-list')
            .addClass(`rv-vehicles-${layout}`);

        // Update individual cards
        $container.find('.rv-vehicle-card')
            .removeClass('rv-vehicle-card-grid rv-vehicle-card-list')
            .addClass(`rv-vehicle-card-${layout}`);
    }

    /**
     * Toggle favorite
     */
    function toggleFavorite(vehicleId, $btn) {
        // This would typically make an AJAX call to add/remove from favorites
        // For now, we'll just toggle the visual state

        $btn.toggleClass('active');

        if ($btn.hasClass('active')) {
            $btn.css('color', '#e74c3c');
            showNotification('Added to favorites');
        } else {
            $btn.css('color', '');
            showNotification('Removed from favorites');
        }
    }

    /**
     * Update active filters count
     */
    function updateActiveFiltersCount() {
        const $form = $('#rv-filters-form');
        const activeFilters = $form.find('input:checked, input[type="number"]:not([value=""]), select:not([value=""])').length;

        // Update clear button text
        const $clearBtn = $('#rv-clear-filters');
        if (activeFilters > 0) {
            $clearBtn.text(`Clear All (${activeFilters})`);
            $clearBtn.show();
        } else {
            $clearBtn.hide();
        }
    }

    /**
     * Show loading indicator
     */
    function showLoadingIndicator() {
        const $indicator = $('#rv-loading-indicator');
        if ($indicator.length === 0) return;

        $indicator.show();
    }

    /**
     * Hide loading indicator
     */
    function hideLoadingIndicator() {
        const $indicator = $('#rv-loading-indicator');
        if ($indicator.length === 0) return;

        $indicator.hide();
    }

    /**
     * Show error message
     */
    function showError(message) {
        const $container = $('#rv-results-container');
        $container.html(`
            <div class="rv-error-message">
                <div class="rv-error-icon">⚠️</div>
                <h3>Error</h3>
                <p>${message}</p>
                <button type="button" class="rv-btn rv-btn-primary" onclick="location.reload()">
                    Try Again
                </button>
            </div>
        `);
    }

    /**
     * Show notification
     */
    function showNotification(message) {
        // Simple notification implementation
        const $notification = $(`
            <div class="rv-notification">
                ${message}
            </div>
        `);

        $('body').append($notification);

        setTimeout(() => {
            $notification.fadeOut(() => {
                $notification.remove();
            });
        }, 3000);
    }

})(jQuery);
