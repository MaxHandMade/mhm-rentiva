/**
 * Monitoring Dashboard JavaScript
 */

// Global variables
let currentPage = 1;
let totalPages = 1;

jQuery(document).ready(function ($) {
    // Initialize monitoring functionality
    initializeMonitoring();
});

/**
 * Initialize monitoring dashboard
 */
function initializeMonitoring() {
    // Initialize event listeners
    initializeEventListeners();

    // Load initial data
    checkSystemHealth();
    loadPerformanceChart();
}

/**
 * Initialize event listeners
 */
function initializeEventListeners() {
    // Performance monitoring buttons
    jQuery(document).on('click', '#refresh-performance-btn', refreshPerformanceData);
    jQuery(document).on('click', '#generate-performance-report-btn', generatePerformanceReport);
    jQuery(document).on('click', '#clear-performance-data-btn', clearPerformanceData);

    // Log monitoring buttons
    jQuery(document).on('click', '#refresh-log-data-btn', refreshLogData);
    jQuery(document).on('click', '#view-logs-btn', viewLogs);
    jQuery(document).on('click', '#clear-logs-btn', clearOldLogs);

    // System health button
    jQuery(document).on('click', '#check-system-health-btn', checkSystemHealth);

    // Log page buttons
    jQuery(document).on('submit', '#log-filters-form', function (e) {
        e.preventDefault();
        currentPage = 1;
        loadLogs();
    });

    jQuery(document).on('click', '#prev-page', function () {
        if (currentPage > 1) {
            currentPage--;
            loadLogs();
        }
    });

    jQuery(document).on('click', '#next-page', function () {
        if (currentPage < totalPages) {
            currentPage++;
            loadLogs();
        }
    });

    jQuery(document).on('click', '#clear-log-filters-btn', clearLogFilters);
    jQuery(document).on('click', '#clear-old-logs-btn', clearOldLogs);
    jQuery(document).on('click', '#export-logs-btn', exportLogs);
}

/**
 * Refresh performance data
 */
function refreshPerformanceData() {
    jQuery.post(ajaxurl, {
        action: 'mhm_get_performance_report',
        nonce: mhmMonitoring.nonce
    }, function (response) {
        if (response.success) {
            // Debug log removed
            const successMsg = (mhmMonitoring.strings && mhmMonitoring.strings.reportPrinted) || 'Performance report printed to console';
            showNotification(successMsg, 'success');
        } else {
            const errorMsg = (mhmMonitoring.strings && mhmMonitoring.strings.reportFailed) || 'Failed to get performance report';
            showNotification(errorMsg + ': ' + response.data, 'error');
        }
    });
}

/**
 * Generate performance report
 */
function generatePerformanceReport() {
    refreshPerformanceData();
}

/**
 * Clear performance data
 */
function clearPerformanceData() {
    const confirmMsg = (mhmMonitoring.strings && mhmMonitoring.strings.confirmClearPerf) || 'Are you sure you want to clear performance data?';
    if (confirm(confirmMsg)) {
        jQuery.post(ajaxurl, {
            action: 'mhm_clear_performance_data',
            nonce: mhmMonitoring.nonce
        }, function (response) {
            if (response.success) {
                const successMsg = (mhmMonitoring.strings && mhmMonitoring.strings.perfCleared) || 'Performance data cleared';
                showNotification(successMsg, 'success');
                location.reload();
            } else {
                const errorMsg = (mhmMonitoring.strings && mhmMonitoring.strings.clearFailed) || 'Failed to clear data';
                showNotification(errorMsg + ': ' + response.data, 'error');
            }
        });
    }
}

/**
 * Refresh log data
 */
function refreshLogData() {
    location.reload();
}

/**
 * View logs
 */
function viewLogs() {
    window.open(mhmMonitoring.logsUrl, '_blank');
}

/**
 * Check system health
 */
function checkSystemHealth() {
    jQuery.post(ajaxurl, {
        action: 'mhm_get_system_health',
        nonce: mhmMonitoring.nonce
    }, function (response) {
        if (response.success) {
            displaySystemHealth(response.data.checks);
        } else {
            showNotification('System health could not be checked: ' + response.data, 'error');
        }
    });
}

/**
 * Display system health results
 */
function displaySystemHealth(checks) {
    let html = '<div class="health-status">';

    checks.forEach(function (check) {
        let statusClass = check.status === 'ok' ? 'health-ok' : 'health-warning';
        html += '<div class="health-item ' + statusClass + '">';
        html += '<span class="health-icon">' + (check.status === 'ok' ? '✓' : '⚠') + '</span>';
        html += '<span class="health-message">' + check.message + '</span>';
        html += '</div>';
    });

    html += '</div>';
    jQuery('#system-health-content').html(html);
}

/**
 * Load performance chart
 */
function loadPerformanceChart() {
    // Create performance chart with Chart.js
    // This section requires Chart.js library
    // Debug log removed
}

/**
 * Load logs
 */
function loadLogs() {
    const formData = {
        action: 'mhm_get_message_logs',
        nonce: mhmMonitoring.nonce,
        limit: 20,
        offset: (currentPage - 1) * 20
    };

    // Add form data
    const form = jQuery('#log-filters-form').serializeArray();
    form.forEach(function (field) {
        if (field.value) {
            formData[field.name] = field.value;
        }
    });

    jQuery.post(ajaxurl, formData, function (response) {
        if (response.success) {
            displayLogs(response.data.logs);
            updatePagination(response.data.pages);
        } else {
            showNotification('Error occurred while loading logs: ' + response.data, 'error');
        }
    });
}

