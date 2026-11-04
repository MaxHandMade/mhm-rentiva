import { render, screen, fireEvent } from '@testing-library/react';
import ContactMessagesList from './ContactMessagesList';
import { contactApi } from './api';

/**
 * The contact messages table on the ui-core 0.19 table pieces (spec v2.1 §7):
 * a caption names it (visually hidden: the page title already shows it), the
 * kit's card replaces the screen's own wrap,
 * the header box shows a partial selection, and on an empty view the box is
 * disabled and the empty state sits in the card's footer, before the pager.
 */
jest.mock(
	'@wordpress/components',
	() => ( { Spinner: () => require( '@wordpress/element' ).createElement( 'span', { 'data-testid': 'spinner' } ) } ),
	{ virtual: true }
);

jest.mock( './api', () => ( {
	contactApi: { list: jest.fn(), bulk: jest.fn(), destroy: jest.fn() },
} ) );

window.mhmRentivaContactMessages = {
	types: { general: 'General', booking: 'Booking' },
	pageUrl: '/wp-admin/admin.php?page=mhm-rentiva-contact-messages',
	shortcodePagesUrl: '',
	months: [],
};

const row = ( id, name ) => ( {
	id, name, email: `${ id }@example.com`, email_linkable: true, initials: 'X',
	type: 'general', type_label: 'General', snippet: 'Hi', vehicle: null,
	has_attachment: false, rating: 0, status: 'read', status_label: 'Read',
	date_iso: '2026-09-27T07:12:00+00:00', date_label: '27 Sep 2026', trashed: false,
} );

const page = ( items ) => ( {
	items, total: items.length, pages: 1, page: 1,
	counts: { all: items.length, new: 0, read: items.length, replied: 0, trash: 0 },
	stats: { new: 0, awaiting: 0, last_7_days: 0, total: items.length },
} );

const renderList = () => render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );

describe( 'contact messages table semantics', () => {
	beforeEach( () => jest.clearAllMocks() );

	test( 'a visually hidden caption names the table inside the kit card (the page title shows the name); the old wrap is gone', async () => {
		contactApi.list.mockResolvedValue( page( [ row( 1, 'Ali' ) ] ) );
		const { container } = renderList();
		await screen.findByText( 'Ali' );

		const table = container.querySelector( 'table' );
		const caption = table.querySelector( 'caption' );
		expect( caption.textContent ).toBe( 'Contact Messages' );
		expect( caption.classList.contains( 'mhmui-table__caption--hidden' ) ).toBe( true );
		expect( table.classList.contains( 'mhm-contact-messages__table' ) ).toBe( true );
		expect( table.classList.contains( 'mhmui-table' ) ).toBe( true );
		expect( table.closest( '.mhmui-table-card' ) ).not.toBeNull();
		expect( container.querySelector( '.mhm-contact-messages__table-wrap' ) ).toBeNull();
	} );

	test( 'one of three selected shows a mixed header box; a click selects all three', async () => {
		contactApi.list.mockResolvedValue( page( [ row( 1, 'Ali' ), row( 2, 'Banu' ), row( 3, 'Can' ) ] ) );
		renderList();

		fireEvent.click( await screen.findByRole( 'checkbox', { name: /Banu/ } ) );
		const all = screen.getByRole( 'checkbox', { name: 'Select all messages' } );
		expect( all.indeterminate ).toBe( true );
		expect( all.checked ).toBe( false );

		fireEvent.click( all );
		[ /Ali/, /Banu/, /Can/ ].forEach( ( name ) => {
			expect( screen.getByRole( 'checkbox', { name } ).checked ).toBe( true );
		} );
		expect( all.checked ).toBe( true );
		expect( all.indeterminate ).toBe( false );
	} );

	test( 'an empty view disables the header box and puts the empty state in the footer, before the pager', async () => {
		contactApi.list.mockResolvedValue( page( [] ) );
		const { container } = renderList();
		await screen.findByText( 'No contact messages yet' );

		expect( screen.getByRole( 'checkbox', { name: 'Select all messages' } ).disabled ).toBe( true );

		const card = container.querySelector( '.mhmui-table-card' );
		const empty = card.querySelector( '.mhm-contact-messages__empty' );
		const pager = card.querySelector( '.mhmui-pagination--footer' );
		expect( empty ).not.toBeNull();
		expect( empty.closest( 'table' ) ).toBeNull();
		expect( pager ).not.toBeNull();
		// eslint-disable-next-line no-bitwise
		expect( empty.compareDocumentPosition( pager ) & Node.DOCUMENT_POSITION_FOLLOWING ).toBeTruthy();
	} );
} );
