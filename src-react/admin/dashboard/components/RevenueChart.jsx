import { useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Chart from 'chart.js/auto';
import { fmtMoney } from '../../../shared/format';

export default function RevenueChart( { revenueData, currency } ) {
	const canvasRef = useRef( null );
	const chartRef  = useRef( null );

	useEffect( () => {
		if ( ! canvasRef.current || ! revenueData?.daily_data ) {
			return;
		}

		if ( chartRef.current ) {
			chartRef.current.destroy();
		}

		const labels  = revenueData.daily_data.map( ( d ) => d.date );
		const amounts = revenueData.daily_data.map( ( d ) => Number( d.revenue ) );

		chartRef.current = new Chart( canvasRef.current, {
			type: 'bar',
			data: {
				labels,
				datasets: [ {
					// "Revenue" DEĞİL: DashboardService::get_revenue_data() günleri DATE(p.post_date)
					// ile, yani rezervasyonun OLUŞTURULMA tarihiyle kovalıyor -- kiralama tarihiyle
					// değil. Etiket ölçtüğü şeyi söylüyor; tabanı 5 kardeş metot paylaştığı için
					// taban değiştirilmedi (bkz. docs/plans/2026-07-28-pro-admin-i18n-plan.md T7).
					label:           __( 'Daily Bookings Value', 'mhm-rentiva' ),
					data:            amounts,
					backgroundColor: 'rgba(54, 162, 235, 0.5)',
					borderColor:     'rgba(54, 162, 235, 1)',
					borderWidth:     1,
				} ],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false, // Container'a uy, default aspect ratio dikey büyütme yapmasın (max-height: dashboard.css'te .mhm-revenue-chart canvas).
				plugins: {
					tooltip: {
						callbacks: {
							label: ( ctx ) => fmtMoney( ctx.parsed.y, currency ),
						},
					},
				},
				scales: {
					y: { beginAtZero: true },
				},
			},
		} );

		return () => {
			if ( chartRef.current ) {
				chartRef.current.destroy();
				chartRef.current = null;
			}
		};
	}, [ revenueData, currency ] );

	return (
		<div className="mhm-widget mhm-revenue-chart">
			<h3><span className="dashicons dashicons-chart-bar" />{ __( 'New Bookings Value (Last 7 Days)', 'mhm-rentiva' ) }</h3>
			<canvas ref={ canvasRef } height="200" />
			<p className="mhm-revenue-chart__weekly">
				{ __( 'This week:', 'mhm-rentiva' ) }{ ' ' }
				<strong>{ fmtMoney( revenueData?.weekly_total, currency ) }</strong>
			</p>
		</div>
	);
}
