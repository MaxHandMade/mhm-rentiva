import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { rentivaApi } from '../../shared/api/rentiva';
import StatsBar from './components/StatsBar';
import SystemActions from './components/SystemActions';
import ShortcodeTable from './components/ShortcodeTable';
import DebugPanel from './components/DebugPanel';
import ConfirmModal from './components/ConfirmModal';

export default function ShortcodePagesPage() {
	const [ shortcodes,   setShortcodes   ] = useState( [] );
	const [ stats,        setStats        ] = useState( null );
	const [ query,        setQuery        ] = useState( '' );
	const [ loading,      setLoading      ] = useState( true );
	const [ error,        setError        ] = useState( null );
	const [ pendingSlugs, setPendingSlugs ] = useState( new Set() );
	const [ debugData,    setDebugData    ] = useState( null );
	const [ debugLoading, setDebugLoading ] = useState( false );
	const [ modal,        setModal        ] = useState( null );

	useEffect( () => { fetchList(); }, [] );

	const fetchList = () => {
		setLoading( true );
		rentivaApi.shortcodePages.getList()
			.then( ( { shortcodes: scs, stats: st } ) => {
				setShortcodes( scs );
				setStats( st );
				setError( null );
			} )
			.catch( () => setError( __( 'Failed to load shortcode pages.', 'mhm-rentiva' ) ) )
			.finally( () => setLoading( false ) );
	};

	const addPending  = ( slug ) => setPendingSlugs( ( prev ) => new Set( prev ).add( slug ) );
	const dropPending = ( slug ) => setPendingSlugs( ( prev ) => {
		const next = new Set( prev );
		next.delete( slug );
		return next;
	} );

	const handleCreate = ( slug ) => {
		addPending( slug );
		rentivaApi.shortcodePages.createPage( slug )
			.then( ( data ) => {
				setShortcodes( ( prev ) => prev.map( ( s ) => s.slug === slug ? { ...s, ...data } : s ) );
				setStats( ( prev ) => ( { ...prev, active: prev.active + 1, missing: prev.missing - 1 } ) );
			} )
			.catch( () => {} )
			.finally( () => dropPending( slug ) );
	};

	const handleDelete = ( slug ) => {
		setModal( {
			title:        __( 'Remove Page', 'mhm-rentiva' ),
			message:      __( 'Move this page to trash?', 'mhm-rentiva' ),
			confirmLabel: __( 'Remove', 'mhm-rentiva' ),
			destructive:  false,
			onConfirm:    () => doDelete( slug ),
		} );
	};

	const doDelete = ( slug ) => {
		setModal( null );
		addPending( slug );
		rentivaApi.shortcodePages.deletePage( slug )
			.then( () => {
				setShortcodes( ( prev ) => prev.map( ( s ) => s.slug === slug
					? { ...s, page_id: null, page_title: null, page_url: null, edit_url: null, status: 'missing' }
					: s
				) );
				setStats( ( prev ) => ( { ...prev, active: prev.active - 1, missing: prev.missing + 1 } ) );
			} )
			.catch( () => {} )
			.finally( () => dropPending( slug ) );
	};

	const handleClearCache = () => {
		setModal( {
			title:        __( 'Clear Cache', 'mhm-rentiva' ),
			message:      __( 'Cache will be cleared. Do you want to continue?', 'mhm-rentiva' ),
			confirmLabel: __( 'Clear', 'mhm-rentiva' ),
			destructive:  false,
			onConfirm:    () => {
				setModal( null );
				rentivaApi.shortcodePages.clearCache()
					.then( () => fetchList() )
					.catch( () => {} );
			},
		} );
	};

	const handleDebug = () => {
		setDebugLoading( true );
		rentivaApi.shortcodePages.debug()
			.then( ( data ) => setDebugData( data ) )
			.catch( () => {} )
			.finally( () => setDebugLoading( false ) );
	};

	const handleReset = () => {
		setModal( {
			title:        __( 'Reset All Pages', 'mhm-rentiva' ),
			message:      __( 'This will permanently delete all created shortcode pages. Are you sure?', 'mhm-rentiva' ),
			confirmLabel: __( 'Delete All', 'mhm-rentiva' ),
			destructive:  true,
			onConfirm:    () => {
				setModal( null );
				rentivaApi.shortcodePages.reset()
					.then( () => fetchList() )
					.catch( () => {} );
			},
		} );
	};

	if ( loading ) {
		return <span className="spinner is-active" style={ { float: 'none' } } />;
	}
	if ( error ) {
		return <p className="mhm-error">{ error }</p>;
	}

	const needle   = query.trim().toLowerCase();
	const filtered = needle
		? shortcodes.filter( ( s ) =>
			[ s.slug, s.label, s.description, s.page_title ]
				.some( ( v ) => ( v || '' ).toLowerCase().includes( needle ) ) )
		: shortcodes;

	return (
		<div className="rv-scp">
			<StatsBar stats={ stats } />
			<SystemActions
				onClearCache={ handleClearCache }
				onDebug={ handleDebug }
				onReset={ handleReset }
				debugLoading={ debugLoading }
				query={ query }
				onQueryChange={ setQuery }
			/>
			<ShortcodeTable
				shortcodes={ filtered }
				pendingSlugs={ pendingSlugs }
				onCreate={ handleCreate }
				onDelete={ handleDelete }
			/>
			{ debugData && (
				<DebugPanel data={ debugData } onClose={ () => setDebugData( null ) } />
			) }
			<ConfirmModal modal={ modal } onCancel={ () => setModal( null ) } />
		</div>
	);
}
