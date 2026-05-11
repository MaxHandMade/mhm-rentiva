import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';
import ApplicationTable from './components/ApplicationTable';
import ApplicationDetailPage from './components/ApplicationDetailPage';
import IbanRequestsTab from './components/IbanRequestsTab';

const config = window.mhmRentivaVendorManagement || {};

export default function VendorManagementPage() {
	const [ tab,    setTab    ] = useState( config.initialTab  || 'pending' );
	const [ viewId, setViewId ] = useState( parseInt( config.initialView, 10 ) || 0 );
	const [ notice, setNotice ] = useState( config.flash || null );

	// Flash from PHP (read before WP's common.js strips URL params).
	useEffect( () => {
		if ( config.flash ) {
			setNotice( config.flash );
		}
	}, [] );

	const switchTab = ( newTab ) => {
		setTab( newTab );
		setViewId( 0 );
		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', newTab );
		url.searchParams.delete( 'view' );
		history.pushState( {}, '', url.toString() );
	};

	const openDetail = ( id ) => {
		setViewId( id );
		const url = new URL( window.location.href );
		url.searchParams.set( 'view', id );
		history.pushState( {}, '', url.toString() );
	};

	const closeDetail = () => {
		setViewId( 0 );
		const url = new URL( window.location.href );
		url.searchParams.delete( 'view' );
		history.pushState( {}, '', url.toString() );
	};

	const handleActionSuccess = ( message ) => {
		setNotice( { type: 'success', message } );
		closeDetail();
		fetchPending();
	};

	const [ listData,    setListData    ] = useState( null );
	const [ listLoading, setListLoading ] = useState( false );
	const [ listError,   setListError   ] = useState( null );
	const [ page,        setPage        ] = useState( 1 );
	const PER_PAGE = 20;

	const fetchPending = useCallback( () => {
		setListLoading( true );
		setListError( null );
		rentivaApi.vendorManagement.getApplications( { page, per_page: PER_PAGE } )
			.then( ( res ) => { setListData( res ); setListLoading( false ); } )
			.catch( () => { setListError( __( 'Failed to load applications.', 'mhm-rentiva' ) ); setListLoading( false ); } );
	}, [ page ] );

	useEffect( () => {
		if ( tab === 'pending' && viewId === 0 ) {
			fetchPending();
		}
	}, [ fetchPending, tab, viewId ] );

	return (
		<div className="mhm-vm-app">
			{ notice && (
				<div className={ `notice notice-${ notice.type } is-dismissible` }>
					<p>{ notice.message }</p>
					<button type="button" className="notice-dismiss" onClick={ () => setNotice( null ) } />
				</div>
			) }

{ viewId > 0
				? <ApplicationDetailPage
					applicationId={ viewId }
					onBack={ closeDetail }
					onActionSuccess={ handleActionSuccess }
				/>
				: tab === 'iban_requests'
					? <IbanRequestsTab onNotice={ setNotice } />
					: (
					<>
						{ listLoading && <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p> }
						{ listError   && <div className="notice notice-error"><p>{ listError }</p></div> }
						{ ! listLoading && ! listError && (
							<ApplicationTable applications={ listData?.applications } onOpen={ openDetail } />
						) }
						{ listData && listData.pages > 1 && (
							<div className="mhm-vm-pagination">
								{ page > 1 && (
									<button type="button" className="button" onClick={ () => setPage( p => p - 1 ) }>
										{ __( '← Previous', 'mhm-rentiva' ) }
									</button>
								) }
								<span>{ page } / { listData.pages }</span>
								{ page < listData.pages && (
									<button type="button" className="button" onClick={ () => setPage( p => p + 1 ) }>
										{ __( 'Next →', 'mhm-rentiva' ) }
									</button>
								) }
							</div>
						) }
					</>
				)
			}
		</div>
	);
}
