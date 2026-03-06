/**
 * MHM Rentiva - Main JavaScript File
 *
 * Loads and coordinates all core modules.
 * This file must be loaded on all pages.
 */

(function ($) {
    'use strict';

    // Global MHM Rentiva namespace
    window.MHMRentiva = window.MHMRentiva || {};

    /**
     * Core class
     */
    MHMRentiva.Core = {

        /**
         * Version
         */
        version: '3.0.1',

        /**
         * Initialized flag
         */
        initialized: false,

        /**
         * Configuration
         */
        config: {
            debug: false,
            ajaxUrl: '',
            nonce: window.mhm_ajax_nonce || '',
            locale: 'en-US',
            currency: 'USD',
            dateFormat: 'MM/DD/YYYY',
            timeFormat: 'HH:mm'
        },

        /**
         * Initialize core
         */
        init: function () {
            if (this.initialized) return;

            this.setupConfig();
            this.loadModules();
            this.setupGlobalEvents();
            this.setupErrorHandling();

            this.initialized = true;

            // Trigger core ready event
            $(document).trigger('mhm:core:ready');

            // Debug log removed
        },

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
         * Setup configuration
         */
        setupConfig: function () {
            // Re-initialize ajaxUrl after config merge
            this.config.ajaxUrl = this.getAjaxUrl();

            // Get data from WordPress
            if (window.mhm_rentiva_config) {
                $.extend(this.config, window.mhm_rentiva_config);
                // Ensure ajaxUrl is set correctly after merge
                if (!this.config.ajaxUrl || this.config.ajaxUrl === '/wp-admin/admin-ajax.php') {
                    this.config.ajaxUrl = this.getAjaxUrl();
                }
            }

            // Check debug mode
            if (window.location.search.indexOf('mhm_debug=1') !== -1) {
                this.config.debug = true;
            }
        },

        /**
         * Load modules
         */
        loadModules: function () {
            // Load core modules in order
            const modules = [
                'utilities',
                'i18n',
                'performance',
                'module-loader'
            ];

            modules.forEach(module => {
                try {
                    // Load module file dynamically
                    this.loadModuleScript(module);
                } catch (error) {
                    if (typeof console !== 'undefined' && console.error) {
                        console.error(`[MHM Core] Failed to load module: ${module}`, error);
                    }
                }
            });
        },

        /**
         * Load module script
         * @param {string} moduleName - Module name
         */
        loadModuleScript: function (moduleName) {
            const script = document.createElement('script');
            script.src = `${this.getBaseUrl()}/assets/js/core/${moduleName}.js`;
            script.async = false;
            document.head.appendChild(script);
        },

        /**
         * Get base URL
         * @returns {string} Base URL
         */
        getBaseUrl: function () {
            // Get from config first, otherwise use fallback
            if (window.mhm_rentiva_config?.baseUrl) {
                return window.mhm_rentiva_config.baseUrl;
            }

            // Fallback: Extract base URL from current script's URL
            // Use a specific selector to avoid matching jQuery UI's core.js
            const currentScript = document.currentScript ||
                document.querySelector('script[src*="mhm-rentiva/assets/js/core/core.js"]');

            if (currentScript && currentScript.src) {
                const url = new URL(currentScript.src);
                return url.origin + url.pathname.replace('/assets/js/core/core.js', '');
            }

            // Final fallback: use plugin URL pattern from any mhm-rentiva script
            const anyPluginScript = document.querySelector('script[src*="mhm-rentiva/assets/"]');
            if (anyPluginScript && anyPluginScript.src) {
                const url = new URL(anyPluginScript.src);
                const match = url.pathname.match(/(.*\/mhm-rentiva)\//);
                if (match) {
                    return url.origin + match[1];
                }
            }

            return '';
        },

        /**
         * Setup global events
         */
        setupGlobalEvents: function () {
            // AJAX error handling
            $(document).ajaxError((event, xhr, settings, error) => {
                this.handleAjaxError(xhr, settings, error);
            });

            // Global click handler
            $(document).on('click', '[data-mhm-action]', (e) => {
                this.handleActionClick(e);
            });

            // Global form handler
            $(document).on('submit', '[data-mhm-form]', (e) => {
                this.handleFormSubmit(e);
            });
        },

        /**
         * Setup error handling
         */
        setupErrorHandling: function () {
            // Global error handler
            window.addEventListener('error', (event) => {
                this.handleGlobalError(event);
            });

            // Unhandled promise rejection handler
            window.addEventListener('unhandledrejection', (event) => {
                this.handlePromiseRejection(event);
            });
        },

        /**
         * Handle AJAX error
         * @param {Object} xhr - XMLHttpRequest
         * @param {Object} settings - AJAX settings
         * @param {string} error - Error message
         */
        handleAjaxError: function (xhr, settings, error) {
            // Skip error handling for messages-related AJAX calls on My Account page
            // These are handled by the template's REST API code
            if (settings.url && (
                settings.url.indexOf('mhm_customer_get_messages') !== -1 ||
                settings.url.indexOf('mhm_customer_send_message') !== -1 ||
                settings.url.indexOf('mhm_get_customer_messages') !== -1 ||
                settings.url.indexOf('mhm_send_customer_message') !== -1
            )) {
                return; // Silent fail for messages AJAX calls
            }

            if (this.config.debug) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('[MHM Core] AJAX Error:', {
                        url: settings.url,
                        status: xhr.status,
                        error: error,
                        response: xhr.responseText
                    });
                }
            }

            // Show error message to user
            if (MHMRentiva.Utils && MHMRentiva.Utils.showNotification) {
                const errorMessage = (MHMRentiva.i18n && MHMRentiva.i18n.__)
                    ? MHMRentiva.i18n.__('An error occurred')
                    : 'An error occurred';
                MHMRentiva.Utils.showNotification(errorMessage, 'error');
            }
        },

        /**
         * Handle action click
         * @param {Event} e - Click event
         */
        handleActionClick: function (e) {
            e.preventDefault();

            const $target = $(e.currentTarget);
            const action = $target.data('mhm-action');
            const data = $target.data('mhm-data') || {};

            if (typeof this[action] === 'function') {
                this[action](data, $target);
            } else if (window.MHMRentiva[action]) {
                window.MHMRentiva[action](data, $target);
            }
        },

        /**
         * Handle form submit
         * @param {Event} e - Submit event
         */
        handleFormSubmit: function (e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const action = $form.data('mhm-form');
            const data = $form.serialize();

            if (typeof this[action] === 'function') {
                this[action](data, $form);
            }
        },

        /**
         * Handle global error
         * @param {Event} event - Error event
         */
        handleGlobalError: function (event) {
            if (this.config.debug) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('[MHM Core] Global Error:', {
                        message: event.message,
                        filename: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        error: event.error
                    });
                }
            }
        },

        /**
         * Handle promise rejection
         * @param {Event} event - Rejection event
         */
        handlePromiseRejection: function (event) {
            if (this.config.debug) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('[MHM Core] Unhandled Promise Rejection:', event.reason);
                }
            }
        },

        /**
         * Show debug message
         * @param {string} message - Message
         * @param {*} data - Data
         */
        debug: function (message, data) {
            // Debug log removed
        },

        /**
         * Get configuration
         * @param {string} key - Configuration key
         * @returns {*} Configuration value
         */
        getConfig: function (key) {
            return key ? this.config[key] : this.config;
        },

        /**
         * Set configuration
         * @param {string} key - Configuration key
         * @param {*} value - Configuration value
         */
        setConfig: function (key, value) {
            this.config[key] = value;
        }
    };

    // Initialize core when page loads
    $(document).ready(() => {
        MHMRentiva.Core.init();
    });

    // Make it globally available
    window.MHM = window.MHM || MHMRentiva;

})(jQuery);
