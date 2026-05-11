import { __ } from '@wordpress/i18n';
import StatusBadge from './StatusBadge';

export default function ReportRow( { report, onOpen } ) {
	const handleClick = () => onOpen( report.id );

	return (
		<tr onClick={ handleClick }>
			<td>#{ report.id }</td>
			<td>{ report.vendor_name }</td>
			<td>{ report.context_label }</td>
			<td>{ report.title }</td>
			<td><StatusBadge status={ report.status } label={ report.status_label } /></td>
			<td>{ report.created_human }</td>
			<td>
				<button type="button" className="button button-small" onClick={ handleClick }>
					{ __( 'Open', 'mhm-rentiva' ) }
				</button>
			</td>
		</tr>
	);
}
