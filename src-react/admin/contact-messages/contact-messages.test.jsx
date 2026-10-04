import { render, screen, fireEvent, waitFor, within, act } from '@testing-library/react';
import ContactMessagesList from './ContactMessagesList';
import ContactMessagesApp from './ContactMessagesApp';
import ContactMessageDetail from './ContactMessageDetail';
import ContactBulkBar from './components/ContactBulkBar';
import ContactTable from './components/ContactTable';
import ContactToolbar from './components/ContactToolbar';
import { contactApi } from './api';
import { buildMailto } from './mailto';

// A virtual mock: @wordpress/components is not a real devDependency of this
// project (webpack externalises every @wordpress/* import to the wp-admin
// global at build time; only Jest needs a real module to require()), and
// ContactMessagesList's only use of it is the loading Spinner.
jest.mock( '@wordpress/components', () => ( { Spinner: () => null } ), { virtual: true } );

jest.mock( './api', () => ( {
	contactApi: { list: jest.fn(), bulk: jest.fn(), destroy: jest.fn() },
} ) );

window.mhmRentivaContactMessages = {
	types: { general: 'General Contact', booking: 'Booking Inquiry', support: 'Technical Support', feedback: 'Feedback' },
	pageUrl: '/wp-admin/admin.php?page=mhm-rentiva-contact-messages',
	shortcodePagesUrl: 'http://example.test/wp-admin/admin.php?page=mhm-rentiva-shortcode-pages',
	months: [],
};

const row = ( over = {} ) => ( {
	id: 1, name: 'Şule Çağ', email: 'sule@example.com', email_linkable: true, initials: 'ŞÇ',
	type: 'booking', type_label: 'Booking Inquiry', snippet: 'Airport pickup?', vehicle: null,
	has_attachment: false, rating: 0, status: 'new', status_label: 'New',
	date_iso: '2026-09-27T07:12:00+00:00', date_label: '27 Sep 2026 10:12', trashed: false, ...over,
} );

const page = ( items, over = {} ) => ( {
	items, total: items.length, pages: 1, page: 1,
	counts: { all: items.length, new: 1, read: 0, replied: 0, trash: 0 },
	stats: { new: 1, awaiting: 1, last_7_days: 1, total: items.length }, ...over,
} );

// Collapsed-card preferences live in localStorage: one test's write must not leak into the next.
const realRO = window.ResizeObserver;
beforeEach( () => window.localStorage.clear() );
afterEach( () => {
	window.ResizeObserver = realRO;
} );

