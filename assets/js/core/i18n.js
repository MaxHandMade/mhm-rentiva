/**
 * MHM Rentiva - Internationalization (i18n) System
 * 
 * Multi-language support integrated with WordPress i18n system.
 * Can be used in all JavaScript files.
 */

(function ($) {
    'use strict';

    // Global MHM Rentiva namespace
    window.MHMRentiva = window.MHMRentiva || {};

    /**
     * i18n Utility functions
     */
    MHMRentiva.i18n = {

        /**
         * Store translation data
         */
        translations: {},

        /**
         * Language settings
         */
        locale: 'en_US',

        /**
         * Load translation data
         * @param {Object} translations - Translation data
         * @param {string} locale - Language code
         */
        loadTranslations: function (translations, locale = 'en_US') {
            this.translations = translations || {};
            this.locale = locale;

            // Use locale from WordPress
            if (window.mhm_rentiva_config && window.mhm_rentiva_config.locale) {
                this.locale = window.mhm_rentiva_config.locale;
            }
        },

        /**
         * Get translation key
         * @param {string} key - Translation key
         * @param {Object} params - Parameters
         * @param {string} domain - Translation domain
         * @returns {string} Translated text
         */
        __: function (key, params = {}, domain = 'mhm-rentiva') {
            const translation = this.getTranslation(key, domain);
            return this.interpolate(translation, params);
        },

        /**
         * Get translation key (plural form)
         * @param {string} single - Singular form
         * @param {string} plural - Plural form
         * @param {number} count - Number
         * @param {Object} params - Parameters
         * @param {string} domain - Translation domain
         * @returns {string} Translated text
         */
        _n: function (single, plural, count, params = {}, domain = 'mhm-rentiva') {
            const key = count === 1 ? single : plural;
            const translation = this.getTranslation(key, domain);
            const finalParams = $.extend({ count: count }, params);
            return this.interpolate(translation, finalParams);
        },

        /**
         * Get translation key (with context)
         * @param {string} context - Context
         * @param {string} key - Translation key
         * @param {Object} params - Parameters
         * @param {string} domain - Translation domain
         * @returns {string} Translated text
         */
        _x: function (context, key, params = {}, domain = 'mhm-rentiva') {
            const contextKey = `${context}${this.getContextSeparator()}${key}`;
            const translation = this.getTranslation(contextKey, domain);
            return this.interpolate(translation, params);
        },

        /**
         * Get translation key (with context and plural)
         * @param {string} context - Context
         * @param {string} single - Singular form
         * @param {string} plural - Plural form
         * @param {number} count - Number
         * @param {Object} params - Parameters
         * @param {string} domain - Translation domain
         * @returns {string} Translated text
         */
        _nx: function (context, single, plural, count, params = {}, domain = 'mhm-rentiva') {
            const key = count === 1 ? single : plural;
            const contextKey = `${context}${this.getContextSeparator()}${key}`;
            const translation = this.getTranslation(contextKey, domain);
            const finalParams = $.extend({ count: count }, params);
            return this.interpolate(translation, finalParams);
        },

        /**
         * Get translation key
         * @param {string} key - Translation key
         * @param {string} domain - Translation domain
         * @returns {string} Translation
         */
        getTranslation: function (key, domain = 'mhm-rentiva') {
            if (this.translations[domain] && this.translations[domain][key]) {
                return this.translations[domain][key];
            }

            // Fallback to key if translation not found
            return key;
        },

        /**
         * Insert parameters into text
         * @param {string} text - Text
         * @param {Object} params - Parameters
         * @returns {string} Processed text
         */
        interpolate: function (text, params) {
            if (!text || typeof text !== 'string') return text;

            return text.replace(/\{\{(\w+)\}\}/g, function (match, key) {
                return params[key] !== undefined ? params[key] : match;
            });
        },

        /**
         * Get context separator
         * @returns {string} Separator
         */
        getContextSeparator: function () {
            return '\u0004'; // Unicode character for context separation
        },

        /**
         * Get date format
         * @param {string} format - Format type
         * @returns {Object} Date format
         */
        getDateFormat: function (format = 'default') {
            const formats = {
                tr_TR: {
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
                    time: {
                        hour: '2-digit',
                        minute: '2-digit'
                    },
                    datetime: {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit'
                    }
                },
                en_US: {
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
                    time: {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    },
                    datetime: {
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    }
                }
            };

            const locale = this.locale.split('_')[0];
            return formats[locale] && formats[locale][format] ? formats[locale][format] : formats.tr_TR[format];
        },

        /**
         * Get currency format
         * @param {string} currency - Currency
         * @returns {Object} Currency format
         */
        getCurrencyFormat: function (currency) {
            // Get from config first
            const configCurrency = (window.mhm_rentiva_config && window.mhm_rentiva_config.currency) || 'USD';
            const currencyCode = currency || configCurrency;

            const formats = {
                TRY: {
                    symbol: '₺',
                    position: 'after',
                    decimals: 2
                },
                USD: {
                    symbol: '$',
                    position: 'before',
                    decimals: 2
                },
                EUR: {
                    symbol: '€',
                    position: 'after',
                    decimals: 2
                },
                GBP: {
                    symbol: '£',
                    position: 'before',
                    decimals: 2
                }
            };

            return formats[currencyCode] || formats.USD;
        },

        /**
         * Get number format
         * @returns {Object} Number format
         */
        getNumberFormat: function () {
            const formats = {
                tr_TR: {
                    thousandsSeparator: '.',
                    decimalSeparator: ',',
                    decimals: 2
                },
                en_US: {
                    thousandsSeparator: ',',
                    decimalSeparator: '.',
                    decimals: 2
                }
            };

            const locale = this.locale.split('_')[0];
            return formats[locale] || formats.tr_TR;
        }
    };

    /**
     * Default translations (English)
     */
    const defaultTranslations = {
        'mhm-rentiva': {
            // General
            'Loading...': 'Loading...',
            'Error': 'Error',
            'Success': 'Success',
            'Warning': 'Warning',
            'Info': 'Info',
            'Yes': 'Yes',
            'No': 'No',
            'Cancel': 'Cancel',
            'Confirm': 'Confirm',
            'Save': 'Save',
            'Delete': 'Delete',
            'Edit': 'Edit',
            'Add': 'Add',
            'Update': 'Update',
            'Search': 'Search',
            'Filter': 'Filter',
            'Reset': 'Reset',
            'Close': 'Close',
            'Back': 'Back',
            'Next': 'Next',
            'Previous': 'Previous',
            'Submit': 'Submit',
            'Clear': 'Clear',
            'Select All': 'Select All',
            'Select None': 'Select None',
            'No data found': 'No data found',
            'No results found': 'No results found',
            'Please wait...': 'Please wait...',
            'Processing...': 'Processing...',
            'An error occurred': 'An error occurred',
            'Please try again': 'Please try again',
            'Operation completed successfully': 'Operation completed successfully',
            'Operation failed': 'Operation failed',
            'Are you sure?': 'Are you sure?',
            'This action cannot be undone': 'This action cannot be undone',
            'Invalid input': 'Invalid input',
            'Required field': 'Required field',
            'Please fill all required fields': 'Please fill all required fields',

            // Vehicles
            'Vehicles': 'Vehicles',
            'Vehicle': 'Vehicle',
            'Add Vehicle': 'Add Vehicle',
            'Edit Vehicle': 'Edit Vehicle',
            'Delete Vehicle': 'Delete Vehicle',
            'Vehicle Details': 'Vehicle Details',
            'Vehicle List': 'Vehicle List',
            'Vehicle Type': 'Vehicle Type',
            'Vehicle Brand': 'Vehicle Brand',
            'Vehicle Model': 'Vehicle Model',
            'Vehicle Year': 'Vehicle Year',
            'Vehicle Price': 'Vehicle Price',
            'Vehicle Status': 'Vehicle Status',
            'Available': 'Available',
            'Unavailable': 'Unavailable',
            'Maintenance': 'Maintenance',
            'Rented': 'Rented',

            // Bookings
            'Bookings': 'Bookings',
            'Booking': 'Booking',
            'Add Booking': 'Add Booking',
            'Edit Booking': 'Edit Booking',
            'Delete Booking': 'Delete Booking',
            'Booking Details': 'Booking Details',
            'Booking List': 'Booking List',
            'Booking Date': 'Booking Date',
            'Start Date': 'Start Date',
            'End Date': 'End Date',
            'Booking Status': 'Booking Status',
            'Pending': 'Pending',
            'Confirmed': 'Confirmed',
            'Cancelled': 'Cancelled',
            'Completed': 'Completed',
            'Total Amount': 'Total Amount',
            'Booking Total': 'Booking Total',

            // Customers
            'Customers': 'Customers',
            'Customer': 'Customer',
            'Add Customer': 'Add Customer',
            'Edit Customer': 'Edit Customer',
            'Delete Customer': 'Delete Customer',
            'Customer Details': 'Customer Details',
            'Customer List': 'Customer List',
            'Customer Name': 'Customer Name',
            'Customer Email': 'Customer Email',
            'Customer Phone': 'Customer Phone',
            'Customer Address': 'Customer Address',
            'Customer ID': 'Customer ID',
            'New Customer': 'New Customer',
            'Existing Customer': 'Existing Customer',

            // Payments
            'Payments': 'Payments',
            'Payment': 'Payment',
            'Payment Method': 'Payment Method',
            'Payment Status': 'Payment Status',
            'Payment Date': 'Payment Date',
            'Payment Amount': 'Payment Amount',
            'Paid': 'Paid',
            'Unpaid': 'Unpaid',
            'Refunded': 'Refunded',
            'Cash': 'Cash',
            'Credit Card': 'Credit Card',
            'Bank Transfer': 'Bank Transfer',
            'PayPal': 'PayPal',
            'Stripe': 'Stripe',
            'PayTR': 'PayTR',

            // Reports
            'Reports': 'Reports',
            'Report': 'Report',
            'Generate Report': 'Generate Report',
            'Export Report': 'Export Report',
            'Print Report': 'Print Report',
            'Revenue Report': 'Revenue Report',
            'Booking Report': 'Booking Report',
            'Customer Report': 'Customer Report',
            'Vehicle Report': 'Vehicle Report',
            'Monthly Report': 'Monthly Report',
            'Yearly Report': 'Yearly Report',
            'Custom Report': 'Custom Report',
            'Report Period': 'Report Period',
            'From Date': 'From Date',
            'To Date': 'To Date',

            // Messages
            'Messages': 'Messages',
            'Message': 'Message',
            'Send Message': 'Send Message',
            'Reply': 'Reply',
            'Mark as Read': 'Mark as Read',
            'Mark as Unread': 'Mark as Unread',
            'Delete Message': 'Delete Message',
            'Message Thread': 'Message Thread',
            'New Message': 'New Message',
            'Unread Messages': 'Unread Messages',
            'Message Subject': 'Message Subject',
            'Message Content': 'Message Content',
            'Message Date': 'Message Date',
            'Message Status': 'Message Status',
            'Read': 'Read',
            'Unread': 'Unread',
            'Replied': 'Replied',

            // Calendar
            'Calendar': 'Calendar',
            'Today': 'Today',
            'Yesterday': 'Yesterday',
            'Tomorrow': 'Tomorrow',
            'This Week': 'This Week',
            'This Month': 'This Month',
            'This Year': 'This Year',
            'Last Week': 'Last Week',
            'Last Month': 'Last Month',
            'Last Year': 'Last Year',
            'Next Week': 'Next Week',
            'Next Month': 'Next Month',
            'Next Year': 'Next Year',
            'January': 'January',
            'February': 'February',
            'March': 'March',
            'April': 'April',
            'May': 'May',
            'June': 'June',
            'July': 'July',
            'August': 'August',
            'September': 'September',
            'October': 'October',
            'November': 'November',
            'December': 'December',
            'Monday': 'Monday',
            'Tuesday': 'Tuesday',
            'Wednesday': 'Wednesday',
            'Thursday': 'Thursday',
            'Friday': 'Friday',
            'Saturday': 'Saturday',
            'Sunday': 'Sunday',
            'Mon': 'Mon',
            'Tue': 'Tue',
            'Wed': 'Wed',
            'Thu': 'Thu',
            'Fri': 'Fri',
            'Sat': 'Sat',
            'Sun': 'Sun',

            // Form validations
            'Please enter a valid email address': 'Please enter a valid email address',
            'Please enter a valid phone number': 'Please enter a valid phone number',
            'Please enter a valid date': 'Please enter a valid date',
            'Please enter a valid number': 'Please enter a valid number',
            'Please enter a valid URL': 'Please enter a valid URL',
            'Password must be at least 8 characters': 'Password must be at least 8 characters',
            'Passwords do not match': 'Passwords do not match',
            'Please select an option': 'Please select an option',
            'Please upload a file': 'Please upload a file',
            'File size must be less than {{size}}': 'File size must be less than {{size}}',
            'File type not allowed': 'File type not allowed',

            // AJAX messages
            'Request failed': 'Request failed',
            'Network error': 'Network error',
            'Server error': 'Server error',
            'Timeout error': 'Timeout error',
            'Unauthorized access': 'Unauthorized access',
            'Forbidden access': 'Forbidden access',
            'Not found': 'Not found',
            'Internal server error': 'Internal server error',
            'Bad request': 'Bad request',
            'Conflict': 'Conflict',
            'Too many requests': 'Too many requests',

            // Success messages
            'Data saved successfully': 'Data saved successfully',
            'Data updated successfully': 'Data updated successfully',
            'Data deleted successfully': 'Data deleted successfully',
            'Operation completed successfully': 'Operation completed successfully',
            'Email sent successfully': 'Email sent successfully',
            'Message sent successfully': 'Message sent successfully',
            'File uploaded successfully': 'File uploaded successfully',
            'Settings saved successfully': 'Settings saved successfully',
            'Profile updated successfully': 'Profile updated successfully',
            'Password changed successfully': 'Password changed successfully'
        }
    };

    // Use translations from WordPress
    if (window.mhm_i18n_translations && window.mhm_i18n_translations['mhm-rentiva']) {
        MHMRentiva.i18n.loadTranslations(window.mhm_i18n_translations);
    } else {
        // Load default translations as fallback
        MHMRentiva.i18n.loadTranslations(defaultTranslations, 'en_US');
    }

    // Make globally available
    window.MHM = window.MHM || MHMRentiva;

})(jQuery);
