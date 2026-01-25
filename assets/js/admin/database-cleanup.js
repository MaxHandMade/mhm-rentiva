/**
 * MHM Rentiva Database Cleanup JavaScript
 *
 * @package MHM_Rentiva
 * @since 1.0.0
 */

jQuery(document).ready(function ($) {
    'use strict';

    // Analyze Database
    $('#mhm-analyze-db-btn').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.analyzing_text);

        $.post(ajaxurl, {
            action: 'mhm_analyze_database',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                $('#mhm-cleanup-results').html(response.data.html);
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> ' + mhm_db_cleanup_vars.analyze_text);
        });
    });

    // Clean Orphaned Meta
    $('#mhm-cleanup-orphaned-btn').on('click', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_orphaned_text)) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.cleaning_text);

        $.post(ajaxurl, {
            action: 'mhm_cleanup_orphaned',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-analyze-db-btn').trigger('click'); // Re-analyze
                $('#mhm-refresh-backups-btn').trigger('click'); // Refresh backup list
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + mhm_db_cleanup_vars.clean_orphaned_text);
        });
    });

    // Clean Expired Transients
    $('#mhm-cleanup-transients-btn').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.cleaning_text);

        $.post(ajaxurl, {
            action: 'mhm_cleanup_transients',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-analyze-db-btn').trigger('click'); // Re-analyze
                $('#mhm-refresh-backups-btn').trigger('click'); // Refresh backup list
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + mhm_db_cleanup_vars.clean_transients_text);
        });
    });

    // Optimize Autoload
    $('#mhm-optimize-autoload-btn').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.optimizing_text);

        $.post(ajaxurl, {
            action: 'mhm_optimize_autoload',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-analyze-db-btn').trigger('click'); // Re-analyze
                $('#mhm-refresh-backups-btn').trigger('click'); // Refresh backup list
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> ' + mhm_db_cleanup_vars.optimize_autoload_text);
        });
    });

    // Optimize Tables
    $('#mhm-optimize-tables-btn').on('click', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_tables_text)) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.optimizing_text);

        $.post(ajaxurl, {
            action: 'mhm_optimize_tables',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-refresh-backups-btn').trigger('click'); // Refresh backup list
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-database"></span> ' + mhm_db_cleanup_vars.optimize_tables_text);
        });
    });

    // Purge Old Logs
    $('#mhm-cleanup-logs-btn').on('click', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_old_logs_text || 'This will delete logs and queue records older than 30 days. Continue?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.cleaning_text);

        $.post(ajaxurl, {
            action: 'mhm_cleanup_logs',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-analyze-db-btn').trigger('click'); // Re-analyze
                $('#mhm-refresh-backups-btn').trigger('click'); // Refresh backup list
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-calendar-alt"></span> ' + (mhm_db_cleanup_vars.purge_logs_text || 'Purge Old Logs'));
        });
    });

    // Clean Invalid Meta Keys
    // Use event delegation because button is dynamically added via AJAX
    $(document).on('click', '#mhm-cleanup-invalid-meta-btn', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_invalid_meta_text || 'This will delete invalid meta keys. A backup will be created. Continue?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.cleaning_text);

        $.post(ajaxurl, {
            action: 'mhm_cleanup_invalid_meta',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-analyze-db-btn').trigger('click'); // Re-analyze
                $('#mhm-refresh-backups-btn').trigger('click'); // Refresh backup list
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + (mhm_db_cleanup_vars.clean_invalid_meta_text || 'Clean'));
        });
    });

    // Refresh Backup List
    $('#mhm-refresh-backups-btn').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.loading_text || 'Loading...');

        $.post(ajaxurl, {
            action: 'mhm_list_backups',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                renderBackupList(response.data.backups || []);
            } else {
                $('#mhm-backup-list').html('<div class="notice notice-error"><p>' + (response.data || mhm_db_cleanup_vars.error_text) + '</p></div>');
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + (mhm_db_cleanup_vars.refresh_text || 'Refresh Backup List'));
        });
    });

    // Download Backup
    $(document).on('click', '.mhm-download-backup-btn', function () {
        const tableName = $(this).data('table');
        const form = $('<form>', {
            method: 'POST',
            action: ajaxurl
        });
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'mhm_download_backup'
        }));
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: mhm_db_cleanup_vars.nonce
        }));
        form.append($('<input>', {
            type: 'hidden',
            name: 'table_name',
            value: tableName
        }));
        $('body').append(form);
        form.submit();
        form.remove();
    });

    // Restore Backup
    $(document).on('click', '.mhm-restore-backup-btn', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_restore_text || 'This will restore the backup data. Continue?')) {
            return;
        }

        const btn = $(this);
        const tableName = btn.data('table');
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.restoring_text || 'Restoring...');

        $.post(ajaxurl, {
            action: 'mhm_restore_backup',
            nonce: mhm_db_cleanup_vars.nonce,
            table_name: tableName
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-refresh-backups-btn').trigger('click');
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + (mhm_db_cleanup_vars.restore_text || 'Restore'));
        });
    });

    // Delete Backup
    $(document).on('click', '.mhm-delete-backup-btn', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_delete_backup_text || 'This will permanently delete the backup. This action cannot be undone. Continue?')) {
            return;
        }

        const btn = $(this);
        const tableName = btn.data('table');
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.deleting_text || 'Deleting...');

        $.post(ajaxurl, {
            action: 'mhm_delete_backup',
            nonce: mhm_db_cleanup_vars.nonce,
            table_name: tableName
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-refresh-backups-btn').trigger('click');
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + (mhm_db_cleanup_vars.delete_text || 'Delete'));
        });
    });

    // Render backup list
    function renderBackupList(backups) {
        if (backups.length === 0) {
            $('#mhm-backup-list').html('<div class="notice notice-info"><p>' + (mhm_db_cleanup_vars.no_backups_text || 'No backups found.') + '</p></div>');
            return;
        }

        let html = '<table class="widefat striped">';
        html += '<thead><tr>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_table_text || 'Table Name') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_type_text || 'Type') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_date_text || 'Date') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_rows_text || 'Rows') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_size_text || 'Size') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.actions_text || 'Actions') + '</th>';
        html += '</tr></thead><tbody>';

        backups.forEach(function (backup) {
            const typeLabels = {
                'invalid_meta': mhm_db_cleanup_vars.type_invalid_meta_text || 'Invalid Meta',
                'orphaned_meta': mhm_db_cleanup_vars.type_orphaned_meta_text || 'Orphaned Meta',
                'custom': mhm_db_cleanup_vars.type_custom_text || 'Custom'
            };

            const dateFormatted = backup.date !== 'unknown'
                ? backup.date.replace(/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/, '$1-$2-$3 $4:$5:$6')
                : backup.date;

            html += '<tr>';
            html += '<td><code>' + backup.table_name + '</code></td>';
            html += '<td>' + (typeLabels[backup.type] || backup.type) + '</td>';
            html += '<td>' + dateFormatted + '</td>';
            html += '<td>' + backup.rows + '</td>';
            html += '<td>' + backup.size_mb.toFixed(2) + ' MB</td>';
            html += '<td>';
            html += '<button type="button" class="button button-small mhm-download-backup-btn" data-table="' + backup.table_name + '" style="margin-right: 5px;">';
            html += '<span class="dashicons dashicons-download"></span> ' + (mhm_db_cleanup_vars.download_text || 'Download');
            html += '</button>';
            html += '<button type="button" class="button button-small mhm-restore-backup-btn" data-table="' + backup.table_name + '" style="margin-right: 5px;">';
            html += '<span class="dashicons dashicons-undo"></span> ' + (mhm_db_cleanup_vars.restore_text || 'Restore');
            html += '</button>';
            html += '<button type="button" class="button button-small mhm-delete-backup-btn" data-table="' + backup.table_name + '">';
            html += '<span class="dashicons dashicons-trash"></span> ' + (mhm_db_cleanup_vars.delete_text || 'Delete');
            html += '</button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#mhm-backup-list').html(html);
    }

    // Load backups on page load
    $('#mhm-refresh-backups-btn').trigger('click');

    // Create Full Backup
    $('#mhm-create-full-backup-btn').on('click', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_create_full_backup_text || 'This will create a full backup of all plugin-related tables. This may take a few minutes. Continue?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> ' + (mhm_db_cleanup_vars.creating_backup_text || 'Creating Backup...'));

        $.post(ajaxurl, {
            action: 'mhm_create_full_backup',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-refresh-full-backups-btn').trigger('click');
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> ' + (mhm_db_cleanup_vars.create_full_backup_text || 'Create Full Backup'));
        });
    });

    // Refresh Full Backup List
    $('#mhm-refresh-full-backups-btn').on('click', function () {
        const btn = $(this);
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.loading_text || 'Loading...');

        $.post(ajaxurl, {
            action: 'mhm_list_full_backups',
            nonce: mhm_db_cleanup_vars.nonce
        }, function (response) {
            if (response.success) {
                renderFullBackupList(response.data.backups || []);
            } else {
                $('#mhm-full-backup-list').html('<div class="notice notice-error"><p>' + (response.data || mhm_db_cleanup_vars.error_text) + '</p></div>');
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ' + (mhm_db_cleanup_vars.refresh_text || 'Refresh List'));
        });
    });

    // Download Full Backup
    $(document).on('click', '.mhm-download-full-backup-btn', function () {
        const filePath = $(this).data('file');
        const form = $('<form>', {
            method: 'POST',
            action: ajaxurl
        });
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'mhm_download_full_backup'
        }));
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: mhm_db_cleanup_vars.nonce
        }));
        form.append($('<input>', {
            type: 'hidden',
            name: 'file_path',
            value: filePath
        }));
        $('body').append(form);
        form.submit();
        form.remove();
    });

    // Restore Full Backup
    $(document).on('click', '.mhm-restore-full-backup-btn', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_restore_full_backup_text || 'WARNING: This will restore the backup and may overwrite existing data. This operation is irreversible. Continue?')) {
            return;
        }

        const btn = $(this);
        const filePath = btn.data('file');
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.restoring_text || 'Restoring...');

        $.post(ajaxurl, {
            action: 'mhm_restore_full_backup',
            nonce: mhm_db_cleanup_vars.nonce,
            file_path: filePath
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-refresh-full-backups-btn').trigger('click');
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-undo"></span> ' + (mhm_db_cleanup_vars.restore_text || 'Restore'));
        });
    });

    // Delete Full Backup
    $(document).on('click', '.mhm-delete-full-backup-btn', function () {
        if (!confirm(mhm_db_cleanup_vars.confirm_delete_backup_text || 'This will permanently delete the backup. This action cannot be undone. Continue?')) {
            return;
        }

        const btn = $(this);
        const backupName = btn.data('backup');
        btn.prop('disabled', true).text(mhm_db_cleanup_vars.deleting_text || 'Deleting...');

        $.post(ajaxurl, {
            action: 'mhm_delete_full_backup',
            nonce: mhm_db_cleanup_vars.nonce,
            backup_name: backupName
        }, function (response) {
            if (response.success) {
                alert(mhm_db_cleanup_vars.success_text + ' ' + response.data.message);
                $('#mhm-refresh-full-backups-btn').trigger('click');
            } else {
                alert(mhm_db_cleanup_vars.error_text + ' ' + response.data);
            }
            btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + (mhm_db_cleanup_vars.delete_text || 'Delete'));
        });
    });

    // Render full backup list
    function renderFullBackupList(backups) {
        if (backups.length === 0) {
            $('#mhm-full-backup-list').html('<div class="notice notice-info"><p>' + (mhm_db_cleanup_vars.no_backups_text || 'No full backups found. Create one to get started.') + '</p></div>');
            return;
        }

        let html = '<table class="widefat striped">';
        html += '<thead><tr>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_name_text || 'Backup Name') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_date_text || 'Date') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_tables_text || 'Tables') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_rows_text || 'Rows') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_size_text || 'Size') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.backup_status_text || 'Status') + '</th>';
        html += '<th>' + (mhm_db_cleanup_vars.actions_text || 'Actions') + '</th>';
        html += '</tr></thead><tbody>';

        backups.forEach(function (backup) {
            const dateFormatted = backup.created_at || backup.date || 'Unknown';

            html += '<tr>';
            html += '<td><code>' + backup.backup_name + '</code></td>';
            html += '<td>' + dateFormatted + '</td>';
            html += '<td>' + (backup.tables_count || '-') + '</td>';
            html += '<td>' + (backup.rows_count || '-') + '</td>';
            html += '<td>' + backup.file_size_mb.toFixed(2) + ' MB</td>';
            html += '<td>';
            if (backup.file_exists) {
                html += '<span class="dashicons dashicons-yes-alt" style="color: green;" title="File exists"></span> ' + (mhm_db_cleanup_vars.file_exists_text || 'Available');
            } else {
                html += '<span class="dashicons dashicons-warning" style="color: orange;" title="File not found"></span> ' + (mhm_db_cleanup_vars.file_missing_text || 'Missing');
            }
            html += '</td>';
            html += '<td>';
            if (backup.file_exists) {
                html += '<button type="button" class="button button-small mhm-download-full-backup-btn" data-file="' + backup.file_path + '" style="margin-right: 5px;">';
                html += '<span class="dashicons dashicons-download"></span> ' + (mhm_db_cleanup_vars.download_text || 'Download');
                html += '</button>';
                html += '<button type="button" class="button button-small mhm-restore-full-backup-btn" data-file="' + backup.file_path + '" style="margin-right: 5px;">';
                html += '<span class="dashicons dashicons-undo"></span> ' + (mhm_db_cleanup_vars.restore_text || 'Restore');
                html += '</button>';
            }
            html += '<button type="button" class="button button-small mhm-delete-full-backup-btn" data-backup="' + backup.backup_name + '">';
            html += '<span class="dashicons dashicons-trash"></span> ' + (mhm_db_cleanup_vars.delete_text || 'Delete');
            html += '</button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#mhm-full-backup-list').html(html);
    }

    // Load full backups on page load
    $('#mhm-refresh-full-backups-btn').trigger('click');
});
