/**
 * Transfer Results - Frontend JavaScript
 *
 * Handles transfer search results interactions:
 * - Result filtering
 * - Sorting
 * - Booking button actions
 *
 * @package MHMRentiva
 * @since 3.0.0
 */

(function ($) {
    'use strict';

    /**
     * Transfer Results Module
     */
    const MHMTransferResults = {
        /**
         * Initialize module
         */
        init: function () {
            this.bindEvents();
        },

        /**
         * Bind DOM events
         */
        bindEvents: function () {
            // Book Now button click
            $(document).on('click', '.mhm-transfer-card__btn', this.handleBookClick);

            // Sort change
            $(document).on('change', '.mhm-transfer-results__sort', this.handleSortChange);
        },

        /**
         * Handle Book Now button click
         * @param {Event} e Click event
         */
        handleBookClick: function (e) {
            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');
            const routeId = $btn.data('route-id');

            // Add loading state
            $btn.addClass('is-loading');
            $btn.prop('disabled', true);

            // Track click event if analytics available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'transfer_book_click', {
                    'vehicle_id': vehicleId,
                    'route_id': routeId
                });
            }
        },

        /**
         * Handle sort selection change
         * @param {Event} e Change event
         */
        handleSortChange: function (e) {
            const sortValue = $(this).val();
            const $container = $('.mhm-transfer-results');
            const $cards = $container.find('.mhm-transfer-card');

            // Sort cards based on selection
            const sorted = $cards.sort(function (a, b) {
                const priceA = parseFloat($(a).data('price')) || 0;
                const priceB = parseFloat($(b).data('price')) || 0;

                if (sortValue === 'price-asc') {
                    return priceA - priceB;
                } else if (sortValue === 'price-desc') {
                    return priceB - priceA;
                }
                return 0;
            });

            // Re-append sorted cards
            $container.append(sorted);
        },

        /**
         * Show loading state
         */
        showLoading: function () {
            $('.mhm-transfer-results').addClass('is-loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function () {
            $('.mhm-transfer-results').removeClass('is-loading');
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        MHMTransferResults.init();
    });

})(jQuery);
