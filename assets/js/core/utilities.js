/**
 * MHM Rentiva - Central Utility Functions
 * 
 * Common functions that can be used in all JavaScript files.
 * Use this file to prevent code duplication and ensure consistency.
 */

(function ($) {
    'use strict';

    // Global MHM Rentiva namespace
    window.MHMRentiva = window.MHMRentiva || {};

    /**
     * Utility functions
     */
    MHMRentiva.Utils = {

        /**
         * Debounce function - for performance optimization
         * @param {Function} func - Function to execute
         * @param {number} wait - Wait time (ms)
         * @param {boolean} immediate - Execute immediately
         * @returns {Function} Debounced function
         */
        debounce: function (func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function () {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        /**
         * Throttle function - for performance optimization
         * @param {Function} func - Function to execute
         * @param {number} limit - Limit time (ms)
         * @returns {Function} Throttled function
         */
        throttle: function (func, limit) {
            let inThrottle;
            return function () {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Check if element is in viewport
         * @param {jQuery|HTMLElement} element - Element to check
         * @returns {boolean} Is in viewport
         */
        isInViewport: function (element) {
            const $el = $(element);
            if (!$el.length) return false;

            const elementTop = $el.offset().top;
            const elementBottom = elementTop + $el.outerHeight();
            const viewportTop = $(window).scrollTop();
            const viewportBottom = viewportTop + $(window).height();

            return elementBottom > viewportTop && elementTop < viewportBottom;
        },

        /**
         * Load element for lazy loading
         * @param {jQuery|HTMLElement} element - Element to load
         * @param {Function} callback - Function to run when loading is complete
         */
        lazyLoad: function (element, callback) {
            const $el = $(element);
            if (!$el.length) return;

            if (this.isInViewport($el)) {
                callback($el);
            } else {
                const scrollHandler = this.throttle(() => {
                    if (this.isInViewport($el)) {
                        callback($el);
                        window.removeEventListener('scroll', scrollHandler);
                    }
                }, 100);

                window.addEventListener('scroll', scrollHandler, { passive: true });
            }
        },

        /**
         * Format number - format numbers
         * @param {number} number - Number to format
         * @param {number} decimals - Number of decimal places
         * @param {string} thousandsSep - Thousands separator
         * @param {string} decimalSep - Decimal separator
         * @returns {string} Formatted number
         */
        formatNumber: function (number, decimals = 2, thousandsSep = '.', decimalSep = ',') {
            if (isNaN(number)) return '0';

            const num = parseFloat(number);
            const locale = (window.mhm_rentiva_config && window.mhm_rentiva_config.locale) || 'en-US';
            return num.toLocaleString(locale, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },

        /**
         * Format currency - format currency
         * @param {number} amount - Amount
         * @param {string} currency - Currency
         * @returns {string} Formatted currency
         */
        formatCurrency: function (amount, currency) {
            if (isNaN(amount)) return '0.00 ' + (currency || 'USD');

            const num = parseFloat(amount);
            const currencyCode = currency || (window.mhm_rentiva_config && window.mhm_rentiva_config.currency) || 'USD';
            const locale = (window.mhm_rentiva_config && window.mhm_rentiva_config.locale) || 'en-US';

            return num.toLocaleString(locale, {
                style: 'currency',
                currency: currencyCode,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },

        /**
         * Format date - format date
         * @param {Date|string} date - Date
         * @param {string} format - Format (tr, en, custom)
         * @returns {string} Formatted date
         */
        formatDate: function (date, format = 'default') {
            if (!date) return '';

            const d = new Date(date);
            if (isNaN(d.getTime())) return '';

            const locale = (window.mhm_rentiva_config && window.mhm_rentiva_config.locale) || 'en-US';

            const options = {
                default: {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                },
                short: {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                },
                long: {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    weekday: 'long'
                }
            };

            return d.toLocaleDateString(locale, options[format] || options.default);
        },

        /**
         * Sanitize string - clean string
         * @param {string} str - String to clean
         * @returns {string} Cleaned string
         */
        sanitizeString: function (str) {
            if (typeof str !== 'string') return '';
            return str.replace(/[<>]/g, '').trim();
        },

        /**
         * Generate unique ID
         * @param {string} prefix - Prefix
         * @returns {string} Unique ID
         */
        generateId: function (prefix = 'mhm') {
            return prefix + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Deep clone object
         * @param {Object} obj - Object to clone
         * @returns {Object} Cloned object
         */
        deepClone: function (obj) {
            if (obj === null || typeof obj !== 'object') return obj;
            if (obj instanceof Date) return new Date(obj.getTime());
            if (obj instanceof Array) return obj.map(item => this.deepClone(item));
            if (typeof obj === 'object') {
                const clonedObj = {};
                for (const key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        clonedObj[key] = this.deepClone(obj[key]);
                    }
                }
                return clonedObj;
            }
        },

        /**
         * Check if element exists and is visible
         * @param {string|jQuery|HTMLElement} selector - Element selector
         * @returns {boolean} Is element present and visible
         */
        isVisible: function (selector) {
            const $el = $(selector);
            return $el.length > 0 && $el.is(':visible');
        },

        /**
         * Show loading spinner
         * @param {string|jQuery|HTMLElement} container - Container element
         * @param {string} message - Loading message
         */
        showLoading: function (container, message) {
            const $container = $(container);
            if (!$container.length) return;

            const loadingText = message ||
                (window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__('Loading...')) ||
                'Loading...';

            const loadingHtml = `
                <div class="mhm-loading-overlay">
                    <div class="mhm-loading-spinner">
                        <div class="spinner"></div>
                        <p class="loading-message">${this.sanitizeString(loadingText)}</p>
                    </div>
                </div>
            `;

            $container.append(loadingHtml);
        },

        /**
         * Hide loading spinner
         * @param {string|jQuery|HTMLElement} container - Container element
         */
        hideLoading: function (container) {
            const $container = $(container);
            $container.find('.mhm-loading-overlay').remove();
        },

        /**
         * Show notification
         * @param {string} message - Message
         * @param {string} type - Type (success, error, warning, info)
         * @param {number} duration - Duration (ms)
         */
        showNotification: function (message, type = 'info', duration = 5000) {
            const notificationId = this.generateId('notification');
            const notificationHtml = `
                <div id="${notificationId}" class="mhm-notification mhm-notification-${type}">
                    <div class="notification-content">
                        <span class="notification-message">${this.sanitizeString(message)}</span>
                        <button class="notification-close" type="button">&times;</button>
                    </div>
                </div>
            `;

            $('body').append(notificationHtml);

            const $notification = $(`#${notificationId}`);

            // Auto remove
            setTimeout(() => {
                $notification.fadeOut(300, function () {
                    $(this).remove();
                });
            }, duration);

            // Manual close
            $notification.find('.notification-close').on('click', function () {
                $notification.fadeOut(300, function () {
                    $(this).remove();
                });
            });
        },

        /**
         * Confirm dialog
         * @param {string} message - Message
         * @param {Function} onConfirm - Function to run when confirmed
         * @param {Function} onCancel - Function to run when cancelled
         */
        confirm: function (message, onConfirm, onCancel) {
            if (confirm(this.sanitizeString(message))) {
                if (typeof onConfirm === 'function') onConfirm();
            } else {
                if (typeof onCancel === 'function') onCancel();
            }
        },

        /**
         * Get URL parameters
         * @param {string} name - Parameter name
         * @returns {string|null} Parameter value
         */
        getUrlParam: function (name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        },

        /**
         * Set URL parameter
         * @param {string} name - Parameter name
         * @param {string} value - Parameter value
         */
        setUrlParam: function (name, value) {
            const url = new URL(window.location);
            url.searchParams.set(name, value);
            window.history.pushState({}, '', url);
        },

        /**
         * Remove URL parameter
         * @param {string} name - Parameter name
         */
        removeUrlParam: function (name) {
            const url = new URL(window.location);
            url.searchParams.delete(name);
            window.history.pushState({}, '', url);
        },

        /**
         * Copy text to clipboard
         * @param {string} text - Text to copy
         * @param {Function} onSuccess - Function to run on success
         * @param {Function} onError - Function to run on error
         */
        copyToClipboard: function (text, onSuccess, onError) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(
                    () => {
                        if (typeof onSuccess === 'function') onSuccess();
                    },
                    () => {
                        if (typeof onError === 'function') onError();
                    }
                );
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();

                try {
                    document.execCommand('copy');
                    if (typeof onSuccess === 'function') onSuccess();
                } catch (err) {
                    if (typeof onError === 'function') onError();
                }

                document.body.removeChild(textArea);
            }
        }
    };

    /**
     * AJAX Utility functions
     */
    MHMRentiva.Ajax = {

        /**
         * Get AJAX URL dynamically
         * @returns {string} AJAX URL
         */
        getAjaxUrl: function () {
            // Check for WordPress ajaxurl (available in admin)
            if (typeof window.ajaxurl !== 'undefined') {
                return window.ajaxurl;
            }

            // Check for config URL
            if (window.mhm_rentiva_config && window.mhm_rentiva_config.ajaxUrl) {
                return window.mhm_rentiva_config.ajaxUrl;
            }

            // Fallback: construct from current location
            const pathname = window.location.pathname;
            const adminPath = pathname.includes('/wp-admin/')
                ? pathname.substring(0, pathname.indexOf('/wp-admin/') + '/wp-admin'.length)
                : '/wp-admin';

            return window.location.origin + adminPath + '/admin-ajax.php';
        },

        /**
         * Default AJAX settings
         */
        defaults: {
            url: '',
            type: 'POST',
            dataType: 'json',
            timeout: 30000,
            cache: false
        },

        /**
         * Send AJAX request
         * @param {Object} options - AJAX options
         * @returns {Promise} Promise object
         */
        request: function (options) {
            // Ensure ajaxUrl is up-to-date
            this.defaults.url = this.getAjaxUrl();
            const settings = $.extend({}, this.defaults, options);

            // Add nonce
            if (!settings.data) settings.data = {};
            if (!settings.data.nonce) {
                settings.data.nonce = window.mhm_ajax_nonce || '';
            }

            return $.ajax(settings).fail(function (xhr, status, error) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('AJAX Error:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                }

                // Global error handler
                if (window.MHMRentiva && window.MHMRentiva.Utils) {
                    const errorMessage = (window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
                        ? window.MHMRentiva.i18n.__('An error occurred. Please try again.')
                        : 'An error occurred. Please try again.';
                    window.MHMRentiva.Utils.showNotification(errorMessage, 'error');
                }
            });
        },

        /**
         * GET request
         * @param {string} action - AJAX action
         * @param {Object} data - Data to send
         * @returns {Promise} Promise object
         */
        get: function (action, data = {}) {
            return this.request({
                type: 'GET',
                data: $.extend({ action: action }, data)
            });
        },

        /**
         * POST request
         * @param {string} action - AJAX action
         * @param {Object} data - Data to send
         * @returns {Promise} Promise object
         */
        post: function (action, data = {}) {
            return this.request({
                type: 'POST',
                data: $.extend({ action: action }, data)
            });
        }
    };

    /**
     * DOM Utility functions
     */
    MHMRentiva.DOM = {

        /**
         * Safely select element
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @returns {jQuery} jQuery object
         */
        select: function (selector) {
            return $(selector);
        },

        /**
         * Check if element exists
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @returns {boolean} Does element exist
         */
        exists: function (selector) {
            return $(selector).length > 0;
        },

        /**
         * Add class to element
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {string} className - Class name
         */
        addClass: function (selector, className) {
            $(selector).addClass(className);
        },

        /**
         * Remove class from element
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {string} className - Class name
         */
        removeClass: function (selector, className) {
            $(selector).removeClass(className);
        },

        /**
         * Check if element has class
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {string} className - Class name
         * @returns {boolean} Does element have class
         */
        hasClass: function (selector, className) {
            return $(selector).hasClass(className);
        },

        /**
         * Hide element
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {number} duration - Animation duration
         */
        hide: function (selector, duration = 300) {
            $(selector).fadeOut(duration);
        },

        /**
         * Show element
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {number} duration - Animation duration
         */
        show: function (selector, duration = 300) {
            $(selector).fadeIn(duration);
        },

        /**
         * Toggle element
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {number} duration - Animation duration
         */
        toggle: function (selector, duration = 300) {
            $(selector).fadeToggle(duration);
        }
    };

    /**
     * Event Utility functions
     */
    MHMRentiva.Events = {

        /**
         * Add event listener
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {string} event - Event type
         * @param {Function} handler - Event handler
         * @param {string} namespace - Event namespace
         */
        on: function (selector, event, handler, namespace = '') {
            const eventName = namespace ? `${event}.${namespace}` : event;
            $(selector).on(eventName, handler);
        },

        /**
         * Remove event listener
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {string} event - Event type
         * @param {string} namespace - Event namespace
         */
        off: function (selector, event, namespace = '') {
            const eventName = namespace ? `${event}.${namespace}` : event;
            $(selector).off(eventName);
        },

        /**
         * Trigger event
         * @param {string|jQuery|HTMLElement} selector - Selector
         * @param {string} event - Event type
         * @param {*} data - Event data
         */
        trigger: function (selector, event, data) {
            $(selector).trigger(event, data);
        }
    };

    // Make globally available
    window.MHM = window.MHM || MHMRentiva;

})(jQuery);
