import { __ } from '@wordpress/i18n';
import ApplicationRow from './ApplicationRow';

export default function ApplicationTable( { applications, onOpen } ) {
	if ( ! applications || applications.length === 0 ) {
		return <p className="mhm-vm-empty">{ __( 'No pending vendor applications.', 'mhm-rentiva' ) }</p>;
	}

	return (
		<table className="wp-list-table widefat fixed striped mhm-vm-table">
			<thead>
				<tr>
					<th>{ __( 'Applicant', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Email', 'mhm-rentiva' ) }</th>
					<th style={ { width: '140px' } }>{ __( 'City', 'mhm-rentiva' ) }</th>
					<th style={ { width: '140px' } }>{ __( 'Applied', 'mhm-rentiva' ) }</th>
					<th style={ { width: '100px' } }>{ __( 'Actions', 'mhm-rentiva' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ applications.map( ( app ) => (
					<ApplicationRow key={ app.id } app={ app } onOpen={ onOpen } />
				) ) }
			</tbody>
		</table>
	);
}