/**
 * Display logs
 */
function displayLogs(logs) {
    let html = '';
    const noLogsMsg = (mhmMonitoring.strings && mhmMonitoring.strings.noLogs) || 'No logs found';

    if (logs.length === 0) {
        html = '<p>' + noLogsMsg + '</p>';
    } else {
        logs.forEach(function (log) {
            html += '<div class="log-item">';
            html += '<div class="log-header">';
            html += '<span class="log-level log-level-' + log.level + '">' + log.level.toUpperCase() + '</span>';
            html += '<span class="log-timestamp">' + log.created_at + '</span>';
            html += '</div>';
            html += '<div class="log-message">' + escapeHtml(log.message) + '</div>';

            if (log.context && Object.keys(log.context).length > 0) {
                html += '<div class="log-context">';
                html += '<strong>Context:</strong> ';
                html += '<code>' + escapeHtml(JSON.stringify(log.context, null, 2)) + '</code>';
                html += '</div>';
            }
            html += '</div>';
        });
    }

    jQuery('#log-list').html(html);
}

/**
 * Update pagination
 */
function updatePagination(pages) {
    totalPages = pages;
    const pageText = (mhmMonitoring.strings && mhmMonitoring.strings.page) || 'Page';

    jQuery('#page-info').text(pageText + ' ' + currentPage + ' / ' + totalPages);

    jQuery('#prev-page').prop('disabled', currentPage <= 1);
    jQuery('#next-page').prop('disabled', currentPage >= totalPages);
}

/**
 * Clear log filters
 */
function clearLogFilters() {
    jQuery('#log-filters-form')[0].reset();
    currentPage = 1;
    loadLogs();
}

/**
 * Clear old logs
 */
function clearOldLogs() {
    const confirmMsg = (mhmMonitoring.strings && mhmMonitoring.strings.confirmClearLogs) || 'Are you sure you want to clear logs older than 7 days?';
    if (confirm(confirmMsg)) {
        jQuery.post(ajaxurl, {
            action: 'mhm_clear_message_logs',
            nonce: mhmMonitoring.nonce,
            older_than: 7
        }, function (response) {
            if (response.success) {
                showNotification(response.data.message, 'success');
                loadLogs();
            } else {
                const errorMsg = (mhmMonitoring.strings && mhmMonitoring.strings.error) || 'Error';
                showNotification(errorMsg + ': ' + response.data, 'error');
            }
        });
    }
}

/**
 * Export logs
 */
function exportLogs() {
    const infoMsg = (mhmMonitoring.strings && mhmMonitoring.strings.exportSoon) || 'Log export feature coming soon';
    showNotification(infoMsg, 'info');
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Show notification
 */
function showNotification(message, type) {
    // Create notification element
    const notification = jQuery('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

    // Add to page
    jQuery('.wrap').prepend(notification);

    // Auto dismiss after 5 seconds
    setTimeout(function () {
        notification.fadeOut(function () {
            notification.remove();
        });
    }, 5000);

    // Add dismiss functionality
    notification.find('.notice-dismiss').on('click', function () {
        notification.fadeOut(function () {
            notification.remove();
        });
    });
}

// Message Logger Functions
window.mhmMessageLogger = {
    viewLogs: function () {
        window.open(mhmMonitoring.logsUrl, '_blank');
    },

    clearOldLogs: function () {
        const confirmMsg = (mhmMonitoring.strings && mhmMonitoring.strings.confirmClearLogs) || 'Are you sure you want to clear logs older than 7 days?';
        if (confirm(confirmMsg)) {
            jQuery.post(ajaxurl, {
                action: 'mhm_clear_message_logs',
                nonce: mhmMonitoring.nonce,
                older_than: 7
            }, function (response) {
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    location.reload();
                } else {
                    const errorMsg = (mhmMonitoring.strings && mhmMonitoring.strings.error) || 'Error';
                    showNotification(errorMsg + ': ' + response.data, 'error');
                }
            });
        }
    }
};

// Performance Monitor Functions
window.mhmMessagesPerformance = {
    clearData: function () {
        const confirmMsg = (mhmMonitoring.strings && mhmMonitoring.strings.confirmClearPerf) || 'Are you sure you want to clear performance data?';
        if (confirm(confirmMsg)) {
            jQuery.post(ajaxurl, {
                action: 'mhm_clear_performance_data',
                nonce: mhmMonitoring.nonce
            }, function (response) {
                if (response.success) {
                    const successMsg = (mhmMonitoring.strings && mhmMonitoring.strings.perfCleared) || 'Performance data cleared';
                    showNotification(successMsg, 'success');
                    location.reload();
                } else {
                    const errorMsg = (mhmMonitoring.strings && mhmMonitoring.strings.clearFailed) || 'Failed to clear data';
                    showNotification(errorMsg + ': ' + response.data, 'error');
                }
            });
        }
    },

    generateReport: function () {
        jQuery.post(ajaxurl, {
            action: 'mhm_get_performance_report',
            nonce: mhmMonitoring.nonce
        }, function (response) {
            if (response.success) {
                // Debug log removed
                const successMsg = (mhmMonitoring.strings && mhmMonitoring.strings.reportPrinted) || 'Performance report printed to console';
                showNotification(successMsg, 'success');
            } else {
                const errorMsg = (mhmMonitoring.strings && mhmMonitoring.strings.reportFailed) || 'Failed to get performance report';
                showNotification(errorMsg + ': ' + response.data, 'error');
            }
        });
    }
};
