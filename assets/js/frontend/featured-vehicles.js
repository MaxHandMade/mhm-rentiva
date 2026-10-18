/* global mhmFeaturedVehicles, Swiper */
(function ($) {
    'use strict';

    /**
     * Featured Vehicles Slider Initialization
     */
    let idCounter = 0;

    // Fresh, document-unique id for every mount. A re-rendered Elementor node is a
    // copy that still carries the previous id, so an existing attribute is overwritten.
    const nextId = () => {
        let id;
        do {
            id = Date.now().toString(36) + '-' + (++idCounter);
        } while (document.querySelector('[data-rv-swiper="' + id + '"]'));
        return id;
    };

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

            // Mount by selector string. In the Elementor editor the widget nodes are
            // created in the parent (top) editor window realm and then moved into the
            // preview iframe, while this script and Swiper run inside the iframe. So
            // Swiper's `instanceof HTMLElement` (the iframe's HTMLElement) rejects the
            // parent-realm node and an element argument fails silently. The selector
            // branch uses document.querySelector and has no instanceof check.
            const id = nextId();
            el.setAttribute('data-rv-swiper', id);
            const selector = '[data-rv-swiper="' + id + '"]';

            try {
                const result = new Swiper(selector, {
                    loop: enableLoop,
                    speed: 600,
                    autoplay: autoplayEnabled ? {
                        delay: interval,
                        disableOnInteraction: false,
                        pauseOnMouseEnter: true,
                    } : false,
                    pagination: {
                        el: selector + ' .swiper-pagination',
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
                if (Array.isArray(result) || !result || !result.initialized) {
                    console.warn('MHM Rentiva: Swiper did not initialise', selector);
                }
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
