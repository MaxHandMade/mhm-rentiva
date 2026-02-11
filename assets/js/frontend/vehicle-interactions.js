/**
 * MHM Rentiva - Vehicle Interactions (Favorites & Compare)
 * 
 * Handles optimistic UI updates and AJAX requests for vehicle actions.
 */
(function ($) {
    'use strict';

    const RentivaInteractions = {
        init: function () {
            if (typeof mhm_rentiva_vars === 'undefined') {
                console.error('MHM Rentiva: mhm_rentiva_vars is not defined. JS interactions disabled.');
                return;
            }
            $(document).on('click', '.mhm-vehicle-favorite-btn', RentivaInteractions.toggleFavorite);
            $(document).on('click', '.mhm-vehicle-compare-btn', RentivaInteractions.toggleCompare);
        },

        toggleFavorite: function (e) {
            e.preventDefault();
            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');
            const nonce = $btn.data('nonce') || mhm_rentiva_vars.nonce;

            if ($btn.hasClass('loading')) return;
            $btn.addClass('loading');

            // Optimistic UI
            const isActive = $btn.hasClass('is-active');
            $btn.toggleClass('is-active');

            $.ajax({
                url: mhm_rentiva_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_toggle_favorite',
                    vehicle_id: vehicleId,
                    nonce: nonce
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    if (response.success) {
                        const isFavorited = response.data.is_favorite;

                        // Sync state
                        if (isFavorited) {
                            $btn.addClass('is-active');
                            $btn.attr('aria-pressed', 'true');
                            $btn.find('.text-label').text(mhm_rentiva_vars.i18n.remove_favorite || 'Remove from Favorites');
                        } else {
                            $btn.removeClass('is-active');
                            $btn.attr('aria-pressed', 'false');
                            $btn.find('.text-label').text(mhm_rentiva_vars.i18n.add_favorite || 'Add to Favorites');

                            // Handling "My Favorites" page: Remove card from DOM
                            const $wrapper = $btn.closest('.mhm-my-favorites-container, .rv-my-favorites-wrapper');
                            if ($wrapper.length) {
                                $btn.closest('.mhm-vehicle-card').fadeOut(400, function () {
                                    $(this).remove();
                                    // If last item removed, we might want to reload or show empty state
                                    if ($wrapper.find('.mhm-vehicle-card').length === 0) {
                                        location.reload();
                                    }
                                });
                            }
                        }

                        // Show notification
                        RentivaInteractions.showNotification(response.data.message, 'success');
                    } else {
                        // Revert on failure
                        $btn.toggleClass('is-active');
                        RentivaInteractions.showNotification(response.data.message || 'Error', 'error');
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    $btn.toggleClass('is-active'); // Revert
                    RentivaInteractions.showNotification('Network error', 'error');
                }
            });
        },

        toggleCompare: function (e) {
            e.preventDefault();
            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');
            const nonce = $btn.data('nonce') || mhm_rentiva_vars.nonce;

            if ($btn.hasClass('loading')) return;
            $btn.addClass('loading');

            // Optimistic UI
            $btn.toggleClass('is-active');

            $.ajax({
                url: mhm_rentiva_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_toggle_compare',
                    vehicle_id: vehicleId,
                    nonce: nonce
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    if (response.success) {
                        if (response.data.is_in_compare) {
                            $btn.addClass('is-active');
                        } else {
                            $btn.removeClass('is-active');
                        }
                        RentivaInteractions.showNotification(response.data.message, 'success');
                    } else {
                        // Revert on failure
                        $btn.toggleClass('is-active');
                        RentivaInteractions.showNotification(response.data.message || 'Error', 'error');
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    $btn.toggleClass('is-active');
                    RentivaInteractions.showNotification('Network error', 'error');
                }
            });
        },

        showNotification: function (message, type) {
            type = type || 'info';

            // Remove existing notifications
            $('.rv-notification').remove();

            const icon = type === 'success' ? '✓' : '!';
            const $notification = $(`
                <div class="rv-notification rv-notification--show rv-notification--${type}">
                    <div class="rv-notification-body">
                        <span class="rv-notification-icon-badge">${icon}</span>
                        <span class="rv-notification-text">${message}</span>
                    </div>
                </div>
            `);

            $('body').append($notification);

            // Auto-hide
            setTimeout(function () {
                $notification.fadeOut(400, function () {
                    $(this).remove();
                });
            }, 3500);
        }
    };

    // Export for global use
    window.mhm_show_notification = RentivaInteractions.showNotification;

    $(document).ready(function () {
        RentivaInteractions.init();
    });

})(jQuery);
