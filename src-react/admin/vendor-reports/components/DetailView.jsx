import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';
import StatusBadge from './StatusBadge';
import ActionForm  from './ActionForm';

export default function DetailView( { reportId, onBack } ) {
	const [ report,  setReport  ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error,   setError   ] = useState( null );

	useEffect( () => {
		if ( ! reportId ) return;
		setLoading( true );
		setError( null );
		rentivaApi.vendorReports.getDetail( reportId )
			.then( ( res ) => {
				setReport( res.report );
				setLoading( false );
			} )
			.catch( () => {
				setError( __( 'Failed to load report.', 'mhm-rentiva' ) );
				setLoading( false );
			} );
	}, [ reportId ] );

	if ( loading ) return <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p>;
	if ( error )   return <div className="notice notice-error"><p>{ error }</p></div>;
	if ( ! report ) return null;

	return (
		<div>
			<p>
				<button type="button" className="button-link" onClick={ onBack }>
					{ __( '← Back to Reports', 'mhm-rentiva' ) }
				</button>
			</p>

			<div className="mhm-vr-detail">
				<h2>{ report.title }</h2>

				<p className="mhm-vr-meta">
					<strong>{ __( 'Status:', 'mhm-rentiva' ) }</strong>{ ' ' }
					<StatusBadge status={ report.status } label={ report.status_label } />
					{ ' · ' }
					<strong>{ __( 'Vendor:', 'mhm-rentiva' ) }</strong>{ ' ' }
					{ report.vendor_name } ({ report.vendor_email })
					{ ' · ' }
					<strong>{ __( 'Context:', 'mhm-rentiva' ) }</strong>{ ' ' }
					{ report.context_label }
					{ report.context_id ? ` (#${ report.context_id })` : '' }
				</p>

				<p className="mhm-vr-meta">
					<strong>{ __( 'Submitted:', 'mhm-rentiva' ) }</strong>{ ' ' }
					{ report.created_at }
				</p>

				<h3>{ __( 'Description', 'mhm-rentiva' ) }</h3>
				<div className="mhm-vr-description">{ report.description }</div>

				{ report.admin_note && (
					<>
						<h3>{ __( 'Administrator Note', 'mhm-rentiva' ) }</h3>
						<div className="mhm-vr-admin-note">{ report.admin_note }</div>
					</>
				) }

				{ report.is_terminal
					? <p className="mhm-vr-closed-notice">{ __( 'This report is closed. No further action is possible.', 'mhm-rentiva' ) }</p>
					: <ActionForm reportId={ report.id } />
				}
			</div>
		</div>
	);
}
