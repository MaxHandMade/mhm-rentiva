/**
 * MHM Rentiva Reports Charts JavaScript
 * Raporlar sayfasındaki grafikleri yönetir
 */

(function ($) {
	'use strict';

	// Global değişken kontrolü
	if (typeof mhmRentivaCharts === 'undefined') {
		if (typeof console !== 'undefined' && console.warn) {
			console.warn( 'mhmRentivaCharts not available' );
		}
		return;
	}

	/**
	 * Revenue Chart
	 */
	function initRevenueChart(chartId, startDate, endDate) {
		const ctx = document.getElementById( chartId );
		if ( ! ctx) {
			return;
		}

		$.ajax(
			{
				url: mhmRentivaCharts.ajax_url,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_reports_data',
					type: 'revenue',
					start_date: startDate,
					end_date: endDate,
					nonce: mhmRentivaCharts.nonce
				},
				success: function (response) {
					if (response.success && response.data.daily.length > 0) {
						// Use formatted label from PHP (WordPress date_format), fallback to raw date
						var labels = response.data.daily.map( function( item ) {
							return item.label || item.date;
						});

						var datasets = [{
							label: mhmRentivaCharts.strings.daily_revenue,
							data: response.data.daily.map( function( item ) { return item.revenue; } ),
							borderColor: '#0073aa',
							backgroundColor: 'rgba(0, 115, 170, 0.1)',
							fill: true,
							tension: 0.4
						}];

						// Add cancelled dataset if data exists
						var cancelled = response.data.cancelled || [];
						if (cancelled.length > 0) {
							// Build a cancelled map keyed by raw date for alignment
							var cancelledMap = {};
							cancelled.forEach( function( item ) {
								cancelledMap[ item.date ] = parseFloat( item.revenue ) || 0;
							});

							// Align cancelled data to the same labels (dates) as revenue
							var cancelledData = response.data.daily.map( function( item ) {
								return cancelledMap[ item.date ] || 0;
							});

							// Only add if there's at least one non-zero value
							var hasData = cancelledData.some( function( v ) { return v > 0; } );
							if (hasData) {
								datasets.push({
									label: mhmRentivaCharts.strings.cancelled_revenue || 'Cancelled',
									data: cancelledData,
									borderColor: '#dc3545',
									backgroundColor: 'rgba(220, 53, 69, 0.08)',
									borderDash: [5, 3],
									fill: true,
									tension: 0.4,
									pointStyle: 'crossRot',
									pointRadius: 4
								});
							}
						}

						new Chart(
							ctx,
							{
								type: 'line',
								data: {
									labels: labels,
									datasets: datasets
								},
								options: {
									responsive: true,
									maintainAspectRatio: false,
									interaction: {
										mode: 'index',
										intersect: false
									},
									scales: {
										y: {
											beginAtZero: true,
											ticks: {
												callback: function (value) {
													var jsLocale = mhmRentivaCharts.locale.replace( '_', '-' );
													return mhmRentivaCharts.currencySymbol + value.toLocaleString( jsLocale );
												}
											}
										}
									},
									plugins: {
										tooltip: {
											callbacks: {
												label: function (context) {
													var jsLocale = mhmRentivaCharts.locale.replace( '_', '-' );
													var val = context.parsed.y || 0;
													return context.dataset.label + ': ' + mhmRentivaCharts.currencySymbol + val.toLocaleString( jsLocale );
												}
											}
										}
									}
								}
							}
						);
					} else {
						if (ctx && ctx.canvas) {
							ctx.font      = '16px Arial';
							ctx.fillStyle = '#666';
							ctx.textAlign = 'center';
							ctx.fillText( mhmRentivaCharts.strings.no_data, ctx.canvas.width / 2, ctx.canvas.height / 2 );
						}
					}
				},
				error: function () {
					if (ctx && ctx.canvas) {
						ctx.font      = '16px Arial';
						ctx.fillStyle = '#f00';
						ctx.textAlign = 'center';
						ctx.fillText( mhmRentivaCharts.strings.error_loading, ctx.canvas.width / 2, ctx.canvas.height / 2 );
					}
				}
			}
		);
	}

	/**
	 * Bookings Chart
	 */
	function initBookingsChart(chartId, startDate, endDate) {
		const ctx = document.getElementById( chartId );
		if ( ! ctx) {
			return;
		}

		$.ajax(
			{
				url: mhmRentivaCharts.ajax_url,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_reports_data',
					type: 'bookings',
					start_date: startDate,
					end_date: endDate,
					nonce: mhmRentivaCharts.nonce
				},
				success: function (response) {
					if (response.success && response.data.status_distribution.length > 0) {
						new Chart(
							ctx,
							{
								type: 'doughnut',
								data: {
									labels: response.data.status_distribution.map(
										item =>
										mhmRentivaCharts.getStatusLabel( item.status )
									),
								datasets: [{
									data: response.data.status_distribution.map( item => item.count ),
									backgroundColor: [
									'#ffc107', // Pending
									'#28a745', // Confirmed
									'#0073aa', // Completed
									'#dc3545'  // Cancelled
									]
									}]
								},
								options: {
									responsive: true,
									maintainAspectRatio: false,
									plugins: {
										legend: {
											position: 'bottom'
										}
									}
								}
							}
						);
					} else {
						if (ctx && ctx.canvas) {
							ctx.font      = '16px Arial';
							ctx.fillStyle = '#666';
							ctx.textAlign = 'center';
							ctx.fillText( mhmRentivaCharts.strings.no_data, ctx.canvas.width / 2, ctx.canvas.height / 2 );
						}
					}
				},
				error: function () {
					if (ctx && ctx.canvas) {
						ctx.font      = '16px Arial';
						ctx.fillStyle = '#f00';
						ctx.textAlign = 'center';
						ctx.fillText( mhmRentivaCharts.strings.error_loading, ctx.canvas.width / 2, ctx.canvas.height / 2 );
					}
				}
			}
		);
	}

	/**
	 * Vehicles Chart
	 */
	function initVehiclesChart(chartId, startDate, endDate) {
		const ctx = document.getElementById( chartId );
		if ( ! ctx) {
			return;
		}

		$.ajax(
			{
				url: mhmRentivaCharts.ajax_url,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_reports_data',
					type: 'vehicles',
					start_date: startDate,
					end_date: endDate,
					nonce: mhmRentivaCharts.nonce
				},
				success: function (response) {
					if (response.success && response.data.top_vehicles.length > 0) {
						new Chart(
							ctx,
							{
								type: 'bar',
								data: {
									labels: response.data.top_vehicles.map( item => item.vehicle_name ),
									datasets: [{
										label: mhmRentivaCharts.strings.booking_count || 'Booking Count',
										data: response.data.top_vehicles.map( item => item.booking_count ),
										backgroundColor: '#0073aa'
									}]
								},
								options: {
									responsive: true,
									maintainAspectRatio: false,
									scales: {
										y: {
											beginAtZero: true
										}
									}
								}
							}
						);
					} else {
						if (ctx && ctx.canvas) {
							ctx.font      = '16px Arial';
							ctx.fillStyle = '#666';
							ctx.textAlign = 'center';
							ctx.fillText( mhmRentivaCharts.strings.no_data, ctx.canvas.width / 2, ctx.canvas.height / 2 );
						}
					}
				},
				error: function () {
					if (ctx && ctx.canvas) {
						ctx.font      = '16px Arial';
						ctx.fillStyle = '#f00';
						ctx.textAlign = 'center';
						ctx.fillText( mhmRentivaCharts.strings.error_loading, ctx.canvas.width / 2, ctx.canvas.height / 2 );
					}
				}
			}
		);
	}

	/**
	 * Customers Chart
	 */
	function initCustomersChart(chartId, startDate, endDate) {
		const ctx = document.getElementById( chartId );
		if ( ! ctx) {
			return;
		}

		$.ajax(
			{
				url: mhmRentivaCharts.ajax_url,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_reports_data',
					type: 'customers',
					start_date: startDate,
					end_date: endDate,
					nonce: mhmRentivaCharts.nonce
				},
				success: function (response) {
					if (response.success && response.data.segments) {
						new Chart(
							ctx,
							{
								type: 'pie',
								data: {
									labels: [
									mhmRentivaCharts.strings.vip_customers,
									mhmRentivaCharts.strings.regular_customers,
									mhmRentivaCharts.strings.new_customers
									],
									datasets: [{
										data: [
										response.data.segments.vip || 0,
										response.data.segments.regular || 0,
										response.data.segments.new || 0
										],
										backgroundColor: [
										'#ffc107', // VIP
										'#28a745', // Regular
										'#0073aa'  // New
										]
									}]
								},
								options: {
									responsive: true,
									maintainAspectRatio: false,
									plugins: {
										legend: {
											position: 'bottom'
										}
									}
								}
							}
						);
					} else {
						if (ctx && ctx.canvas) {
							ctx.font      = '16px Arial';
							ctx.fillStyle = '#666';
							ctx.textAlign = 'center';
							ctx.fillText( mhmRentivaCharts.strings.no_data, ctx.canvas.width / 2, ctx.canvas.height / 2 );
						}
					}
				},
				error: function () {
					if (ctx && ctx.canvas) {
						ctx.font      = '16px Arial';
						ctx.fillStyle = '#f00';
						ctx.textAlign = 'center';
						ctx.fillText( mhmRentivaCharts.strings.error_loading, ctx.canvas.width / 2, ctx.canvas.height / 2 );
					}
				}
			}
		);
	}

	// Global fonksiyonları window'a ekle
	window.mhmRentivaCharts                    = window.mhmRentivaCharts || {};
	window.mhmRentivaCharts.initRevenueChart   = initRevenueChart;
	window.mhmRentivaCharts.initBookingsChart  = initBookingsChart;
	window.mhmRentivaCharts.initVehiclesChart  = initVehiclesChart;
	window.mhmRentivaCharts.initCustomersChart = initCustomersChart;

	// Status label helper
	window.mhmRentivaCharts.getStatusLabel = function (status) {
		const labels = window.mhmRentivaCharts.statusLabels || {
			'pending': 'Pending',
			'confirmed': 'Confirmed',
			'completed': 'Completed',
			'cancelled': 'Cancelled'
		};
		return labels[status] || status;
	};

})( jQuery );