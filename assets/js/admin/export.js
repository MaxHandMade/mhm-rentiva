/**
 * Export Page JavaScript
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize export page
        initExportPage();
    });

    /**
     * Initialize export page
     */
    function initExportPage() {
        // Date range change handler
        $('#date_range').on('change', function () {
            handleDateRangeChange();
        });

        // Filter form submission
        $('#export-filters-form').on('submit', function (e) {
            e.preventDefault();
            applyFilters();
        });

        // Reset filters
        $(document).on('click', 'button[onclick="resetFilters()"]', function () {
            resetFilters();
        });

        // Apply filters
        $(document).on('click', 'button[onclick="applyFilters()"]', function () {
            applyFilters();
        });

        // Custom export form toggle
        $(document).on('click', 'button[onclick="showCustomExportForm()"]', function () {
            showCustomExportForm();
        });
    }

    /**
     * Handle date range change
     */
    function handleDateRangeChange() {
        var dateRange = $('#date_range').val();
        var customDateRange = $('.custom-date-range');

        if (dateRange === 'custom') {
            customDateRange.show();
        } else {
            customDateRange.hide();
        }
    }

    /**
     * Apply filters
     */
    function applyFilters() {

        var form = $('#export-filters-form');
        if (form.length === 0) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Export filters form not found');
            }
            showNotice('Export filters form not found!', 'error');
            return;
        }

        var formData = form.serialize();

        var nonce = $('#mhm_rentiva_export_filters_nonce').val();

        // Show loading
        showLoading();

        // Send AJAX request
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : window.location.origin + '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'mhm_rentiva_apply_export_filters',
                nonce: nonce,
                filters: formData
            },
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showNotice('Filters applied successfully!', 'success');
                    // Show filtered results
                    showFilteredResults(response.data);
                } else {
                    showNotice('Error applying filters: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                if (typeof console !== 'undefined' && console.error) {
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('AJAX error:', xhr, status, error);
                    }
                }
                hideLoading();
                showNotice('Error applying filters. Please try again.', 'error');
            }
        });
    }

    // Global functions for onclick handlers
    window.applyFilters = applyFilters;

    /**
     * Reset filters
     */
    function resetFilters() {
        $('#export-filters-form')[0].reset();
        $('.custom-date-range').hide();
        showNotice('Filters reset successfully!', 'success');
    }

    // Global functions for onclick handlers
    window.resetFilters = resetFilters;

    /**
     * Show custom export form
     */
    function showCustomExportForm() {
        var form = $('.mhm-custom-export-form');
        if (form.is(':visible')) {
            form.slideUp();
        } else {
            form.slideDown();
        }
    }

    // Global functions for onclick handlers
    window.showCustomExportForm = showCustomExportForm;

    /**
     * Show filtered results
     */
    function showFilteredResults(data) {
        // Remove existing results
        $('.mhm-filtered-results').remove();

        // Create results container
        var resultsHtml = '<div class="mhm-filtered-results" style="margin: 20px 0; padding: 20px; background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 8px;">';
        resultsHtml += '<h3 style="margin: 0 0 15px 0; color: #1d2327;">📊 Filtered Results</h3>';

        if (data && data.records_count !== undefined) {
            resultsHtml += '<div class="results-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">';
            resultsHtml += '<div class="result-card" style="background: #fff; padding: 15px; border-radius: 6px; border-left: 4px solid #0073aa;">';
            resultsHtml += '<div style="font-size: 24px; font-weight: bold; color: #0073aa;">' + data.records_count + '</div>';
            resultsHtml += '<div style="color: #646970; font-size: 14px;">Total Records</div>';
            resultsHtml += '</div>';

            if (data.total_amount) {
                resultsHtml += '<div class="result-card" style="background: #fff; padding: 15px; border-radius: 6px; border-left: 4px solid #00a32a;">';
                resultsHtml += '<div style="font-size: 24px; font-weight: bold; color: #00a32a;">₺' + data.total_amount + '</div>';
                resultsHtml += '<div style="color: #646970; font-size: 14px;">Total Amount</div>';
                resultsHtml += '</div>';
            }

            resultsHtml += '</div>';
        }

        // Show sample data if available
        if (data && data.sample_records && data.sample_records.length > 0) {
            resultsHtml += '<div class="sample-data" style="margin-top: 20px;">';
            resultsHtml += '<h4 style="margin: 0 0 10px 0; color: #1d2327;">Sample Records (First 5)</h4>';
            resultsHtml += '<div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 6px; overflow: hidden;">';
            resultsHtml += '<table style="width: 100%; border-collapse: collapse;">';
            resultsHtml += '<thead style="background: #f8f9fa;">';
            resultsHtml += '<tr><th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e5e9;">ID</th>';
            resultsHtml += '<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e5e9;">Date</th>';
            resultsHtml += '<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e5e9;">Status</th>';
            resultsHtml += '<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e1e5e9;">Amount</th></tr>';
            resultsHtml += '</thead><tbody>';

            data.sample_records.forEach(function (record) {
                resultsHtml += '<tr>';
                resultsHtml += '<td style="padding: 12px; border-bottom: 1px solid #f1f3f4;">' + record.id + '</td>';
                resultsHtml += '<td style="padding: 12px; border-bottom: 1px solid #f1f3f4;">' + record.date + '</td>';
                resultsHtml += '<td style="padding: 12px; border-bottom: 1px solid #f1f3f4;">' + record.status + '</td>';
                resultsHtml += '<td style="padding: 12px; border-bottom: 1px solid #f1f3f4;">₺' + record.amount + '</td>';
                resultsHtml += '</tr>';
            });

            resultsHtml += '</tbody></table>';
            resultsHtml += '</div>';
            resultsHtml += '</div>';
        }

        resultsHtml += '<div style="margin-top: 20px; text-align: center;">';
        resultsHtml += '<button type="button" class="button button-primary" onclick="exportFilteredData()">Export Filtered Data</button>';
        resultsHtml += '<button type="button" class="button" onclick="clearFilteredResults()" style="margin-left: 10px;">Clear Results</button>';
        resultsHtml += '</div>';

        resultsHtml += '</div>';

        // Add to page
        $('.mhm-advanced-filters').after(resultsHtml);
    }

    /**
     * Update export options with filtered data
     */
    function updateExportOptions(data) {
        // Update export buttons with filtered data
        $('.export-options .button').each(function () {
            var button = $(this);
            var form = button.closest('form');

            // Add filter data to form
            form.find('input[name="filters"]').remove();
            form.append('<input type="hidden" name="filters" value="' + encodeURIComponent(JSON.stringify(data)) + '">');
        });
    }

    /**
     * Show loading indicator
     */
    function showLoading() {
        $('.mhm-export-filters').append('<div class="loading-overlay"><div class="spinner is-active"></div></div>');
    }

    /**
     * Hide loading indicator
     */
    function hideLoading() {
        $('.loading-overlay').remove();
    }

    /**
     * Show notice message
     */
    function showNotice(message, type) {

        type = type || 'info';
        var noticeClass = 'notice-' + type;
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + message + '</strong></p></div>');

        // Remove any existing notices first
        $('.notice').remove();

        // Add to body for better visibility
        $('body').append(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notice.fadeOut(500, function () {
                notice.remove();
            });
        }, 5000);
    }

    /**
     * Download export file
     */
    function downloadExportFile(exportId) {
        showNotice('Downloading export file...', 'info');

        // Create a new export with the same filters
        var form = $('#export-filters-form');
        var formData = form.serialize();

        // Create export form - use admin-post.php for export action
        var exportForm = $('<form method="post" action="' + window.location.origin + window.location.pathname.replace('/wp-admin/admin.php', '') + '/wp-admin/admin-post.php" style="display: none;">');
        exportForm.append('<input type="hidden" name="action" value="mhm_rentiva_export">');
        exportForm.append('<input type="hidden" name="_wpnonce" value="' + $('input[name="_wpnonce"]').val() + '">');
        exportForm.append('<input type="hidden" name="post_type" value="vehicle_booking">');
        exportForm.append('<input type="hidden" name="format" value="csv">');
        exportForm.append('<input type="hidden" name="filters" value="' + encodeURIComponent(formData) + '">');

        $('body').append(exportForm);
        exportForm.submit();

        // Close modal after download starts
        setTimeout(function () {
            closeExportDetailsModal();
        }, 1000);
    }


    /**
     * Delete export file
     */
    function deleteExportFile(exportId) {
        if (confirm('Are you sure you want to delete this export?')) {
            showNotice('Deleting export...', 'info');

            // Send AJAX request to delete export
            $.ajax({
                url: typeof ajaxurl !== 'undefined' ? ajaxurl : window.location.origin + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_delete_export',
                    nonce: $('#mhm_rentiva_export_filters_nonce').val(),
                    export_id: exportId
                },
                success: function (response) {
                    if (response.success) {
                        showNotice('Export deleted successfully!', 'success');
                        // Remove the export row from the table
                        $('button[onclick*="' + exportId + '"]').closest('tr').fadeOut(500, function () {
                            $(this).remove();
                        });
                        closeExportDetailsModal();
                    } else {
                        showNotice('Error deleting export: ' + response.data, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('AJAX error:', xhr, status, error);
                    }
                    showNotice('Error deleting export. Please try again.', 'error');
                }
            });
        }
    }

    /**
     * Close export details modal
     */
    function closeExportDetailsModal() {
        $('#export-details-modal').remove();
    }

    /**
     * View export details
     */
    window.viewExportDetails = function (exportId) {

        // Show loading
        showNotice('Loading export details...', 'info');

        // Get export details via AJAX
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : window.location.origin + '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'mhm_rentiva_get_export_details',
                nonce: $('#mhm_rentiva_export_filters_nonce').val(),
                export_id: exportId
            },
            success: function (response) {
                if (response.success) {
                    var exportData = response.data.export;
                    showExportDetailsModal(exportData);
                } else {
                    showNotice('Error loading export details: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                showNotice('Error loading export details. Please try again.', 'error');
            }
        });
    };

    function showExportDetailsModal(exportData) {
        // Create modal HTML with real data
        var modalHtml = '<div id="export-details-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">';
        modalHtml += '<div style="background: #fff; border-radius: 8px; padding: 30px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">';
        modalHtml += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e1e5e9; padding-bottom: 15px;">';
        modalHtml += '<h2 style="margin: 0; color: #1d2327;">📊 Export Details</h2>';
        modalHtml += '<button onclick="closeExportDetailsModal()" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 8px 12px; cursor: pointer; font-size: 14px;">✕ Close</button>';
        modalHtml += '</div>';

        // Export details content with real data
        modalHtml += '<div class="export-details-content">';
        modalHtml += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">';
        modalHtml += '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #0073aa;">';
        modalHtml += '<div style="font-weight: bold; color: #0073aa; margin-bottom: 5px;">Export ID</div>';
        modalHtml += '<div style="color: #646970;">' + exportData.date + '</div>';
        modalHtml += '</div>';
        modalHtml += '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #00a32a;">';
        modalHtml += '<div style="font-weight: bold; color: #00a32a; margin-bottom: 5px;">Status</div>';
        modalHtml += '<div style="color: #646970;">✅ ' + exportData.status + '</div>';
        modalHtml += '</div>';
        modalHtml += '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #ff6900;">';
        modalHtml += '<div style="font-weight: bold; color: #ff6900; margin-bottom: 5px;">Records</div>';
        modalHtml += '<div style="color: #646970;">' + exportData.records + ' records exported</div>';
        modalHtml += '</div>';
        modalHtml += '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #8c8f94;">';
        modalHtml += '<div style="font-weight: bold; color: #8c8f94; margin-bottom: 5px;">Format</div>';
        modalHtml += '<div style="color: #646970;">' + exportData.format + '</div>';
        modalHtml += '</div>';
        modalHtml += '</div>';

        // Export summary with real data
        modalHtml += '<div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 6px; padding: 20px; margin-bottom: 20px;">';
        modalHtml += '<h3 style="margin: 0 0 15px 0; color: #1d2327;">Export Summary</h3>';
        modalHtml += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">';
        modalHtml += '<div><strong>Export Type:</strong> ' + exportData.type + '</div>';
        modalHtml += '<div><strong>Format:</strong> ' + exportData.format + '</div>';
        modalHtml += '<div><strong>Date:</strong> ' + exportData.date + '</div>';
        modalHtml += '<div><strong>Filters Applied:</strong> ' + (exportData.filters_applied ? 'Yes' : 'No') + '</div>';
        modalHtml += '<div><strong>Records:</strong> ' + exportData.records + '</div>';
        modalHtml += '<div><strong>User ID:</strong> ' + exportData.user_id + '</div>';
        modalHtml += '</div>';
        modalHtml += '</div>';

        // Actions
        modalHtml += '<div style="display: flex; gap: 10px; justify-content: center;">';
        modalHtml += '<button onclick="downloadExportFile(\'' + exportData.date + '\')" style="background: #0073aa; color: white; border: none; border-radius: 4px; padding: 10px 20px; cursor: pointer; font-size: 14px;">📥 Download Again</button>';
        modalHtml += '<button onclick="deleteExportFile(\'' + exportData.date + '\')" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 10px 20px; cursor: pointer; font-size: 14px;">🗑️ Delete</button>';
        modalHtml += '</div>';

        modalHtml += '</div>';
        modalHtml += '</div>';
        modalHtml += '</div>';

        // Add modal to page
        $('body').append(modalHtml);

        // Close modal when clicking outside
        $('#export-details-modal').on('click', function (e) {
            if (e.target === this) {
                closeExportDetailsModal();
            }
        });
    }

    /**
     * Clear filtered results
     */
    function clearFilteredResults() {
        $('.mhm-filtered-results').remove();
        showNotice('Filtered results cleared!', 'info');
    }

    /**
     * Export filtered data
     */
    function exportFilteredData() {
        var form = $('#export-filters-form');
        var formData = form.serialize();

        // Create export form - use admin-post.php for export action
        var exportForm = $('<form method="post" action="' + window.location.origin + window.location.pathname.replace('/wp-admin/admin.php', '') + '/wp-admin/admin-post.php" style="display: none;">');
        exportForm.append('<input type="hidden" name="action" value="mhm_rentiva_export">');
        exportForm.append('<input type="hidden" name="_wpnonce" value="' + $('input[name="_wpnonce"]').val() + '">');
        exportForm.append('<input type="hidden" name="post_type" value="vehicle_booking">');
        exportForm.append('<input type="hidden" name="format" value="csv">');
        exportForm.append('<input type="hidden" name="filters" value="' + encodeURIComponent(formData) + '">');

        $('body').append(exportForm);
        exportForm.submit();
    }

    // Make functions globally accessible
    window.closeExportDetailsModal = closeExportDetailsModal;
    window.downloadExportFile = downloadExportFile;
    window.deleteExportFile = deleteExportFile;

    // Global functions for onclick handlers
    window.clearFilteredResults = clearFilteredResults;
    window.exportFilteredData = exportFilteredData;

    /**
     * Export with filters
     */
    window.exportWithFilters = function (postType, format) {
        var form = $('#export-filters-form');
        var formData = form.serialize();

        // Create export form
        var exportForm = $('<form method="post" action="' + ajaxurl + '" style="display: none;">');
        exportForm.append('<input type="hidden" name="action" value="mhm_rentiva_export">');
        exportForm.append('<input type="hidden" name="_wpnonce" value="' + $('#mhm_rentiva_export_filters_nonce').val() + '">');
        exportForm.append('<input type="hidden" name="post_type" value="' + postType + '">');
        exportForm.append('<input type="hidden" name="format" value="' + format + '">');
        exportForm.append('<input type="hidden" name="filters" value="' + encodeURIComponent(formData) + '">');

        $('body').append(exportForm);
        exportForm.submit();
    };

})(jQuery);
