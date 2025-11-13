/**
 * Vehicles List JavaScript
 * 
 * Araç listesi shortcode'u için JavaScript işlevleri
 * 
 * @since 3.0.1
 */

(function ($) {
    'use strict';

    // Global object
    window.MHMRentivaVehiclesList = {
        initialized: false, // Tekrar yüklemeyi engelle

        init: function () {
            // Tekrar init'i engelle
            if (this.initialized) {
                return;
            }

            this.bindEvents();
            // Animasyonlar KALDIRILDI - Kullanıcı talebi

            this.initialized = true;
        },

        bindEvents: function () {
            var self = this;

            // Event namespace for easy unbind
            var ns = '.mhmRentivaVehiclesList';

            // Önce tüm event'leri kaldır (tekrar binding'i önle)
            $(document).off('click' + ns);
            $(document).off('mouseenter' + ns);
            $(document).off('mouseleave' + ns);

            // Favorite button clicks
            $(document).on('click' + ns, '.rv-vehicle-card__favorite', function (e) {
                e.preventDefault();
                self.handleFavoriteClick($(this));
            });

            // Booking button clicks
            $(document).on('click' + ns, '.rv-btn-booking', function (e) {
                e.preventDefault();
                self.handleBookingClick($(this));
            });

            // Card clicks (for navigation)
            $(document).on('click' + ns, '.rv-vehicle-card__title-link, .rv-vehicle-card__image-link', function (e) {
                self.handleCardClick($(this));
            });

            // Image hover effects
            $(document).on('mouseenter' + ns, '.rv-vehicle-card__image', function () {
                self.handleImageHover($(this));
            });

            $(document).on('mouseleave' + ns, '.rv-vehicle-card__image', function () {
                self.handleImageLeave($(this));
            });

            // Resize handler for responsive adjustments
            $(window).off('resize' + ns);
            $(window).on('resize' + ns, $.debounce(250, function () {
                self.handleResize();
            }));

            // Intersection Observer KALDIRILDI
        },

        handleFavoriteClick: function ($button) {
            var vehicleId = $button.data('vehicle-id');
            var $card = $button.closest('.rv-vehicle-card');
            var $icon = $button.find('.rv-heart-icon');

            if (!vehicleId) {
                this.showNotification(mhmRentivaVehiclesList.strings?.invalid_vehicle_id || mhmRentivaVehiclesList.i18n?.invalid_vehicle_id || 'Invalid vehicle ID', 'error');
                return;
            }

            // Toggle button state
            $button.prop('disabled', true);
            $icon.addClass('loading');

            // AJAX request
            $.ajax({
                url: mhmRentivaVehiclesList.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_toggle_favorite',
                    vehicle_id: vehicleId,
                    nonce: mhmRentivaVehiclesList.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var action = response.data.action;
                        var message = response.data.message;

                        // Update button state
                        if (action === 'added') {
                            $button.addClass('is-favorited');
                            $icon.addClass('favorited');
                            $card.addClass('is-favorite');
                            $button.find('svg').attr('fill', 'currentColor');
                        } else {
                            $button.removeClass('is-favorited');
                            $icon.removeClass('favorited');
                            $card.removeClass('is-favorite');
                            $button.find('svg').attr('fill', 'none');
                        }

                        // Show notification
                        MHMRentivaVehiclesList.showNotification(message, 'success');

                        // Trigger custom event
                        $(document).trigger('mhm_rentiva_favorite_toggled', [vehicleId, action]);

                    } else {
                        MHMRentivaVehiclesList.showNotification(response.data.message || (mhmRentivaVehiclesList.strings?.error_occurred || mhmRentivaVehiclesList.i18n?.error_occurred || 'An error occurred'), 'error');
                    }
                },
                error: function () {
                    MHMRentivaVehiclesList.showNotification(mhmRentivaVehiclesList.strings?.connection_error || mhmRentivaVehiclesList.i18n?.connection_error || 'Connection error', 'error');
                },
                complete: function () {
                    $button.prop('disabled', false);
                    $icon.removeClass('loading');
                }
            });
        },

        handleBookingClick: function ($button) {
            var vehicleId = $button.data('vehicle-id');
            var bookingUrl = $button.attr('href') || mhmRentivaVehiclesList.bookingUrl;

            if (!bookingUrl || bookingUrl === '#') {
                this.showNotification(mhmRentivaVehiclesList.strings?.booking_url_not_configured || mhmRentivaVehiclesList.i18n?.booking_url_not_configured || 'Booking URL is not configured', 'error');
                return;
            }

            // Navigate to booking page (URL already contains vehicle_id parameter)
            window.location.href = bookingUrl;
        },

        handleCardClick: function ($link) {
            var href = $link.attr('href');
            if (href && href !== '#') {
                // Add click tracking
                this.trackCardClick($link);

                // Navigate to vehicle detail
                window.location.href = href;
            }
        },

        handleImageHover: function ($image) {
            $image.addClass('is-hovered');

            // Show rating overlay if hidden
            var $ratingOverlay = $image.find('.rv-vehicle-card__rating-overlay');
            if ($ratingOverlay.length) {
                $ratingOverlay.addClass('is-visible');
            }
        },

        handleImageLeave: function ($image) {
            $image.removeClass('is-hovered');

            // Hide rating overlay
            var $ratingOverlay = $image.find('.rv-vehicle-card__rating-overlay');
            if ($ratingOverlay.length) {
                $ratingOverlay.removeClass('is-visible');
            }
        },

        handleResize: function () {
            // Update lazy loading
            this.updateLazyLoading();
        },

        initLazyLoading: function () {
            if ('IntersectionObserver' in window) {
                var lazyImages = document.querySelectorAll('.rv-vehicle-card__img[loading="lazy"]');

                var imageObserver = new IntersectionObserver(function (entries, observer) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;

                            // Zaten yüklendiyse tekrar yükleme
                            if (img.dataset.lazyLoaded === 'true') {
                                return;
                            }

                            img.src = img.dataset.src || img.src;
                            img.classList.remove('lazy');
                            img.dataset.lazyLoaded = 'true';
                            imageObserver.unobserve(img);
                        }
                    });
                });

                lazyImages.forEach(function (img) {
                    // Zaten observe edilmişse atla
                    if (!img.dataset.lazyObserved) {
                        img.dataset.lazyObserved = 'true';
                        imageObserver.observe(img);
                    }
                });
            }
        },

        initAnimations: function () {
            var $cards = $('.rv-vehicle-card');

            $cards.each(function (index) {
                var $card = $(this);

                // Zaten animasyon uygulandıysa atla
                if ($card.hasClass('animate-in') || $card.hasClass('animation-applied')) {
                    return;
                }

                // Animasyon flag'i ekle
                $card.addClass('animation-applied');

                // Stagger animation
                setTimeout(function () {
                    $card.addClass('animate-in');
                }, index * 100);
            });
        },

        initIntersectionObserver: function () {
            var $cards = $('.rv-vehicle-card');

            var cardObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        // Zaten is-visible class'ı varsa ekleme
                        if (!entry.target.classList.contains('is-visible')) {
                            entry.target.classList.add('is-visible');
                        }
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });

            $cards.each(function () {
                // Zaten observe edilmişse tekrar etme
                if (!this.dataset.observed) {
                    this.dataset.observed = 'true';
                    cardObserver.observe(this);
                }
            });
        },


        updateLazyLoading: function () {
            // Reinitialize lazy loading for new viewport
            this.initLazyLoading();
        },

        trackCardClick: function ($link) {
            var vehicleId = $link.closest('.rv-vehicle-card').data('vehicle-id');
            var linkType = $link.hasClass('rv-vehicle-card__title-link') ? 'title' : 'image';

            // Google Analytics tracking (if available)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'vehicle_card_click', {
                    'vehicle_id': vehicleId,
                    'link_type': linkType,
                    'page_title': document.title
                });
            }

            // Custom tracking event
            // Vehicle card shortcode kaldırıldı
        },

        showNotification: function (message, type) {
            type = type || 'info';

            // Create notification element
            var $notification = $('<div class="rv-notification rv-notification--' + type + '">' + message + '</div>');

            // Add to page
            $('body').append($notification);

            // Show notification
            setTimeout(function () {
                $notification.addClass('rv-notification--show');
            }, 100);

            // Hide notification after 3 seconds
            setTimeout(function () {
                $notification.removeClass('rv-notification--show');
                setTimeout(function () {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        // Public methods
        refresh: function () {
            location.reload();
        },

        filter: function (criteria) {
            // Implement filtering logic
        },

        sort: function (orderBy, order) {
            // Implement sorting logic
        }
    };

    // Debounce utility function
    $.debounce = function (delay, callback) {
        var timeout;
        return function () {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                callback.apply(context, args);
            }, delay);
        };
    };

    // Initialize when document is ready
    $(document).ready(function () {
        MHMRentivaVehiclesList.init();
    });

    // Initialize on AJAX content load
    $(document).on('mhm_rentiva_content_loaded', function () {
        MHMRentivaVehiclesList.init();
    });

})(jQuery);

