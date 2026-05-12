import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../../shared/api/rentiva';

export default function VendorTable( { onNotice } ) {
	const [ vendors, setVendors ] = useState( [] );
	const [ total,   setTotal   ] = useState( 0 );
	const [ pages,   setPages   ] = useState( 1 );
	const [ page,    setPage    ] = useState( 1 );
	const [ search,  setSearch  ] = useState( '' );
	const [ status,  setStatus  ] = useState( 'all' );
	const [ loading, setLoading ] = useState( false );
	const [ debouncedSearch, setDebouncedSearch ] = useState( '' );

	// 300ms debounce for search.
	useEffect( () => {
		const t = setTimeout( () => setDebouncedSearch( search ), 300 );
		return () => clearTimeout( t );
	}, [ search ] );

	const fetchVendors = useCallback( () => {
		setLoading( true );
		const params = { page, per_page: 20 };
		if ( debouncedSearch ) params.search = debouncedSearch;
		if ( status !== 'all' ) params.status = status;
		rentivaApi.vendorManagement.getVendors( params )
			.then( ( res ) => {
				setVendors( res.vendors || [] );
				setTotal( res.total || 0 );
				setPages( res.pages || 1 );
				setLoading( false );
			} )
			.catch( () => {
				onNotice( { type: 'error', message: __( 'Failed to load vendors.', 'mhm-rentiva' ) } );
				setLoading( false );
			} );
	}, [ page, debouncedSearch, status, onNotice ] );

	useEffect( () => { fetchVendors(); }, [ fetchVendors ] );

	// Reset to page 1 when filter changes.
	useEffect( () => { setPage( 1 ); }, [ debouncedSearch, status ] );

	const handleAction = ( vendorId, currentStatus ) => {
		const isActive = currentStatus !== 'suspended';
		const label = isActive
			? __( 'Suspend this vendor?', 'mhm-rentiva' )
			: __( 'Unsuspend this vendor? Their vehicles will move to Pending Review.', 'mhm-rentiva' );
		if ( ! window.confirm( label ) ) return; // eslint-disable-line no-alert
		const call = isActive
			? rentivaApi.vendorManagement.suspendVendor( vendorId )
			: rentivaApi.vendorManagement.unsuspendVendor( vendorId );
		call.then( () => {
			const msg = isActive
				? __( 'Vendor suspended.', 'mhm-rentiva' )
				: __( 'Vendor unsuspended.', 'mhm-rentiva' );
			onNotice( { type: 'success', message: msg } );
			fetchVendors();
		} ).catch( () => {
			onNotice( { type: 'error', message: __( 'Action failed.', 'mhm-rentiva' ) } );
		} );
	};

	return (
		<div className="mhm-vm-vendors-tab">
			<div className="mhm-vm-vendor-filters" style={ { display: 'flex', gap: '12px', marginBottom: '16px', flexWrap: 'wrap' } }>
				<input
					type="search"
					className="regular-text"
					placeholder={ __( 'Search vendors…', 'mhm-rentiva' ) }
					value={ search }
					onChange={ ( e ) => setSearch( e.target.value ) }
				/>
				<select
					className="postform"
					value={ status }
					onChange={ ( e ) => setStatus( e.target.value ) }
				>
					<option value="all">{ __( 'All Statuses', 'mhm-rentiva' ) }</option>
					<option value="active">{ __( 'Active', 'mhm-rentiva' ) }</option>
					<option value="suspended">{ __( 'Suspended', 'mhm-rentiva' ) }</option>
				</select>
				<span style={ { alignSelf: 'center', color: '#666' } }>
					{ total } { __( 'vendors', 'mhm-rentiva' ) }
				</span>
			</div>

			{ loading && <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p> }

			{ ! loading && vendors.length === 0 && (
				<p className="mhm-vm-empty">{ __( 'No vendors found.', 'mhm-rentiva' ) }</p>
			) }

			{ ! loading && vendors.length > 0 && (
				<table className="wp-list-table widefat fixed striped mhm-vm-table">
					<thead>
						<tr>
							<th>{ __( 'Name', 'mhm-rentiva' ) }</th>
							<th>{ __( 'Email', 'mhm-rentiva' ) }</th>
							<th style={ { width: '120px' } }>{ __( 'City', 'mhm-rentiva' ) }</th>
							<th style={ { width: '100px' } }>{ __( 'Vehicles', 'mhm-rentiva' ) }</th>
							<th style={ { width: '80px' } }>{ __( 'Score', 'mhm-rentiva' ) }</th>
							<th style={ { width: '100px' } }>{ __( 'Status', 'mhm-rentiva' ) }</th>
							<th style={ { width: '110px' } }>{ __( 'Action', 'mhm-rentiva' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ vendors.map( ( v ) => {
							const isSuspended = v.status === 'suspended';
							return (
								<tr key={ v.id }>
									<td>{ v.display_name }</td>
									<td>{ v.email }</td>
									<td>{ v.city || '—' }</td>
									<td>{ v.vehicle_count }</td>
									<td>{ v.reliability_score || '—' }</td>
									<td>
										<span style={ { color: isSuspended ? '#c62828' : '#2e7d32', fontWeight: 600 } }>
											{ isSuspended ? __( 'Suspended', 'mhm-rentiva' ) : __( 'Active', 'mhm-rentiva' ) }
										</span>
									</td>
									<td>
										<button
											type="button"
											className="button button-small"
											onClick={ () => handleAction( v.id, v.status ) }
										>
											{ isSuspended ? __( 'Unsuspend', 'mhm-rentiva' ) : __( 'Suspend', 'mhm-rentiva' ) }
										</button>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			) }

			{ pages > 1 && (
				<div className="mhm-vm-pagination" style={ { marginTop: '12px', display: 'flex', gap: '8px', alignItems: 'center' } }>
					<button type="button" className="button" disabled={ page <= 1 } onClick={ () => setPage( p => p - 1 ) }>
						{ __( '← Previous', 'mhm-rentiva' ) }
					</button>
					<span>{ page } / { pages }</span>
					<button type="button" className="button" disabled={ page >= pages } onClick={ () => setPage( p => p + 1 ) }>
						{ __( 'Next →', 'mhm-rentiva' ) }
					</button>
				</div>
			) }
		</div>
	);
}
