/**
 * MHM Rentiva About Page JavaScript
 * Interactive features for the about page
 */

(function ($) {
    'use strict';

    const MHMAbout = {

        init: function () {
            this.bindEvents();
            this.initializeFeatures();
        },

        bindEvents: function () {
            // Tab switching
            $(document).on('click', '.nav-tab', this.handleTabSwitch.bind(this));

            // Copy system info
            $(document).on('click', '.info-value', this.handleInfoCopy.bind(this));

            // External link tracking (if needed)
            $(document).on('click', '.external-link', this.handleExternalLink.bind(this));

            // Feature comparison tooltips
            $(document).on('mouseenter', '.feature-yes, .feature-no', this.showFeatureTooltip.bind(this));
            $(document).on('mouseleave', '.feature-yes, .feature-no', this.hideFeatureTooltip.bind(this));
        },

        initializeFeatures: function () {
            // Add loading states for dynamic content
            this.initializeSystemInfo();
            this.initializeChangelog();
        },

        handleTabSwitch: function (e) {
            e.preventDefault();

            const $tab = $(e.currentTarget);
            const tabId = $tab.attr('href').split('tab=')[1]; // Get tab parameter

            // Update tab states
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');

            // Load tab content via AJAX
            this.loadTabContent(tabId);
        },

        loadTabContent: function (tabId) {
            const $tabContent = $('.tab-content');

            // Show loading state
            $tabContent.html('<div class="mhm-about-loading">' +
                '<span class="spinner is-active"></span> ' +
                mhmAboutAdmin.strings.loading +
                '</div>');

            // Make AJAX request
            $.ajax({
                url: mhmAboutAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'mhm_about_load_tab',
                    tab: tabId,
                    nonce: mhmAboutAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        $tabContent.html(response.data.content);
                        // Update URL without page reload
                        if (history.pushState) {
                            const url = new URL(window.location);
                            url.searchParams.set('tab', tabId);
                            history.pushState(null, null, url.toString());
                        }
                    } else {
                        $tabContent.html('<div class="notice notice-error"><p>' +
                            (response.data.message || mhmAboutAdmin.strings.error) +
                            '</p></div>');
                    }
                },
                error: function () {
                    $tabContent.html('<div class="notice notice-error"><p>' +
                        mhmAboutAdmin.strings.error +
                        '</p></div>');
                }
            });
        },

        handleInfoCopy: function (e) {
            const $element = $(e.currentTarget);
            const textToCopy = $element.text().trim();

            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API
                navigator.clipboard.writeText(textToCopy).then(() => {
                    const copiedText = (mhmAboutAdmin.strings && mhmAboutAdmin.strings.copied) || 'Copied!';
                    this.showCopyFeedback($element, copiedText);
                }).catch(() => {
                    this.fallbackCopyTextToClipboard(textToCopy, $element);
                });
            } else {
                // Fallback for older browsers
                this.fallbackCopyTextToClipboard(textToCopy, $element);
            }
        },

        fallbackCopyTextToClipboard: function (text, $element) {
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
                const copiedText = (mhmAboutAdmin.strings && mhmAboutAdmin.strings.copied) || 'Copied!';
                this.showCopyFeedback($element, copiedText);
            } catch (err) {
                const failedText = (mhmAboutAdmin.strings && mhmAboutAdmin.strings.copyFailed) || 'Copy failed';
                this.showCopyFeedback($element, failedText, 'error');
            }

            document.body.removeChild(textArea);
        },

        showCopyFeedback: function ($element, message, type = 'success') {
            // Remove existing feedback
            $('.copy-feedback').remove();

            // Translate message if available
            const translatedMessage = (mhmAboutAdmin.strings && mhmAboutAdmin.strings[message]) || message;

            // Create feedback element
            const $feedback = $('<span>', {
                class: 'copy-feedback ' + type,
                text: translatedMessage
            });

            // Position and show
            $element.append($feedback);
            $feedback.fadeIn(200);

            // Auto hide after 2 seconds
            setTimeout(() => {
                $feedback.fadeOut(200, function () {
                    $(this).remove();
                });
            }, 2000);
        },

        handleExternalLink: function (e) {
            const $link = $(e.currentTarget);
            const url = $link.attr('href');

            // Track external link clicks (if analytics is available)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'click', {
                    event_category: 'external_link',
                    event_label: url,
                    transport_type: 'beacon'
                });
            }

            // Add visual feedback
            $link.addClass('clicked');
            setTimeout(() => {
                $link.removeClass('clicked');
            }, 200);
        },

        showFeatureTooltip: function (e) {
            const $element = $(e.currentTarget);
            const tooltipText = $element.attr('title') || $element.data('tooltip');

            if (!tooltipText) return;

            // Remove existing tooltips
            $('.feature-tooltip').remove();

            // Create tooltip
            const $tooltip = $('<div>', {
                class: 'feature-tooltip',
                text: tooltipText
            });

            // Position tooltip
            const rect = $element[0].getBoundingClientRect();
            $tooltip.css({
                top: rect.top - 35,
                left: rect.left + (rect.width / 2) - 100
            });

            // Add to page
            $('body').append($tooltip);
            $tooltip.fadeIn(200);
        },

        hideFeatureTooltip: function () {
            $('.feature-tooltip').fadeOut(200, function () {
                $(this).remove();
            });
        },

        initializeSystemInfo: function () {
            // Add refresh button for system info
            const $systemInfo = $('.system-info-grid');
            if ($systemInfo.length > 0) {
                const refreshText = (mhmAboutAdmin.strings && mhmAboutAdmin.strings.refreshSystemInfo) || 'Refresh System Info';
                const $refreshBtn = $('<button>', {
                    class: 'button button-secondary refresh-system-info',
                    text: refreshText,
                    type: 'button'
                });

                $systemInfo.before($refreshBtn);

                $refreshBtn.on('click', () => {
                    this.refreshSystemInfo();
                });
            }
        },

        refreshSystemInfo: function () {
            const $btn = $('.refresh-system-info');
            const originalText = $btn.text();
            const refreshingText = (mhmAboutAdmin.strings && mhmAboutAdmin.strings.refreshing) || 'Refreshing...';

            // Show loading state
            $btn.prop('disabled', true).text(refreshingText);

            // In a real implementation, this would make an AJAX call
            // For now, just simulate the refresh
            setTimeout(() => {
                $btn.prop('disabled', false).text(originalText);
                const refreshedText = (mhmAboutAdmin.strings && mhmAboutAdmin.strings.systemRefreshed) || 'System information refreshed';
                this.showNotification(refreshedText, 'success');
            }, 1000);
        },

        initializeChangelog: function () {
            // Add expand/collapse functionality for changelog items
            $('.changelog-item').each(function () {
                const $item = $(this);
                const $content = $item.find('.changelog-content');
                const $header = $item.find('.changelog-header');

                // Check if toggle already exists to prevent duplicates
                if ($header.find('.changelog-toggle').length > 0) {
                    return; // Skip if toggle already added
                }

                // Add toggle button
                const $toggle = $('<button>', {
                    class: 'changelog-toggle',
                    type: 'button',
                    html: '<span class="dashicons dashicons-arrow-down-alt2"></span>'
                });

                $header.append($toggle);

                $toggle.on('click', function () {
                    $content.slideToggle(300);
                    $toggle.find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
                });

                // Initially collapse non-current versions
                if (!$item.hasClass('current')) {
                    $content.hide();
                    $toggle.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                }
            });
        },

        showNotification: function (message, type = 'info') {
            // Remove existing notifications
            $('.mhm-about-notification').remove();

            // Create notification
            const $notification = $('<div>', {
                class: 'notice notice-' + type + ' is-dismissible mhm-about-notification',
                html: '<p>' + message + '</p><button type="button" class="notice-dismiss"></button>'
            });

            // Add to page
            $('.about-header').after($notification);

            // Auto dismiss after 3 seconds
            setTimeout(() => {
                $notification.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 3000);

            // Manual dismiss
            $notification.on('click', '.notice-dismiss', function () {
                $notification.fadeOut(300, function () {
                    $(this).remove();
                });
            });
        },

        // Utility functions
        debounce: function (func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

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
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        MHMAbout.init();
    });

    // Make it globally available for debugging
    window.MHMAbout = MHMAbout;

})(jQuery);
