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

            // Determine action for optimistic toast
            const isCurrentlyActive = $btn.hasClass('is-active') || $btn.hasClass('active');
            const optimisticMessage = !isCurrentlyActive ?
                (mhm_rentiva_vars.i18n.added_to_compare || 'Added to comparison') :
                (mhm_rentiva_vars.i18n.removed_from_compare || 'Removed from comparison');

            // Optimistic UI update
            $btn.addClass('loading');
            $btn.toggleClass('is-active active');
            $btn.attr('aria-pressed', !isCurrentlyActive);

            // Perceived Speed: Show notification immediately
            RentivaInteractions.showNotification(optimisticMessage, 'success');

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
                        const isInCompare = response.data.is_in_compare;
                        // Final Sync
                        if (isInCompare) {
                            $btn.addClass('is-active active');
                            $btn.attr('aria-pressed', 'true');
                        } else {
                            $btn.removeClass('is-active active');
                            $btn.attr('aria-pressed', 'false');
                        }

                        // Threshold Logic & Navigation Hook
                        const count = response.data.count;
                        const action = response.data.action;
                        let message = response.data.message;
                        let ctaHtml = '';

                        if (action === 'added') {
                            if (count >= 2) {
                                if (mhm_rentiva_vars.compare_page_url && mhm_rentiva_vars.compare_page_url !== '#') {
                                    ctaHtml = `<a href="${mhm_rentiva_vars.compare_page_url}" class="rv-notification-cta">${mhm_rentiva_vars.i18n.view_comparison || 'View Comparison'}</a>`;
                                } else {
                                    console.warn('MHM Rentiva: Comparison page URL is missing in settings.');
                                }
                            } else {
                                message = mhm_rentiva_vars.i18n.add_one_more || 'Add one more vehicle to compare';
                            }
                        } else if (action === 'removed') {
                            if (count < 2 && count > 0) {
                                message = mhm_rentiva_vars.i18n.need_at_least_two || 'Comparison needs at least 2 vehicles';
                            }
                        }

                        // Update toast with final logic
                        RentivaInteractions.showNotification(message, 'success', ctaHtml);
                    } else {
                        // Revert on failure
                        $btn.removeClass('is-active active');
                        if (isCurrentlyActive) $btn.addClass('is-active active');
                        $btn.attr('aria-pressed', isCurrentlyActive);
                        $btn.removeClass('loading');

                        RentivaInteractions.showNotification(response.data.message || 'Error', 'error');
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    // Revert
                    $btn.removeClass('is-active active');
                    if (isCurrentlyActive) $btn.addClass('is-active active');
                    $btn.attr('aria-pressed', isCurrentlyActive);

                    RentivaInteractions.showNotification('Network error', 'error');
                }
            });
        },

        showNotification: function (message, type, actionHtml) {
            type = type || 'info';

            // Remove existing notifications
            $('.rv-notification').remove();

            const icon = type === 'success' ? '✓' : '!';
            const $notification = $(`
                <div class="rv-notification rv-notification--show rv-notification--${type}">
                    <div class="rv-notification-body">
                        <span class="rv-notification-icon-badge">${icon}</span>
                        <div class="rv-notification-content">
                            <span class="rv-notification-text">${message}</span>
                            ${actionHtml ? `<div class="rv-notification-actions">${actionHtml}</div>` : ''}
                        </div>
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
