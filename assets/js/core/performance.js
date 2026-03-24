/**
 * MHM Rentiva - Performance Optimizations
 * 
 * Lazy loading, debouncing, throttling and other performance improvements.
 */

(function ($) {
    'use strict';

    // Global MHM Rentiva namespace
    window.MHMRentiva = window.MHMRentiva || {};

    // Debug mode - set to false in production
    var DEBUG_MODE = false;

    /**
     * Performance Utility functions
     */
    MHMRentiva.Performance = {

        /**
         * Intersection Observer for lazy loading
         */
        observer: null,

        /**
         * Lazy loading elements
         */
        lazyElements: new Map(),

        /**
         * Cache system
         */
        cache: new Map(),

        /**
         * Performance metrics
         */
        metrics: {
            loadTime: 0,
            renderTime: 0,
            ajaxTime: 0,
            domReadyTime: 0
        },

        /**
         * Initialize performance optimizations
         */
        init: function () {
            this.initIntersectionObserver();
            this.initPerformanceMonitoring();
            this.initLazyLoading();
            this.initImageOptimization();
            this.initScrollOptimization();
            this.initResizeOptimization();
        },

        /**
         * Initialize Intersection Observer
         */
        initIntersectionObserver: function () {
            if ('IntersectionObserver' in window) {
                this.observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            this.handleLazyElement(entry.target);
                        }
                    });
                }, {
                    root: null,
                    rootMargin: '50px',
                    threshold: 0.1
                });
            }
        },

        /**
         * Initialize performance monitoring
         */
        initPerformanceMonitoring: function () {
            // Measure page load time
            window.addEventListener('load', () => {
                this.metrics.loadTime = performance.now();
                this.logPerformance('Page Load', this.metrics.loadTime);
            });

            // Measure DOM ready time
            $(document).ready(() => {
                this.metrics.domReadyTime = performance.now();
                this.logPerformance('DOM Ready', this.metrics.domReadyTime);
            });

            // Measure render time
            requestAnimationFrame(() => {
                this.metrics.renderTime = performance.now();
                this.logPerformance('First Paint', this.metrics.renderTime);
            });
        },

        /**
         * Initialize lazy loading
         */
        initLazyLoading: function () {
            // Find lazy loading elements
            $('[data-lazy]').each((index, element) => {
                this.registerLazyElement(element);
            });

            // Monitor dynamic elements with modern MutationObserver
            this.initMutationObserver();
        },

        /**
         * Monitor dynamic elements with MutationObserver
         */
        initMutationObserver: function () {
            // Browser compatibility check
            if (!window.MutationObserver) {
                if (typeof console !== 'undefined' && console.warn) {
                    console.warn('[MHM Performance] MutationObserver not supported, falling back to manual detection');
                }
                this.initFallbackDetection();
                return;
            }

            // Create MutationObserver
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    // Check newly added nodes
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Is the element itself lazy?
                            if (node.hasAttribute && node.hasAttribute('data-lazy')) {
                                this.registerLazyElement(node);
                            }

                            // Find lazy elements within the element
                            const lazyElements = node.querySelectorAll && node.querySelectorAll('[data-lazy]');
                            if (lazyElements) {
                                lazyElements.forEach((lazyEl) => {
                                    this.registerLazyElement(lazyEl);
                                });
                            }
                        }
                    });
                });
            });

            // Start observer
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });

            // Store observer globally (to stop if needed)
            this.mutationObserver = observer;
        },

        /**
         * Register lazy element
         * @param {HTMLElement} element - Element
         */
        registerLazyElement: function (element) {
            const $el = $(element);
            const lazyType = $el.data('lazy');
            const lazySrc = $el.data('lazy-src');
            const lazyCallback = $el.data('lazy-callback');

            if (this.observer) {
                this.observer.observe(element);
            }

            this.lazyElements.set(element, {
                type: lazyType,
                src: lazySrc,
                callback: lazyCallback,
                loaded: false
            });
        },

        /**
         * Handle lazy element
         * @param {HTMLElement} element - Element
         */
        handleLazyElement: function (element) {
            const lazyData = this.lazyElements.get(element);
            if (!lazyData || lazyData.loaded) return;

            const $el = $(element);

            switch (lazyData.type) {
                case 'image':
                    this.loadLazyImage($el, lazyData);
                    break;
                case 'content':
                    this.loadLazyContent($el, lazyData);
                    break;
                case 'script':
                    this.loadLazyScript($el, lazyData);
                    break;
                case 'component':
                    this.loadLazyComponent($el, lazyData);
                    break;
            }

            lazyData.loaded = true;
            this.lazyElements.delete(element);

            if (this.observer) {
                this.observer.unobserve(element);
            }
        },

        /**
         * Load lazy image
         * @param {jQuery} $el - Element
         * @param {Object} lazyData - Lazy data
         */
        loadLazyImage: function ($el, lazyData) {
            const img = new Image();
            img.onload = () => {
                $el.attr('src', lazyData.src);
                $el.removeClass('lazy-loading').addClass('lazy-loaded');
                this.triggerLazyCallback($el, lazyData.callback);
            };
            img.onerror = () => {
                $el.removeClass('lazy-loading').addClass('lazy-error');
            };
            img.src = lazyData.src;
        },

        /**
         * Load lazy content
         * @param {jQuery} $el - Element
         * @param {Object} lazyData - Lazy data
         */
        loadLazyContent: function ($el, lazyData) {
            if (lazyData.src) {
                $.get(lazyData.src)
                    .done((data) => {
                        $el.html(data);
                        $el.removeClass('lazy-loading').addClass('lazy-loaded');
                        this.triggerLazyCallback($el, lazyData.callback);
                    })
                    .fail(() => {
                        $el.removeClass('lazy-loading').addClass('lazy-error');
                    });
            }
        },

        /**
         * Load lazy script
         * @param {jQuery} $el - Element
         * @param {Object} lazyData - Lazy data
         */
        loadLazyScript: function ($el, lazyData) {
            if (lazyData.src) {
                const script = document.createElement('script');
                script.src = lazyData.src;
                script.onload = () => {
                    $el.removeClass('lazy-loading').addClass('lazy-loaded');
                    this.triggerLazyCallback($el, lazyData.callback);
                };
                script.onerror = () => {
                    $el.removeClass('lazy-loading').addClass('lazy-error');
                };
                document.head.appendChild(script);
            }
        },

        /**
         * Load lazy component
         * @param {jQuery} $el - Element
         * @param {Object} lazyData - Lazy data
         */
        loadLazyComponent: function ($el, lazyData) {
            // Component loading logic will be here
            // For example: React, Vue, Angular components
            $el.removeClass('lazy-loading').addClass('lazy-loaded');
            this.triggerLazyCallback($el, lazyData.callback);
        },

        /**
         * Trigger lazy callback
         * @param {jQuery} $el - Element
         * @param {string} callback - Callback function
         */
        triggerLazyCallback: function ($el, callback) {
            if (callback && typeof window[callback] === 'function') {
                window[callback]($el);
            }
        },

        /**
         * Initialize image optimization
         */
        initImageOptimization: function () {
            // Check WebP support
            this.checkWebPSupport();

            // For responsive images
            this.initResponsiveImages();

            // Image compression
            this.initImageCompression();
        },

        /**
         * Check WebP support
         */
        checkWebPSupport: function () {
            const webP = new Image();
            webP.onload = webP.onerror = () => {
                document.documentElement.classList.add(webP.height === 2 ? 'webp' : 'no-webp');
            };
            webP.src = 'data:image/webp;base64,UklGRjoAAABXRUJQVlA4IC4AAACyAgCdASoCAAIALmk0mk0iIiIiIgBoSygABc6WWgAA/veff/0PP8bA//LwYAAA';
        },

        /**
         * Initialize responsive images
         */
        initResponsiveImages: function () {
            $('img[data-srcset]').each((index, img) => {
                const $img = $(img);
                const srcset = $img.data('srcset');
                const sizes = $img.data('sizes');

                if (srcset) {
                    $img.attr('srcset', srcset);
                }
                if (sizes) {
                    $img.attr('sizes', sizes);
                }
            });
        },

        /**
         * Initialize image compression
         */
        initImageCompression: function () {
            // Image compression with Canvas API
            // This feature can be added later
        },

        /**
         * Initialize scroll optimization
         */
        initScrollOptimization: function () {
            let ticking = false;

            const scrollHandler = () => {
                if (!ticking) {
                    requestAnimationFrame(() => {
                        this.handleScroll();
                        ticking = false;
                    });
                    ticking = true;
                }
            };

            window.addEventListener('scroll', scrollHandler, { passive: true });
        },

        /**
         * Handle scroll event
         */
        handleScroll: function () {
            // Scroll-based lazy loading
            this.checkLazyElements();

            // Scroll-based animations
            this.triggerScrollAnimations();

            // Scroll-based navigation
            this.updateScrollNavigation();
        },

        /**
         * Check lazy elements
         */
        checkLazyElements: function () {
            if (!this.observer) {
                this.lazyElements.forEach((lazyData, element) => {
                    if (MHMRentiva.Utils.isInViewport(element)) {
                        this.handleLazyElement(element);
                    }
                });
            }
        },

        /**
         * Trigger scroll animations
         */
        triggerScrollAnimations: function () {
            $('[data-scroll-animation]').each((index, element) => {
                const $el = $(element);
                if (MHMRentiva.Utils.isInViewport($el) && !$el.hasClass('animated')) {
                    const animation = $el.data('scroll-animation');
                    $el.addClass(`mhm-${animation} animated`);
                }
            });
        },

        /**
         * Update scroll navigation
         */
        updateScrollNavigation: function () {
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();

            // Back to top button
            if (scrollTop > 300) {
                $('.back-to-top').fadeIn();
            } else {
                $('.back-to-top').fadeOut();
            }

            // Sticky navigation
            if (scrollTop > 100) {
                $('.sticky-nav').addClass('sticky-active');
            } else {
                $('.sticky-nav').removeClass('sticky-active');
            }
        },

        /**
         * Initialize resize optimization
         */
        initResizeOptimization: function () {
            let resizeTimeout;

            $(window).on('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    this.handleResize();
                }, 250);
            });
        },

        /**
         * Handle resize event
         */
        handleResize: function () {
            // Update responsive images
            this.updateResponsiveImages();

            // Recalculate layout
            this.recalculateLayout();

            // Resize charts
            this.resizeCharts();
        },

        /**
         * Update responsive images
         */
        updateResponsiveImages: function () {
            $('img[data-srcset]').each((index, img) => {
                const $img = $(img);
                const currentSrc = $img.attr('src');
                const srcset = $img.attr('srcset');

                if (srcset && currentSrc) {
                    // Select best image
                    const bestSrc = this.selectBestImage(srcset, $img.width());
                    if (bestSrc !== currentSrc) {
                        $img.attr('src', bestSrc);
                    }
                }
            });
        },

        /**
         * Select best image
         * @param {string} srcset - Srcset string
         * @param {number} width - Container width
         * @returns {string} Best image URL
         */
        selectBestImage: function (srcset, width) {
            const sources = srcset.split(',').map(src => {
                const parts = src.trim().split(' ');
                return {
                    url: parts[0],
                    width: parseInt(parts[1]) || 0
                };
            });

            sources.sort((a, b) => a.width - b.width);

            for (let i = sources.length - 1; i >= 0; i--) {
                if (sources[i].width <= width) {
                    return sources[i].url;
                }
            }

            return sources[0].url;
        },

        /**
         * Recalculate layout
         */
        recalculateLayout: function () {
            // Recalculate grid layouts (with masonry plugin check)
            if (typeof $.fn.masonry !== 'undefined') {
                $('.masonry-grid').masonry('layout');
            } else {
                // Fallback with CSS Grid if masonry is not available
                $('.masonry-grid').each((index, element) => {
                    const $element = $(element);
                    $element.css('display', 'grid');
                    $element.css('grid-template-columns', 'repeat(auto-fit, minmax(250px, 1fr))');
                    $element.css('gap', '20px');
                });
            }

            // Recalculate flexbox layouts
            $('.flex-container').each((index, container) => {
                const $container = $(container);
                $container.css('height', 'auto');
            });
        },

        /**
         * Resize charts
         */
        resizeCharts: function () {
            if (window.Chart && window.Chart.instances) {
                Object.values(window.Chart.instances).forEach(chart => {
                    chart.resize();
                });
            }
        },

        /**
         * Add data to cache
         * @param {string} key - Cache key
         * @param {*} data - Cache data
         * @param {number} ttl - Time to live (ms)
         */
        setCache: function (key, data, ttl = 300000) { // 5 minutes default
            this.cache.set(key, {
                data: data,
                timestamp: Date.now(),
                ttl: ttl
            });
        },

        /**
         * Get data from cache
         * @param {string} key - Cache key
         * @returns {*} Cache data
         */
        getCache: function (key) {
            const cached = this.cache.get(key);
            if (!cached) return null;

            if (Date.now() - cached.timestamp > cached.ttl) {
                this.cache.delete(key);
                return null;
            }

            return cached.data;
        },

        /**
         * Clear cache
         * @param {string} key - Cache key (optional)
         */
        clearCache: function (key) {
            if (key) {
                this.cache.delete(key);
            } else {
                this.cache.clear();
            }
        },

        /**
         * Log performance metrics
         * @param {string} name - Metric name
         * @param {number} value - Metric value
         */
        logPerformance: function (name, value) {
            // Debug log removed

            // Send to analytics (optional)
            if (window.gtag) {
                gtag('event', 'timing_complete', {
                    name: name,
                    value: Math.round(value)
                });
            }
        },

        /**
         * Fallback detection for older browsers
         */
        initFallbackDetection: function () {
            // Periodically check for new lazy elements
            this.fallbackInterval = setInterval(() => {
                const newLazyElements = document.querySelectorAll('[data-lazy]:not([data-lazy-processed])');
                newLazyElements.forEach((element) => {
                    this.registerLazyElement(element);
                    element.setAttribute('data-lazy-processed', 'true');
                });
            }, 1000); // Check every second
        },

        /**
         * Stop MutationObserver
         */
        stopMutationObserver: function () {
            if (this.mutationObserver) {
                this.mutationObserver.disconnect();
                this.mutationObserver = null;
                // Debug log removed
            }

            // Also stop fallback interval
            if (this.fallbackInterval) {
                clearInterval(this.fallbackInterval);
                this.fallbackInterval = null;
                // Debug log removed
            }
        },

        /**
         * Check memory usage
         */
        checkMemoryUsage: function () {
            if (performance.memory) {
                const memory = performance.memory;
                const used = memory.usedJSHeapSize / 1024 / 1024;
                const total = memory.totalJSHeapSize / 1024 / 1024;
                const limit = memory.jsHeapSizeLimit / 1024 / 1024;



                // Memory warning
                if (used / limit > 0.8) {
                    if (typeof console !== 'undefined' && console.warn) {
                        console.warn('[MHM Memory] High memory usage detected!');
                    }
                    this.clearCache();
                }
            }
        },

        /**
         * Get performance report
         * @returns {Object} Performance report
         */
        getPerformanceReport: function () {
            return {
                metrics: this.metrics,
                cache: {
                    size: this.cache.size,
                    keys: Array.from(this.cache.keys())
                },
                lazyElements: {
                    total: this.lazyElements.size,
                    loaded: Array.from(this.lazyElements.values()).filter(item => item.loaded).length
                },
                memory: performance.memory ? {
                    used: performance.memory.usedJSHeapSize / 1024 / 1024,
                    total: performance.memory.totalJSHeapSize / 1024 / 1024,
                    limit: performance.memory.jsHeapSizeLimit / 1024 / 1024
                } : null
            };
        }
    };

    // Initialize performance optimizations when page loads
    $(document).ready(() => {
        MHMRentiva.Performance.init();

        // Memory check (every 30 seconds)
        setInterval(() => {
            MHMRentiva.Performance.checkMemoryUsage();
        }, 30000);
    });

    // Make globally available
    window.MHM = window.MHM || MHMRentiva;

})(jQuery);
