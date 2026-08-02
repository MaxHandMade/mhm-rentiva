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
            // Swiper'in loop modu duplicate slide ihtiyacı duyar. Slide sayısı
            // columns * 2'den az ise duplicate yetersiz kalır ve geçişlerde
            // boş kart / flicker oluşur. Bu yüzden loop'u sadece yeterli
            // sayıda kart varsa açıyoruz.
            const slideCount = container.find('.swiper-slide').length;
            const enableLoop = slideCount > columns * 2;

            try {
                new Swiper(container[0], {
                    loop: enableLoop,
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

    // Elementor editor preview: the widget is injected/re-rendered after the page has
    // loaded, so $(document).ready never fires for it (this was why the slider showed
    // empty in the editor while the frontend worked). (1) Initialise immediately — when
    // Elementor injects this script alongside the rendered widget the markup is already
    // in the DOM. (2) Register Elementor's per-widget lifecycle hook so editing controls
    // (which re-render the widget node) re-initialise. initFeaturedVehicles is idempotent
    // via the swiper-initialized guard.
    if (document.readyState !== 'loading') {
        initFeaturedVehicles();
    }

    const registerElementorInit = function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction(
                'frontend/element_ready/mhmrentiva_featured_vehicles.default',
                initFeaturedVehicles
            );
            return true;
        }
        return false;
    };

    if (!registerElementorInit()) {
        $(window).on('elementor/frontend/init', registerElementorInit);
    }

})(jQuery);
