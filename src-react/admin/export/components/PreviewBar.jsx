import { __ } from '@wordpress/i18n';

export default function PreviewBar( { count, sample } ) {
	return (
		<div className="mhm-export-preview">
			<p className="mhm-export-preview__count">
				{ count === 0
					? __( 'No records match the selected filters.', 'mhm-rentiva' )
					: <>
						{ __( 'Records found:', 'mhm-rentiva' ) }
						{ ' ' }
						<strong>{ count }</strong>
					</>
				}
			</p>

			{ Array.isArray( sample ) && sample.length > 0 && (
				<table className="mhm-export-preview__table widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'ID', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Date', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Status', 'mhm-rentiva' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ sample.map( ( row ) => (
							<tr key={ row.id }>
								<td>{ row.id }</td>
								<td>{ row.date }</td>
								<td>{ row.status }</td>
							</tr>
						) ) }
					</tbody>
					{ count > sample.length && (
						<tfoot>
							<tr>
								<td colSpan="3" className="mhm-export-preview__more">
									{ `…${ count - sample.length } ${ __( 'more records', 'mhm-rentiva' ) }` }
								</td>
							</tr>
						</tfoot>
					) }
				</table>
			) }
		</div>
	);
}
