import { __ } from '@wordpress/i18n';

export default function CustomersTab( { data } ) {
	return (
		<div className="mhm-reports__tab-content">
			<div className="mhm-reports__summary">
				<p>{ __( 'Total Customers', 'mhm-rentiva' ) }: { data?.total_customers ?? 0 }</p>
				<p>{ __( 'New Customers', 'mhm-rentiva' ) }: { data?.new_customers ?? 0 }</p>
			</div>
			<pre className="mhm-reports__debug">{ JSON.stringify( data, null, 2 ) }</pre>
		</div>
	);
}
