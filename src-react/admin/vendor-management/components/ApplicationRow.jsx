import { __ } from '@wordpress/i18n';

export default function ApplicationRow( { app, onOpen } ) {
	return (
		<tr onClick={ () => onOpen( app.id ) }>
			<td><strong>{ app.applicant_name }</strong></td>
			<td>{ app.applicant_email }</td>
			<td>{ app.city || '—' }</td>
			<td>{ app.applied_human }</td>
			<td>
				<button type="button" className="button button-small" onClick={ () => onOpen( app.id ) }>
					{ __( 'Review', 'mhm-rentiva' ) }
				</button>
			</td>
		</tr>
	);
}
