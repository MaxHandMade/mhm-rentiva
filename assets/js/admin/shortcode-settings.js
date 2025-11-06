/**
 * Shortcode Settings Admin JavaScript
 * 
 * @package MHMRentiva
 * @since 3.0.1
 */

(function ($) {
    'use strict';

    /**
     * Shortcode Settings Controller
     */
    const ShortcodeSettings = {
        /**
         * Initialize
         */
        init: function () {
            this.bindEvents();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function () {
            // Copy shortcode button
            $('.mhm-copy-shortcode').on('click', this.copyShortcode.bind(this));

            // Reset settings button
            $('.mhm-reset-settings').on('click', this.resetSettings.bind(this));

            // Toggle all in group
            $('.mhm-toggle-group').on('click', this.toggleGroup.bind(this));

            // Cache enabled toggle
            $('input[name$="[cache_enabled]"]').on('change', this.toggleCacheTTL.bind(this));

            // Form change tracking
            $('form').on('change', 'input, select, textarea', this.markUnsavedChanges.bind(this));
        },

        /**
         * Copy shortcode to clipboard
         */
        copyShortcode: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const shortcode = $button.data('shortcode');

            // Create temporary input
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(shortcode).select();

            try {
                document.execCommand('copy');
                this.showNotification(mhmRentivaShortcodeSettings.i18n.copied, 'success');

                // Visual feedback
                $button.addClass('copied');
                setTimeout(function () {
                    $button.removeClass('copied');
                }, 2000);
            } catch (err) {
                this.showNotification(mhmRentivaShortcodeSettings.i18n.copy_failed, 'error');
            }

            $temp.remove();
        },

        /**
         * Reset settings to defaults
         */
        resetSettings: function (e) {
            e.preventDefault();

            if (!confirm(mhmRentivaShortcodeSettings.i18n.confirm_reset)) {
                return;
            }

            // Set all checkboxes to checked (default is enabled)
            $('input[type="checkbox"]').prop('checked', true);

            // Set default cache TTL
            $('input[name$="[cache_ttl]"]').val(300);

            // Submit form
            $('form').submit();
        },

        /**
         * Toggle group shortcodes
         */
        toggleGroup: function (e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $group = $button.closest('.mhm-settings-section');
            const enabled = $button.data('enabled');

            // Toggle all shortcodes in group
            $group.find('.mhm-shortcode-card input[name$="[enabled]"]').prop('checked', !enabled);

            // Update button state
            $button.data('enabled', !enabled);
            $button.text(!enabled ?
                mhmRentivaShortcodeSettings.i18n.disable_all :
                mhmRentivaShortcodeSettings.i18n.enable_all
            );
        },

        /**
         * Toggle cache TTL field visibility
         */
        toggleCacheTTL: function (e) {
            const $checkbox = $(e.currentTarget);
            const $card = $checkbox.closest('.mhm-shortcode-card');
            const $ttlGroup = $card.find('.cache-ttl-group');

            if ($checkbox.is(':checked')) {
                $ttlGroup.slideDown(200);
            } else {
                $ttlGroup.slideUp(200);
            }
        },

        /**
         * Mark form as having unsaved changes
         */
        markUnsavedChanges: function () {
            const unsavedText = (mhmRentivaShortcodeSettings.i18n && mhmRentivaShortcodeSettings.i18n.unsaved_changes) ||
                'You have unsaved changes. Are you sure you want to leave this page?';
            window.onbeforeunload = function () {
                return unsavedText;
            };

            // Clear warning on form submit
            $('form').on('submit', function () {
                window.onbeforeunload = null;
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function () {
            // Add tooltip functionality if needed
            $('.mhm-tooltip').each(function () {
                const $tooltip = $(this);
                const text = $tooltip.data('tooltip');

                if (text) {
                    $tooltip.attr('title', text);
                }
            });
        },

        /**
         * Show notification
         */
        showNotification: function (message, type) {
            type = type || 'info';

            const $notification = $('<div class="mhm-notification ' + type + '">' + message + '</div>');
            $('body').append($notification);

            // Show notification
            setTimeout(function () {
                $notification.addClass('show');
            }, 100);

            // Hide after 3 seconds
            setTimeout(function () {
                $notification.removeClass('show');
                setTimeout(function () {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Filter shortcodes
         */
        filterShortcodes: function (searchTerm) {
            searchTerm = searchTerm.toLowerCase();

            $('.mhm-shortcode-card').each(function () {
                const $card = $(this);
                const shortcode = $card.find('code').text().toLowerCase();

                if (shortcode.includes(searchTerm)) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        },

        /**
         * Export settings
         */
        exportSettings: function () {
            const settings = this.collectSettings();
            const json = JSON.stringify(settings, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'mhm-rentiva-shortcode-settings.json';
            a.click();
            URL.revokeObjectURL(url);
        },

        /**
         * Collect current settings
         */
        collectSettings: function () {
            const settings = {
                global: {},
                shortcodes: {}
            };

            // Global settings
            $('input[name^="mhm_rentiva_shortcode_settings[global]"]').each(function () {
                const $input = $(this);
                const name = $input.attr('name').match(/\[global\]\[([^\]]+)\]/)[1];

                if ($input.attr('type') === 'checkbox') {
                    settings.global[name] = $input.is(':checked');
                } else {
                    settings.global[name] = $input.val();
                }
            });

            // Shortcode settings
            $('.mhm-shortcode-card').each(function () {
                const $card = $(this);
                const shortcode = $card.find('code').text();

                settings.shortcodes[shortcode] = {
                    enabled: $card.find('input[name$="[enabled]"]').is(':checked'),
                    cache_enabled: $card.find('input[name$="[cache_enabled]"]').is(':checked'),
                    cache_ttl: parseInt($card.find('input[name$="[cache_ttl]"]').val() || 300)
                };
            });

            return settings;
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        ShortcodeSettings.init();
    });

    /**
     * Expose to global scope
     */
    window.MHMRentivaShortcodeSettings = ShortcodeSettings;

})(jQuery);

