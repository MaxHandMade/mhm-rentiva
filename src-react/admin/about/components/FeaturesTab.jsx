import { __ } from '@wordpress/i18n';

export default function FeaturesTab( { data } ) {
	return (
		<div className="mhm-widget mhm-about-features">
			<h3>{ __( 'Lite vs Pro Comparison', 'mhm-rentiva' ) }</h3>
			<div className="mhm-about-features-table-wrap">
				<table className="widefat mhm-features-table">
					<thead>
						<tr>
							<th>{ __( 'Feature', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Lite', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Pro', 'mhm-rentiva' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ data.comparison.map( ( row, i ) => (
							<tr key={ i }>
								<td><strong>{ row.name }</strong></td>
								<td>{ row.lite }</td>
								<td><strong>{ row.pro }</strong></td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>
		</div>
	);
}
