/**
 * MHM Rentiva - Settings Page Central JavaScript
 * 
 * Handles general settings interactions, test connections, and tab resets.
 * Optimized to prevent double execution and provide visual feedback.
 */
jQuery(document).ready(function ($) {
    'use strict';

    /**
     * 1. Test Email Connection
     * Sends a connection test email using AJAX.
     */
    $(document).off('click', '.mhm-send-test-email').on('click', '.mhm-send-test-email', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const nonce = $btn.data('nonce');
        const originalHtml = $btn.html();

        if ($btn.prop('disabled')) return;

        // Button Loading State
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span> Sending...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_send_test_email_ajax',
                nonce: nonce,
                template_key: 'booking_created_admin' // General connection test template
            },
            success: function (response) {
                if (response.success) {
                    showNotice(response.data || 'Test email sent successfully!', 'success');
                } else {
                    showNotice('Error: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function () {
                showNotice('Connection error. Please check server logs.', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    /**
     * 2. Tab Reset Functionality
     * Triggers a factory reset for the current tab's settings.
     */
    $(document).off('click', '.mhm-reset-tab-settings').on('click', '.mhm-reset-tab-settings', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const tab = $btn.data('tab');

        // Use localized strings from AssetManager if available
        const strings = (window.mhmRentivaSettings && window.mhmRentivaSettings.strings) || {};
        const confirmMsg = strings.confirmResetTab || 'Are you sure you want to reset this tab\'s settings to default values? This action cannot be undone.';

        if (confirm(confirmMsg)) {
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span> ' + (strings.resetting || 'Resetting...'));

            // Redirect to the reset URL handled by SettingsHandler.php
            const nonce = (window.mhmRentivaSettings && window.mhmRentivaSettings.resetNonce) || '';
            const resetUrl = new URL(window.location.href);
            resetUrl.searchParams.set('reset_defaults', 'true');
            resetUrl.searchParams.set('tab', tab);
            resetUrl.searchParams.set('_wpnonce', nonce);

            window.location.href = resetUrl.toString();
        }
    });

    /**
     * 4. Accordion Toggle Logic (Frontend & Display Tab)
     */
    $(document).off('click', '.mhm-accordion-header').on('click', '.mhm-accordion-header', function () {
        const $header = $(this);
        const $content = $header.next('.mhm-accordion-content');
        const $icon = $header.find('.dashicons');

        // Toggle current
        $content.slideToggle(250);
        $header.toggleClass('active');

        // Swap Icons
        if ($icon.hasClass('dashicons-arrow-down')) {
            $icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
        } else {
            $icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
        }
    });

    /**
     * 5. Centralized Notice System
     */
    function showNotice(message, type) {
        const noticeClass = 'notice-' + (type || 'info');
        const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); border-left-width: 4px !important; display: none;"><p><strong>' + message + '</strong></p></div>');

        // Remove existing notices to avoid clutter
        $('.notice.is-dismissible').filter(':visible').fadeOut(200, function () { $(this).remove(); });

        $('body').append($notice);
        $notice.fadeIn(300);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            $notice.fadeOut(500, function () {
                $(this).remove();
            });
        }, 5000);
    }
});
