/**
 * Vehicles Grid JavaScript
 * 
 * Grid layout için özel JavaScript - sadece grid düzenini destekler
 */

(function ($) {
    'use strict';

    // Global namespace
    window.MHMRentivaVehiclesGrid = {
        initialized: false,

        init: function () {
            if (this.initialized) return;

            this.bindEvents();
            this.initLazyLoading();
            this.initialized = true;

        },

        bindEvents: function () {
            var self = this;

            // Favorite button clicks
            $(document).on('click', '.rv-vehicle-card__favorite', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.handleFavoriteClick($(this));
            });

            // Booking button clicks
            $(document).on('click', '.rv-btn-booking', function (e) {
                self.handleBookingClick($(this));
            });

            // Card clicks for analytics
            $(document).on('click', '.rv-vehicle-card', function (e) {
                if (!$(e.target).closest('.rv-vehicle-card__favorite, .rv-btn-booking').length) {
                    self.handleCardClick($(this));
                }
            });

            // Window resize
            $(window).on('resize', $.debounce(250, function () {
                self.handleResize();
            }));
        },

        handleFavoriteClick: function ($button) {
            var vehicleId = $button.data('vehicle-id');
            var $card = $button.closest('.rv-vehicle-card');

            if (!vehicleId) return;

            // Loading state
            $button.prop('disabled', true);

            $.ajax({
                url: mhmRentivaVehiclesGrid.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_toggle_favorite',
                    vehicle_id: vehicleId,
                    nonce: mhmRentivaVehiclesGrid.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Update button state
                        if (response.data.action === 'added') {
                            $button.addClass('is-favorited');
                            $button.find('svg').attr('fill', 'currentColor');
                        } else {
                            $button.removeClass('is-favorited');
                            $button.find('svg').attr('fill', 'none');
                        }

                        // Show notification
                        self.showNotification(response.data.message, 'success');

                        // Analytics tracking
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'favorite_toggle', {
                                'vehicle_id': vehicleId,
                                'action': response.data.action,
                                'event_category': 'engagement'
                            });
                        }
                    } else {
                        self.showNotification(response.data.message || (window.mhmRentivaVehiclesGrid?.strings?.error_occurred || 'An error occurred'), 'error');
                    }
                },
                error: function () {
                    self.showNotification(window.mhmRentivaVehiclesGrid?.strings?.connection_error || 'Connection error', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false);
                }
            });
        },

        handleBookingClick: function ($button) {
            var href = $button.attr('href');
            var vehicleId = $button.closest('.rv-vehicle-card').data('vehicle-id');

            // Analytics tracking
            if (typeof gtag !== 'undefined') {
                gtag('event', 'booking_click', {
                    'vehicle_id': vehicleId,
                    'event_category': 'conversion',
                    'event_label': 'grid_booking_button'
                });
            }

            // Track in console for debugging
        },

        handleCardClick: function ($card) {
            var vehicleId = $card.data('vehicle-id');
            var vehicleTitle = $card.find('.rv-vehicle-card__title a').text();

            // Analytics tracking
            if (typeof gtag !== 'undefined') {
                gtag('event', 'vehicle_card_click', {
                    'vehicle_id': vehicleId,
                    'vehicle_title': vehicleTitle,
                    'event_category': 'engagement',
                    'event_label': 'grid_card'
                });
            }

        },

        initLazyLoading: function () {
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function (entries, observer) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src || img.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                }, {
                    rootMargin: '50px 0px',
                    threshold: 0.01
                });

                // Observe all images
                document.querySelectorAll('.rv-vehicle-card__image img[data-src]').forEach(function (img) {
                    imageObserver.observe(img);
                });
            }
        },

        handleResize: function () {
            // Grid responsive adjustments if needed
            var windowWidth = $(window).width();

            // Update grid gaps based on screen size
            $('.rv-vehicles-grid').each(function () {
                var $grid = $(this);

                if (windowWidth <= 480) {
                    $grid.addClass('rv-vehicles-grid--gap-small');
                } else if (windowWidth >= 1200) {
                    $grid.addClass('rv-vehicles-grid--gap-large');
                } else {
                    $grid.removeClass('rv-vehicles-grid--gap-small rv-vehicles-grid--gap-large');
                }
            });
        },

        showNotification: function (message, type) {
            type = type || 'info';

            // Create notification element
            var $notification = $('<div class="rv-notification rv-notification--' + type + '">' + message + '</div>');

            // Add to page
            $('body').append($notification);

            // Show with animation
            setTimeout(function () {
                $notification.addClass('rv-notification--show');
            }, 100);

            // Remove after 3 seconds
            setTimeout(function () {
                $notification.removeClass('rv-notification--show');
                setTimeout(function () {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        refresh: function () {
            // Refresh grid data
            location.reload();
        },

        filter: function (criteria) {
            // Filter vehicles by criteria
            // Implementation would go here
        },

        sort: function (orderBy, order) {
            // Sort vehicles
            // Implementation would go here
        }
    };

    // Debounce utility function
    $.debounce = function (delay, fn) {
        var timer;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    };

    // Initialize when document is ready
    $(document).ready(function () {
        MHMRentivaVehiclesGrid.init();
    });

    // Re-initialize on AJAX content load using MutationObserver (modern alternative)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) { // Element node
                            var $node = $(node);
                            if ($node.hasClass('rv-vehicles-grid') || $node.find('.rv-vehicles-grid').length) {
                                MHMRentivaVehiclesGrid.init();
                            }
                        }
                    });
                }
            });
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

})(jQuery);
