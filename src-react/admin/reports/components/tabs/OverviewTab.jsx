import { __ } from '@wordpress/i18n';

export default function OverviewTab( { data } ) {
	return (
		<div className="mhm-reports__tab-content mhm-reports__overview">
			<div className="mhm-reports__overview-grid">
				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Revenue', 'mhm-rentiva' ) }</h3>
					<pre className="mhm-reports__debug">{ JSON.stringify( data.revenue, null, 2 ) }</pre>
				</section>
				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Bookings', 'mhm-rentiva' ) }</h3>
					<pre className="mhm-reports__debug">{ JSON.stringify( data.bookings, null, 2 ) }</pre>
				</section>
				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Vehicles', 'mhm-rentiva' ) }</h3>
					<pre className="mhm-reports__debug">{ JSON.stringify( data.vehicles, null, 2 ) }</pre>
				</section>
				<section className="mhm-reports__overview-card">
					<h3>{ __( 'Customers', 'mhm-rentiva' ) }</h3>
					<pre className="mhm-reports__debug">{ JSON.stringify( data.customers, null, 2 ) }</pre>
				</section>
			</div>
		</div>
	);
}
