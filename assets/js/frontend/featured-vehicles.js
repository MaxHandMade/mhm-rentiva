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
                const swiper = new Swiper(container[0], {
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
                            slidesPerView: Math.min(2, columns),
                            spaceBetween: 25,
                        },
                        // Desktop
                        1024: {
                            slidesPerView: columns,
                            spaceBetween: 30,
                        },
                    },
                });

                // After Swiper fully initializes, compact the slide heights
                // Use setTimeout to ensure DOM is fully painted
                setTimeout(() => compactSlideHeights(swiper), 100);

                // Also recalculate on window resize
                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => compactSlideHeights(swiper), 200);
                });

            } catch (e) {
                console.warn('MHM Rentiva: Swiper initialization failed', e);
            }
        });
    };

    /**
     * Recalculate slide heights based on actual card content.
     * Swiper forces all slides to wrapper height — we override
     * wrapper height to match the tallest card's natural content.
     */
    const compactSlideHeights = (swiper) => {
        if (!swiper || !swiper.slides || swiper.slides.length === 0) return;


        const slides = swiper.slides;

        // Step 1: Measure each card's natural height
        let maxCardHeight = 0;
        const origHeights = [];

        for (let i = 0; i < slides.length; i++) {
            const card = slides[i].querySelector('.mhm-vehicle-card');
            if (!card) continue;

            // Save original inline style
            origHeights.push({ slide: slides[i], card: card, origStyle: card.style.height });

            // Temporarily force auto height to measure natural content
            card.style.height = 'auto';
            slides[i].style.height = 'auto';
        }

        // Force reflow to get accurate measurements
        // eslint-disable-next-line no-unused-expressions
        swiper.wrapperEl.offsetHeight;

        for (let i = 0; i < slides.length; i++) {
            const card = slides[i].querySelector('.mhm-vehicle-card');
            if (!card) continue;
            const h = card.getBoundingClientRect().height;
            if (h > maxCardHeight) maxCardHeight = h;
        }

        if (maxCardHeight <= 0) {

            return;
        }

        // Step 2: Set all slides and cards to the tallest card's height
        const finalHeight = Math.ceil(maxCardHeight);


        for (let i = 0; i < slides.length; i++) {
            slides[i].style.height = finalHeight + 'px';
            const card = slides[i].querySelector('.mhm-vehicle-card');
            if (card) card.style.height = finalHeight + 'px';
        }

        // Step 3: Set wrapper height to match
        swiper.wrapperEl.style.height = finalHeight + 'px';
    };

    // Init on load
    $(document).ready(initFeaturedVehicles);

    // Re-init on Customizer/Widget updates if needed
    $(document).on('mhm-rentiva-reinit-sliders', initFeaturedVehicles);

})(jQuery);
