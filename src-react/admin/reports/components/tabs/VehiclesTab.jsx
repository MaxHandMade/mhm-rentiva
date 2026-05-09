import { __ } from '@wordpress/i18n';

export default function VehiclesTab( { data } ) {
	return (
		<div className="mhm-reports__tab-content">
			<div className="mhm-reports__summary">
				<p>{ __( 'Total Vehicles', 'mhm-rentiva' ) }: { data?.total_vehicles ?? 0 }</p>
				<p>{ __( 'Active Vehicles', 'mhm-rentiva' ) }: { data?.active_vehicles ?? 0 }</p>
			</div>
			<pre className="mhm-reports__debug">{ JSON.stringify( data, null, 2 ) }</pre>
		</div>
	);
}
