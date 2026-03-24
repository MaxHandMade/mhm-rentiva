/* global mhmFeaturedVehicles, Swiper */
(function ($) {
    'use strict';

    /**
     * Featured Vehicles Slider Initialization
     */
    const initFeaturedVehicles = () => {
        $('.mhm-featured-swiper').each(function () {
            const container = $(this);

            // Avoid double init
            if (container.hasClass('swiper-initialized')) {
                return;
            }

            const el = container[0];
            const cfg = JSON.parse(el.getAttribute('data-swiper') || '{}');
            const columns = cfg.columns || 3;
            const autoplayEnabled = cfg.autoplay !== false;
            const interval = cfg.interval || 5000;

            try {
                new Swiper(container[0], {
                    loop: true,
                    speed: 600,
                    autoplay: autoplayEnabled ? {
                        delay: interval,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    } : false,
                    pagination: {
                        el: container.find('.swiper-pagination')[0],
                        clickable: true,
                    },
                    breakpoints: {
                        0: {
                            slidesPerView: 1,
                            spaceBetween: 8,
                        },
                        782: {
                            slidesPerView: Math.min(2, columns),
                            spaceBetween: 10,
                        },
                        1024: {
                            slidesPerView: columns,
                            spaceBetween: 14,
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

    // Re-init on Customizer/Widget updates if needed
    $(document).on('mhm-rentiva-reinit-sliders', initFeaturedVehicles);

})(jQuery);
