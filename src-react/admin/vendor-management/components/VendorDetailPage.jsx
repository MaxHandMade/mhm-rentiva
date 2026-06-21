import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

const STATUS_COLORS = { active: '#2e7d32', suspended: '#c62828' };

const LIFECYCLE_LABELS = () => ( {
	active:         __( 'Active', 'mhm-rentiva' ),
	paused:         __( 'Paused', 'mhm-rentiva' ),
	expired:        __( 'Expired', 'mhm-rentiva' ),
	withdrawn:      __( 'Withdrawn', 'mhm-rentiva' ),
	pending_review: __( 'Pending Review', 'mhm-rentiva' ),
} );

export default function VendorDetailPage( { vendorId, onBack } ) {
	const [ vendor,  setVendor  ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error,   setError   ] = useState( null );

	useEffect( () => {
		let active = true;
		setLoading( true );
		setError( null );
		rentivaApi.vendorManagement.getVendorDetail( vendorId )
			.then( ( res ) => { if ( active ) { setVendor( res.vendor ); setLoading( false ); } } )
			.catch( () => { if ( active ) { setError( __( 'Failed to load vendor.', 'mhm-rentiva' ) ); setLoading( false ); } } );
		return () => { active = false; };
	}, [ vendorId ] );

	const lifecycleLabels = LIFECYCLE_LABELS();

	return (
		<div className="mhm-vm-vendor-detail">
			<p>
				<button type="button" className="button" onClick={ onBack }>
					{ __( '← Back to vendors', 'mhm-rentiva' ) }
				</button>
			</p>

			{ loading && <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p> }
			{ error && <div className="notice notice-error"><p>{ error }</p></div> }

			{ ! loading && ! error && vendor && (
				<>
					<h2 style={ { display: 'flex', alignItems: 'center', gap: '12px', marginTop: 0 } }>
						{ vendor.display_name }
						<span style={ {
							fontSize: '13px',
							fontWeight: 600,
							color: STATUS_COLORS[ vendor.status ] || '#555',
						} }>
							{ vendor.status === 'suspended'
								? __( 'Suspended', 'mhm-rentiva' )
								: __( 'Active', 'mhm-rentiva' ) }
						</span>
					</h2>

					<table className="widefat striped" style={ { maxWidth: '640px', marginBottom: '24px' } }>
						<tbody>
							<tr><th style={ { width: '180px' } }>{ __( 'Email', 'mhm-rentiva' ) }</th><td>{ vendor.email || '—' }</td></tr>
							<tr><th>{ __( 'Phone', 'mhm-rentiva' ) }</th><td>{ vendor.phone || '—' }</td></tr>
							<tr><th>{ __( 'City', 'mhm-rentiva' ) }</th><td>{ vendor.city || '—' }</td></tr>
							<tr><th>{ __( 'Reliability Score', 'mhm-rentiva' ) }</th><td>{ vendor.reliability_score ?? '—' }</td></tr>
							<tr><th>{ __( 'IBAN', 'mhm-rentiva' ) }</th><td>{ vendor.iban_masked || '—' }</td></tr>
							<tr><th>{ __( 'Approved At', 'mhm-rentiva' ) }</th><td>{ vendor.approved_at || '—' }</td></tr>
						</tbody>
					</table>

					<h3>{ __( 'Vehicles', 'mhm-rentiva' ) }</h3>
					{ vendor.vehicles && vendor.vehicles.length > 0 ? (
						<table className="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th>{ __( 'Title', 'mhm-rentiva' ) }</th>
									<th style={ { width: '120px' } }>{ __( 'Post Status', 'mhm-rentiva' ) }</th>
									<th style={ { width: '140px' } }>{ __( 'Lifecycle', 'mhm-rentiva' ) }</th>
									<th style={ { width: '80px' } }>{ __( 'Edit', 'mhm-rentiva' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ vendor.vehicles.map( ( v ) => (
									<tr key={ v.id }>
										<td>{ v.title || `#${ v.id }` }</td>
										<td>{ v.status }</td>
										<td>{ lifecycleLabels[ v.lifecycle ] || v.lifecycle }</td>
										<td>
											{ v.edit_link
												? <a href={ v.edit_link }>{ __( 'Edit', 'mhm-rentiva' ) }</a>
												: '—' }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) : (
						<p className="mhm-vm-empty">{ __( 'This vendor has no vehicles.', 'mhm-rentiva' ) }</p>
					) }
				</>
			) }
		</div>
	);
}
