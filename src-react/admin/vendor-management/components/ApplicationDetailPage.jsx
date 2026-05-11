import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';
import ApproveForm from './ApproveForm';
import RejectForm  from './RejectForm';

export default function ApplicationDetailPage( { applicationId, onBack, onActionSuccess } ) {
	const [ app,         setApp         ] = useState( null );
	const [ loading,     setLoading     ] = useState( true );
	const [ error,       setError       ] = useState( null );
	const [ inlineError, setInlineError ] = useState( null );

	useEffect( () => {
		if ( ! applicationId ) return;
		setLoading( true );
		setError( null );
		rentivaApi.vendorManagement.getApplication( applicationId )
			.then( ( res ) => { setApp( res.application ); setLoading( false ); } )
			.catch( () => { setError( __( 'Failed to load application.', 'mhm-rentiva' ) ); setLoading( false ); } );
	}, [ applicationId ] );

	if ( loading ) return <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p>;
	if ( error )   return <div className="notice notice-error"><p>{ error }</p></div>;
	if ( ! app )   return null;

	const handleSuccess = ( message ) => onActionSuccess( message );
	const handleError   = ( message ) => setInlineError( message );

	return (
		<div>
			<p>
				<button type="button" className="button-link" onClick={ onBack }>
					&larr; { __( 'Back to applications', 'mhm-rentiva' ) }
				</button>
			</p>

			{ inlineError && (
				<div className="notice notice-error is-dismissible">
					<p>{ inlineError }</p>
					<button type="button" className="notice-dismiss" onClick={ () => setInlineError( null ) } />
				</div>
			) }

			<div className="mhm-vm-detail">
				<h2>
					{ /* translators: %s: applicant name */ }
					{ sprintf( __( 'Application: %s', 'mhm-rentiva' ), app.applicant_name ) }
				</h2>

				<table className="mhm-vm-meta-table">
					<tbody>
						<tr><th>{ __( 'Full Name', 'mhm-rentiva' ) }</th><td>{ app.applicant_name }</td></tr>
						<tr><th>{ __( 'Email', 'mhm-rentiva' ) }</th><td>{ app.applicant_email }</td></tr>
						<tr><th>{ __( 'Phone', 'mhm-rentiva' ) }</th><td>{ app.phone || '—' }</td></tr>
						<tr><th>{ __( 'City', 'mhm-rentiva' ) }</th><td>{ app.city || '—' }</td></tr>
						<tr><th>{ __( 'Account Holder', 'mhm-rentiva' ) }</th><td>{ app.account_holder || '—' }</td></tr>
						<tr><th>{ __( 'IBAN (masked)', 'mhm-rentiva' ) }</th><td><code>{ app.iban_masked }</code></td></tr>
						<tr><th>{ __( 'Tax Office', 'mhm-rentiva' ) }</th><td>{ app.tax_office || '—' }</td></tr>
						<tr><th>{ __( 'Tax Number', 'mhm-rentiva' ) }</th><td>{ app.tax_number || '—' }</td></tr>
						<tr>
							<th style={ { verticalAlign: 'top' } }>{ __( 'Bio', 'mhm-rentiva' ) }</th>
							<td style={ { whiteSpace: 'pre-wrap' } }>{ app.bio || '—' }</td>
						</tr>
						<tr><th>{ __( 'Applied', 'mhm-rentiva' ) }</th><td>{ app.applied_date }</td></tr>
					</tbody>
				</table>

				<h3>{ __( 'Documents', 'mhm-rentiva' ) }</h3>
				<table className="widefat fixed mhm-vm-docs-table">
					<tbody>
						{ Object.entries( app.documents ).map( ( [ key, doc ] ) => (
							<tr key={ key }>
								<th style={ { width: '200px' } }>{ doc.label }</th>
								<td>
									{ doc.url
										? <a href={ doc.url } target="_blank" rel="noreferrer">{ __( 'View', 'mhm-rentiva' ) }</a>
										: <em>{ __( 'Not uploaded', 'mhm-rentiva' ) }</em>
									}
								</td>
							</tr>
						) ) }
					</tbody>
				</table>

				<div className="mhm-vm-actions">
					<ApproveForm applicationId={ app.id } onSuccess={ handleSuccess } onError={ handleError } />
					<RejectForm  applicationId={ app.id } onSuccess={ handleSuccess } onError={ handleError } />
				</div>
			</div>
		</div>
	);
}
