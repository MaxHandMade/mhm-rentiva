import { useRef, useEffect } from '@wordpress/element';
import { __ }               from '@wordpress/i18n';
import { fmtAmount, fmtMoney } from '../../../../shared/format';

export default function RevenueTab( { data, currency } ) {
	const canvasRef = useRef( null );
	const chartRef  = useRef( null );

	useEffect( () => {
		if ( ! canvasRef.current || ! window.Chart || ! data?.daily ) return;
		if ( chartRef.current ) {
			chartRef.current.destroy();
		}
		const labels   = data.daily.map( ( d ) => d.label ?? d.date );
		const revenues = data.daily.map( ( d ) => parseFloat( d.revenue ?? 0 ) );

		chartRef.current = new window.Chart( canvasRef.current, {
			type: 'bar',
			data: {
				labels,
				datasets: [
					{
						label:           __( 'Revenue', 'mhm-rentiva' ),
						data:            revenues,
						backgroundColor: 'rgba(37, 99, 235, 0.7)',
						borderRadius:    4,
					},
				],
			},
			options: {
				responsive: true,
				plugins:    { legend: { display: false } },
				scales:     {
					y: {
						beginAtZero: true,
						ticks:       { callback: ( v ) => fmtMoney( v, currency, 0 ) },
					},
				},
			},
		} );

		return () => {
			if ( chartRef.current ) {
				chartRef.current.destroy();
				chartRef.current = null;
			}
		};
	}, [data, currency] );

	return (
		<div className="mhm-reports__tab-content">
			<div className="mhm-widget">
				<canvas ref={ canvasRef } height="250" />
			</div>
			<div className="mhm-reports__summary">
				<p>{ __( 'Total Revenue', 'mhm-rentiva' ) }: { fmtMoney( data?.total, currency ) }</p>
			</div>
		</div>
	);
}
