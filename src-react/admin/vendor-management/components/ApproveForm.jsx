import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function ApproveForm( { applicationId, onSuccess, onError } ) {
	const [ busy, setBusy ] = useState( false );

	const handleApprove = () => {
		if ( ! window.confirm( __( 'Approve this vendor application?', 'mhm-rentiva' ) ) ) return;
		setBusy( true );
		rentivaApi.vendorManagement.approveApplication( applicationId )
			.then( () => onSuccess( __( 'Vendor application approved. The vendor has been notified.', 'mhm-rentiva' ) ) )
			.catch( ( err ) => { onError( err?.message || __( 'Approval failed. Please try again.', 'mhm-rentiva' ) ); setBusy( false ); } );
	};

	return (
		<div className="mhm-vm-action-block">
			<h3 className="approve">{ __( 'Approve Application', 'mhm-rentiva' ) }</h3>
			<p>{ __( 'This will assign the vendor role and notify the applicant.', 'mhm-rentiva' ) }</p>
			<button
				type="button"
				className="button button-primary"
				onClick={ handleApprove }
				disabled={ busy }
			>
				{ busy ? __( 'Processing…', 'mhm-rentiva' ) : __( 'Approve & Activate Vendor', 'mhm-rentiva' ) }
			</button>
		</div>
	);
}
