import { useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fmtMoney } from '../../../shared/format';

export default function RevenueChart( { revenueData, currency } ) {
	const canvasRef = useRef( null );
	const chartRef  = useRef( null );

	useEffect( () => {
		if ( ! canvasRef.current || ! window.Chart || ! revenueData?.daily_data ) {
			return;
		}

		if ( chartRef.current ) {
			chartRef.current.destroy();
		}

		const labels  = revenueData.daily_data.map( ( d ) => d.date );
		const amounts = revenueData.daily_data.map( ( d ) => Number( d.revenue ) );

		chartRef.current = new window.Chart( canvasRef.current, {
			type: 'bar',
			data: {
				labels,
				datasets: [ {
					label:           __( 'Daily Revenue', 'mhm-rentiva' ),
					data:            amounts,
					backgroundColor: 'rgba(54, 162, 235, 0.5)',
					borderColor:     'rgba(54, 162, 235, 1)',
					borderWidth:     1,
				} ],
			},
			options: {
				responsive: true,
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
			<h3><span className="dashicons dashicons-chart-bar" />{ __( 'Revenue (Last 7 Days)', 'mhm-rentiva' ) }</h3>
			<canvas ref={ canvasRef } height="200" />
			<p className="mhm-revenue-chart__weekly">
				{ __( 'This week:', 'mhm-rentiva' ) }{ ' ' }
				<strong>{ fmtMoney( revenueData?.weekly_total, currency ) }</strong>
			</p>
		</div>
	);
}
