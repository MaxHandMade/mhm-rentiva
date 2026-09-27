import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import ContactMessagesList from './ContactMessagesList';
import ContactMessagesApp from './ContactMessagesApp';
import ContactTable from './components/ContactTable';
import { contactApi } from './api';

// A virtual mock: @wordpress/components is not a real devDependency of this
// project (webpack externalises every @wordpress/* import to the wp-admin
// global at build time; only Jest needs a real module to require()), and
// ContactMessagesList's only use of it is the loading Spinner.
jest.mock( '@wordpress/components', () => ( { Spinner: () => null } ), { virtual: true } );

jest.mock( './api', () => ( {
	contactApi: { list: jest.fn(), bulk: jest.fn() },
} ) );

window.mhmRentivaContactMessages = {
	types: { general: 'General Contact', booking: 'Booking Inquiry', support: 'Technical Support', feedback: 'Feedback' },
	pageUrl: '/wp-admin/admin.php?page=mhm-rentiva-contact-messages',
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

describe( 'contact messages list', () => {
	beforeEach( () => jest.clearAllMocks() );

	test( 'renders the stat cards, tabs with counts and a row', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Şule Çağ' );
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
		).toBe( '1 of 2' );
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

	test( 'an unrecognised ?status= in the URL is not sent to the API', async () => {
		window.history.pushState( null, '', '/wp-admin/admin.php?page=mhm-rentiva-contact-messages&status=bogus' );
		contactApi.list.mockResolvedValue( page( [] ) );
		render( <ContactMessagesApp /> );
		await waitFor( () => expect( contactApi.list ).toHaveBeenCalled() );
		const params = contactApi.list.mock.calls[ 0 ][ 0 ];
		expect( params.status ).toBeUndefined();
	} );
} );
