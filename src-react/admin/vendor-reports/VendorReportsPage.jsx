import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ }      from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';

export default function VendorReportsPage() {
	// Routing
	const [ view,     setView     ] = useState( 'list' );
	const [ reportId, setReportId ] = useState( null );

	// List filters
	const [ status,  setStatus  ] = useState( 'open' );
	const [ context, setContext ] = useState( '' );
	const [ page,    setPage    ] = useState( 1 );

	// List data
	const [ data,    setData    ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error,   setError   ] = useState( null );

	// Notices (from redirect params)
	const [ notice, setNotice ] = useState( null );

	const fetchList = useCallback( () => {
		setLoading( true );
		setError( null );
		rentivaApi.vendorReports.getList( { status, context_type: context, page, per_page: 20 } )
			.then( ( res ) => {
				setData( res );
				setLoading( false );
			} )
			.catch( () => {
				setError( __( 'Failed to load reports.', 'mhm-rentiva' ) );
				setLoading( false );
			} );
	}, [ status, context, page ] );

	useEffect( () => {
		if ( view === 'list' ) {
			fetchList();
		}
	}, [ fetchList, view ] );

	// Read URL params on mount
	useEffect( () => {
		const params = new URLSearchParams( window.location.search );
		const viewId = parseInt( params.get( 'view' ) || '0', 10 );

		if ( viewId > 0 ) {
			setView( 'detail' );
			setReportId( viewId );
		}

		if ( params.get( 'updated' ) === '1' ) {
			setNotice( { type: 'success', message: __( 'Report updated.', 'mhm-rentiva' ) } );
			const clean = new URL( window.location.href );
			clean.searchParams.delete( 'updated' );
			history.replaceState( {}, '', clean.toString() );
		}

		if ( params.get( 'error' ) === '1' ) {
			setNotice( { type: 'error', message: __( 'An error occurred. Please try again.', 'mhm-rentiva' ) } );
			const clean = new URL( window.location.href );
			clean.searchParams.delete( 'error' );
			history.replaceState( {}, '', clean.toString() );
		}
	}, [] );

	const handleFilter = ( { status: s, context: c } ) => {
		setStatus( s );
		setContext( c );
		setPage( 1 );
	};

	const handleOpenReport = ( id ) => {
		setView( 'detail' );
		setReportId( id );
		const url = new URL( window.location.href );
		url.searchParams.set( 'view', id );
		history.pushState( {}, '', url.toString() );
	};

	const handleBack = () => {
		setView( 'list' );
		setReportId( null );
		const url = new URL( window.location.href );
		url.searchParams.delete( 'view' );
		history.pushState( {}, '', url.toString() );
		fetchList();
	};

	return (
		<div className="mhm-vendor-reports-app">
			{ notice && (
				<div className={ `notice notice-${ notice.type } is-dismissible` }>
					<p>{ notice.message }</p>
					<button type="button" className="notice-dismiss" onClick={ () => setNotice( null ) } />
				</div>
			) }

			{ view === 'list' && (
				<>
					{ loading && <p>{ __( 'Loading…', 'mhm-rentiva' ) }</p> }
					{ error   && <div className="notice notice-error"><p>{ error }</p></div> }
					{ ! loading && ! error && (
						<p>{ __( 'List view placeholder — components coming next.', 'mhm-rentiva' ) }</p>
					) }
				</>
			) }

			{ view === 'detail' && (
				<p>{ __( 'Detail view placeholder — coming in Task 7.', 'mhm-rentiva' ) } (ID: { reportId })</p>
			) }
		</div>
	);
}
