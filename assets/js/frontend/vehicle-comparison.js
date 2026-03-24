/**
 * Vehicle Comparison JavaScript (v1.3.4)
 * 
 * Simplified "View + Remove" logic for the comparison page.
 * Uses canonical compare toggle for optimistic UI updates.
 */

(function ($) {
    'use strict';

    class VehicleComparison {
        constructor() {
            this.container = $('.rv-vehicle-comparison');
            if (this.container.length === 0) return;

            this.maxVehicles = parseInt(this.container.data('max-vehicles')) || 4;
            this.config = window.mhmRentivaVehicleComparison || {};

            this.init();
        }

        init() {
            this.bindEvents();
            this.adjustTableWidth();
        }

        bindEvents() {
            // Remove vehicle button (Optimistic)
            this.container.on('click', '.rv-remove-vehicle', (e) => {
                e.preventDefault();
                const $btn = $(e.currentTarget);
                const vehicleId = $btn.data('vehicle-id');

                if (vehicleId) {
                    this.removeVehicle(vehicleId, $btn);
                }
            });

            // Window resize event
            $(window).on('resize', () => {
                this.adjustTableWidth();
            });
        }

        /**
         * Remove vehicle via canonical toggle endpoint
         */
        removeVehicle(vehicleId, $btn) {
            // Optimistic Removal from DOM
            this.removeVehicleFromDOM(vehicleId);

            $.ajax({
                url: this.config.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_toggle_compare',
                    nonce: this.config.toggle_nonce || this.config.nonce,
                    vehicle_id: vehicleId
                },
                success: (response) => {
                    if (response.success && response.data.action === 'removed') {
                        // Success toast
                        if (window.mhmRentivaInteractions && typeof window.mhmRentivaInteractions.showToast === 'function') {
                            window.mhmRentivaInteractions.showToast(response.data.message, 'success');
                        }

                        // Check threshold after removal
                        this.checkThreshold(response.data.count);
                    } else {
                        // Revert on failure? For now just log and alert
                        console.error('Failed to remove vehicle from service:', response.data?.message);
                        location.reload(); // Hard reset on logic error
                    }
                },
                error: () => {
                    console.error('AJAX error during vehicle removal');
                    location.reload();
                }
            });
        }

        /**
         * Optimistically remove columns/cards from DOM
         */
        removeVehicleFromDOM(vehicleId) {
            // 1. Handle Table Layout
            const $headers = this.container.find(`th[data-vehicle-id="${vehicleId}"]`);
            if ($headers.length) {
                const colIndex = $headers.index();
                $headers.remove();

                // Remove cells in tbody
                this.container.find('tbody tr').each(function () {
                    $(this).find('td').eq(colIndex).remove();
                });
            }

            // 2. Handle Cards Layout
            this.container.find(`.rv-vehicle-card[data-vehicle-id="${vehicleId}"]`).fadeOut(300, function () {
                $(this).remove();
            });

            // 3. Handle Mobile Cards
            this.container.find(`.rv-mobile-card-item[data-vehicle-id="${vehicleId}"]`).fadeOut(300, function () {
                $(this).remove();
            });

            this.adjustTableWidth();
        }

        /**
         * Check if we still have at least 2 vehicles to compare
         */
        checkThreshold(count) {
            if (count < 2) {
                // If count is low, we might want to show empty state immediately or let PHP do it on next load
                // Authoritative rule: No JS template recreation. 
                // So if we fall below threshold, we just reload or show a simple "needs more" overlay.
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                this.updateCountDisplay(count);
            }
        }

        updateCountDisplay(count) {
            const $countEl = this.container.find('.rv-comparison-count');
            if ($countEl.length) {
                const template = count === 1 ?
                    (this.config.one_vehicle_compared || '1 vehicle compared') :
                    (this.config.multiple_vehicles_compared || '%d vehicles compared');

                $countEl.text(template.replace('%d', count));
            }
        }

        adjustTableWidth() {
            const $table = this.container.find('.rv-comparison-table');
            if ($table.length === 0) return;

            // Simple responsive adjustment
            if (window.innerWidth <= 768) {
                $table.parent().css('overflow-x', 'auto');
            } else {
                $table.css('width', '100%');
            }
        }
    }

    // Initialize on document ready
    $(document).ready(() => {
        new VehicleComparison();
    });

})(jQuery);
