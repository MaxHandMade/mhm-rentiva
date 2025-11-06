/**
 * Dark Mode JavaScript for MHM Rentiva Plugin
 * 
 * @since 4.0.0
 */
(function ($) {
    'use strict';

    // Dark Mode Manager
    const DarkModeManager = {
        init: function () {
            this.bindEvents();
            this.applyDarkMode();
            this.updateStatus();
        },

        bindEvents: function () {
            // Dark mode toggle change
            $('#mhm_rentiva_dark_mode').on('change', function () {
                DarkModeManager.saveDarkMode($(this).val());
            });

            // Test dark mode button
            $('#mhm-test-dark-mode').on('click', function () {
                DarkModeManager.testDarkMode();
            });

            // System preference change detection
            if (window.matchMedia) {
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                    if (get_option('mhm_rentiva_dark_mode') === 'auto') {
                        DarkModeManager.applyDarkMode();
                    }
                });
            }
        },

        saveDarkMode: function (mode) {
            $.ajax({
                url: mhmDarkMode.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_save_dark_mode',
                    mode: mode,
                    nonce: mhmDarkMode.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // Update the select field value after successful save
                        $('#mhm_rentiva_dark_mode').val(mode);
                        DarkModeManager.applyDarkModeDirect(mode);
                        DarkModeManager.updateStatus(mode);
                        DarkModeManager.showNotice('Dark mode preference saved successfully!', 'success');
                    } else {
                        DarkModeManager.showNotice('Failed to save dark mode preference.', 'error');
                    }
                },
                error: function () {
                    DarkModeManager.showNotice('Failed to save dark mode preference.', 'error');
                }
            });
        },

        applyDarkMode: function () {
            const mode = get_option('mhm_rentiva_dark_mode') || 'auto';
            DarkModeManager.applyDarkModeDirect(mode);
        },

        applyDarkModeDirect: function (mode) {
            const body = $('body');

            // Remove existing dark mode classes
            body.removeClass('mhm-dark-mode mhm-light-mode');

            if (mode === 'dark') {
                body.addClass('mhm-dark-mode');
            } else if (mode === 'light') {
                body.addClass('mhm-light-mode');
            } else if (mode === 'auto') {
                // Check system preference
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    body.addClass('mhm-dark-mode');
                } else {
                    body.addClass('mhm-light-mode');
                }
            }
        },

        testDarkMode: function () {
            const body = $('body');
            const currentMode = get_option('mhm_rentiva_dark_mode') || 'auto';

            // Toggle dark mode for testing
            if (body.hasClass('mhm-dark-mode')) {
                body.removeClass('mhm-dark-mode').addClass('mhm-light-mode');
                DarkModeManager.updateStatus('light');
            } else {
                body.removeClass('mhm-light-mode').addClass('mhm-dark-mode');
                DarkModeManager.updateStatus('dark');
            }

            // Restore after 3 seconds
            setTimeout(function () {
                DarkModeManager.applyDarkModeDirect(currentMode);
                DarkModeManager.updateStatus(currentMode);
            }, 3000);
        },

        // Real dark mode change (saves to database)
        changeDarkMode: function (mode) {
            DarkModeManager.saveDarkMode(mode);
        },

        updateStatus: function (overrideMode) {
            const mode = overrideMode || get_option('mhm_rentiva_dark_mode') || 'auto';
            const statusElement = $('#mhm-dark-mode-status');

            if (statusElement.length) {
                statusElement
                    .removeClass('auto light dark')
                    .addClass(mode)
                    .text(mode.toUpperCase());
            }
        },

        showNotice: function (message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p><strong>' + message + '</strong></p></div>');

            $('.mhm-settings-page').prepend(notice);

            // Auto-dismiss after 3 seconds
            setTimeout(function () {
                notice.fadeOut(function () {
                    notice.remove();
                });
            }, 3000);
        }
    };

    // Helper function to get option value
    function get_option(optionName, defaultValue) {
        // Try different selectors for the dark mode field
        let select = $('select[name="mhm_rentiva_settings[' + optionName + ']"]');
        if (select.length === 0) {
            select = $('select[name="' + optionName + '"]');
        }
        if (select.length === 0) {
            select = $('#' + optionName);
        }

        if (select.length) {
            return select.val();
        }

        // Try to get from input field
        const input = $('input[name="' + optionName + '"]');
        if (input.length) {
            return input.val();
        }

        return defaultValue;
    }

    // Initialize when document is ready
    $(document).ready(function () {
        DarkModeManager.init();
    });

    // Expose to global scope for testing
    window.MHMDarkMode = DarkModeManager;

})(jQuery);
