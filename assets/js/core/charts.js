/**
 * MHM Rentiva Admin Reports JavaScript
 * Chart.js integration and AJAX functionality
 */

(function ($) {
	'use strict';

	// Global charts storage to prevent memory leaks - Safe initialization
	if ( ! window.MHMRentivaCharts) {
		window.MHMRentivaCharts = {};
	}
	if ( ! window.MHMRentivaCharts.instances) {
		window.MHMRentivaCharts.instances = {};
	}

	// Add lowercase alias (for console error)
	if ( ! window.mhmRentivaCharts) {
		window.mhmRentivaCharts = window.MHMRentivaCharts;
	}

	const MHMRentivaCharts = {

		init: function () {
			// Check if Chart.js is loaded
			if (typeof Chart === 'undefined') {
				return;
			}

			this.bindEvents();
			this.initCharts();
		},

		bindEvents: function () {
			// Filter form submission
			$( document ).on( 'submit', '.mhm-rentiva-reports-filters form', this.handleFilterSubmit.bind( this ) );

			// Date range changes
			$( document ).on( 'change', '.mhm-rentiva-reports-filters input[type="date"]', this.debounce( this.handleDateChange.bind( this ), 500 ) );

			// Export button clicks
			$( document ).on( 'click', '.export-actions .button', this.handleExportClick.bind( this ) );

			// Tab changes
			$( document ).on( 'click', '.nav-tab', this.handleTabChange.bind( this ) );
		},

		initCharts: function () {
			// Load charts based on page type
			if (this.isDashboardPage()) {
				this.loadDashboardCharts();
			} else if (this.isReportsPage()) {
				// Charts.php manages its own charts on reports page
				// Don't run Charts.js
				return;
			} else {
				this.loadAllCharts();
			}
		},

		isDashboardPage: function () {
			// Are we on dashboard page?
			return window.location.href.indexOf( 'mhm-rentiva-dashboard' ) !== -1;
		},

		isReportsPage: function () {
			// Are we on reports page?
			return window.location.href.indexOf( 'mhm-rentiva-reports' ) !== -1;
		},

		loadDashboardCharts: function () {
			// Load only revenue chart for dashboard
			const startDate = this.getStartDate();
			const endDate   = this.getEndDate();
			this.loadRevenueChart( startDate, endDate );
		},

		handleFilterSubmit: function (e) {
			e.preventDefault();
			this.loadAllCharts();
		},

		handleDateChange: function () {
			// Debounced date change handler
			this.showLoadingState();
			this.loadAllCharts();
		},

		handleExportClick: function (e) {
			e.preventDefault();

			const $button = $( e.currentTarget );
			const format  = $button.data( 'format' );
			const type    = $button.data( 'type' );

			if ( ! format || ! type) {
				const errorMsg = (window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
					? window.MHMRentiva.i18n.__( 'Export format or type not specified' )
					: 'Export format or type not specified';
				this.showError( errorMsg );
				return;
			}

			this.performExport( type, format, $button );
		},

		handleTabChange: function (e) {
			// Update URL with tab parameter
			const tab = $( e.currentTarget ).data( 'tab' );
			const url = new URL( window.location );

			url.searchParams.set( 'tab', tab );
			window.history.pushState( {}, '', url );
		},

		loadAllCharts: function () {
			const startDate = this.getStartDate();
			const endDate   = this.getEndDate();

			// Load different chart types based on current tab
			const currentTab = this.getCurrentTab();

			switch (currentTab) {
				case 'overview':
					this.loadRevenueChart( startDate, endDate );
					this.loadBookingStatusChart( startDate, endDate );
					break;
				case 'revenue':
					this.loadRevenueChart( startDate, endDate );
					break;
				case 'bookings':
					this.loadBookingStatusChart( startDate, endDate );
					break;
				case 'vehicles':
					this.loadVehicleChart( startDate, endDate );
					break;
				case 'customers':
					this.loadCustomerChart( startDate, endDate );
					break;
			}
		},

		loadRevenueChart: function (startDate, endDate) {
			this.ajaxRequest( 'revenue', { start_date: startDate, end_date: endDate } )
				.done(
					function (response) {
						if (response.success && response.data.daily) {
							MHMRentivaCharts.renderRevenueChart( response.data );
						}
					}
				);
		},

		loadBookingStatusChart: function (startDate, endDate) {
			this.ajaxRequest( 'bookings', { start_date: startDate, end_date: endDate } )
				.done(
					function (response) {
						if (response.success && response.data.status_distribution) {
							MHMRentivaCharts.renderBookingStatusChart( response.data );
						}
					}
				);
		},

		loadVehicleChart: function (startDate, endDate) {
			this.ajaxRequest( 'vehicles', { start_date: startDate, end_date: endDate } )
				.done(
					function (response) {
						if (response.success && response.data.top_vehicles) {
							MHMRentivaCharts.renderVehicleChart( response.data );
						}
					}
				);
		},

		loadCustomerChart: function (startDate, endDate) {
			this.ajaxRequest( 'customers', { start_date: startDate, end_date: endDate } )
				.done(
					function (response) {
						if (response.success && response.data.segments) {
							MHMRentivaCharts.renderCustomerChart( response.data );
						}
					}
				);
		},

		ajaxRequest: function (type, data) {
			const ajaxData = {
				action: 'mhm_rentiva_reports_data',
				type: type,
				nonce: mhmRentivaCharts.nonce
			};

			return $.ajax(
				{
					url: mhmRentivaCharts.ajax_url,
					type: 'POST',
					data: $.extend( ajaxData, data ),
					beforeSend: function () {
						MHMRentivaCharts.showLoadingState();
					}
				}
			)
				.always(
					function () {
						MHMRentivaCharts.hideLoadingState();
					}
				)
				.fail(
					function () {
						const errorMsg = (mhmRentivaCharts.strings && mhmRentivaCharts.strings.error)
						? mhmRentivaCharts.strings.error
						: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
							? window.MHMRentiva.i18n.__( 'An error occurred' )
							: 'An error occurred');
						MHMRentivaCharts.showError( errorMsg );
					}
				);
		},

		renderRevenueChart: function (data) {
			const canvasId = 'revenue-chart-canvas';
			this.destroyChart( canvasId );

			const ctx = this.createCanvasIfNeeded( canvasId );
			if ( ! ctx) {
				return;
			}

			// Safe instance saving - support dynamic IDs
			if ( ! window.MHMRentivaCharts.instances) {
				window.MHMRentivaCharts.instances = {};
			}

			// Find real canvas ID
			const realCanvasId                              = ctx.canvas.id;
			window.MHMRentivaCharts.instances[realCanvasId] = new Chart(
				ctx,
				{
					type: 'line',
					data: {
						labels: data.daily.map( item => this.formatDate( item.date ) ),
						datasets: [{
							label: (mhmRentivaCharts.strings && mhmRentivaCharts.strings.revenue)
							? mhmRentivaCharts.strings.revenue
							: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
								? window.MHMRentiva.i18n.__( 'Revenue' )
								: 'Revenue'),
						data: data.daily.map( item => parseFloat( item.revenue ) ),
						borderColor: 'rgb(75, 192, 192)',
						backgroundColor: 'rgba(75, 192, 192, 0.2)',
						tension: 0.4,
						fill: true
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: { position: 'top' },
							title: {
								display: true,
								text: (mhmRentivaCharts.strings && mhmRentivaCharts.strings.revenue_chart)
								? mhmRentivaCharts.strings.revenue_chart
								: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
									? window.MHMRentiva.i18n.__( 'Revenue Trend' )
									: 'Revenue Trend')
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									callback: function (value) {
										const locale   = (window.mhm_rentiva_config && window.mhm_rentiva_config.locale) || 'en-US';
										const currency = (window.mhm_rentiva_config && window.mhm_rentiva_config.currencySymbol) || '$';
										return value.toLocaleString( locale ) + ' ' + currency;
									}
								}
							}
						},
						interaction: {
							intersect: false,
							mode: 'index'
						}
					}
				}
			);
		},

		renderBookingStatusChart: function (data) {
			const canvasId = 'booking-status-chart-canvas';
			this.destroyChart( canvasId );

			const ctx = this.createCanvasIfNeeded( canvasId );
			if ( ! ctx) {
				return;
			}

			// Safe instance saving - support dynamic IDs
			if ( ! window.MHMRentivaCharts.instances) {
				window.MHMRentivaCharts.instances = {};
			}

			// Find real canvas ID
			const realCanvasId                              = ctx.canvas.id;
			window.MHMRentivaCharts.instances[realCanvasId] = new Chart(
				ctx,
				{
					type: 'doughnut',
					data: {
						labels: data.status_distribution.map(
							item =>
							this.getStatusLabel( item.status )
						),
					datasets: [{
						data: data.status_distribution.map( item => item.count ),
						backgroundColor: [
						'rgb(255, 99, 132)',   // pending
						'rgb(54, 162, 235)',   // confirmed
						'rgb(255, 205, 86)',   // in_progress
						'rgb(75, 192, 192)',   // completed
						'rgb(153, 102, 255)',  // cancelled
						'rgb(255, 159, 64)',   // refunded
						'rgb(199, 199, 199)',  // no_show
						'rgb(83, 102, 255)'    // draft
						],
						borderWidth: 2,
						borderColor: '#fff'
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: { position: 'right' },
							title: {
								display: true,
								text: (mhmRentivaCharts.strings && mhmRentivaCharts.strings.booking_status)
								? mhmRentivaCharts.strings.booking_status
								: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
									? window.MHMRentiva.i18n.__( 'Booking Status' )
									: 'Booking Status')
							},
							tooltip: {
								callbacks: {
									label: function (context) {
										const total      = context.dataset.data.reduce( (a, b) => a + b, 0 );
										const percentage = ((context.parsed / total) * 100).toFixed( 1 );
										return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
									}
								}
							}
						}
					}
				}
			);
		},

		renderVehicleChart: function (data) {
			const canvasId = 'vehicle-chart-canvas';
			this.destroyChart( canvasId );

			const ctx = this.createCanvasIfNeeded( canvasId );
			if ( ! ctx) {
				return;
			}

			// Safe instance saving - support dynamic IDs
			if ( ! window.MHMRentivaCharts.instances) {
				window.MHMRentivaCharts.instances = {};
			}

			// Find real canvas ID
			const realCanvasId                              = ctx.canvas.id;
			window.MHMRentivaCharts.instances[realCanvasId] = new Chart(
				ctx,
				{
					type: 'bar',
					data: {
						labels: data.top_vehicles.slice( 0, 10 ).map(
							item =>
							item.vehicle_title.substring( 0, 20 ) + (item.vehicle_title.length > 20 ? '...' : '')
						),
					datasets: [{
						label: (mhmRentivaCharts.strings && mhmRentivaCharts.strings.vehicle_revenue)
						? mhmRentivaCharts.strings.vehicle_revenue
						: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
							? window.MHMRentiva.i18n.__( 'Total Revenue' )
							: 'Total Revenue'),
						data: data.top_vehicles.slice( 0, 10 ).map( item => parseFloat( item.total_revenue ) ),
						backgroundColor: 'rgba(54, 162, 235, 0.8)',
						borderColor: 'rgb(54, 162, 235)',
						borderWidth: 1
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: { position: 'top' },
							title: {
								display: true,
								text: (mhmRentivaCharts.strings && mhmRentivaCharts.strings.top_vehicles)
								? mhmRentivaCharts.strings.top_vehicles
								: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
									? window.MHMRentiva.i18n.__( 'Top Revenue Vehicles' )
									: 'Top Revenue Vehicles')
							}
						},
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									callback: function (value) {
										const locale   = (window.mhm_rentiva_config && window.mhm_rentiva_config.locale) || 'en-US';
										const currency = (window.mhm_rentiva_config && window.mhm_rentiva_config.currencySymbol) || '$';
										return value.toLocaleString( locale ) + ' ' + currency;
									}
								}
							},
							x: {
								ticks: {
									maxRotation: 45,
									minRotation: 45
								}
							}
						}
					}
				}
			);
		},

		renderCustomerChart: function (data) {
			const canvasId = 'customer-chart-canvas';
			this.destroyChart( canvasId );

			const ctx = this.createCanvasIfNeeded( canvasId );
			if ( ! ctx) {
				return;
			}

			// Safe instance saving - support dynamic IDs
			if ( ! window.MHMRentivaCharts.instances) {
				window.MHMRentivaCharts.instances = {};
			}

			// Find real canvas ID
			const realCanvasId                              = ctx.canvas.id;
			window.MHMRentivaCharts.instances[realCanvasId] = new Chart(
				ctx,
				{
					type: 'pie',
					data: {
						labels: [
						data.segments.vip.label,
						data.segments.regular.label,
						data.segments.new.label
						],
						datasets: [{
							data: [
							data.segments.vip.count,
							data.segments.regular.count,
							data.segments.new.count
							],
							backgroundColor: [
							'rgb(255, 215, 0)',   // VIP - Gold
							'rgb(54, 162, 235)',  // Regular - Blue
							'rgb(128, 128, 128)'  // New - Gray
							],
							borderWidth: 2,
							borderColor: '#fff'
						}]
					},
					options: {
						responsive: true,
						plugins: {
							legend: { position: 'bottom' },
							title: {
								display: true,
								text: (mhmRentivaCharts.strings && mhmRentivaCharts.strings.customer_segments)
								? mhmRentivaCharts.strings.customer_segments
								: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
									? window.MHMRentiva.i18n.__( 'Customer Segmentation' )
									: 'Customer Segmentation')
							},
							tooltip: {
								callbacks: {
									label: function (context) {
										const total      = context.dataset.data.reduce( (a, b) => a + b, 0 );
										const percentage = ((context.parsed / total) * 100).toFixed( 1 );
										return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
									}
								}
							}
						}
					}
				}
			);
		},

		performExport: function (type, format, $button) {
			const startDate    = this.getStartDate();
			const endDate      = this.getEndDate();
			const originalText = $button.html();

			// Show loading state
			const loadingText = (mhmRentivaCharts.strings && mhmRentivaCharts.strings.loading)
				? mhmRentivaCharts.strings.loading
				: ((window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__)
					? window.MHMRentiva.i18n.__( 'Loading...' )
					: 'Loading...');
			$button.html( '<span class="dashicons dashicons-download"></span> ' + loadingText )
				.prop( 'disabled', true );

			// Create form and submit
			const $form = $(
				'<form>',
				{
					action: mhmRentivaCharts.ajax_url,
					method: 'POST',
					style: 'display: none;'
				}
			);

			$form.append(
				$(
					'<input>',
					{
						name: 'action',
						value: 'mhm_rentiva_export'
					}
				)
			);

			$form.append(
				$(
					'<input>',
					{
						name: 'type',
						value: type
					}
				)
			);

			$form.append(
				$(
					'<input>',
					{
						name: 'format',
						value: format
					}
				)
			);

			$form.append(
				$(
					'<input>',
					{
						name: 'start_date',
						value: startDate
					}
				)
			);

			$form.append(
				$(
					'<input>',
					{
						name: 'end_date',
						value: endDate
					}
				)
			);

			$form.append(
				$(
					'<input>',
					{
						name: 'nonce',
						value: mhmRentivaCharts.nonce
					}
				)
			);

			$( 'body' ).append( $form );
			$form.submit();

			// Reset button after a delay
			setTimeout(
				function () {
					$button.html( originalText ).prop( 'disabled', false );
				},
				2000
			);
		},

		// Utility methods
		getStartDate: function () {
			return $( '#mhm-reports-start-date' ).val() || this.getDefaultStartDate();
		},

		getEndDate: function () {
			return $( '#mhm-reports-end-date' ).val() || this.getDefaultEndDate();
		},

		getDefaultStartDate: function () {
			return new Date( Date.now() - 30 * 24 * 60 * 60 * 1000 ).toISOString().split( 'T' )[0];
		},

		getDefaultEndDate: function () {
			return new Date().toISOString().split( 'T' )[0];
		},

		getCurrentTab: function () {
			return $( '.nav-tab-active' ).data( 'tab' ) || 'overview';
		},

		createCanvasIfNeeded: function (canvasId) {
			let $canvas = $( '#' + canvasId );

			if ($canvas.length === 0) {
				// Support dynamic IDs - unique IDs from Charts.php
				const $containers = $( '.mhm-rentiva-chart-container' );
				let foundCanvas   = null;

				// Check existing canvases
				$containers.each(
					function () {
						const $containerCanvas = $( this ).find( 'canvas' );
						if ($containerCanvas.length > 0) {
							// Check if canvas ID matches specific pattern
							const canvasIdPattern = canvasId.replace( '-canvas', '' );
							if ($containerCanvas.attr( 'id' ).indexOf( canvasIdPattern ) !== -1) {
								foundCanvas = $containerCanvas[0];
								return false; // break
							}
						}
					}
				);

				if (foundCanvas) {
					return foundCanvas.getContext( '2d' );
				}

				// Canvas not found
				return null;
			}

			if ($canvas.length === 0 || ! $canvas[0]) {
				return null;
			}

			return $canvas[0].getContext( '2d' );
		},

		destroyChart: function (canvasId) {
			// Safe instance saving - support dynamic IDs
			if (window.MHMRentivaCharts && window.MHMRentivaCharts.instances) {
				// Try exact ID first
				if (window.MHMRentivaCharts.instances[canvasId]) {
					try {
						window.MHMRentivaCharts.instances[canvasId].destroy();
					} catch (e) {
						// Silent error handling
					}
					delete window.MHMRentivaCharts.instances[canvasId];
				} else {
					// Search by pattern
					const pattern = canvasId.replace( '-canvas', '' );
					for (const [key, instance] of Object.entries( window.MHMRentivaCharts.instances )) {
						if (key.indexOf( pattern ) !== -1) {
							try {
								instance.destroy();
							} catch (e) {
								// Silent error handling
							}
							delete window.MHMRentivaCharts.instances[key];
							break;
						}
					}
				}
			}
		},

		showLoadingState: function () {
			$( '.mhm-rentiva-chart-container' ).addClass( 'loading' );
		},

		hideLoadingState: function () {
			$( '.mhm-rentiva-chart-container' ).removeClass( 'loading' );
		},

		showError: function (message) {
			// Simple error notification
			showNotice( message, 'error' );
		},

		formatDate: function (dateString) {
			const date   = new Date( dateString );
			const locale = (window.mhm_rentiva_config && window.mhm_rentiva_config.locale) || 'en-US';
			return date.toLocaleDateString( locale );
		},

		getStatusLabel: function (status) {
			// Use translations from WordPress if available
			if (window.MHMRentiva && window.MHMRentiva.i18n && window.MHMRentiva.i18n.__) {
				const statusKey  = status.charAt( 0 ).toUpperCase() + status.slice( 1 ).replace( /_/g, ' ' );
				const translated = window.MHMRentiva.i18n.__( statusKey );
				// If translation exists and is different from key, return it
				if (translated && translated !== statusKey) {
					return translated;
				}
			}

			// Fallback labels
			const labels = {
				'draft': 'Draft',
				'pending': 'Pending',
				'confirmed': 'Confirmed',
				'in_progress': 'In Progress',
				'completed': 'Completed',
				'cancelled': 'Cancelled',
				'refunded': 'Refunded',
				'no_show': 'No Show'
			};

			return labels[status] || status;
		},

		debounce: function (func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout( timeout );
					func( ...args );
				};
				clearTimeout( timeout );
				timeout = setTimeout( later, wait );
			};
		},

		/**
		 * Escape HTML special characters
		 */
		escapeHtml: function (text) {
			if ( ! text) {
				return '';
			}
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.toString().replace(
				/[&<>"']/g,
				function (m) {
					return map[m]; }
			);
		}
	};

	// Make it globally available first
	window.MHMRentivaCharts = MHMRentivaCharts;
	window.mhmRentivaCharts = MHMRentivaCharts; // Lowercase alias

	/**
	 * Show notice message
	 */
	function showNotice(message, type) {
		type            = type || 'info';
		var noticeClass = 'notice-' + type;

		// Escape message
		var safeMessage = MHMRentivaCharts.escapeHtml( message );

		var notice = $( '<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + safeMessage + '</strong></p></div>' );

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

	// Initialize on document ready
	$( document ).ready(
		function () {
			// Make sure global variable is defined
			if (typeof window.mhmRentivaCharts !== 'undefined') {
				window.mhmRentivaCharts.init();
			}
		}
	);

})( jQuery );
