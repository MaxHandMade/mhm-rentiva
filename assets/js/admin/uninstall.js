/**
 * MHM Rentiva Uninstall Plugin JavaScript
 *
 * @package MHM_Rentiva
 * @since 1.0.0
 */

jQuery( document ).ready(
	function ($) {
		'use strict';

		// Load stats on page load
		refreshStats();

		// Refresh Statistics
		$( '#mhm-refresh-stats-btn' ).on(
			'click',
			function () {
				refreshStats();
			}
		);

		// Uninstall Plugin
		$( '#mhm-uninstall-btn' ).on(
			'click',
			function () {
				if ( ! confirm( mhm_uninstall_vars.confirm_uninstall_text || 'WARNING: This will permanently delete ALL plugin data from the database. This action is IRREVERSIBLE. Are you absolutely sure you want to proceed?' )) {
					return;
				}

				const deleteBackups = confirm( mhm_uninstall_vars.confirm_delete_backups_text || 'Do you also want to delete all backup files?' );

				const btn          = $( this );
				const originalText = btn.html();
				btn.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update"></span> ' + (mhm_uninstall_vars.uninstalling_text || 'Uninstalling...') );

				$.post(
					ajaxurl,
					{
						action: 'mhm_uninstall_plugin',
						nonce: mhm_uninstall_vars.nonce,
						delete_backups: deleteBackups ? '1' : '0'
					},
					function (response) {
						if (response.success) {
							renderUninstallResults( response.data.results || {} );
							$( '#mhm-uninstall-stats' ).html( '<div class="notice notice-success"><p><strong>' + mhm_uninstall_vars.success_text + '</strong> ' + (response.data.message || 'Uninstall completed successfully') + '</p></div>' );
						} else {
							$( '#mhm-uninstall-results' ).html( '<div class="notice notice-error"><p><strong>' + mhm_uninstall_vars.error_text + '</strong> ' + (response.data || 'Uninstall failed') + '</p></div>' );
						}
						btn.prop( 'disabled', false ).html( originalText );
					}
				).fail(
					function () {
						$( '#mhm-uninstall-results' ).html( '<div class="notice notice-error"><p><strong>' + mhm_uninstall_vars.error_text + '</strong> Network error occurred</p></div>' );
						btn.prop( 'disabled', false ).html( originalText );
					}
				);
			}
		);

		/**
		 * Refresh uninstall statistics
		 */
		function refreshStats() {
			const btn          = $( '#mhm-refresh-stats-btn' );
			const originalText = btn.html();
			btn.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update"></span> ' + (mhm_uninstall_vars.analyzing_text || 'Analyzing...') );

			$.post(
				ajaxurl,
				{
					action: 'mhm_get_uninstall_stats',
					nonce: mhm_uninstall_vars.nonce
				},
				function (response) {
					if (response.success) {
						renderStats( response.data || {} );
					} else {
						$( '#mhm-uninstall-stats' ).html( '<div class="notice notice-error"><p>' + (response.data || mhm_uninstall_vars.error_text) + '</p></div>' );
					}
					btn.prop( 'disabled', false ).html( originalText );
				}
			).fail(
				function () {
					$( '#mhm-uninstall-stats' ).html( '<div class="notice notice-error"><p>' + mhm_uninstall_vars.error_text + ' Network error occurred</p></div>' );
					btn.prop( 'disabled', false ).html( originalText );
				}
			);
		}

		/**
		 * Render uninstall statistics
		 */
		function renderStats(stats) {
			let html = '<div class="mhm-uninstall-stats-container">';
			html    += '<h3>What will be deleted:</h3>';
			html    += '<table class="wp-list-table widefat fixed striped">';
			html    += '<thead>';
			html    += '<tr>';
			html    += '<th>Item</th>';
			html    += '<th>Count</th>';
			html    += '</tr>';
			html    += '</thead>';
			html    += '<tbody>';

			// Options
			html += '<tr>';
			html += '<td><strong>Options</strong></td>';
			html += '<td>' + (stats.options || 0) + '</td>';
			html += '</tr>';

			// Post Types
			if (stats.post_types) {
				html += '<tr>';
				html += '<td><strong>Vehicles</strong></td>';
				html += '<td>' + (stats.post_types.vehicles || 0) + '</td>';
				html += '</tr>';

				html += '<tr>';
				html += '<td><strong>Bookings</strong></td>';
				html += '<td>' + (stats.post_types.bookings || 0) + '</td>';
				html += '</tr>';
			}

			// Postmeta
			html += '<tr>';
			html += '<td><strong>Post Meta</strong></td>';
			html += '<td>' + (stats.postmeta || 0) + '</td>';
			html += '</tr>';

			// Custom Tables
			if (stats.custom_tables && Object.keys( stats.custom_tables ).length > 0) {
				html        += '<tr>';
				html        += '<td><strong>Custom Tables</strong></td>';
				html        += '<td>';
				const tables = [];
				for (const table in stats.custom_tables) {
					if (stats.custom_tables[table] > 0) {
						tables.push( table + ' (' + stats.custom_tables[table] + ' rows)' );
					}
				}
				html += tables.length > 0 ? tables.join( '<br>' ) : '0';
				html += '</td>';
				html += '</tr>';
			}

			// Cron Jobs
			html += '<tr>';
			html += '<td><strong>Cron Jobs</strong></td>';
			html += '<td>' + (stats.cron_jobs || 0) + '</td>';
			html += '</tr>';

			// Transients
			html += '<tr>';
			html += '<td><strong>Transients</strong></td>';
			html += '<td>' + (stats.transients || 0) + '</td>';
			html += '</tr>';

			// Backup Files
			html += '<tr>';
			html += '<td><strong>Backup Files</strong></td>';
			html += '<td>' + (stats.backup_files || 0) + '</td>';
			html += '</tr>';

			html += '</tbody>';
			html += '</table>';
			html += '</div>';

			$( '#mhm-uninstall-stats' ).html( html );
		}

		/**
		 * Render uninstall results
		 */
		function renderUninstallResults(results) {
			let html = '<div class="mhm-uninstall-results-container">';
			html    += '<h3>Uninstall Results:</h3>';
			html    += '<table class="wp-list-table widefat fixed striped">';
			html    += '<thead>';
			html    += '<tr>';
			html    += '<th>Item</th>';
			html    += '<th>Deleted</th>';
			html    += '</tr>';
			html    += '</thead>';
			html    += '<tbody>';

			html += '<tr>';
			html += '<td><strong>Options</strong></td>';
			html += '<td>' + (results.options_deleted || 0) + '</td>';
			html += '</tr>';

			html += '<tr>';
			html += '<td><strong>Posts (Vehicles & Bookings)</strong></td>';
			html += '<td>' + (results.posts_deleted || 0) + '</td>';
			html += '</tr>';

			html += '<tr>';
			html += '<td><strong>Post Meta</strong></td>';
			html += '<td>' + (results.postmeta_deleted || 0) + '</td>';
			html += '</tr>';

			html += '<tr>';
			html += '<td><strong>Custom Tables</strong></td>';
			html += '<td>' + (results.tables_dropped || 0) + '</td>';
			html += '</tr>';

			html += '<tr>';
			html += '<td><strong>Cron Jobs</strong></td>';
			html += '<td>' + (results.cron_jobs_cleared || 0) + '</td>';
			html += '</tr>';

			html += '<tr>';
			html += '<td><strong>Transients</strong></td>';
			html += '<td>' + (results.transients_deleted || 0) + '</td>';
			html += '</tr>';

			if (results.backup_files_deleted > 0) {
				html += '<tr>';
				html += '<td><strong>Backup Files</strong></td>';
				html += '<td>' + (results.backup_files_deleted || 0) + '</td>';
				html += '</tr>';
			}

			html += '</tbody>';
			html += '</table>';
			html += '</div>';

			$( '#mhm-uninstall-results' ).html( html );
		}
	}
);