// CSS for notifications (injected dynamically) - ANİMASYONSUZ
var notificationCSS = `
    .rv-notification {
        position: fixed;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 9999;
        min-width: 220px;
        padding: 0.875rem 1.25rem;
        border-radius: 10px;
        background-color: rgba(31, 41, 55, 0.92);
        color: #ffffff;
        font-size: 0.95rem;
        font-weight: 500;
        box-shadow: 0 18px 30px rgba(15, 23, 42, 0.18);
        opacity: 0;
        transform: translateY(-10px);
        transition: opacity 0.25s ease, transform 0.25s ease;
    }
    
    .rv-notification--show {
        opacity: 1;
        transform: translateY(0);
    }
    
    .rv-notification--success {
        background-color: rgba(16, 185, 129, 0.95);
        color: #0b1f17;
    }
    
    .rv-notification--error {
        background-color: rgba(248, 113, 113, 0.95);
        color: #2f0c0c;
    }
    
    .rv-notification--warning {
        background-color: rgba(251, 191, 36, 0.95);
        color: #2b1b02;
    }
    
    .rv-notification--info {
        background-color: rgba(96, 165, 250, 0.95);
        color: #0b1a2b;
    }
    
    @media (max-width: 600px) {
        .rv-notification {
            left: 1rem;
            right: 1rem;
            top: auto;
            bottom: 1.5rem;
            text-align: center;
        }
    }
    
    .rv-heart-icon.favorited {
        fill: #ef4444;
        color: #ef4444;
    }
    
    /* TÜM ANİMASYONLAR KALDIRILDI */
    .rv-vehicle-card {
        opacity: 1 !important;
        transform: none !important;
        animation: none !important;
    }
    
    .rv-vehicle-card__img {
        opacity: 1 !important;
        transition: none !important;
    }
`;

// Inject CSS
var style = document.createElement('style');
style.textContent = notificationCSS;
document.head.appendChild(style);
