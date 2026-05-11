import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function IbanRequestRow( { request, onSuccess, onError } ) {
	const [ busy, setBusy ] = useState( false );

	const handleApprove = () => {
		if ( ! window.confirm( __( 'Approve this new IBAN? The vendor will receive payouts to this new account.', 'mhm-rentiva' ) ) ) return;
		setBusy( true );
		rentivaApi.vendorManagement.approveIban( request.vendor_id )
			.then( () => onSuccess( __( 'IBAN request approved and updated.', 'mhm-rentiva' ) ) )
			.catch( () => { onError( __( 'IBAN approval failed.', 'mhm-rentiva' ) ); setBusy( false ); } );
	};

	const handleReject = () => {
		if ( ! window.confirm( __( 'Reject this IBAN request? The vendor will continue using their old IBAN.', 'mhm-rentiva' ) ) ) return;
		setBusy( true );
		rentivaApi.vendorManagement.rejectIban( request.vendor_id )
			.then( () => onSuccess( __( 'IBAN request rejected.', 'mhm-rentiva' ) ) )
			.catch( () => { onError( __( 'IBAN rejection failed.', 'mhm-rentiva' ) ); setBusy( false ); } );
	};

	return (
		<tr>
			<td>
				<strong>{ request.vendor_name }</strong>
				<br />
				<small>{ request.vendor_email }</small>
			</td>
			<td><code className="mhm-vm-iban-table current">{ request.current_iban_masked }</code></td>
			<td><code className="mhm-vm-iban-table pending">{ request.pending_iban_masked }</code></td>
			<td>
				<div style={ { display: 'flex', gap: '8px' } }>
					<button
						type="button"
						className="button button-primary button-small"
						onClick={ handleApprove }
						disabled={ busy }
					>
						{ __( 'Approve', 'mhm-rentiva' ) }
					</button>
					<button
						type="button"
						className="button button-small"
						style={ { color: '#c62828', borderColor: '#c62828' } }
						onClick={ handleReject }
						disabled={ busy }
					>
						{ __( 'Reject', 'mhm-rentiva' ) }
					</button>
				</div>
			</td>
		</tr>
	);
}
