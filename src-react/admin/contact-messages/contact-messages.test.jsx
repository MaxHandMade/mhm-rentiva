import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import ContactMessagesList from './ContactMessagesList';
import ContactMessagesApp from './ContactMessagesApp';
import ContactMessageDetail from './ContactMessageDetail';
import ContactBulkBar from './components/ContactBulkBar';
import ContactTable from './components/ContactTable';
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
		expect( screen.getByRole( 'button', { name: 'Mark as read' } ).disabled ).toBe( false );
		expect( screen.getByRole( 'region', { name: 'Bulk actions' } ) ).toBeTruthy();
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
		fireEvent.click( screen.getByRole( 'button', { name: 'Show' } ) );
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

	test( 'a failed technical fetch shows an inline error inside the widget and leaves the button usable', async () => {
		contactApi.technical = jest.fn().mockRejectedValue( new Error( 'network' ) );
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		fireEvent.click( screen.getByRole( 'button', { name: 'Show' } ) );
		expect( await screen.findByText( 'The technical record could not be loaded.' ) ).toBeTruthy();
		expect( screen.getByRole( 'button', { name: 'Hide' } ).disabled ).toBe( false );
	} );

	test( 'an attachment without a download URL shows "File not available" and no link', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { attachment: { name: 'photo.jpg', download_url: null } } ) );
		const { container } = render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		await screen.findByText( /Line one/ );
		expect( screen.getByText( 'File not available' ) ).toBeTruthy();
		expect( container.querySelector( '.mhm-contact-messages__attachment a' ) ).toBeNull();
	} );

	test( 'no fields and no vehicle shows the "no further details" placeholder', async () => {
		contactApi.get = jest.fn().mockResolvedValue( detail( { fields: [], vehicle: null } ) );
		render( <ContactMessageDetail id={ 1 } onBack={ () => {} } /> );
		expect( await screen.findByText( 'No further details were filled in.' ) ).toBeTruthy();
	} );
} );
