import { __ } from '@wordpress/i18n';
import ReportRow from './ReportRow';

export default function ReportTable( { reports, onOpen } ) {
	if ( ! reports || reports.length === 0 ) {
		return <p>{ __( 'No reports found.', 'mhm-rentiva' ) }</p>;
	}

	return (
		<table className="wp-list-table widefat fixed striped mhm-vr-table">
			<thead>
				<tr>
					<th style={ { width: '60px' } }>{ __( 'ID', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Vendor', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Context', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Title', 'mhm-rentiva' ) }</th>
					<th style={ { width: '100px' } }>{ __( 'Status', 'mhm-rentiva' ) }</th>
					<th style={ { width: '130px' } }>{ __( 'Date', 'mhm-rentiva' ) }</th>
					<th style={ { width: '80px' } }>{ __( 'Action', 'mhm-rentiva' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ reports.map( ( r ) => (
					<ReportRow key={ r.id } report={ r } onOpen={ onOpen } />
				) ) }
			</tbody>
		</table>
	);
}
