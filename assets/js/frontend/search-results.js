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

        // Force sync layout on page load (multiple checks to ensure it works)
        setTimeout(function () {
            forceSyncLayout();
        }, 50);
        
        setTimeout(function () {
            forceSyncLayout();
        }, 200);
        
        setTimeout(function () {
            forceSyncLayout();
        }, 500);
    });

    /**
     * Force sync layout - ensures container class matches active button
     */
    function forceSyncLayout() {
        const $layoutContainer = $('#rv-results-layout-container');
        const $activeBtn = $('.rv-view-btn.active');
        
        if ($layoutContainer.length === 0 || $activeBtn.length === 0) {
            return; // Not on search results page
        }
        
        const activeView = $activeBtn.data('view');
        const containerClass = $layoutContainer.attr('class') || '';
        
        // Check if container class matches active button
        const hasGridClass = containerClass.includes('rv-layout-grid');
        const hasListClass = containerClass.includes('rv-layout-list');
        
        if (activeView === 'grid' && (!hasGridClass || hasListClass)) {
            // Button says grid but container doesn't have grid class or has list class
            $layoutContainer.removeClass('rv-layout-list rv-layout-grid').addClass('rv-layout-grid');
            // Force reflow
            $layoutContainer[0].offsetHeight;
            console.log('Force synced: Set container to grid layout');
        } else if (activeView === 'list' && (!hasListClass || hasGridClass)) {
            // Button says list but container doesn't have list class or has grid class
            $layoutContainer.removeClass('rv-layout-grid rv-layout-list').addClass('rv-layout-list');
            // Force reflow
            $layoutContainer[0].offsetHeight;
            console.log('Force synced: Set container to list layout');
        }
    }

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
        const $layoutContainer = $('#rv-results-layout-container');

        if ($viewBtns.length === 0) return;

        // Check which button is currently active (from PHP)
        let activeButtonView = null;
        $viewBtns.each(function () {
            if ($(this).hasClass('active')) {
                activeButtonView = $(this).data('view');
            }
        });

        // Get initial layout from PHP (container class) or default to grid
        let initialLayout = 'grid';
        if ($layoutContainer.length > 0) {
            const containerClass = $layoutContainer.attr('class') || '';
            if (containerClass.includes('rv-layout-list')) {
                initialLayout = 'list';
            } else if (containerClass.includes('rv-layout-grid')) {
                initialLayout = 'grid';
            }
        }

        // Priority: Active button > Saved preference > Container class > Default
        let viewToUse = initialLayout;
        if (activeButtonView && (activeButtonView === 'grid' || activeButtonView === 'list')) {
            viewToUse = activeButtonView;
        } else {
            const savedView = localStorage.getItem('mhm_rentiva_view_mode');
            if (savedView === 'list' || savedView === 'grid') {
                viewToUse = savedView;
            }
        }

        // Force sync: Update container class and button state
        $viewBtns.removeClass('active');
        $(`.rv-view-btn[data-view="${viewToUse}"]`).addClass('active');
        updateLayout(viewToUse, true); // Save to localStorage

        // Handle button clicks
        $viewBtns.on('click', function () {
            const view = $(this).data('view');

            if (!view || (view !== 'grid' && view !== 'list')) {
                console.warn('Invalid view mode:', view);
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
            console.warn('Invalid view mode:', view);
            return;
        }

        // TARGET THE NEW PERMANENT WRAPPER
        const $layoutContainer = $('#rv-results-layout-container');

        if ($layoutContainer.length > 0) {
            // Remove all layout classes first
            $layoutContainer.removeClass('rv-layout-grid rv-layout-list');
            // Add the correct layout class
            $layoutContainer.addClass(`rv-layout-${view}`);

            // Force a reflow to ensure CSS is applied
            $layoutContainer[0].offsetHeight;
        } else {
            console.warn('Layout container #rv-results-layout-container not found');
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

                    if (response.data.pagination) {
                        updatePagination(response.data.pagination);
                    }

                    if (response.data.total !== undefined) {
                        updateResultsCount(response.data.total);
                    }
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
        // Target the inner content area
        const $resultsContent = $('#rv-results-grid-content');
        const $mainContainer = $('#rv-results-container'); // Fallback

        const currentLayout = localStorage.getItem('mhm_rentiva_view_mode') || 'grid';

        // Update the content
        if (data.html) {
            if ($resultsContent.length > 0) {
                $resultsContent.html(data.html);
            } else if ($mainContainer.length > 0) {
                $mainContainer.html(data.html);
            }

            // Ensure layout wrapper state is correct
            updateLayout(currentLayout);

        } else if (data.vehicles && data.vehicles.length > 0) {
            // JSON fallback (client-side rendering)
            const html = generateVehiclesHtml(data.vehicles, currentLayout);

            if ($resultsContent.length > 0) {
                $resultsContent.html(html);
            } else if ($mainContainer.length > 0) {
                $mainContainer.html(html);
            }
        } else {
            // Empty state
            const emptyHtml = `
                <div class="rv-no-results">
                    <div class="rv-no-results-icon">🚗</div>
                    <h3>${mhmRentivaSearchResults.i18n.no_results || 'No vehicles found'}</h3>
                    <p>Try adjusting your search criteria or filters.</p>
                    <a href="${mhmRentivaSearchResults.search_page_url || '#'}" class="rv-back-to-search">
                        Back to Search
                    </a>
                </div>
             `;

            if ($resultsContent.length > 0) {
                $resultsContent.html(emptyHtml);
            } else if ($mainContainer.length > 0) {
                $mainContainer.html(emptyHtml);
            }
        }
    }

    /**
     * Generate vehicles HTML (String builder fallback)
     */
    function generateVehiclesHtml(vehicles, layout) {
        let html = '';
        vehicles.forEach(vehicle => {
            html += generateVehicleCardHtml(vehicle, layout);
        });
        return html;
    }

    /**
     * Generate individual vehicle card HTML
     */
    function generateVehicleCardHtml(vehicle, layout) {
        const esc = (str) => str || '';

        return `
            <div class="rv-vehicle-card" data-vehicle-id="${vehicle.id}">
                <div class="rv-vehicle-image">
                    ${vehicle.featured_image && vehicle.featured_image.url ?
                `<img src="${vehicle.featured_image.url}" alt="${esc(vehicle.featured_image.alt)}" loading="lazy">` :
                `<div class="rv-no-image">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM5 19V5h14v14H5z"/><path d="M7 7h10v6H7z"/>
                    </svg>
                 </div>`
            }
                    <div class="rv-price-badge">
                        <span class="rv-price-amount">${esc(vehicle.currency_symbol)}${parseFloat(vehicle.price_per_day).toLocaleString()}</span>
                        <span class="rv-price-period">/day</span>
                    </div>
                </div>
                
                <div class="rv-vehicle-info">
                    <h3 class="rv-vehicle-title">
                        <a href="${esc(vehicle.url)}">${esc(vehicle.title)}</a>
                    </h3>
                    
                    ${(vehicle.brand || vehicle.model || vehicle.year) ? `
                        <p class="rv-vehicle-meta">
                            ${vehicle.brand ? `<span class="rv-brand">${esc(vehicle.brand)}</span>` : ''}
                            ${vehicle.model ? `<span class="rv-model">${esc(vehicle.model)}</span>` : ''}
                            ${vehicle.year ? `<span class="rv-year">${esc(vehicle.year)}</span>` : ''}
                        </p>
                    ` : ''}
                    
                    <div class="rv-vehicle-features">
                        ${vehicle.fuel_type ? `
                            <span class="rv-feature">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                                ${esc(vehicle.fuel_type)}
                            </span>
                        ` : ''}
                        ${vehicle.transmission ? `
                            <span class="rv-feature">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                ${esc(vehicle.transmission)}
                            </span>
                        ` : ''}
                        ${vehicle.seats ? `
                            <span class="rv-feature">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                ${esc(vehicle.seats)} seats
                            </span>
                        ` : ''}
                    </div>
                    
                    ${(vehicle.rating && vehicle.rating.average > 0) ? `
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
                        <a href="${esc(vehicle.url)}" class="rv-btn rv-btn-primary">View Details</a>
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
     * Toggle favorite
     */
    function toggleFavorite(vehicleId, $btn) {
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
        $('#rv-loading-indicator').show();
    }

    /**
     * Hide loading indicator
     */
    function hideLoadingIndicator() {
        $('#rv-loading-indicator').hide();
    }

    /**
     * Show error message
     */
    function showError(message) {
        const $container = $('#rv-results-grid-content');

        const html = `
            <div class="rv-error-message">
                <div class="rv-error-icon">⚠️</div>
                <h3>Error</h3>
                <p>${message}</p>
                <button type="button" class="rv-btn rv-btn-primary" onclick="location.reload()">
                    Try Again
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
     * Show notification
     */
    function showNotification(message) {
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
