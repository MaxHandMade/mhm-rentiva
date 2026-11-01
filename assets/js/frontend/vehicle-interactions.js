/**
 * MHM Rentiva - Vehicle Interactions (Favorites & Compare)
 * 
 * Handles optimistic UI updates and AJAX requests for vehicle actions.
 */
(function ($) {
    'use strict';

    /**
     * Read one localized string. mhmrentiva_vars has exactly one definition
     * (AssetManager::enqueue_vehicle_interactions), which carries every key used
     * here. A missing key costs that one label instead of throwing mid-handler
     * and leaving the button stuck in its optimistic state.
     */
    function t(key) {
        const strings = (typeof mhmrentiva_vars !== 'undefined' && mhmrentiva_vars.i18n) || {};
        return strings[key] || '';
    }

    /**
     * A link with the rental window of this page's address (the server's names,
     * VehicleCardLinks::FORWARD_KEYS): results refresh by AJAX, so the address is
     * the one place the visitor's dates still are.
     */
    function withWindow(url) {
        const keys = (typeof mhmrentiva_vars !== 'undefined' && mhmrentiva_vars.forward_keys) || [];
        if (!url || !keys.length) {
            return url;
        }
        const here = new URLSearchParams(window.location.search);
        const target = new URL(url, window.location.href);
        keys.forEach(function (key) {
            if (here.get(key) && !target.searchParams.has(key)) {
                target.searchParams.set(key, here.get(key));
            }
        });
        return target.toString();
    }

    /**
     * Compare toggles, one at a time. A guest's list lives in a cookie: two
     * requests in flight each start from the same cookie, the later answer
     * overwrites the earlier and a vehicle is lost. The list's state is applied
     * once, from the last answer, when nothing is waiting.
     */
    const CompareQueue = {
        waiting: [],
        running: false,
        latest: null,

        toggle: function (vehicleId, nonce, handlers) {
            this.waiting.push({ vehicleId: vehicleId, nonce: nonce, handlers: handlers });
            this.next();
        },

        next: function () {
            if (this.running) {
                return;
            }
            const job = this.waiting.shift();
            if (!job) {
                this.settle();
                return;
            }
            this.running = true;
            const queue = this;
            $.ajax({
                url: mhmrentiva_vars.ajax_url,
                type: 'POST',
                // A request that never answers must not hold the others back.
                timeout: 30000,
                data: {
                    action: 'mhmrentiva_toggle_compare',
                    vehicle_id: job.vehicleId,
                    nonce: job.nonce
                },
                success: function (response) {
                    queue.running = false;
                    if (response && response.success && response.data) {
                        queue.latest = response.data;
                    }
                    try {
                        job.handlers.success(response);
                    } finally {
                        queue.next();
                    }
                },
                error: function () {
                    queue.running = false;
                    try {
                        job.handlers.error();
                    } finally {
                        queue.next();
                    }
                }
            });
        },

        settle: function () {
            const data = this.latest;
            this.latest = null;
            if (!data) {
                return;
            }
            if (Array.isArray(data.items)) {
                const ids = data.items.map(function (item) { return String(item.id); });
                $('input[data-rvs-compare]').each(function () {
                    if (!$(this).data('busy')) {
                        $(this).prop('checked', ids.indexOf(String($(this).data('vehicle-id'))) !== -1);
                    }
                });
            }
            $(document).trigger('mhm-rentiva:compare-changed', [data]);
        }
    };

    window.MHMRentivaCompare = {
        toggle: function (vehicleId, nonce, handlers) {
            CompareQueue.toggle(vehicleId, nonce, handlers);
        },
        withWindow: withWindow
    };

    const RentivaInteractions = {
        init: function () {
            if (typeof mhmrentiva_vars === 'undefined') {
                // Expected on FSE pages where vehicle interactions data is not localized
                return;
            }
            // The favourite button keeps .mhm-vehicle-favorite-btn on the front-end
            // standard card too ([data-rvs-fav]); the old classes also serve the
            // Pro transfer card and the booking form's vehicle summary.
            $(document).on('click', '.mhm-vehicle-favorite-btn', RentivaInteractions.toggleFavorite);
            $(document).on('click', '.mhm-vehicle-compare-btn', RentivaInteractions.toggleCompare);
            // The standard card's compare control is a checkbox.
            $(document).on('change', 'input[data-rvs-compare]', RentivaInteractions.toggleCompareCheckbox);
        },

        toggleCompareCheckbox: function () {
            const $box = $(this);
            const vehicleId = $box.data('vehicle-id');
            const nonce = $box.data('nonce') || mhmrentiva_vars.nonce;
            const wanted = $box.prop('checked');

            if ($box.data('busy')) {
                $box.prop('checked', !wanted);
                return;
            }
            $box.data('busy', true);

            const idempotencyKey = wanted ? `compare:add:${vehicleId}` : `compare:remove:${vehicleId}`;
            MHMRentivaToast.show(wanted ? t('adding_compare') : t('removing_compare'), {
                type: 'info',
                idempotencyKey: idempotencyKey,
                duration: 0
            });

            CompareQueue.toggle(vehicleId, nonce, {
                success: function (response) {
                    $box.data('busy', false);
                    if (!response.success) {
                        $box.prop('checked', !wanted);
                        MHMRentivaToast.show((response.data && response.data.message) || t('error'), { type: 'error' });
                        return;
                    }
                    const isInCompare = !!response.data.is_in_compare;
                    $box.prop('checked', isInCompare);

                    const options = { type: 'success', idempotencyKey: idempotencyKey, duration: 3000 };
                    if (isInCompare && mhmrentiva_vars.compare_page_url) {
                        options.action = {
                            label: t('view_comparison'),
                            href: withWindow(mhmrentiva_vars.compare_page_url)
                        };
                    }
                    MHMRentivaToast.show(isInCompare ? t('added_to_compare') : t('removed_from_compare'), options);
                },
                error: function () {
                    $box.data('busy', false);
                    $box.prop('checked', !wanted);
                    MHMRentivaToast.show(t('network_error'), { type: 'error' });
                }
            });
        },

        toggleFavorite: function (e) {
            e.preventDefault();
            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');
            const nonce = $btn.data('nonce') || mhmrentiva_vars.nonce;

            if ($btn.hasClass('loading')) return;
            $btn.addClass('loading');

            // Optimistic UI
            const isActive = $btn.hasClass('is-active');
            $btn.toggleClass('is-active');

            // Show optimistic toast (Two-Stage)
            const isAdd = !isActive;
            const optimisticMsg = isAdd ? t('adding_favorite') : t('removing_favorite');
            const idempotencyKey = isAdd ? `fav:add:${vehicleId}` : `fav:remove:${vehicleId}`;

            const toastId = MHMRentivaToast.show(optimisticMsg, {
                type: 'info',
                idempotencyKey: idempotencyKey,
                duration: 0 // Sticky while processing
            });

            $.ajax({
                url: mhmrentiva_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhmrentiva_toggle_favorite',
                    vehicle_id: vehicleId,
                    nonce: nonce
                },
                success: function (response) {
                    $btn.removeClass('loading');
                    if (response.success) {
                        const isFavorited = response.data.is_favorite;
                        const favoriteLabel = isFavorited ?
                            t('remove_favorite') :
                            t('add_favorite');

                        // Sync state
                        if (isFavorited) {
                            $btn.addClass('is-active');
                            $btn.attr('aria-pressed', 'true');
                        } else {
                            $btn.removeClass('is-active');
                            $btn.attr('aria-pressed', 'false');

                            // Handling "My Favorites" page: Remove card from DOM
                            const $wrapper = $btn.closest('.mhm-my-favorites-container, .rv-my-favorites-wrapper');
                            if ($wrapper.length) {
                                $btn.closest('[data-rvs-card], .mhm-vehicle-card').fadeOut(400, function () {
                                    $(this).remove();
                                    if ($wrapper.find('[data-rvs-card], .mhm-vehicle-card').length === 0) {
                                        location.reload();
                                    }
                                });
                            }
                        }
                        $btn.attr('aria-label', favoriteLabel);
                        $btn.attr('title', favoriteLabel);
                        $btn.find('.text-label').text(favoriteLabel);

                        // Final toast with action (Two-Stage Update)
                        const options = {
                            type: 'success',
                            idempotencyKey: idempotencyKey,
                            duration: 3000
                        };

                        if (isFavorited && mhmrentiva_vars.favorites_page_url) {
                            options.action = {
                                label: t('go_to_favorites'),
                                href: mhmrentiva_vars.favorites_page_url
                            };
                        }

                        const finalMsg = isFavorited ? t('added_favorite') : t('removed_favorite');
                        MHMRentivaToast.show(finalMsg || response.data.message, options);
                    } else {
                        // Revert on failure
                        $btn.toggleClass('is-active');
                        MHMRentivaToast.show((response.data && response.data.message) || t('error'), { type: 'error' });
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    $btn.toggleClass('is-active'); // Revert
                    MHMRentivaToast.show(t('network_error'), { type: 'error' });
                }
            });
        },

        toggleCompare: function (e) {
            e.preventDefault();
            const $btn = $(this);
            const vehicleId = $btn.data('vehicle-id');
            const nonce = $btn.data('nonce') || mhmrentiva_vars.nonce;

            if ($btn.hasClass('loading')) return;
            $btn.addClass('loading');

            const isCurrentlyActive = $btn.hasClass('is-active') || $btn.hasClass('active');
            // Show optimistic toast (Two-Stage)
            const isAdd = !isCurrentlyActive;
            const optimisticMessage = isAdd ? t('adding_compare') : t('removing_compare');
            const idempotencyKey = isAdd ? `compare:add:${vehicleId}` : `compare:remove:${vehicleId}`;

            MHMRentivaToast.show(optimisticMessage, {
                type: 'info',
                idempotencyKey: idempotencyKey,
                duration: 0 // Sticky while processing
            });

            CompareQueue.toggle(vehicleId, nonce, {
                success: function (response) {
                    $btn.removeClass('loading');
                    if (response.success) {
                        const isInCompare = response.data.is_in_compare;
                        const compareLabel = isInCompare ?
                            t('remove_compare') :
                            t('add_compare');
                        // Final Sync
                        if (isInCompare) {
                            $btn.addClass('is-active active');
                            $btn.attr('aria-pressed', 'true');
                        } else {
                            $btn.removeClass('is-active active');
                            $btn.attr('aria-pressed', 'false');
                        }
                        $btn.attr('aria-label', compareLabel);
                        $btn.attr('title', compareLabel);
                        $btn.find('.text-label').text(compareLabel);

                        const options = {
                            type: 'success',
                            idempotencyKey: idempotencyKey,
                            duration: 3000
                        };

                        if (isInCompare && mhmrentiva_vars.compare_page_url) {
                            options.action = {
                                label: t('view_comparison'),
                                href: withWindow(mhmrentiva_vars.compare_page_url)
                            };
                        }

                        const finalMsg = isInCompare ? t('added_to_compare') : t('removed_from_compare');
                        MHMRentivaToast.show(finalMsg || response.data.message, options);
                    } else {
                        // Revert on failure
                        $btn.removeClass('is-active active');
                        if (isCurrentlyActive) $btn.addClass('is-active active');
                        $btn.attr('aria-pressed', isCurrentlyActive);
                        $btn.removeClass('loading');

                        MHMRentivaToast.show((response.data && response.data.message) || t('error'), { type: 'error' });
                    }
                },
                error: function () {
                    $btn.removeClass('loading');
                    // Revert
                    $btn.removeClass('is-active active');
                    if (isCurrentlyActive) $btn.addClass('is-active active');
                    $btn.attr('aria-pressed', isCurrentlyActive);

                    MHMRentivaToast.show(t('network_error'), { type: 'error' });
                }
            });
        }
    };

    $(document).ready(function () {
        RentivaInteractions.init();
        // The results band's link (R3): the server gave it the first paint's window.
        $('[data-rvs-compare-link]').each(function () {
            this.setAttribute('href', withWindow(this.getAttribute('href')));
        });
    });

})(jQuery);
