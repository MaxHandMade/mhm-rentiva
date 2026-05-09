import { useRef, useEffect } from '@wordpress/element';
import { __ }               from '@wordpress/i18n';

export default function BookingsTab( { data } ) {
	const canvasRef = useRef( null );
	const chartRef  = useRef( null );

	useEffect( () => {
		if ( ! canvasRef.current || ! window.Chart || ! data?.daily_data ) return;
		if ( chartRef.current ) {
			chartRef.current.destroy();
		}
		const labels = data.daily_data.map( ( d ) => d.label ?? d.date );
		const counts = data.daily_data.map( ( d ) => parseInt( d.count ?? 0, 10 ) );

		chartRef.current = new window.Chart( canvasRef.current, {
			type: 'line',
			data: {
				labels,
				datasets: [
					{
						label:           __( 'Bookings', 'mhm-rentiva' ),
						data:            counts,
						borderColor:     'rgba(5, 150, 105, 0.9)',
						backgroundColor: 'rgba(5, 150, 105, 0.1)',
						tension:         0.3,
						fill:            true,
					},
				],
			},
			options: {
				responsive: true,
				plugins:    { legend: { display: false } },
				scales:     { y: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		} );

		return () => {
			if ( chartRef.current ) {
				chartRef.current.destroy();
				chartRef.current = null;
			}
		};
	}, [data] );

	return (
		<div className="mhm-reports__tab-content">
			<div className="mhm-widget">
				<canvas ref={ canvasRef } height="250" />
			</div>
			<div className="mhm-reports__summary">
				<p>{ __( 'Total Bookings', 'mhm-rentiva' ) }: { data?.total_bookings ?? 0 }</p>
			</div>
		</div>
	);
}
