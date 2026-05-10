import { useState, useEffect, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';
import FilterBar    from './components/FilterBar';
import MessageTable from './components/MessageTable';
import Pagination   from './components/Pagination';
import BulkBar      from './components/BulkBar';
import ThreadView   from './components/ThreadView';
import SettingsView from './components/SettingsView';

function getInitialView() {
	const p = new URLSearchParams( window.location.search );
	if ( p.get( 'tab' ) === 'settings' ) return 'settings';
	if ( p.get( 'id' ) )                 return 'thread';
	return 'list';
}

function getInitialMessageId() {
	return parseInt( new URLSearchParams( window.location.search ).get( 'id' ) ?? '0', 10 ) || null;
}

export default function MessagesPage() {
	const [ view,      setView ]      = useState( getInitialView );
	const [ messageId, setMessageId ] = useState( getInitialMessageId );

	const [ page,     setPage ]     = useState( 1 );
	const [ filters,  setFilters ]  = useState( { search: '', status: '', category: '', priority: '' } );
	const [ selected, setSelected ] = useState( [] );
	const [ data,     setData ]     = useState( null );
	const [ loading,  setLoading ]  = useState( false );
	const [ error,    setError ]    = useState( null );

	const [ deletedNotice, setDeletedNotice ] = useState( () => {
		const d = new URLSearchParams( window.location.search ).get( 'deleted' );
		return d !== null ? parseInt( d, 10 ) : null;
	} );

	const fetchList = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const params = { page, per_page: 20, ...filters };
			Object.keys( params ).forEach( ( k ) => { if ( params[ k ] === '' ) delete params[ k ]; } );
			const result = await rentivaApi.messages.getList( params );
			setData( result );
		} catch {
			setError( __( 'Failed to load messages.', 'mhm-rentiva' ) );
		} finally {
			setLoading( false );
		}
	}, [ page, filters ] );

	useEffect( () => {
		if ( view === 'list' ) fetchList();
	}, [ view, fetchList ] );

	const handleFilterChange = ( patch ) => {
		setFilters( ( prev ) => ( { ...prev, ...patch } ) );
		setPage( 1 );
		setSelected( [] );
	};

	const openThread = ( id ) => {
		setMessageId( id );
		setView( 'thread' );
		history.pushState( null, '', `?page=mhm-rentiva-messages&id=${ id }` );
	};

	const goBack = () => {
		setView( 'list' );
		setMessageId( null );
		history.pushState( null, '', '?page=mhm-rentiva-messages' );
	};

	const openSettings = ( e ) => {
		e.preventDefault();
		setView( 'settings' );
		history.pushState( null, '', '?page=mhm-rentiva-messages&tab=settings' );
	};

	if ( view === 'thread' && messageId ) {
		return <ThreadView messageId={ messageId } onBack={ goBack } />;
	}

	if ( view === 'settings' ) {
		return <SettingsView />;
	}

	return (
		<div>
			{ /* Tab bar */ }
			<div style={ { marginBottom: 12 } }>
				<a
					href="?page=mhm-rentiva-messages"
					className="button button-secondary"
					onClick={ ( e ) => { e.preventDefault(); goBack(); } }
					style={ { marginRight: 4 } }
				>
					{ __( 'Messages', 'mhm-rentiva' ) }
				</a>
				<a
					href="?page=mhm-rentiva-messages&tab=settings"
					className="button button-secondary"
					onClick={ openSettings }
				>
					{ __( 'Settings', 'mhm-rentiva' ) }
				</a>
			</div>

			{ deletedNotice !== null && (
				<div className="notice notice-success">
					<p>
						{ deletedNotice > 0
							? `${ deletedNotice } ${ __( 'message(s) deleted.', 'mhm-rentiva' ) }`
							: __( 'No messages were deleted.', 'mhm-rentiva' )
						}
					</p>
				</div>
			) }

			<FilterBar filters={ filters } onChange={ handleFilterChange } />

			<BulkBar
				selected={ selected }
				onDone={ () => { setSelected( [] ); fetchList(); } }
			/>

			{ loading && <Spinner /> }
			{ error   && <p className="mhm-error">{ error }</p> }

			{ ! loading && (
				<MessageTable
					items={ data?.messages ?? [] }
					selected={ selected }
					onSelect={ setSelected }
					onSelectAll={ setSelected }
					onRowClick={ openThread }
				/>
			) }

			<Pagination
				page={ page }
				totalPages={ data?.pages ?? 1 }
				loading={ loading }
				onPageChange={ ( p ) => { setPage( p ); setSelected( [] ); } }
			/>
		</div>
	);
}
