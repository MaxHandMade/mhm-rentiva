import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';
import IbanRequestRow from './IbanRequestRow';

export default function IbanRequestsTab( { onNotice } ) {
	const [ requests, setRequests ] = useState( null );
	const [ loading,  setLoading  ] = useState( false );
	const [ error,    setError    ] = useState( null );

	const fetchRequests = useCallback( () => {
		setLoading( true );
		setError( null );
		rentivaApi.vendorManagement.getIbanRequests()
			.then( ( res ) => { setRequests( res.requests ); setLoading( false ); } )
			.catch( () => { setError( __( 'Failed to load IBAN requests.', 'mhm-rentiva' ) ); setLoading( false ); } );
	}, [] );

	useEffect( () => { fetchRequests(); }, [ fetchRequests ] );

	const handleSuccess = ( message ) => {
		onNotice( { type: 'success', message } );
		fetchRequests();
	};

	const handleError = ( message ) => {
		onNotice( { type: 'error', message } );
	};

	if ( loading ) return <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p>;
	if ( error )   return <div className="notice notice-error"><p>{ error }</p></div>;

	if ( ! requests || requests.length === 0 ) {
		return <p className="mhm-vm-empty">{ __( 'No pending IBAN changes.', 'mhm-rentiva' ) }</p>;
	}

	return (
		<table className="widefat fixed striped mhm-vm-iban-table">
			<thead>
				<tr>
					<th>{ __( 'Vendor', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Current IBAN (Masked)', 'mhm-rentiva' ) }</th>
					<th>{ __( 'Requested IBAN', 'mhm-rentiva' ) }</th>
					<th style={ { width: '180px' } }>{ __( 'Actions', 'mhm-rentiva' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ requests.map( ( req ) => (
					<IbanRequestRow
						key={ req.vendor_id }
						request={ req }
						onSuccess={ handleSuccess }
						onError={ handleError }
					/>
				) ) }
			</tbody>
		</table>
	);
}
