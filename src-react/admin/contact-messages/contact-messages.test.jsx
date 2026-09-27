import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import ContactMessagesList from './ContactMessagesList';
import ContactTable from './components/ContactTable';
import { contactApi } from './api';

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
} );
