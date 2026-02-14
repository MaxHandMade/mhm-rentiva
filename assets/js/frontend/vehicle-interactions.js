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

            // Show optimistic toast (Two-Stage)
            const isAdd = !isActive;
            const optimisticMsg = isAdd ? mhm_rentiva_vars.i18n.adding_favorite : mhm_rentiva_vars.i18n.removing_favorite;
            const idempotencyKey = isAdd ? `fav:add:${vehicleId}` : `fav:remove:${vehicleId}`;

            const toastId = MHMRentivaToast.show(optimisticMsg, {
                type: 'info',
                idempotencyKey: idempotencyKey,
                duration: 0 // Sticky while processing
            });

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
                                    if ($wrapper.find('.mhm-vehicle-card').length === 0) {
                                        location.reload();
                                    }
                                });
                            }
                        }

                        // Final toast with action (Two-Stage Update)
                        const options = {
                            type: 'success',
                            idempotencyKey: idempotencyKey,
                            duration: 3000
                        };

                        if (isFavorited && mhm_rentiva_vars.favorites_page_url) {
                            options.action = {
                                label: mhm_rentiva_vars.i18n.go_to_favorites || 'Go to My Favorites',
                                href: mhm_rentiva_vars.favorites_page_url
                            };
                        }

                        const finalMsg = isFavorited ? mhm_rentiva_vars.i18n.added_favorite : mhm_rentiva_vars.i18n.removed_favorite;
                        MHMRentivaToast.show(finalMsg || response.data.message, options);
                    } else {
                        // Revert on failure
                        $btn.toggleClass('is-active');
                        MHMRentivaToast.show(response.data.message || 'Error', { type: 'error' });
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    $btn.toggleClass('is-active'); // Revert
                    MHMRentivaToast.show('Network error', { type: 'error' });
                }
            });
        },

        toggleCompare: function (e) {
            e.preventDefault();
            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');
            const nonce = $btn.data('nonce') || mhm_rentiva_vars.nonce;

            if ($btn.hasClass('loading')) return;

            const isCurrentlyActive = $btn.hasClass('is-active') || $btn.hasClass('active');
            // Show optimistic toast (Two-Stage)
            const isAdd = !isCurrentlyActive;
            const optimisticMessage = isAdd ? mhm_rentiva_vars.i18n.adding_compare : mhm_rentiva_vars.i18n.removing_compare;
            const idempotencyKey = isAdd ? `compare:add:${vehicleId}` : `compare:remove:${vehicleId}`;

            MHMRentivaToast.show(optimisticMessage, {
                type: 'info',
                idempotencyKey: idempotencyKey,
                duration: 0 // Sticky while processing
            });

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

                        const options = {
                            type: 'success',
                            idempotencyKey: idempotencyKey,
                            duration: 3000
                        };

                        if (isInCompare && mhm_rentiva_vars.compare_page_url) {
                            options.action = {
                                label: mhm_rentiva_vars.i18n.view_comparison || 'View Comparison',
                                href: mhm_rentiva_vars.compare_page_url
                            };
                        }

                        const finalMsg = isInCompare ? mhm_rentiva_vars.i18n.added_to_compare : mhm_rentiva_vars.i18n.removed_from_compare;
                        MHMRentivaToast.show(finalMsg || response.data.message, options);
                    } else {
                        // Revert on failure
                        $btn.removeClass('is-active active');
                        if (isCurrentlyActive) $btn.addClass('is-active active');
                        $btn.attr('aria-pressed', isCurrentlyActive);
                        $btn.removeClass('loading');

                        MHMRentivaToast.show(response.data.message || 'Error', { type: 'error' });
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    // Revert
                    $btn.removeClass('is-active active');
                    if (isCurrentlyActive) $btn.addClass('is-active active');
                    $btn.attr('aria-pressed', isCurrentlyActive);

                    MHMRentivaToast.show('Network error', { type: 'error' });
                }
            });
        }
    };

    $(document).ready(function () {
        RentivaInteractions.init();
    });

})(jQuery);
