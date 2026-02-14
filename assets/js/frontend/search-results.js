/**
 * Search Results JavaScript
 * Handles filtering, sorting, view toggles, and AJAX updates
 * in an instance-scoped way so multiple modules on same page do not clash.
 */
(function ($) {
    'use strict';

    const stateByInstance = {};

    function getInstanceId($scope) {
        return $scope.data('rv-instance') || 'default';
    }

    function getState($scope) {
        const id = getInstanceId($scope);
        if (!stateByInstance[id]) {
            stateByInstance[id] = {
                isLoading: false,
                filterTimeout: null,
            };
        }
        return stateByInstance[id];
    }

    function normalizeFlag(value, fallback = '1') {
        const v = (value ?? fallback).toString();
        return (v === '1' || v === 'true') ? '1' : '0';
    }

    function getInstanceSettings($scope) {
        return {
            show_favorite_button: normalizeFlag($scope.data('show-favorite-button'), '1'),
            show_compare_button: normalizeFlag($scope.data('show-compare-button'), '1'),
            show_booking_btn: normalizeFlag($scope.data('show-booking-btn'), '1'),
            show_price: normalizeFlag($scope.data('show-price'), '1'),
            show_title: normalizeFlag($scope.data('show-title'), '1'),
            show_features: normalizeFlag($scope.data('show-features'), '1'),
            show_rating: normalizeFlag($scope.data('show-rating'), '1'),
            show_badges: normalizeFlag($scope.data('show-badges'), '1'),
            per_page: parseInt($scope.data('results-per-page'), 10) || 12,
        };
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $(document).ready(function () {
        $('.rv-search-results').each(function () {
            initializeInstance($(this));
        });
    });

    function initializeInstance($scope) {
        if ($scope.length === 0) return;

        initializeResultsPage($scope);
        initializeFilters($scope);
        initializeSorting($scope);
        initializeViewToggle($scope);
        initializeFavorites($scope);
        initializePagination($scope);
    }

    function initializeResultsPage($scope) {
        updateActiveFiltersCount($scope);
        initializePriceSliders($scope);
        initializeYearSliders($scope);
    }

    function initializeFilters($scope) {
        const $filtersForm = $scope.find('.rv-filters-form');
        const $clearBtn = $scope.find('.rv-clear-filters');

        if ($filtersForm.length === 0) return;

        $filtersForm.find('input, select').on('change', function () {
            handleFilterChange($scope);
        });

        $filtersForm.find('input[type="checkbox"]').on('change', function () {
            handleFilterChange($scope);
        });

        $clearBtn.on('click', function (e) {
            e.preventDefault();
            clearAllFilters($scope);
        });

        $filtersForm.find('input[type="number"]').on('input', function () {
            const state = getState($scope);
            clearTimeout(state.filterTimeout);
            state.filterTimeout = setTimeout(function () {
                handleFilterChange($scope);
            }, 500);
        });
    }

    function initializeSorting($scope) {
        const $sortSelect = $scope.find('.rv-sort-select');

        if ($sortSelect.length === 0) return;

        $sortSelect.on('change', function () {
            reloadResults($scope);
        });
    }

    function initializeViewToggle($scope) {
        const $viewBtns = $scope.find('.rv-view-btn');
        if ($viewBtns.length === 0) return;

        const state = getState($scope);
        const defaultView = ($scope.data('default-view') === 'list') ? 'list' : 'grid';
        const viewToUse = $scope.find('.rv-view-btn.active').data('view') || state.currentView || defaultView;
        state.currentView = (viewToUse === 'list') ? 'list' : 'grid';
        updateLayout($scope, viewToUse, false);

        $viewBtns.on('click', function () {
            const view = $(this).data('view');
            if (view !== 'grid' && view !== 'list') return;

            $viewBtns.removeClass('active');
            $(this).addClass('active');

            updateLayout($scope, view, true);
        });
    }

    function updateLayout($scope, view, persistState = true) {
        if (view !== 'grid' && view !== 'list') return;

        const $layoutContainer = $scope.find('.rv-results-content');
        const $layoutWrapper = $scope.find('.rv-results-content-wrapper');
        const $wrapper = $scope.find('.rv-vehicle-grid-wrapper');

        if ($layoutContainer.length > 0) {
            $layoutContainer.removeClass('rv-layout-grid rv-layout-list');
            $layoutContainer.addClass(`rv-layout-${view}`);
        }

        if ($layoutWrapper.length > 0) {
            $layoutWrapper.removeClass('rv-layout-grid rv-layout-list');
            $layoutWrapper.addClass(`rv-layout-${view}`);
        }

        if ($wrapper.length > 0) {
            const $cards = $wrapper.find('.mhm-vehicle-card');
            $cards.removeClass('mhm-card--grid mhm-card--list');
            $cards.addClass(`mhm-card--${view}`);
        }

        if (persistState) {
            const state = getState($scope);
            state.currentView = view;
        }

        $scope.find('.rv-view-btn').removeClass('active');
        $scope.find(`.rv-view-btn[data-view="${view}"]`).addClass('active');
    }

    function initializeFavorites($scope) {
        $scope.on('click', '.mhm-card-favorite', function (e) {
            e.preventDefault();

            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');
            if (!vehicleId) return;

            toggleFavorite(vehicleId, $btn);
        });
    }

    function initializePagination($scope) {
        $scope.on('click', '.rv-pagination .page-numbers a', function (e) {
            e.preventDefault();
            const url = $(this).attr('href');
            if (url) {
                window.location.href = url;
            }
        });
    }

    function initializePriceSliders($scope) {
        const $slider = $scope.find('.rv-price-slider');
        if ($slider.length === 0) return;

        $slider.on('click', function (e) {
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const maxPrice = parseFloat($(this).data('max-price') || 10000);
            const price = Math.round(percent * maxPrice);

            $scope.find('input[name="max_price"]').val(price);
            handleFilterChange($scope);
        });
    }

    function initializeYearSliders($scope) {
        const $slider = $scope.find('.rv-year-slider');
        if ($slider.length === 0) return;

        $slider.on('click', function (e) {
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const minYear = parseInt($(this).data('min-year') || 1990, 10);
            const maxYear = parseInt($(this).data('max-year') || new Date().getFullYear(), 10);
            const year = Math.round(minYear + (percent * (maxYear - minYear)));

            $scope.find('input[name="year_max"]').val(year);
            handleFilterChange($scope);
        });
    }

    function handleFilterChange($scope) {
        const state = getState($scope);
        if (state.isLoading) return;

        reloadResults($scope);
        updateActiveFiltersCount($scope);
    }

    function clearAllFilters($scope) {
        const $form = $scope.find('.rv-filters-form');

        $form.find('input[type="checkbox"]').prop('checked', false);
        $form.find('input[type="number"]').val('');

        reloadResults($scope);
        updateActiveFiltersCount($scope);
    }

    function reloadResults($scope) {
        const state = getState($scope);
        if (state.isLoading) return;

        state.isLoading = true;
        showLoadingIndicator($scope);

        const currentLayout = state.currentView ||
            $scope.find('.rv-view-btn.active').data('view') ||
            (($scope.data('default-view') === 'list') ? 'list' : 'grid');

        const sortValue = $scope.find('.rv-sort-select').val() || '';
        const settings = getInstanceSettings($scope);

        const data = {
            action: 'mhm_rentiva_filter_results',
            nonce: mhmRentivaSearchResults.nonce,
            layout: currentLayout,
            per_page: settings.per_page,
            sort: sortValue,
            ...settings,
            ...getCurrentFilters($scope)
        };

        $.ajax({
            url: mhmRentivaSearchResults.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success && response.data) {
                    updateResultsContainer($scope, response.data);

                    if (response.data.pagination) {
                        $scope.find('.rv-pagination').html(response.data.pagination);
                    }

                    if (response.data.meta && response.data.meta.total !== undefined) {
                        updateResultsCount($scope, response.data.meta.total);
                    }
                } else {
                    showError($scope, response.data?.message || 'An error occurred');
                }
            },
            error: function () {
                showError($scope, 'Network error. Please try again.');
            },
            complete: function () {
                state.isLoading = false;
                hideLoadingIndicator($scope);
            }
        });
    }

    function getCurrentFilters($scope) {
        const $form = $scope.find('.rv-filters-form');
        const filters = {};

        if ($form.length === 0) return {};

        const formData = $form.serializeArray();

        formData.forEach(function (item) {
            if (item.name.endsWith('[]')) {
                const key = item.name.replace('[]', '');
                if (!filters[key]) {
                    filters[key] = [];
                }
                filters[key].push(item.value);
            } else {
                filters[item.name] = item.value;
            }
        });

        return filters;
    }

    function updateResultsContainer($scope, data) {
        const $resultsContent = $scope.find('.rv-vehicle-grid-wrapper');
        const state = getState($scope);
        const currentLayout = state.currentView || (($scope.data('default-view') === 'list') ? 'list' : 'grid');

        if (data.html) {
            $resultsContent.html(data.html);
            setTimeout(function () {
                updateLayout($scope, currentLayout, false);
            }, 50);
        } else {
            const emptyHtml = `
                <div class="rv-no-results">
                    <div class="rv-no-results-icon">?</div>
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

    function updateResultsCount($scope, total) {
        const $title = $scope.find('.rv-results-title');
        if ($title.length === 0) return;

        const text = total === 1 ?
            (mhmRentivaSearchResults.i18n.vehicle_found || '%d vehicle found').replace('%d', '1') :
            (mhmRentivaSearchResults.i18n.vehicles_found || '%d vehicles found').replace('%d', total);

        $title.text(text);
    }

    function toggleFavorite(vehicleId, $btn) {
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

    function updateActiveFiltersCount($scope) {
        const $form = $scope.find('.rv-filters-form');
        if ($form.length === 0) return;

        let activeFilters = 0;
        activeFilters += $form.find('input[type="checkbox"]:checked').length;

        $form.find('input[type="number"]').each(function () {
            const value = $(this).val();
            if (value && value !== '' && parseFloat(value) > 0) {
                activeFilters++;
            }
        });

        const $clearBtn = $scope.find('.rv-clear-filters');
        const clearAllText = mhmRentivaSearchResults.i18n.clear_all || 'Clear All';

        if (activeFilters > 0) {
            const clearAllWithCount = (mhmRentivaSearchResults.i18n.clear_all_with_count || 'Clear All (%d)').replace('%d', activeFilters);
            $clearBtn.text(clearAllWithCount).show();
        } else {
            $clearBtn.text(clearAllText).hide();
        }
    }

    function showLoadingIndicator($scope) {
        $scope.find('.rv-loading-indicator').show();
    }

    function hideLoadingIndicator($scope) {
        $scope.find('.rv-loading-indicator').hide();
    }

    function showError($scope, message) {
        const $container = $scope.find('.rv-vehicle-grid-wrapper');
        const errorText = mhmRentivaSearchResults.i18n.error || 'Error';
        const tryAgainText = mhmRentivaSearchResults.i18n.try_again || 'Try Again';

        const html = `
            <div class="rv-error-message">
                <div class="rv-error-icon">!</div>
                <h3>${errorText}</h3>
                <p>${escapeHtml(message)}</p>
                <button type="button" class="rv-btn rv-btn-primary" onclick="location.reload()">
                    ${tryAgainText}
                </button>
            </div>
        `;

        $container.html(html);
    }

})(jQuery);
