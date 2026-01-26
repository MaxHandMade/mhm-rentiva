/**
 * MHM Rentiva - Dashboard JavaScript
 * Dashboard functionality and Chart.js integration
 */

// Modern JavaScript to reduce jQuery dependency
(function () {
	'use strict';

	// jQuery fallback check
	if (typeof jQuery === 'undefined') {
		if (typeof console !== 'undefined' && console.error) {
			console.error( 'MHM Rentiva Dashboard: jQuery is required but not loaded' );
		}
		return;
	}

	var $ = jQuery;

	// Use namespace to prevent global scope pollution
	if (typeof window.MHMRentiva === 'undefined') {
		window.MHMRentiva = {};
	}

	if (typeof window.MHMRentiva.Dashboard === 'undefined') {
		window.MHMRentiva.Dashboard = {};
	}

	// Dashboard initialization
	var Dashboard = {
		chart: null, // Store chart instance in namespace

		init: function () {
			this.initRevenueChart();
			this.initSortable();
			this.initEventHandlers();
		},

		// Initialize sortable widgets
		initSortable: function () {
			var self       = this;
			var $container = $( '#mhm-dashboard-widgets' );

			if ( ! $container.length) {
				return;
			}

			$container.sortable(
				{
					handle: '.mhm-widget-drag-handle',
					placeholder: 'mhm-sortable-placeholder',
					forcePlaceholderSize: true,
					opacity: 0.8,
					update: function (event, ui) {
						self.saveWidgetOrder();
					}
				}
			);
		},

		// Save widget order via AJAX
		saveWidgetOrder: function () {
			var order = [];
			$( '.mhm-dashboard-widget-wrapper' ).each(
				function () {
					order.push( $( this ).data( 'widget' ) );
				}
			);

			$.ajax(
				{
					url: mhm_dashboard_vars.ajax_url,
					type: 'POST',
					data: {
						action: 'mhm_save_dashboard_order',
						nonce: mhm_dashboard_vars.nonce,
						order: order
					},
					success: function (response) {
						if (response.success) {
						}
					}
				}
			);
		},

		// Initialize revenue chart
		initRevenueChart: function () {
			var ctx = document.getElementById( 'revenue-chart-canvas' );
			if ( ! ctx) {
				if (typeof console !== 'undefined' && console.warn) {
					console.warn( 'MHM Rentiva Dashboard: Revenue chart canvas not found' );
				}
				return;
			}

			// JavaScript variables check
			if (typeof mhm_dashboard_vars === 'undefined') {
				if (typeof console !== 'undefined' && console.error) {
					console.error( 'MHM Rentiva Dashboard: mhm_dashboard_vars not defined' );
				}
				return;
			}

			var revenueData = mhm_dashboard_vars.revenue_data;
			var currency    = mhm_dashboard_vars.currency;
			var locale      = 'en-US'; // Define locale

			// Prepare chart data
			var labels       = [];
			var data         = [];
			var totalRevenue = 0;

			// Process last 14 days data
			for (var i = 13; i >= 0; i--) {
				var date = new Date();
				date.setDate( date.getDate() - i );
				var dateString = date.toLocaleDateString( 'en-US', { day: '2-digit', month: '2-digit' } );
				labels.push( dateString );

				// Get revenue value from daily_data array
				var dayRevenue = 0;
				if (revenueData.daily_data && revenueData.daily_data[i]) {
					dayRevenue = revenueData.daily_data[i].revenue || 0;
				}
				data.push( dayRevenue );
				totalRevenue += dayRevenue;
			}

			// Chart.js configuration - BAR CHART
			var config = {
				type: 'bar',
				data: {
					labels: labels,
					datasets: [{
						label: (mhm_dashboard_vars.strings && mhm_dashboard_vars.strings.daily_revenue) || 'Daily Revenue' + ' (' + currency + ')',
						data: data,
						backgroundColor: 'rgba(59, 130, 246, 0.8)',  // Tek mavi renk
						borderColor: 'rgba(59, 130, 246, 1)',         // Tek mavi border
						borderWidth: 2,
						borderRadius: 6,
						borderSkipped: false,
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: false
						},
						tooltip: {
							backgroundColor: 'rgba(0, 0, 0, 0.9)',
							titleColor: '#ffffff',
							bodyColor: '#ffffff',
							borderColor: '#333333',
							borderWidth: 1,
							callbacks: {
								label: function (context) {
									var revenueText = (mhm_dashboard_vars.strings && mhm_dashboard_vars.strings.revenue) || 'Revenue';
									return revenueText + ': ' + context.parsed.y.toLocaleString( locale ) + ' ' + currency;
								}
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							grid: {
								color: 'var(--mhm-chart-grid, rgba(0, 0, 0, 0.1))'
							},
							ticks: {
								callback: function (value) {
									return value.toLocaleString( locale ) + ' ' + currency;
								}
							}
						},
						x: {
							grid: {
								display: false
							}
						}
					}
				}
			};

			// Destroy existing chart instance to prevent 'Canvas already in use' error
			if (this.chart) {
				this.chart.destroy();
			}

			// Create Chart.js instance
			this.chart = new Chart( ctx, config );
		},

		// Initialize tooltips - Bootstrap tooltips not loaded
		// initTooltips: function () {
		//     // Activate Bootstrap tooltips
		//     $('[data-bs-toggle="tooltip"]').tooltip();
		// },

		// Initialize event handlers
		initEventHandlers: function () {
			var self = this;

			// Handle Mobile Accordion Logic
			var isMobile = window.innerWidth <= 768;

			if (isMobile) {
				$( '.mhm-dashboard-widget' ).addClass( 'is-collapsible is-collapsed' );
				// Quick actions should probably stay open by default as it's the most used
				$( '.mhm-dashboard-widget-wrapper[data-widget="quick-actions"] .mhm-dashboard-widget' ).removeClass( 'is-collapsed' );
			}

			// Widget Header Click - Toggle Collapse
			$( document ).on(
				'click',
				'.mhm-dashboard-widget h3',
				function (e) {
					// Only allow collapse on mobile or if specifically made collapsible
					var $widget = $( this ).closest( '.mhm-dashboard-widget' );

					if (window.innerWidth <= 768 || $widget.hasClass( 'is-collapsible' )) {
						$widget.toggleClass( 'is-collapsed' );

						// Re-calculate chart if revenue chart is expanded
						if ( ! $widget.hasClass( 'is-collapsed' ) && $widget.find( '#revenue-chart-canvas' ).length) {
							setTimeout(
								function () {
									self.initRevenueChart();
								},
								10
							);
						}
					}
				}
			);

			// Refresh buttons
			$( '.mhm-refresh-btn' ).on(
				'click',
				function (e) {
					e.preventDefault();
					var btn = $( this );
					btn.prop( 'disabled', true ).text( 'Refreshing...' );

					setTimeout(
						function () {
							location.reload();
						},
						1000
					);
				}
			);

			// Reset layout button
			$( '#mhm-reset-dashboard' ).on(
				'click',
				function (e) {
					e.preventDefault();
					if ( ! confirm( 'Are you sure you want to reset the dashboard layout?' )) {
						return;
					}

					$.ajax(
						{
							url: mhm_dashboard_vars.ajax_url,
							type: 'POST',
							data: {
								action: 'mhm_save_dashboard_order',
								nonce: mhm_dashboard_vars.nonce,
								order: [] // Empty order to reset to defaults
							},
							success: function (response) {
								if (response.success) {
									location.reload();
								}
							}
						}
					);
				}
			);
		}
	};

	// Initialize when DOM is ready
	$( document ).ready(
		function () {
			Dashboard.init();
		}
	);

	// Assign to global namespace
	window.MHMRentiva.Dashboard = Dashboard;

})();