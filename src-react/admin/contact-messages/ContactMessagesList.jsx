import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import PageHeader from '../../../vendor/mhm/ui-core/src-react/components/PageHeader';
import Tabs from '../../../vendor/mhm/ui-core/src-react/components/Tabs';
import Pagination from '../../../vendor/mhm/ui-core/src-react/components/Pagination';
import Notice from '../../../vendor/mhm/ui-core/src-react/components/Notice';
import ContactStats from './components/ContactStats';
import ContactToolbar from './components/ContactToolbar';
import ContactBulkBar from './components/ContactBulkBar';
import ContactTable from './components/ContactTable';
import { contactApi } from './api';

const PER_PAGE = 20;

export default function ContactMessagesList( { status, initialPage = 1, onOpen, onStatusChange } ) {
	// Read inside the component (house pattern, CustomersPage.jsx:19): a
	// module-level read runs before a test can set the global.
	const pageUrl = window.mhmRentivaContactMessages?.pageUrl ?? '';
	const [ page, setPage ] = useState( initialPage );
	const [ filters, setFilters ] = useState( { search: '', type: '', period: '' } );
	const [ data, setData ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ error, setError ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	// Request-sequence guard: a status change re-creates `load` (it closes over
	// `status`), which re-fires the fetch effect below for the STALE page
	// before the status-change effect gets a chance to reset it to 1 in the
	// same commit -- see the effect ordering note above the status effect.
	// That stale request can come back empty for the new status (e.g. "All"
	// page 3 has no counterpart in "Replied"), and without this guard its
	// `targetPage > 1` fall-back would call setPage() a second, wrong time
	// (landing one page short of 1 instead of on it). Bumping a counter per
	// call and checking it's still the latest AFTER the await -- for both the
	// fall-back and the success path -- makes only the newest request able to
	// touch state; an older one that resolves late is silently dropped.
	const requestSeq = useRef( 0 );

	const load = useCallback( async ( targetPage ) => {
		const seq = ++requestSeq.current;
		setError( null );
		try {
			const params = { page: targetPage, per_page: PER_PAGE, status, ...filters };
			Object.keys( params ).forEach( ( k ) => params[ k ] === '' && delete params[ k ] );
			const result = await contactApi.list( params );
			if ( seq !== requestSeq.current ) {
				return; // A newer request started; this response is stale.
			}
			if ( result.items.length === 0 && targetPage > 1 ) {
				setPage( targetPage - 1 );
				return;
			}
			setData( result );
		} catch {
			if ( seq !== requestSeq.current ) {
				return;
			}
			setError( __( 'Contact messages could not be loaded.', 'mhm-rentiva' ) );
		}
	}, [ status, filters ] );

	useEffect( () => {
		load( page );
	}, [ load, page ] );

	// A status change arrives as a new prop: reset page and selection then.
	// Filter changes reset in changeFilters() below, in the same batch as the
	// filter itself, so one change is one request (Fable plan M6).
	const lastStatus = useRef( status );
	useEffect( () => {
		if ( lastStatus.current !== status ) {
			lastStatus.current = status;
			setSelected( [] );
			setPage( 1 );
		}
	}, [ status ] );

	const changeFilters = ( patch ) => {
		setFilters( ( f ) => ( { ...f, ...patch } ) );
		setSelected( [] );
		setPage( 1 );
	};

	const runBulk = async ( action ) => {
		setBusy( true );
		try {
			const { results } = await contactApi.bulk( selected, action );
			const failed = results.filter( ( r ) => ! r.ok ).length;
			if ( failed > 0 ) {
				setError( sprintf(
					/* translators: %d: number of messages the action could not change. */
					__( '%d message(s) could not be changed.', 'mhm-rentiva' ),
					failed
				) );
			}
			setSelected( [] );
			await load( page );
		} finally {
			setBusy( false );
		}
	};

	const counts = data?.counts ?? { all: 0, new: 0, read: 0, replied: 0, trash: 0 };
	const href = ( s ) => ( s ? `${ pageUrl }&status=${ s }` : pageUrl );
	const tabs = [
		{ id: 'all', label: __( 'All', 'mhm-rentiva' ), href: href( '' ), badge: counts.all },
		{ id: 'new', label: __( 'New', 'mhm-rentiva' ), href: href( 'new' ), badge: counts.new,
			/* translators: %d: number of new messages. */
			badgeLabel: sprintf( __( '%d new', 'mhm-rentiva' ), counts.new ) },
		{ id: 'read', label: __( 'Read', 'mhm-rentiva' ), href: href( 'read' ), badge: counts.read },
		{ id: 'replied', label: __( 'Replied', 'mhm-rentiva' ), href: href( 'replied' ), badge: counts.replied },
	];

	return (
		<div className="mhm-contact-messages">
			<PageHeader
				title={ __( 'Contact Messages', 'mhm-rentiva' ) }
				meta={ __( 'Requests sent through the contact form on your site', 'mhm-rentiva' ) }
				level={ 2 }
			/>
			{ data && <ContactStats stats={ data.stats } /> }
			<div className="mhm-contact-messages__tabs">
				<Tabs
					label={ __( 'Filter by status', 'mhm-rentiva' ) }
					current={ status === 'trash' ? undefined : ( status || 'all' ) }
					items={ tabs }
					onSelect={ ( id, e ) => {
						e.preventDefault();
						onStatusChange( id === 'all' ? '' : id );
					} }
				/>
				<a
					href={ href( 'trash' ) }
					className={
						status === 'trash'
							? 'mhm-contact-messages__trash-link mhm-contact-messages__trash-link--current'
							: 'mhm-contact-messages__trash-link'
					}
					aria-current={ status === 'trash' ? 'page' : undefined }
					onClick={ ( e ) => {
						e.preventDefault();
						onStatusChange( 'trash' );
					} }
				>
					{ sprintf(
						/* translators: %d: number of messages in the trash. */
						__( 'Trash (%d)', 'mhm-rentiva' ),
						counts.trash
					) }
				</a>
			</div>
			{ selected.length > 0 ? (
				<ContactBulkBar
					count={ selected.length }
					inTrash={ status === 'trash' }
					busy={ busy }
					onAction={ runBulk }
					onClear={ () => setSelected( [] ) }
				/>
			) : (
				<ContactToolbar filters={ filters } total={ data?.total ?? 0 } onChange={ changeFilters } />
			) }
			{ error && <Notice tone="danger">{ error }</Notice> }
			{ ! data && ! error && <Spinner /> }
			{ data && data.items.length === 0 && (
				<div className="mhm-contact-messages__empty">
					<strong>
						{ counts.all === 0 && status !== 'trash'
							? __( 'No contact messages yet', 'mhm-rentiva' )
							: __( 'No messages in this view', 'mhm-rentiva' ) }
					</strong>
					{ counts.all === 0 && status !== 'trash' && (
						<p>{ __( 'Add the contact form to a page; every request sent through it is listed here.', 'mhm-rentiva' ) }</p>
					) }
				</div>
			) }
			{ data && data.items.length > 0 && (
				<>
					<div className="mhm-contact-messages__table-wrap">
						<table className="mhm-contact-messages__table">
							<ContactTable
								rows={ data.items }
								selected={ selected }
								onToggle={ ( id ) => setSelected( ( s ) => ( s.includes( id ) ? s.filter( ( x ) => x !== id ) : [ ...s, id ] ) ) }
								onToggleAll={ ( on ) => setSelected( on ? data.items.map( ( r ) => r.id ) : [] ) }
								onOpen={ onOpen }
							/>
						</table>
					</div>
					<Pagination
						page={ page }
						totalPages={ data.pages }
						onChange={ setPage }
						labels={ {
							navigation: __( 'Contact messages pages', 'mhm-rentiva' ),
							previous: __( 'Previous', 'mhm-rentiva' ),
							of: __( 'of', 'mhm-rentiva' ),
							next: __( 'Next', 'mhm-rentiva' ),
						} }
					/>
				</>
			) }
		</div>
	);
}
