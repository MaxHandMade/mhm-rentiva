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
			console.error('MHM Rentiva Dashboard: jQuery is required but not loaded');
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
			this.initOpsPagination();
		},

		// Upcoming Operations Pagination
		initOpsPagination: function () {
			var $pagination = $('.ops-pagination');
			if ( !$pagination.length ) {
				return;
			}

			var self = this;

			$(document).on('click', '#ops-prev', function () {
				var current = parseInt( $pagination.data('current'), 10 );
				if ( current > 1 ) {
					self.loadOpsPage( current - 1 );
				}
			});

			$(document).on('click', '#ops-next', function () {
				var current  = parseInt( $pagination.data('current'), 10 );
				var total    = parseInt( $pagination.data('total'), 10 );
				if ( current < total ) {
					self.loadOpsPage( current + 1 );
				}
			});
		},

		loadOpsPage: function ( page ) {
			var $tbody      = $('#mhm-upcoming-ops-body');
			var $pagination = $('.ops-pagination');

			$tbody.css('opacity', '0.5');

			$.ajax({
				url: mhm_dashboard_vars.ajax_url,
				type: 'POST',
				data: {
					action: 'mhm_upcoming_operations_page',
					nonce:  mhm_dashboard_vars.nonce,
					page:   page
				},
				success: function ( response ) {
					if ( response.success ) {
						$tbody.html( response.data.html );
						$pagination.data('current', response.data.page);

						$('#ops-current-page').text( response.data.page );
						$('#ops-prev').prop('disabled', response.data.page <= 1);
						$('#ops-next').prop('disabled', response.data.page >= response.data.total_pages);
					}
				},
				complete: function () {
					$tbody.css('opacity', '1');
				}
			});
		},

		// Initialize sortable widgets
		initSortable: function () {
			var self = this;
			var $container = $('#mhm-dashboard-widgets');

			if (!$container.length) {
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
			$('.mhm-dashboard-widget-wrapper').each(
				function () {
					order.push($(this).data('widget'));
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
			var ctx = document.getElementById('revenue-chart-canvas');
			if (!ctx) {
				if (typeof console !== 'undefined' && console.warn) {
					console.warn('MHM Rentiva Dashboard: Revenue chart canvas not found');
				}
				return;
			}

			// JavaScript variables check
			if (typeof mhm_dashboard_vars === 'undefined') {
				if (typeof console !== 'undefined' && console.error) {
					console.error('MHM Rentiva Dashboard: mhm_dashboard_vars not defined');
				}
				return;
			}

			var revenueData = mhm_dashboard_vars.revenue_data;
			var currency = mhm_dashboard_vars.currency;
			var locale = 'en-US';

			// Prepare chart data
			var labels = [];
			var data = [];
			var totalRevenue = 0;

			// Process last 7 days data
			if (revenueData.daily_data) {
				for (var j = 0; j < revenueData.daily_data.length; j++) {
					var item = revenueData.daily_data[j];
					var parts = item.date ? item.date.split('-') : [];
					var dateString = parts.length === 3 ? parts[1] + '/' + parts[2] : item.date;
					labels.push(dateString);

					var dayRevenue = parseFloat(item.revenue) || 0;
					data.push(dayRevenue);
					totalRevenue += dayRevenue;
				}
			}

			// Build gradient fill
			var canvasCtx = ctx.getContext('2d');
			var gradient = canvasCtx.createLinearGradient(0, 0, 0, ctx.offsetHeight || 220);
			gradient.addColorStop(0, 'rgba(99, 102, 241, 0.45)');
			gradient.addColorStop(0.6, 'rgba(99, 102, 241, 0.12)');
			gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');

			// Chart.js configuration - AREA CHART
			var config = {
				type: 'line',
				data: {
					labels: labels,
					datasets: [{
						label: (mhm_dashboard_vars.strings && mhm_dashboard_vars.strings.daily_revenue) || 'Daily Revenue',
						data: data,
						fill: true,
						backgroundColor: gradient,
						borderColor: 'rgba(99, 102, 241, 1)',
						borderWidth: 2.5,
						pointBackgroundColor: 'rgba(99, 102, 241, 1)',
						pointBorderColor: '#ffffff',
						pointBorderWidth: 2,
						pointRadius: 5,
						pointHoverRadius: 7,
						pointHoverBackgroundColor: '#ffffff',
						pointHoverBorderColor: 'rgba(99, 102, 241, 1)',
						pointHoverBorderWidth: 2.5,
						tension: 0.4
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: {
						mode: 'index',
						intersect: false
					},
					plugins: {
						legend: {
							display: false
						},
						tooltip: {
							backgroundColor: 'rgba(17, 24, 39, 0.95)',
							titleColor: '#f9fafb',
							bodyColor: '#d1d5db',
							borderColor: 'rgba(99, 102, 241, 0.5)',
							borderWidth: 1,
							padding: 12,
							cornerRadius: 8,
							callbacks: {
								label: function (context) {
									var revenueText = (mhm_dashboard_vars.strings && mhm_dashboard_vars.strings.revenue) || 'Revenue';
									return revenueText + ': ' + context.parsed.y.toLocaleString(locale) + ' ' + currency;
								}
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							grid: {
								color: 'rgba(0, 0, 0, 0.06)'
							},
							border: {
								display: false
							},
							ticks: {
								color: '#9ca3af',
								font: { size: 11 },
								callback: function (value) {
									if (value >= 1000) {
										return (value / 1000).toLocaleString(locale) + 'K ' + currency;
									}
									return value.toLocaleString(locale) + ' ' + currency;
								}
							}
						},
						x: {
							grid: {
								display: false
							},
							border: {
								display: false
							},
							ticks: {
								color: '#9ca3af',
								font: { size: 11 }
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
			this.chart = new Chart(ctx, config);
		},

		// Initialize event handlers
		initEventHandlers: function () {
			var self = this;

			// Handle Mobile Accordion Logic
			var isMobile = window.innerWidth <= 992;

			if (isMobile) {
				$('.mhm-dashboard-widget').addClass('is-collapsible is-collapsed');
				// Quick actions should probably stay open by default as it's the most used
				$('.mhm-dashboard-widget-wrapper[data-widget="quick-actions"] .mhm-dashboard-widget').removeClass('is-collapsed');
			}

			// Widget Header Click - Toggle Collapse
			$(document).on(
				'click',
				'.mhm-dashboard-widget h3',
				function (e) {
					// Only allow collapse on mobile or if specifically made collapsible
					var $widget = $(this).closest('.mhm-dashboard-widget');

					if (window.innerWidth <= 992 || $widget.hasClass('is-collapsible')) {
						$widget.toggleClass('is-collapsed');

						// Re-calculate chart if revenue chart is expanded
						if (!$widget.hasClass('is-collapsed') && $widget.find('#revenue-chart-canvas').length) {
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
			$('.mhm-refresh-btn').on(
				'click',
				function (e) {
					e.preventDefault();
					var btn = $(this);
					btn.prop('disabled', true).text('Refreshing...');

					setTimeout(
						function () {
							location.reload();
						},
						1000
					);
				}
			);

			// Reset layout button
			$('#mhm-reset-dashboard').on(
				'click',
				function (e) {
					e.preventDefault();
					if (!confirm('Are you sure you want to reset the dashboard layout?')) {
						return;
					}

					$.ajax(
						{
							url: mhm_dashboard_vars.ajax_url,
							type: 'POST',
							data: {
								action: 'mhm_reset_dashboard_layout',
								nonce: mhm_dashboard_vars.nonce
							},
							success: function (response) {
								if (response.success) {
									location.reload();
								} else {
									alert(response.data || 'Reset failed');
								}
							}
						}
					);
				}
			);
		}
	};

	// Initialize when DOM is ready
	$(document).ready(
		function () {
			Dashboard.init();
		}
	);

	// Assign to global namespace
	window.MHMRentiva.Dashboard = Dashboard;

})();
