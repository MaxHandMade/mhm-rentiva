import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import Tabs from '../../../vendor/mhm/ui-core/src-react/components/Tabs';
import Button from '../../../vendor/mhm/ui-core/src-react/components/Button';
import Pagination from '../../../vendor/mhm/ui-core/src-react/components/Pagination';
import Notice from '../../../vendor/mhm/ui-core/src-react/components/Notice';
import ContactStats from './components/ContactStats';
import ContactToolbar from './components/ContactToolbar';
import ContactBulkBar from './components/ContactBulkBar';
import ContactTable from './components/ContactTable';
import { contactApi } from './api';
import { isTrashEnabled } from './trash';

const PER_PAGE = 20;

export default function ContactMessagesList( { status, initialPage = 1, initialSearch = '', onOpen, onStatusChange } ) {
	// Read inside the component (house pattern, CustomersPage.jsx:19): a
	// module-level read runs before a test can set the global.
	const pageUrl = window.mhmRentivaContactMessages?.pageUrl ?? '';
	const trashEnabled = isTrashEnabled();
	const [ page, setPage ] = useState( initialPage );
	const [ filters, setFilters ] = useState( { search: initialSearch, type: '', period: '' } );
	const [ data, setData ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ error, setError ] = useState( null );
	// Kept apart from `error`: load() clears that one first thing, and the
	// reload that follows a bulk call would wipe the partial-failure notice
	// before anyone could read it.
	const [ bulkNotice, setBulkNotice ] = useState( null );
	// Bumped on every status/filter/page change the operator makes: a bulk
	// result that comes back after they moved to another view must not post
	// its notice or reload the old view there.
	const viewSeq = useRef( 0 );
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
	// filter itself, so one change is one request.
	const lastStatus = useRef( status );
	useEffect( () => {
		if ( lastStatus.current !== status ) {
			lastStatus.current = status;
			viewSeq.current++;
			setSelected( [] );
			setBulkNotice( null );
			setPage( 1 );
		}
	}, [ status ] );

	const changePage = ( next ) => {
		viewSeq.current++;
		// Like a status/filter change: the selection belongs to the rows the
		// operator is leaving, and a retired bulk result no longer clears it.
		setSelected( [] );
		setPage( next );
	};

	const changeFilters = ( patch ) => {
		viewSeq.current++;
		setFilters( ( f ) => ( { ...f, ...patch } ) );
		setSelected( [] );
		setBulkNotice( null );
		setPage( 1 );
	};

	const runBulk = async ( action ) => {
		setBusy( true );
		setBulkNotice( null );
		const view = viewSeq.current;
		try {
			const { results } = await contactApi.bulk( selected, action );
			if ( view !== viewSeq.current ) {
				// The operator moved on: this closure's load() would fetch the
				// old view and, being the newest request, overwrite the new one.
				return;
			}
			const failed = results.filter( ( r ) => ! r.ok ).length;
			if ( failed > 0 ) {
				setBulkNotice( sprintf(
					/* translators: %d: number of messages the action could not change. */
					_n( '%d message could not be changed.', '%d messages could not be changed.', failed, 'mhm-rentiva' ),
					failed
				) );
			}
			setSelected( [] );
			await load( page );
		} catch {
			if ( view === viewSeq.current ) {
				setError( __( 'The bulk action could not be completed.', 'mhm-rentiva' ) );
			}
		} finally {
			setBusy( false );
		}
	};

	const counts = data?.counts ?? { all: 0, new: 0, read: 0, replied: 0, trash: 0 };
	const href = ( s ) => ( s ? `${ pageUrl }&status=${ s }` : pageUrl );
	const tabs = [
		{ id: 'all', label: __( 'All', 'mhm-rentiva' ), href: href( '' ), badge: counts.all },
		{ id: 'new', label: __( 'New', 'mhm-rentiva' ), href: href( 'new' ), badge: counts.new, badgeTone: counts.new > 0 ? 'warning' : undefined,
			/* translators: %d: number of new messages. */
			badgeLabel: sprintf( __( '%d new', 'mhm-rentiva' ), counts.new ) },
		{ id: 'read', label: __( 'Read', 'mhm-rentiva' ), href: href( 'read' ), badge: counts.read },
		{ id: 'replied', label: __( 'Replied', 'mhm-rentiva' ), href: href( 'replied' ), badge: counts.replied },
	];

	const firstRun = counts.all === 0 && status !== 'trash';
	const shortcodePagesUrl = window.mhmRentivaContactMessages?.shortcodePagesUrl ?? '';
	const total = data?.total ?? 0;
	const from = total === 0 ? 0 : ( page - 1 ) * PER_PAGE + 1;
	const to = Math.min( page * PER_PAGE, total );
	const summary = total === 0
		? sprintf(
			/* translators: %d: number of messages (always 0 here). */
			_n( '%d message', '%d messages', 0, 'mhm-rentiva' ),
			0
		)
		: sprintf(
			/* translators: 1: first message number on this page, 2: last message number on this page, 3: total number of messages. */
			_n( '%1$d–%2$d of %3$d message', '%1$d–%2$d of %3$d messages', total, 'mhm-rentiva' ),
			from,
			to,
			total
		);

	return (
		<div className="mhm-contact-messages mhmui-admin mhmui-admin-page">
			{ data && <ContactStats stats={ data.stats } /> }
			<div className="mhmui-tabs-bar">
				<Tabs
					label={ __( 'Filter by status', 'mhm-rentiva' ) }
					current={ status === 'trash' ? undefined : ( status || 'all' ) }
					items={ tabs }
					variant="underline"
					showZero
					onSelect={ ( id, e ) => {
						e.preventDefault();
						onStatusChange( id === 'all' ? '' : id );
					} }
				/>
				{ trashEnabled && (
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
				) }
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
			{ bulkNotice && <Notice tone="warning">{ bulkNotice }</Notice> }
			{ ! data && ! error && <Spinner /> }
			{ data && (
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
					{ data.items.length === 0 && (
						<div className="mhm-contact-messages__empty">
							<svg aria-hidden="true" focusable="false" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
								<rect x="3" y="5.5" width="18" height="13" rx="2" />
								<path d="M3.5 7l8.5 6 8.5-6" />
							</svg>
							<strong>
								{ firstRun
									? __( 'No contact messages yet', 'mhm-rentiva' )
									: __( 'No messages in this view', 'mhm-rentiva' ) }
							</strong>
							{ firstRun && (
								<>
									<p>{ __( 'Add the contact form to a page; every request sent through it is listed here.', 'mhm-rentiva' ) }</p>
									{ shortcodePagesUrl && (
										<Button href={ shortcodePagesUrl }>{ __( 'Go to shortcode pages', 'mhm-rentiva' ) }</Button>
									) }
								</>
							) }
						</div>
					) }
					<Pagination
						variant="footer"
						page={ page }
						totalPages={ data.pages }
						onChange={ changePage }
						summary={ summary }
						labels={ {
							navigation: __( 'Contact messages pages', 'mhm-rentiva' ),
							previous: __( 'Previous', 'mhm-rentiva' ),
							page: __( 'Page', 'mhm-rentiva' ),
							of: '/',
							next: __( 'Next', 'mhm-rentiva' ),
						} }
					/>
				</div>
			) }
		</div>
	);
}
