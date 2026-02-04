/* global mhmFeaturedVehicles, Swiper */
(function ($) {
    'use strict';

    /**
     * Featured Vehicles Slider Initialization
     */
    const initFeaturedVehicles = () => {
        $('.mhm-featured-swiper').each(function () {
            const container = $(this);
            const parent = container.closest('.mhm-rentiva-featured-wrapper');

            // Avoid double init
            if (container.hasClass('swiper-initialized')) {
                return;
            }

            const autoplayEnabled = parent.data('autoplay') === '1';
            const interval = parseInt(parent.data('interval')) || 5000;
            const columns = parseInt(parent.data('columns')) || 3;

            try {
                new Swiper(container[0], {
                    loop: true,
                    speed: 600,
                    spaceBetween: 30,
                    autoplay: autoplayEnabled ? {
                        delay: interval,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    } : false,
                    pagination: {
                        el: container.find('.swiper-pagination')[0],
                        clickable: true,
                    },
                    navigation: {
                        nextEl: container.find('.swiper-button-next')[0],
                        prevEl: container.find('.swiper-button-prev')[0],
                    },
                    breakpoints: {
                        // Mobile
                        0: {
                            slidesPerView: 1,
                            spaceBetween: 20,
                        },
                        // Tablet (782px standard)
                        782: {
                            slidesPerView: Math.min(2, columns), // Don't show more than max columns
                            spaceBetween: 25,
                        },
                        // Desktop
                        1024: {
                            slidesPerView: columns,
                            spaceBetween: 30,
                        },
                    },
                });
            } catch (e) {
                console.warn('MHM Rentiva: Swiper initialization failed', e);
            }
        });
    };

    // Init on load
    $(document).ready(initFeaturedVehicles);

    // Re-init on Customizer/Widget updates if needed (optional)
    $(document).on('mhm-rentiva-reinit-sliders', initFeaturedVehicles);

})(jQuery);