describe( 'contact messages list', () => {
	beforeEach( () => jest.clearAllMocks() );

	test( 'renders the stat cards, tabs with counts and a row', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		expect( container.firstChild.className.split( ' ' ) ).toEqual(
			expect.arrayContaining( [ 'mhmui-admin', 'mhmui-admin-page' ] )
		);
		expect( container.querySelectorAll( '.mhmui-stat-card' ) ).toHaveLength( 4 );
		expect( container.querySelector( '.mhmui-stat-card--warning' ) ).not.toBeNull();
		expect( within( container.querySelector( '.mhm-contact-messages__table' ) ).getByText( 'Booking Inquiry' ) ).toBeTruthy();

		const tabsNav = container.querySelector( '.mhmui-tabs' );
		// The "New" tab's badge carries its count (1) as an accessible name,
		// not only as a bare digit -- see Tabs.jsx's badgeLabel handling.
		expect( within( tabsNav ).getByText( '1 new' ) ).toBeTruthy();
		// The "All" tab's badge is a plain count (no badgeLabel), scoped to
		// that one tab so it isn't confused with the "New" tab's own "1".
		const allTab = within( tabsNav ).getByText( 'All' ).closest( 'a' );
		expect( within( allTab ).getByText( '1' ) ).toBeTruthy();
	} );

	test( 'the list does not print its own page title (PHP prints the H1)', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		expect( screen.queryByRole( 'heading', { name: 'Contact Messages' } ) ).toBeNull();
	} );

	test( 'a partial bulk failure stays visible after the list reloads', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		contactApi.bulk.mockResolvedValue( { results: [ { id: 1, ok: false, error: 'not_allowed' } ] } );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		fireEvent.click( await screen.findByRole( 'checkbox', { name: /Şule Çağ/ } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Mark as read' } ) );
		// The reload after the bulk call has happened...
		await waitFor( () => expect( contactApi.list ).toHaveBeenCalledTimes( 2 ) );
		// ...and the partial-failure notice survived it.
		expect( await screen.findByText( '1 message could not be changed.' ) ).toBeTruthy();
	} );

	test( 'a partial failure that returns after the view changed is not shown in the new view', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		let resolveBulk;
		contactApi.bulk.mockReturnValue( new Promise( ( r ) => {
			resolveBulk = r;
		} ) );
		const { rerender } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		fireEvent.click( await screen.findByRole( 'checkbox', { name: /Şule Çağ/ } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Mark as read' } ) );
		// The operator switches to another tab while the bulk call is in flight.
		rerender( <ContactMessagesList status="replied" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await act( async () => {
			resolveBulk( { results: [ { id: 1, ok: false, error: 'not_allowed' } ] } );
		} );
		// Let the rest of runBulk (reload, busy reset) settle.
		await act( async () => {} );
		expect( contactApi.bulk ).toHaveBeenCalledTimes( 1 );
		expect( screen.queryByText( '1 message could not be changed.' ) ).toBeNull();
		// No stale reload of the old view after the result came back.
		const lastParams = contactApi.list.mock.calls[ contactApi.list.mock.calls.length - 1 ][ 0 ];
		expect( lastParams.status ).toBe( 'replied' );
	} );

	test( 'a bulk result that returns after a page change does not reload the old page', async () => {
		contactApi.list.mockImplementation( async ( params ) => page( [ row( { id: 100 + params.page } ) ], { total: 40, pages: 2, page: params.page } ) );
		let resolveBulk;
		contactApi.bulk.mockReturnValue( new Promise( ( r ) => {
			resolveBulk = r;
		} ) );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		fireEvent.click( await screen.findByRole( 'checkbox', { name: /Şule Çağ/ } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Mark as read' } ) );
		// The operator pages forward while the bulk call is in flight.
		fireEvent.click( screen.getByRole( 'button', { name: 'Next' } ) );
		await waitFor( () => expect( contactApi.list.mock.calls.some( ( [ p ] ) => p.page === 2 ) ).toBe( true ) );
		await act( async () => {
			resolveBulk( { results: [ { id: 101, ok: true } ] } );
		} );
		await act( async () => {} );
		const lastParams = contactApi.list.mock.calls[ contactApi.list.mock.calls.length - 1 ][ 0 ];
		expect( lastParams.page ).toBe( 2 );
		// The page-1 selection does not follow the operator to page 2, so the
		// next bulk action cannot resend ids they can no longer see.
		expect( screen.queryByRole( 'region', { name: 'Bulk actions' } ) ).toBeNull();
	} );

	test( 'a rejected bulk action shows a visible error and re-enables the bar', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		contactApi.bulk.mockRejectedValue( new Error( 'network' ) );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		fireEvent.click( await screen.findByRole( 'checkbox', { name: /Şule Çağ/ } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Mark as read' } ) );
		expect( await screen.findByText( 'The bulk action could not be completed.' ) ).toBeTruthy();
		// The list is not refetched on failure -- only the initial load call.
		expect( contactApi.list ).toHaveBeenCalledTimes( 1 );
		// Busy is cleared (button usable again) and the selection is kept.
		expect( screen.getByRole( 'button', { name: 'Mark as read' } ).hasAttribute( 'aria-disabled' ) ).toBe( false );
		expect( screen.getByRole( 'region', { name: 'Bulk actions' } ) ).toBeTruthy();
	} );

	const six = () => [ 1, 2, 3, 4, 5, 6 ].map( ( id ) => row( { id, name: `Sender ${ id }`, status: [ 'new', 'new', 'read', 'replied', 'replied', 'replied' ][ id - 1 ] } ) );
	const sixPage = () => page( six(), { counts: { all: 6, new: 2, read: 1, replied: 0, trash: 0 } } );

	test( 'the tabs are the underline variant, zero counts shown, New warns only above zero', async () => {
		contactApi.list.mockResolvedValue( sixPage() );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Sender 1' );
		expect( container.querySelector( 'nav.mhmui-tabs' ).className ).toBe( 'mhmui-tabs mhmui-tabs--underline' );
		expect( [ ...container.querySelectorAll( '.mhmui-tabs__badge' ) ].map( ( b ) => b.textContent ) ).toEqual( [ '6', '2' + '2 new', '1', '0' ] );
		expect( container.querySelectorAll( '.mhmui-tabs__badge--warning' ) ).toHaveLength( 1 );
		// The Trash link is a direct child of the bar (kit CSS right-aligns it).
		expect( container.querySelector( '.mhmui-tabs-bar > a.mhm-contact-messages__trash-link' ) ).not.toBeNull();
		expect( container.querySelector( '.mhmui-tabs-bar > nav.mhmui-tabs' ) ).not.toBeNull();
	} );

	test( 'no warning chip on New when there is nothing new', async () => {
		contactApi.list.mockResolvedValue( page( [ row( { status: 'read' } ) ], { counts: { all: 1, new: 0, read: 1, replied: 0, trash: 0 } } ) );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		expect( container.querySelectorAll( '.mhmui-tabs__badge--warning' ) ).toHaveLength( 0 );
	} );

	test( 'the footer sits inside the table card and reads the range on a single page', async () => {
		contactApi.list.mockResolvedValue( sixPage() );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Sender 1' );
		expect( container.querySelector( '.mhm-contact-messages__table-wrap .mhmui-pagination--footer .mhmui-pagination__summary' ).textContent ).toBe( '1–6 of 6 messages' );
	} );

	test( 'the footer summary carries the count for 1, 2 (C-1) and 0', async () => {
		const summary = ( c ) => c.querySelector( '.mhmui-pagination__summary' ).textContent;
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		let r = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		expect( summary( r.container ) ).toBe( '1–1 of 1 message' );
		r.unmount();
		contactApi.list.mockResolvedValue( page( [ row(), row( { id: 2 } ) ] ) );
		r = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findAllByText( 'Şule Çağ' );
		expect( summary( r.container ) ).toBe( '1–2 of 2 messages' );
		r.unmount();
		contactApi.list.mockResolvedValue( page( [], { counts: { all: 3, new: 0, read: 3, replied: 0, trash: 0 } } ) );
		r = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'No messages in this view' );
		expect( summary( r.container ) ).toBe( '0 messages' );
	} );

	test( 'month options come from the localized list, after Last 30 days', () => {
		window.mhmRentivaContactMessages.months = [ { value: '2026-09', label: 'September 2026' } ];
		const { container } = render( <ContactToolbar filters={ { search: '', type: '', period: '' } } total={ 3 } onChange={ () => {} } /> );
		const opts = [ ...container.querySelectorAll( '#mhm-contact-period option' ) ].map( ( o ) => [ o.value, o.textContent ] );
		expect( opts ).toEqual( [ [ '', 'All dates' ], [ '7d', 'Last 7 days' ], [ '30d', 'Last 30 days' ], [ '2026-09', 'September 2026' ] ] );
		window.mhmRentivaContactMessages.months = [];
	} );

	test( 'a ?search= in the URL seeds the search filter and reaches the API', async () => {
		window.history.pushState( null, '', '?page=mhm-rentiva-contact-messages&search=baris%40example.com' );
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		render( <ContactMessagesApp /> );
		await waitFor( () => expect( contactApi.list ).toHaveBeenCalledWith( expect.objectContaining( { search: 'baris@example.com' } ) ) );
		expect( ( await screen.findByRole( 'searchbox' ) ).value ).toBe( 'baris@example.com' );
		window.history.pushState( null, '', '/' );
	} );

	test( 'a nameless, address-less row reads — and draws no initials (R-B15)', () => {
		const { container } = render(
			<table><ContactTable rows={ [ row( { name: '', email: '', initials: '', status: 'read' } ) ] } selected={ [] } onToggle={ () => {} } onToggleAll={ () => {} } onOpen={ () => {} } /></table>
		);
		expect( container.querySelector( '.mhm-contact-messages__avatar' ) ).toBeNull();
		expect( container.querySelector( '.mhm-contact-messages__name' ).textContent ).toBe( '—' );
		expect( container.querySelector( '.mhm-contact-messages__email' ).textContent ).toBe( '—' );
	} );

	test( 'the empty state links to the shortcode pages', async () => {
		contactApi.list.mockResolvedValue( page( [], { counts: { all: 0, new: 0, read: 0, replied: 0, trash: 0 } } ) );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		const link = await screen.findByRole( 'link', { name: 'Go to shortcode pages' } );
		expect( link.getAttribute( 'href' ) ).toContain( 'page=mhm-rentiva-shortcode-pages' );
	} );

	test( 'a row shows the related vehicle title, the paperclip and the star rating', () => {
		const { container } = render(
			<table><ContactTable rows={ [ row( { vehicle: { title: 'Renault Clio', edit_url: 'x' }, has_attachment: true, rating: 5 } ) ] } selected={ [] } onToggle={ () => {} } onToggleAll={ () => {} } onOpen={ () => {} } /></table>
		);
		expect( screen.getByText( 'Renault Clio' ) ).toBeTruthy();
		expect( screen.getByRole( 'columnheader', { name: 'Related vehicle' } ) ).toBeTruthy();
		expect( container.querySelector( '.mhm-contact-messages__attach svg' ) ).not.toBeNull();
		expect( container.querySelector( '.mhm-contact-messages__rating [aria-hidden="true"]' ).textContent ).toBe( '★' );
		expect( container.querySelector( '.mhm-contact-messages__rating' ).textContent ).toBe( '★5/5' );
	} );

	test( 'Open is a link that opens the message on a plain click only', () => {
		const onOpen = jest.fn();
		render( <table><ContactTable rows={ [ row() ] } selected={ [] } onToggle={ () => {} } onToggleAll={ () => {} } onOpen={ onOpen } /></table> );
		const open = screen.getByRole( 'link', { name: 'Open the message from Şule Çağ' } );
		expect( open.getAttribute( 'href' ) ).toContain( 'id=1' );
		// jsdom cannot navigate; the browser's own handling is what we leave alone.
		open.addEventListener( 'click', ( e ) => e.preventDefault() );
		fireEvent.click( open, { ctrlKey: true } );
		expect( onOpen ).not.toHaveBeenCalled();
		fireEvent.click( open );
		expect( onOpen ).toHaveBeenCalledWith( 1 );
	} );

	test( 'no warning tone when there is nothing new', async () => {
		contactApi.list.mockResolvedValue( page( [ row( { status: 'read' } ) ], { stats: { new: 0, awaiting: 1, last_7_days: 1, total: 1 } } ) );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		expect( container.querySelector( '.mhmui-stat-card--warning' ) ).toBeNull();
	} );

	test( 'selecting a row swaps the toolbar for the bulk bar', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		fireEvent.click( await screen.findByRole( 'checkbox', { name: /Şule Çağ/ } ) );
		expect( screen.getByRole( 'region', { name: 'Bulk actions' } ) ).toBeTruthy();
		expect( screen.queryByRole( 'searchbox' ) ).toBeNull();
	} );

	test( 'falls back a page when the current page empties', async () => {
		contactApi.list
			.mockResolvedValueOnce( page( [ row() ], { total: 21, pages: 2, page: 2 } ) )
			.mockResolvedValueOnce( page( [], { total: 20, pages: 1, page: 2 } ) )
			.mockResolvedValueOnce( page( [ row( { id: 2 } ) ], { total: 20, pages: 1, page: 1 } ) );
		contactApi.bulk.mockResolvedValue( { results: [ { id: 1, ok: true } ] } );
		render( <ContactMessagesList status="" initialPage={ 2 } onOpen={ () => {} } onStatusChange={ () => {} } /> );
		fireEvent.click( await screen.findByRole( 'checkbox', { name: /Şule Çağ/ } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Move to trash' } ) );
		await waitFor( () => expect( contactApi.list ).toHaveBeenLastCalledWith( expect.objectContaining( { page: 1 } ) ) );
	} );

	test( 'empty state when there are no messages at all', async () => {
		contactApi.list.mockResolvedValue( page( [], { counts: { all: 0, new: 0, read: 0, replied: 0, trash: 0 }, stats: { new: 0, awaiting: 0, last_7_days: 0, total: 0 } } ) );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		expect( await screen.findByText( 'No contact messages yet' ) ).toBeTruthy();
	} );

	test( 'initials keep Turkish letters', () => {
		render( <table><ContactTable rows={ [ row() ] } selected={ [] } onToggle={ () => {} } onToggleAll={ () => {} } onOpen={ () => {} } /></table> );
		expect( screen.getByText( 'ŞÇ' ) ).toBeTruthy();
	} );

	test( 'a row without a vehicle marks its vehicle cell empty (hidden in card mode)', () => {
		const { container } = render(
			<table><ContactTable rows={ [ row( { vehicle: null } ) ] } selected={ [] } onToggle={ () => {} } onToggleAll={ () => {} } onOpen={ () => {} } /></table>
		);
		const cell = within( container ).getByText( '—' ).closest( 'td' );
		expect( cell.className ).toContain( 'is-empty' );
	} );

	test( 'switching status from a deep page lands the list, and its pagination, on page 1', async () => {
		// "All" has 5 pages; "Replied" has only 2. The status effect and the
		// fetch effect (load() is re-created when `status` changes, so its
		// [load, page] effect fires too) both run in the same commit, so a
		// STALE request for the old page (3) under the NEW status is
		// unavoidable -- the guard in `load` must stop that stale response
		// from ever calling setPage(), not stop the request from being made.
		contactApi.list.mockImplementation( async ( params ) => {
			if ( ! params.status ) {
				return page( [ row( { id: 100 + params.page } ) ], { pages: 5, page: params.page } );
			}
			if ( params.page > 2 ) {
				// The stale (replied, page 3) request: nothing lives there.
				return page( [], { pages: 2, page: params.page } );
			}
			return page(
				[ row( { id: params.page, status: 'replied', status_label: 'Replied' } ) ],
				{ pages: 2, page: params.page }
			);
		} );

		const { rerender, container } = render(
			<ContactMessagesList status="" initialPage={ 3 } onOpen={ () => {} } onStatusChange={ () => {} } />
		);
		await screen.findByText( 'Şule Çağ' );

		rerender(
			<ContactMessagesList status="replied" initialPage={ 3 } onOpen={ () => {} } onStatusChange={ () => {} } />
		);

		await waitFor( () =>
			expect( contactApi.list ).toHaveBeenLastCalledWith(
				expect.objectContaining( { status: 'replied', page: 1 } )
			)
		);

		// No later call ever asked for (replied, page 2) -- the old bug's
		// symptom was landing one page short of 1 instead of on it.
		expect(
			contactApi.list.mock.calls.some(
				( [ params ] ) => params.status === 'replied' && params.page === 2
			)
		).toBe( false );

		expect(
			container.querySelector( '.mhmui-pagination__status' ).textContent.replace( /\s+/g, ' ' ).trim()
		).toBe( 'Page 1 / 2' );
	} );

	test( 'the Trash link marks itself current when viewing the trash', async () => {
		contactApi.list.mockResolvedValue( page( [ row( { status: 'read', status_label: 'Read', trashed: true } ) ] ) );
		const { container } = render( <ContactMessagesList status="trash" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		const trashLink = container.querySelector( '.mhm-contact-messages__trash-link' );
		expect( trashLink.getAttribute( 'aria-current' ) ).toBe( 'page' );
	} );

	test( 'the Trash link is not marked current outside the trash view', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		const trashLink = container.querySelector( '.mhm-contact-messages__trash-link' );
		expect( trashLink.hasAttribute( 'aria-current' ) ).toBe( false );
	} );

	test( 'the Trash link is hidden when trash is disabled (EMPTY_TRASH_DAYS = 0)', async () => {
		window.mhmRentivaContactMessages.trashEnabled = false;
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
		expect( container.querySelector( '.mhm-contact-messages__trash-link' ) ).toBeNull();
		delete window.mhmRentivaContactMessages.trashEnabled;
	} );

	test( 'an unrecognised ?status= in the URL is not sent to the API', async () => {
		window.history.pushState( null, '', '/wp-admin/admin.php?page=mhm-rentiva-contact-messages&status=bogus' );
		contactApi.list.mockResolvedValue( page( [] ) );
		render( <ContactMessagesApp /> );
		await waitFor( () => expect( contactApi.list ).toHaveBeenCalled() );
		const params = contactApi.list.mock.calls[ 0 ][ 0 ];
		expect( params.status ).toBeUndefined();
	} );
} );

describe( 'contact bulk bar', () => {
	afterEach( () => {
		delete window.mhmRentivaContactMessages.trashEnabled;
	} );

	test( 'when trash is disabled the button reads Delete permanently and confirms before acting', async () => {
		window.mhmRentivaContactMessages.trashEnabled = false;
		const onAction = jest.fn();
		render( <ContactBulkBar count={ 2 } inTrash={ false } busy={ false } onAction={ onAction } onClear={ () => {} } /> );

		expect( screen.queryByRole( 'button', { name: 'Move to trash' } ) ).toBeNull();
		const trigger = screen.getByRole( 'button', { name: 'Delete permanently' } );
		fireEvent.click( trigger );
		expect( onAction ).not.toHaveBeenCalled();

		fireEvent.click( screen.getByRole( 'button', { name: 'Yes, delete' } ) );
		await waitFor( () => expect( onAction ).toHaveBeenCalledWith( 'trash' ) );
	} );

	test( 'with the global absent the bulk bar keeps the old Move to trash label', () => {
		const onAction = jest.fn();
		render( <ContactBulkBar count={ 1 } inTrash={ false } busy={ false } onAction={ onAction } onClear={ () => {} } /> );

		expect( screen.getByRole( 'button', { name: 'Move to trash' } ) ).toBeTruthy();
		expect( screen.queryByRole( 'button', { name: 'Delete permanently' } ) ).toBeNull();
		fireEvent.click( screen.getByRole( 'button', { name: 'Move to trash' } ) );
		expect( onAction ).toHaveBeenCalledWith( 'trash' );
	} );

	test.each( [ '', '0' ] )( 'a localized trashEnabled of %p (wp_localize_script stringifies false) disables trash', ( value ) => {
		window.mhmRentivaContactMessages.trashEnabled = value;
		render( <ContactBulkBar count={ 1 } inTrash={ false } busy={ false } onAction={ () => {} } onClear={ () => {} } /> );
		expect( screen.getByRole( 'button', { name: 'Delete permanently' } ) ).toBeTruthy();
		expect( screen.queryByRole( 'button', { name: 'Move to trash' } ) ).toBeNull();
	} );

	test( 'a localized trashEnabled of "1" keeps the Move to trash label', () => {
		window.mhmRentivaContactMessages.trashEnabled = '1';
		render( <ContactBulkBar count={ 1 } inTrash={ false } busy={ false } onAction={ () => {} } onClear={ () => {} } /> );
		expect( screen.getByRole( 'button', { name: 'Move to trash' } ) ).toBeTruthy();
	} );

	test( 'with trash explicitly enabled the bulk bar keeps the old Move to trash label', () => {
		window.mhmRentivaContactMessages.trashEnabled = true;
		render( <ContactBulkBar count={ 1 } inTrash={ false } busy={ false } onAction={ () => {} } onClear={ () => {} } /> );
		expect( screen.getByRole( 'button', { name: 'Move to trash' } ) ).toBeTruthy();
	} );
} );

describe( 'contact message detail', () => {
	const detail = ( over = {} ) => ( {
		...row( { status: 'new' } ),
		content: 'Line one\nLine two',
		fields: [ { key: 'type', label: 'Enquiry type', value: 'Booking Inquiry' } ],
		attachment: null,
		sender: { email: 'sule@example.com', email_linkable: true, phone: '+90 533 000 2940', customer_url: null, other_count: 1 },
		...over,
	} );

	beforeEach( () => {
		contactApi.get = jest.fn().mockResolvedValue( detail() );
		contactApi.markRead = jest.fn().mockResolvedValue( row( { status: 'read', status_label: 'Read' } ) );
		contactApi.setStatus = jest.fn().mockImplementation( ( id, status ) => Promise.resolve( row( { status, status_label: status } ) ) );
		contactApi.technical = jest.fn().mockResolvedValue( { ip_address: '203.0.113.48', user_agent: 'UA/1' } );
	} );

	test( 'opening marks a new message read', async () => {
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		await waitFor( () => expect( contactApi.markRead ).toHaveBeenCalledWith( 1 ) );
	} );

	test( 'the detail root carries the kit page-shell classes', async () => {
		const { container } = render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		expect( container.firstChild.className.split( ' ' ) ).toEqual(
			expect.arrayContaining( [ 'mhmui-admin', 'mhmui-admin-page' ] )
		);
	} );

	test( 'a trashed message offers Restore and Delete permanently, and hides the live-message actions', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'read', trashed: true } ) );
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );

		expect( screen.getByRole( 'button', { name: 'Restore' } ) ).toBeTruthy();
		expect( screen.getByRole( 'button', { name: 'Delete permanently' } ) ).toBeTruthy();

		expect( screen.queryByRole( 'button', { name: 'Mark unread' } ) ).toBeNull();
		expect( screen.queryByRole( 'button', { name: 'Mark as replied' } ) ).toBeNull();
		expect( screen.queryByRole( 'link', { name: 'Reply by e-mail' } ) ).toBeNull();
		expect( screen.queryByRole( 'button', { name: 'Move to trash' } ) ).toBeNull();
		// markRead must not fire for an already-read, trashed message.
		expect( contactApi.markRead ).not.toHaveBeenCalled();
	} );

	test( 'restoring a trashed message calls the bulk restore action and navigates back', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'read', trashed: true } ) );
		contactApi.bulk = jest.fn().mockResolvedValue( { results: [ { id: 1, ok: true } ] } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );

		fireEvent.click( screen.getByRole( 'button', { name: 'Restore' } ) );
		await waitFor( () => expect( contactApi.bulk ).toHaveBeenCalledWith( [ 1 ], 'restore' ) );
		await waitFor( () => expect( onBack ).toHaveBeenCalled() );
	} );

	test( 'a failed permanent delete keeps the message on screen, shows an error, and does not navigate', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'read', trashed: true } ) );
		contactApi.destroy = jest.fn().mockRejectedValue( new Error( 'network' ) );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );

		fireEvent.click( screen.getByRole( 'button', { name: 'Delete permanently' } ) );
		fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, delete' } ) );

		expect( await screen.findByText( 'The message could not be deleted.' ) ).toBeTruthy();
		expect( onBack ).not.toHaveBeenCalled();
	} );

	test( 'a restore that the server reports as not-ok shows an error and does not navigate (bulk resolves 200 with ok:false)', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'read', trashed: true } ) );
		contactApi.bulk = jest.fn().mockResolvedValue( { results: [ { id: 1, ok: false, error: 'not_allowed' } ] } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );

		fireEvent.click( screen.getByRole( 'button', { name: 'Restore' } ) );

		expect( await screen.findByText( 'The message could not be restored.' ) ).toBeTruthy();
		expect( onBack ).not.toHaveBeenCalled();
	} );

	test( 'a permanent delete that the server reports as not deleted shows an error and does not navigate (destroy resolves 200 with deleted:false)', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'read', trashed: true } ) );
		contactApi.destroy = jest.fn().mockResolvedValue( { id: 1, trashed: true, deleted: false } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );

		fireEvent.click( screen.getByRole( 'button', { name: 'Delete permanently' } ) );
		fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, delete' } ) );

		expect( await screen.findByText( 'The message could not be deleted.' ) ).toBeTruthy();
		expect( onBack ).not.toHaveBeenCalled();
	} );

	test( 'a successful permanent delete from the trash navigates back (destroy resolves with deleted:true)', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'read', trashed: true } ) );
		contactApi.destroy = jest.fn().mockResolvedValue( { id: 1, trashed: false, deleted: true } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );

		fireEvent.click( screen.getByRole( 'button', { name: 'Delete permanently' } ) );
		fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, delete' } ) );

		await waitFor( () => expect( onBack ).toHaveBeenCalled() );
	} );

	test( 'a trashed message never triggers the automatic mark-as-read call', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'new', trashed: true } ) );
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		expect( contactApi.markRead ).not.toHaveBeenCalled();
	} );

	test( 'reply link encodes the address and the subject', () => {
		expect( buildMailto( 'a+b@example.com', 'Re: Booking Inquiry' ) ).toBe( 'mailto:a%2Bb@example.com?subject=Re%3A%20Booking%20Inquiry' );
	} );

	test( 'an address with no local part builds no link', () => {
		expect( buildMailto( 'abc', 'Re: Booking Inquiry' ) ).toBe( '' );
	} );

	test( 'an address with query characters gets no link', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { sender: { email: 'a?cc=b%40evil.com&x=@example.com', email_linkable: false, phone: '', customer_url: null, other_count: 0 } } ) );
		const { container } = render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		expect( container.querySelector( 'a[href^="mailto:"]' ) ).toBeNull();
	} );

	test( 'reply by e-mail shows the mark-as-replied prompt, which disappears once replied', async () => {
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		const link = await screen.findByRole( 'link', { name: 'Reply by e-mail' } );
		// jsdom logs "navigation not implemented" for a real activation, and
		// @wordpress/jest-console fails the test on it. A target listener runs
		// before React's root listener: the navigation is cancelled, onClick still fires.
		link.addEventListener( 'click', ( e ) => e.preventDefault(), { once: true } );
		fireEvent.click( link );
		const prompt = await screen.findByRole( 'status' );
		fireEvent.click( within( prompt ).getByRole( 'button', { name: 'Mark as replied' } ) );
		// The status region is always present (a stable live region for screen
		// readers -- fix round 1, finding 5), so its own absence is no longer
		// the signal; the prompt's text disappearing is.
		await waitFor( () => expect( screen.queryByText( /Your e-mail app opened a reply draft/ ) ).toBeNull() );
		expect( contactApi.setStatus ).toHaveBeenCalledWith( 1, 'replied' );
	} );

	test( 'technical record is fetched only on demand', async () => {
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		expect( contactApi.technical ).not.toHaveBeenCalled();
		fireEvent.click( screen.getByRole( 'button', { name: 'Technical record' } ) );
		expect( await screen.findByText( '203.0.113.48' ) ).toBeTruthy();
	} );

	test( 'a failed trash keeps the message on screen and shows an error, without navigating back', async () => {
		contactApi.trash = jest.fn().mockRejectedValue( new Error( 'network' ) );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );
		fireEvent.click( screen.getByRole( 'button', { name: 'Move to trash' } ) );
		fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, move to trash' } ) );
		expect( await screen.findByText( 'The message could not be moved to the trash.' ) ).toBeTruthy();
		expect( onBack ).not.toHaveBeenCalled();
	} );

	test( 'a 200 trash response that neither trashed nor deleted is treated as a failure', async () => {
		contactApi.trash = jest.fn().mockResolvedValue( { id: 1, trashed: false, deleted: false } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );
		fireEvent.click( screen.getByRole( 'button', { name: 'Move to trash' } ) );
		fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, move to trash' } ) );
		expect( await screen.findByText( 'The message could not be moved to the trash.' ) ).toBeTruthy();
		expect( onBack ).not.toHaveBeenCalled();
	} );

	test( 'a successful trash navigates back', async () => {
		contactApi.trash = jest.fn().mockResolvedValue( { id: 1, trashed: true, deleted: false } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
		await screen.findByText( /Line one/ );
		fireEvent.click( screen.getByRole( 'button', { name: 'Move to trash' } ) );
		fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, move to trash' } ) );
		await waitFor( () => expect( onBack ).toHaveBeenCalled() );
	} );

	describe( 'collapsible side cards (Task L2)', () => {
		const narrow = () => {
			window.ResizeObserver = class {
				constructor( cb ) { this.cb = cb; }
				observe() { this.cb( [ { contentRect: { width: 366 } } ] ); }
				disconnect() {}
			};
		};
		const wide = () => {
			window.ResizeObserver = class {
				constructor( cb ) { this.cb = cb; }
				observe() { this.cb( [ { contentRect: { width: 1100 } } ] ); }
				disconnect() {}
			};
		};
		const open = async () => {
			const r = render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			return r;
		};
		const toggle = ( name ) => screen.getByRole( 'button', { name } );

		test( 'on a narrow container the side cards start collapsed and the message card stays open', async () => {
			narrow();
			await open();
			[ 'Request details', 'Sender', 'Technical record', 'Delete message' ].forEach( ( n ) => {
				expect( toggle( n ).getAttribute( 'aria-expanded' ) ).toBe( 'false' );
			} );
			expect( screen.queryByRole( 'button', { name: 'Message' } ) ).toBeNull();
			expect( screen.getByText( /Line one/ ).closest( '[hidden]' ) ).toBeNull();
		} );

		test( 'on a wide container the side cards start open except the technical record', async () => {
			wide();
			await open();
			[ 'Request details', 'Sender', 'Delete message' ].forEach( ( n ) => {
				expect( toggle( n ).getAttribute( 'aria-expanded' ) ).toBe( 'true' );
			} );
			expect( toggle( 'Technical record' ).getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		} );

		test( 'on a narrow container the cards never render open under the wide key', async () => {
			const seen = [];
			let measuredYet = false;
			window.ResizeObserver = class {
				constructor( cb ) { this.cb = cb; }
				observe() {
					measuredYet = true;
					this.cb( [ { contentRect: { width: 366 } } ] );
				}
				disconnect() {}
			};
			const names = [ 'Request details', 'Sender', 'Technical record', 'Delete message' ];
			const snap = () => {
				const expanded = names.filter( ( n ) => {
					const b = screen.queryByRole( 'button', { name: n } );
					return b && b.getAttribute( 'aria-expanded' ) === 'true';
				} );
				const mounted = names.filter( ( n ) => screen.queryByRole( 'button', { name: n } ) );
				seen.push( { measuredYet, open: expanded, mounted } );
			};
			const mo = new window.MutationObserver( snap );
			render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
			mo.observe( document.body, { childList: true, subtree: true, attributes: true } );
			await screen.findByText( /Line one/ );
			await new Promise( ( r ) => setTimeout( r, 0 ) );
			mo.disconnect();
			expect( seen.some( ( s ) => s.mounted.length > 0 ) ).toBe( true );
			expect( seen.every( ( s ) => s.open.length === 0 ) ).toBe( true );
			expect( seen.filter( ( s ) => s.mounted.length > 0 ).every( ( s ) => s.measuredYet ) ).toBe( true );
			expect( window.localStorage.getItem( 'mhm-rentiva:contact:sender:wide' ) ).toBeNull();
		} );

		test( 'a toggled card is remembered per layout', async () => {
			narrow();
			const first = await open();
			fireEvent.click( toggle( 'Sender' ) );
			expect( window.localStorage.getItem( 'mhm-rentiva:contact:sender:narrow' ) ).toBe( '1' );
			expect( window.localStorage.getItem( 'mhm-rentiva:contact:sender:wide' ) ).toBeNull();
			first.unmount();
			await open();
			expect( toggle( 'Sender' ).getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		} );

		test( 'technical record fetches once on first open and retries after a failure', async () => {
			wide();
			await open();
			fireEvent.click( toggle( 'Technical record' ) );
			expect( await screen.findByText( '203.0.113.48' ) ).toBeTruthy();
			fireEvent.click( toggle( 'Technical record' ) );
			fireEvent.click( toggle( 'Technical record' ) );
			expect( contactApi.technical ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'a failed technical fetch shows an inline error and the next open retries', async () => {
			wide();
			contactApi.technical = jest.fn().mockRejectedValueOnce( new Error( 'network' ) ).mockResolvedValue( { ip_address: '203.0.113.48', user_agent: 'UA/1' } );
			await open();
			fireEvent.click( toggle( 'Technical record' ) );
			expect( await screen.findByText( 'The technical record could not be loaded.' ) ).toBeTruthy();
			fireEvent.click( toggle( 'Technical record' ) );
			fireEvent.click( toggle( 'Technical record' ) );
			expect( await screen.findByText( '203.0.113.48' ) ).toBeTruthy();
			expect( contactApi.technical ).toHaveBeenCalledTimes( 2 );
		} );

		test( 'the technical record always starts closed and keeps no stored preference', async () => {
			wide();
			window.localStorage.setItem( 'mhm-rentiva:contact:technical:wide', '1' );
			await open();
			expect( toggle( 'Technical record' ).getAttribute( 'aria-expanded' ) ).toBe( 'false' );
			fireEvent.click( toggle( 'Technical record' ) );
			expect( await screen.findByText( '203.0.113.48' ) ).toBeTruthy();
			expect( window.localStorage.getItem( 'mhm-rentiva:contact:technical:wide' ) ).toBe( '1' );
			expect( window.localStorage.getItem( 'mhm-rentiva:contact:technical:narrow' ) ).toBeNull();
		} );

		test( 'the technical record has no separate Show button any more', async () => {
			wide();
			await open();
			expect( screen.queryByRole( 'button', { name: 'Show' } ) ).toBeNull();
			expect( screen.queryByRole( 'button', { name: 'Hide' } ) ).toBeNull();
		} );

		test( "a trashed message's card uses the delete key", async () => {
			narrow();
			window.localStorage.setItem( 'mhm-rentiva:contact:delete:narrow', '1' );
			contactApi.get = jest.fn().mockResolvedValue( detail( { status: 'read', trashed: true } ) );
			await open();
			expect( toggle( 'Trashed message' ).getAttribute( 'aria-expanded' ) ).toBe( 'true' );
			expect( toggle( 'Request details' ).getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		} );
	} );

	describe( 'trash disabled (EMPTY_TRASH_DAYS = 0)', () => {
		beforeEach( () => {
			window.mhmRentivaContactMessages.trashEnabled = false;
		} );
		afterEach( () => {
			delete window.mhmRentivaContactMessages.trashEnabled;
		} );

		test( 'the delete widget shows permanent-deletion wording instead of trash wording', async () => {
			render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );

			expect( screen.getByText( 'The message is deleted immediately; the trash is disabled on this site.' ) ).toBeTruthy();
			expect( screen.getByRole( 'button', { name: 'Delete permanently' } ) ).toBeTruthy();
			expect( screen.queryByRole( 'button', { name: 'Move to trash' } ) ).toBeNull();
		} );

		test( 'a successful permanent delete calls onBack', async () => {
			contactApi.trash = jest.fn().mockResolvedValue( { id: 1, trashed: false, deleted: true } );
			const onBack = jest.fn();
			render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
			await screen.findByText( /Line one/ );

			fireEvent.click( screen.getByRole( 'button', { name: 'Delete permanently' } ) );
			fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, delete permanently' } ) );

			await waitFor( () => expect( onBack ).toHaveBeenCalled() );
		} );

		test( 'a failed permanent delete keeps the message on screen and shows the deletion error', async () => {
			contactApi.trash = jest.fn().mockRejectedValue( new Error( 'network' ) );
			const onBack = jest.fn();
			render( <ContactMessageDetail id={ 1 } onBack={ onBack } /> );
			await screen.findByText( /Line one/ );

			fireEvent.click( screen.getByRole( 'button', { name: 'Delete permanently' } ) );
			fireEvent.click( await screen.findByRole( 'button', { name: 'Yes, delete permanently' } ) );

			expect( await screen.findByText( 'The message could not be deleted.' ) ).toBeTruthy();
			expect( onBack ).not.toHaveBeenCalled();
		} );
	} );

	test( 'an attachment without a download URL shows "File not available" and no link', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { attachment: { name: 'photo.jpg', download_url: null } } ) );
		const { container } = render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		expect( screen.getByText( 'File not available' ) ).toBeTruthy();
		expect( container.querySelector( '.mhm-contact-messages__attachment a' ) ).toBeNull();
	} );

	test( 'an attachment links to its download URL as "Download", without a download attribute', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { attachment: { name: 'offer.pdf', size: 1234, download_url: 'http://example.test/wp-admin/admin-post.php?action=mhmrentiva_contact_attachment&id=7&_wpnonce=abc' } } ) );
		const { container } = render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		const link = container.querySelector( '.mhm-contact-messages__attachment a' );
		expect( link.getAttribute( 'href' ) ).toBe( 'http://example.test/wp-admin/admin-post.php?action=mhmrentiva_contact_attachment&id=7&_wpnonce=abc' );
		expect( link.hasAttribute( 'download' ) ).toBe( false );
		expect( link.textContent ).toBe( 'Download' );
	} );

	test( 'no fields and no vehicle shows the "no further details" placeholder', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { fields: [], vehicle: null } ) );
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		expect( await screen.findByText( 'No further details were filled in.' ) ).toBeTruthy();
	} );

	describe( 'artboard binding (Task C4)', () => {
		const long = { id: 3531, name: 'Barış Koç', type_label: 'Technical Support', date_label_long: '26/09/2026, 21:40' };
		const email = 'baris@example.com';
		const sender = ( over = {} ) => ( { email, email_linkable: true, phone: '', customer_url: null, other_count: 0, ...over } );

		test( 'the sender name is the page h1 and the meta carries the message number', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, email, sender: sender() } ) );
			const { container } = render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByRole( 'heading', { level: 1 } ).textContent ).toBe( 'Barış Koç' );
			expect( screen.queryAllByRole( 'heading', { level: 1 } ) ).toHaveLength( 1 );
			expect( container.querySelector( '.mhmui-page-header__meta' ).textContent ).toBe( 'Technical Support · 26/09/2026, 21:40 · Message #3531' );
		} );

		test( 'the attachment card shows type and size and downloads', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, attachment: { name: 'odeme-hatasi.png', type_label: 'PNG', size_label: '312 KB', download_url: '/dl' } } ) );
			render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByText( 'odeme-hatasi.png' ) ).toBeTruthy();
			expect( screen.getByText( 'PNG · 312 KB' ) ).toBeTruthy();
			expect( screen.getByRole( 'link', { name: 'Download' } ).getAttribute( 'href' ) ).toBe( '/dl' );
		} );

		test( 'an attachment with only a type shows no dangling separator', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, attachment: { name: 'x.bin', type_label: 'BIN', size_label: null, download_url: null } } ) );
			render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByText( 'BIN' ) ).toBeTruthy();
		} );

		test( 'the vehicle links to its edit screen only when edit_url is given', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, vehicle: { id: 9, title: 'Fiat Egea', edit_url: '/v/9' } } ) );
			const first = render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByRole( 'link', { name: 'Fiat Egea' } ).getAttribute( 'href' ) ).toBe( '/v/9' );
			expect( screen.getByText( 'Related vehicle' ) ).toBeTruthy();
			expect( screen.queryByText( 'Vehicle' ) ).toBeNull();
			first.unmount();
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, vehicle: { id: 9, title: 'Fiat Egea', edit_url: null } } ) );
			render( <ContactMessageDetail id={ 3532 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByText( 'Fiat Egea' ) ).toBeTruthy();
			expect( screen.queryByRole( 'link', { name: 'Fiat Egea' } ) ).toBeNull();
		} );

		test( 'other messages from the address link to the list filtered by that address', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, email, sender: sender( { other_count: 1 } ) } ) );
			render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			const link = screen.getByRole( 'link', { name: /1 more message from this address/ } );
			expect( link.getAttribute( 'href' ).endsWith( '&search=baris%40example.com' ) ).toBe( true );
		} );

		test( 'other messages count reads 1 and 2 (C-1)', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, sender: sender( { other_count: 1 } ) } ) );
			const one = render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByRole( 'link', { name: /^1 more message from this address/ } ) ).toBeTruthy();
			one.unmount();
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, sender: sender( { other_count: 2 } ) } ) );
			render( <ContactMessageDetail id={ 3532 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByRole( 'link', { name: /^2 more messages from this address/ } ) ).toBeTruthy();
		} );

		test( 'a nameless message reads — as its h1 and sender (R-B15)', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, name: '', email: '', initials: '', sender: sender( { email: '', email_linkable: false } ) } ) );
			const { container } = render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByRole( 'heading', { level: 1 } ).textContent ).toBe( '—' );
			const aside = container.querySelector( '.mhmui-detail-layout__aside' );
			expect( within( aside ).getAllByText( '—' ).length ).toBeGreaterThan( 0 );
			expect( within( aside ).queryByRole( 'button', { name: 'Copy' } ) ).toBeNull();
			expect( container.querySelector( 'a[href^="mailto:"]' ) ).toBeNull();
		} );

		test( 'a non-linkable address is plain text, not mailto', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, sender: sender( { email_linkable: false } ) } ) );
			const { container } = render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			const aside = container.querySelector( '.mhmui-detail-layout__aside' );
			expect( aside.querySelector( 'a[href^="mailto:"]' ) ).toBeNull();
			expect( within( aside ).getByText( email ) ).toBeTruthy();
		} );

		test( 'a linkable address is a mailto link next to a Copy button', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, sender: sender() } ) );
			const { container } = render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			const aside = container.querySelector( '.mhmui-detail-layout__aside' );
			expect( aside.querySelector( 'a[href^="mailto:"]' ).textContent ).toBe( email );
			expect( within( aside ).getByRole( 'button', { name: 'Copy' } ) ).toBeTruthy();
		} );

		test( 'a registered customer gets a separate record link', async () => {
			contactApi.get = jest.fn().mockResolvedValue( detail( { ...long, sender: sender( { customer_url: '/c/5' } ) } ) );
			render( <ContactMessageDetail id={ 3531 } onBack={ () => {} } /> );
			await screen.findByText( /Line one/ );
			expect( screen.getByText( 'This e-mail belongs to a registered customer' ) ).toBeTruthy();
			expect( screen.getByRole( 'link', { name: /Open customer record/ } ).getAttribute( 'href' ) ).toBe( '/c/5' );
		} );
	} );
} );

describe( 'contact table accessible names', () => {
	const tableOf = ( r ) => render(
		<table>
			<ContactTable rows={ [ r ] } selected={ [] } onToggle={ () => {} } onToggleAll={ () => {} } onOpen={ () => {} } />
		</table>
	);

	test( 'a nameless row falls back to the address, then to a generic label', () => {
		const first = tableOf( row( { name: '', email: 'x@example.com', initials: '' } ) );
		expect( screen.getByRole( 'checkbox', { name: 'Select the message from x@example.com' } ) ).toBeTruthy();
		expect( screen.getByRole( 'link', { name: 'Open the message from x@example.com' } ) ).toBeTruthy();
		first.unmount();
		tableOf( row( { name: '', email: '', initials: '' } ) );
		expect( screen.getByRole( 'checkbox', { name: 'Select this message' } ) ).toBeTruthy();
		expect( screen.getByRole( 'link', { name: 'Open this message' } ) ).toBeTruthy();
	} );
} );
