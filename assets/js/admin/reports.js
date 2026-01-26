/**
 * Reports Page JavaScript
 * Handles date filtering and form interactions
 */
(function ($) {
	'use strict';

	var ReportsPage = {
		init: function () {
			this.bindEvents();
			this.initializeDatePickers();
		},

		bindEvents: function () {
			// Form submit event
			$( '#reports-filter-form' ).on( 'submit', this.handleFormSubmit );

			// Reset button
			$( '#reset-filter' ).on( 'click', this.handleResetFilter );

			// Date input changes
			$( '#start_date, #end_date' ).on( 'change', this.handleDateChange );

			// Tab changes - preserve date parameters
			$( '.nav-tab' ).on( 'click', this.handleTabChange );
		},

		initializeDatePickers: function () {
			// Set default date range if not set
			var startDate = $( '#start_date' ).val();
			var endDate   = $( '#end_date' ).val();

			if ( ! startDate || ! endDate) {
				var today         = new Date();
				var thirtyDaysAgo = new Date( today.getTime() - (30 * 24 * 60 * 60 * 1000) );

				$( '#start_date' ).val( this.formatDate( thirtyDaysAgo ) );
				$( '#end_date' ).val( this.formatDate( today ) );
			}
		},

		handleFormSubmit: function (e) {
			e.preventDefault();

			var startDate = $( '#start_date' ).val();
			var endDate   = $( '#end_date' ).val();

			// Validate dates
			if ( ! startDate || ! endDate) {
				showNotice( 'Please select both start and end dates.', 'warning' );
				return;
			}

			if (new Date( startDate ) > new Date( endDate )) {
				showNotice( 'Start date cannot be later than end date.', 'error' );
				return;
			}

			// Clear cache before loading new data
			ReportsPage.clearCache();

			// Submit form
			this.submit();
		},

		handleResetFilter: function (e) {
			e.preventDefault();

			var today         = new Date();
			var thirtyDaysAgo = new Date( today.getTime() - (30 * 24 * 60 * 60 * 1000) );

			$( '#start_date' ).val( ReportsPage.formatDate( thirtyDaysAgo ) );
			$( '#end_date' ).val( ReportsPage.formatDate( today ) );

			// Clear cache and reload
			ReportsPage.clearCache();
			$( '#reports-filter-form' ).submit();
		},

		handleDateChange: function () {
			var startDate = $( '#start_date' ).val();
			var endDate   = $( '#end_date' ).val();

			// Auto-submit if both dates are set
			if (startDate && endDate) {
				if (new Date( startDate ) <= new Date( endDate )) {
					// Cache temizleme sadece tarih değiştiğinde
					ReportsPage.clearCache();
					// Form gönderimi
					setTimeout(
						function () {
							$( '#reports-filter-form' ).submit();
						},
						100
					);
				}
			}
		},

		handleTabChange: function (e) {
			var href      = $( this ).attr( 'href' );
			var startDate = $( '#start_date' ).val();
			var endDate   = $( '#end_date' ).val();

			// Add date parameters to tab URL
			if (startDate && endDate) {
				var url = new URL( href, window.location.origin );
				url.searchParams.set( 'start_date', startDate );
				url.searchParams.set( 'end_date', endDate );
				$( this ).attr( 'href', url.toString() );
			}
		},

		clearCache: function () {
			$.ajax(
				{
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'mhm_rentiva_clear_reports_cache',
						nonce: window.mhm_reports_nonce || ''
					},
					success: function (response) {
					},
					error: function () {
					}
				}
			);
		},

		formatDate: function (date) {
			var year  = date.getFullYear();
			var month = String( date.getMonth() + 1 ).padStart( 2, '0' );
			var day   = String( date.getDate() ).padStart( 2, '0' );
			return year + '-' + month + '-' + day;
		}
	};

	/**
	 * Show notice message
	 */
	function showNotice(message, type) {
		type            = type || 'info';
		var noticeClass = 'notice-' + type;
		var notice      = $( '<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + message + '</strong></p></div>' );

		// Remove any existing notices first
		$( '.notice' ).remove();

		// Add to body for better visibility
		$( 'body' ).append( notice );

		// Auto-dismiss after 5 seconds
		setTimeout(
			function () {
				notice.fadeOut(
					500,
					function () {
						notice.remove();
					}
				);
			},
			5000
		);
	}

	// Initialize when document is ready
	$( document ).ready(
		function () {
			ReportsPage.init();
		}
	);

})( jQuery );
