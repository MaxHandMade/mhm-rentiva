/**
 * MHM Rentiva Cron Job Monitor JavaScript
 *
 * @package MHM_Rentiva
 * @since 1.0.0
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Refresh cron list on page load
    refreshCronList();

    // Refresh Cron List
    $('#mhm-refresh-cron-list-btn').on('click', function () {
        refreshCronList();
    });

    // Run Cron Job (event delegation for dynamically created buttons)
    $(document).on('click', '.mhm-run-cron-btn', function () {
        const hook = $(this).data('hook');
        if (!hook) {
            return;
        }

        if (!confirm(mhm_cron_vars.confirm_run_text || 'This will execute the cron job immediately. Continue?')) {
            return;
        }

        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + (mhm_cron_vars.running_text || 'Running...'));

        $.post(ajaxurl, {
            action: 'mhm_run_cron_job',
            nonce: mhm_cron_vars.nonce,
            hook: hook
        }, function (response) {
            if (response.success) {
                alert(mhm_cron_vars.success_text + ' ' + response.data.message);
                refreshCronList(); // Refresh to update next run time
            } else {
                alert(mhm_cron_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html(originalText);
        }).fail(function () {
            alert(mhm_cron_vars.error_text + ' Network error occurred');
            btn.prop('disabled', false).html(originalText);
        });
    });

    /**
     * Refresh cron job list
     */
    function refreshCronList() {
        const btn = $('#mhm-refresh-cron-list-btn');
        const originalText = btn.html();
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + (mhm_cron_vars.loading_text || 'Loading...'));

        $.post(ajaxurl, {
            action: 'mhm_list_cron_jobs',
            nonce: mhm_cron_vars.nonce
        }, function (response) {
            if (response.success) {
                renderCronList(response.data.crons || []);
            } else {
                $('#mhm-cron-list').html('<div class="notice notice-error"><p>' + (response.data || mhm_cron_vars.error_text) + '</p></div>');
            }
            btn.prop('disabled', false).html(originalText);
        }).fail(function () {
            $('#mhm-cron-list').html('<div class="notice notice-error"><p>' + mhm_cron_vars.error_text + ' Network error occurred</p></div>');
            btn.prop('disabled', false).html(originalText);
        });
    }

    /**
     * Render cron job list as table
     */
    function renderCronList(crons) {
        if (!crons || crons.length === 0) {
            $('#mhm-cron-list').html('<div class="notice notice-info"><p>No cron jobs found.</p></div>');
            return;
        }

        let html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead>';
        html += '<tr>';
        html += '<th>' + (mhm_cron_vars.name_text || 'Name') + '</th>';
        html += '<th>' + (mhm_cron_vars.description_text || 'Description') + '</th>';
        html += '<th>' + (mhm_cron_vars.schedule_text || 'Schedule') + '</th>';
        html += '<th>' + (mhm_cron_vars.next_run_text || 'Next Run') + '</th>';
        html += '<th>' + (mhm_cron_vars.status_text || 'Status') + '</th>';
        html += '<th>' + (mhm_cron_vars.actions_text || 'Actions') + '</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';

        crons.forEach(function (cron) {
            const statusClass = cron.is_scheduled ? 'status-scheduled' : 'status-not-scheduled';
            const statusText = cron.is_scheduled
                ? '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ' + (mhm_cron_vars.scheduled_text || 'Scheduled')
                : '<span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> ' + (mhm_cron_vars.not_scheduled_text || 'Not Scheduled');

            // Add hook registration status indicator
            let hookStatus = '';
            if (cron.is_registered !== undefined) {
                if (cron.is_registered) {
                    hookStatus = ' <span class="dashicons dashicons-yes" style="color: #46b450; font-size: 14px;" title="Hook is registered"></span>';
                } else {
                    hookStatus = ' <span class="dashicons dashicons-warning" style="color: #f56e28; font-size: 14px;" title="Hook is not registered - function may not be active"></span>';
                }
            }

            html += '<tr>';
            html += '<td><strong>' + escapeHtml(cron.name) + '</strong>' + hookStatus + '<br><code style="font-size: 11px; color: #666;">' + escapeHtml(cron.hook) + '</code></td>';
            html += '<td>' + escapeHtml(cron.description) + '</td>';
            html += '<td>' + escapeHtml(cron.schedule) + '</td>';
            html += '<td>' + escapeHtml(cron.next_run_formatted) + '</td>';
            html += '<td class="' + statusClass + '">' + statusText + '</td>';
            html += '<td>';
            if (cron.is_registered === false) {
                html += '<button type="button" class="button button-small" disabled title="Hook is not registered - cannot run">';
                html += '<span class="dashicons dashicons-dismiss"></span> ' + (mhm_cron_vars.run_text || 'Run Now');
                html += '</button>';
            } else {
                html += '<button type="button" class="button button-small mhm-run-cron-btn" data-hook="' + escapeHtml(cron.hook) + '">';
                html += '<span class="dashicons dashicons-controls-play"></span> ' + (mhm_cron_vars.run_text || 'Run Now');
                html += '</button>';
            }
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody>';
        html += '</table>';

        $('#mhm-cron-list').html(html);
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        if (!text) {
            return '';
        }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }
});

