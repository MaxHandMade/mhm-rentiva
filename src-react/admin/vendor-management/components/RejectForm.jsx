import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function RejectForm( { applicationId, onSuccess, onError } ) {
	const [ reason, setReason ] = useState( '' );
	const [ busy,   setBusy   ] = useState( false );

	const handleReject = () => {
		if ( reason.trim() === '' ) {
			onError( __( 'Rejection reason is required.', 'mhm-rentiva' ) );
			return;
		}
		setBusy( true );
		rentivaApi.vendorManagement.rejectApplication( applicationId, reason )
			.then( () => onSuccess( __( 'Vendor application rejected.', 'mhm-rentiva' ) ) )
			.catch( ( err ) => { onError( err?.message || __( 'Rejection failed. Please try again.', 'mhm-rentiva' ) ); setBusy( false ); } );
	};

	return (
		<div className="mhm-vm-action-block">
			<h3 className="reject">{ __( 'Reject Application', 'mhm-rentiva' ) }</h3>
			<label htmlFor="mhm-vm-reject-reason">
				<strong>{ __( 'Rejection Reason (required):', 'mhm-rentiva' ) }</strong>
			</label>
			<textarea
				id="mhm-vm-reject-reason"
				rows={ 4 }
				style={ { width: '100%', maxWidth: '400px', marginTop: '6px', display: 'block' } }
				placeholder={ __( 'Explain why this application is being rejected…', 'mhm-rentiva' ) }
				value={ reason }
				onChange={ ( e ) => setReason( e.target.value ) }
				required
			/>
			<br />
			<button
				type="button"
				className="button button-secondary"
				onClick={ handleReject }
				disabled={ busy || reason.trim() === '' }
			>
				{ busy ? __( 'Processing…', 'mhm-rentiva' ) : __( 'Reject Application', 'mhm-rentiva' ) }
			</button>
		</div>
	);
}
