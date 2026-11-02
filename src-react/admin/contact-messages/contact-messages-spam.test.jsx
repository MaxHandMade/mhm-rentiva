import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import ContactMessagesList from './ContactMessagesList';
import ContactMessagesApp from './ContactMessagesApp';
import ContactMessageDetail from './ContactMessageDetail';
import ContactBulkBar from './components/ContactBulkBar';
import { contactApi } from './api';

jest.mock(
	'@wordpress/components',
	() => ( { Spinner: () => require( '@wordpress/element' ).createElement( 'span', { 'data-testid': 'spinner' } ) } ),
	{ virtual: true }
);

jest.mock( './api', () => ( {
	contactApi: {
		list: jest.fn(),
		get: jest.fn(),
		technical: jest.fn(),
		markRead: jest.fn(),
		setStatus: jest.fn(),
		bulk: jest.fn(),
		trash: jest.fn(),
		destroy: jest.fn(),
	},
} ) );

window.mhmRentivaContactMessages = {
	types: { general: 'General' },
	pageUrl: '/wp-admin/admin.php?page=mhm-rentiva-contact-messages',
	shortcodePagesUrl: '',
	months: [],
};

const row = ( over = {} ) => ( {
	id: 7, name: 'Sp Ammer', email: 's@example.com', email_linkable: true, initials: 'SA',
	type: 'general', type_label: 'General', snippet: 'Buy now', vehicle: null,
	has_attachment: false, rating: 0, status: 'spam', status_label: 'Spam',
	date_iso: '2026-11-02T07:12:00+00:00', date_label: '2 Nov 2026 10:12', trashed: false, ...over,
} );

const page = ( items, counts = {} ) => ( {
	items, total: items.length, pages: 1, page: 1,
	counts: { all: 3, new: 1, read: 1, replied: 1, spam: 2, trash: 0, ...counts },
	stats: { new: 1, awaiting: 2, last_7_days: 1, total: 3 },
} );

const detail = ( over = {} ) => ( {
	...row(),
	date_label_long: '2 Nov 2026, 10:12',
	content: 'Buy now',
	fields: [ { key: 'spam_reason', label: 'Why it is here', value: 'Holds too many links' } ],
	attachment: null,
	booking: null,
	sender: { email: 's@example.com', email_linkable: true, phone: '', customer_url: null, other_count: 0 },
	...over,
} );

beforeEach( () => {
	jest.clearAllMocks();
	window.localStorage.clear();
	window.history.pushState( null, '', '/' );
	contactApi.technical.mockResolvedValue( { ip_address: '', user_agent: '' } );
	window.ResizeObserver = class {
		constructor( cb ) {
			this.cb = cb;
		}
		observe( el ) {
			this.cb( [ { target: el, contentRect: { width: 1200 } } ] );
		}
		unobserve() {}
		disconnect() {}
	};
} );

describe( 'the Spam filter', () => {
	test( 'is a tab with its own count, apart from All', async () => {
		contactApi.list.mockResolvedValue( page( [ row( { status: 'new', status_label: 'New', name: 'Ada' } ) ] ) );
		const onStatusChange = jest.fn();
		const { container } = render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ onStatusChange } /> );
		await screen.findByText( 'Ada' );

		const tabs = container.querySelector( '.mhmui-tabs' );
		const spam = within( tabs ).getByText( 'Spam' ).closest( 'a' );
		expect( within( spam ).getByText( '2' ) ).toBeTruthy();
		expect( within( within( tabs ).getByText( 'All' ).closest( 'a' ) ).getByText( '3' ) ).toBeTruthy();

		fireEvent.click( spam );
		expect( onStatusChange ).toHaveBeenCalledWith( 'spam' );
	} );

	test( 'the default list does not ask for spam', async () => {
		contactApi.list.mockResolvedValue( page( [ row( { status: 'new', name: 'Ada' } ) ] ) );
		render( <ContactMessagesList status="" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Ada' );
		expect( contactApi.list.mock.calls[ 0 ][ 0 ] ).not.toHaveProperty( 'status' );
	} );

	test( 'the spam list asks for spam and offers Not spam on a selection', async () => {
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		contactApi.bulk.mockResolvedValue( { results: [ { id: 7, ok: true } ] } );
		render( <ContactMessagesList status="spam" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'Sp Ammer' );
		expect( contactApi.list.mock.calls[ 0 ][ 0 ].status ).toBe( 'spam' );

		fireEvent.click( screen.getByRole( 'checkbox', { name: /Sp Ammer/ } ) );
		fireEvent.click( screen.getByRole( 'button', { name: 'Not spam' } ) );
		await waitFor( () => expect( contactApi.bulk ).toHaveBeenCalledWith( [ 7 ], 'not_spam' ) );
		await waitFor( () => expect( contactApi.list ).toHaveBeenCalledTimes( 2 ) );
	} );

	test( 'an empty spam list is no first-run screen', async () => {
		contactApi.list.mockResolvedValue( page( [], { all: 0, spam: 0 } ) );
		render( <ContactMessagesList status="spam" onOpen={ () => {} } onStatusChange={ () => {} } /> );
		await screen.findByText( 'No messages in this view' );
		expect( screen.queryByText( 'No contact messages yet' ) ).toBeNull();
	} );

	test( 'the bulk bar offers each view its own actions', () => {
		const bar = ( props ) => render( <ContactBulkBar count={ 1 } busy={ false } onAction={ () => {} } onClear={ () => {} } { ...props } /> );

		const spam = bar( { inSpam: true } );
		expect( screen.getByRole( 'button', { name: 'Not spam' } ) ).toBeTruthy();
		expect( screen.getByRole( 'button', { name: 'Delete permanently' } ) ).toBeTruthy();
		expect( screen.queryByRole( 'button', { name: 'Mark as read' } ) ).toBeNull();
		spam.unmount();

		const normal = bar( {} );
		expect( screen.queryByRole( 'button', { name: 'Not spam' } ) ).toBeNull();
		expect( screen.getByRole( 'button', { name: 'Mark as read' } ) ).toBeTruthy();
		normal.unmount();

		bar( { inTrash: true } );
		expect( screen.queryByRole( 'button', { name: 'Not spam' } ) ).toBeNull();
		expect( screen.getByRole( 'button', { name: 'Restore' } ) ).toBeTruthy();
	} );

	test( 'the address ?status=spam reaches the list', async () => {
		window.history.pushState( null, '', '/?page=mhm-rentiva-contact-messages&status=spam' );
		contactApi.list.mockResolvedValue( page( [ row() ] ) );
		render( <ContactMessagesApp /> );
		await screen.findByText( 'Sp Ammer' );
		expect( contactApi.list.mock.calls[ 0 ][ 0 ].status ).toBe( 'spam' );
	} );
} );

describe( 'a spam message on its own screen', () => {
	test( 'offers Not spam and delete, not the reply actions, and is not marked read', async () => {
		contactApi.get.mockResolvedValue( detail() );
		contactApi.bulk.mockResolvedValue( { results: [ { id: 7, ok: true } ] } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 7 } onBack={ onBack } /> );

		fireEvent.click( await screen.findByRole( 'button', { name: 'Not spam' } ) );
		expect( screen.getByRole( 'button', { name: 'Delete permanently' } ) ).toBeTruthy();
		expect( screen.queryByRole( 'button', { name: 'Mark unread' } ) ).toBeNull();
		expect( screen.queryByText( 'Reply by e-mail' ) ).toBeNull();
		expect( screen.getByText( 'Holds too many links' ) ).toBeTruthy();
		expect( contactApi.markRead ).not.toHaveBeenCalled();

		await waitFor( () => expect( contactApi.bulk ).toHaveBeenCalledWith( [ 7 ], 'not_spam' ) );
		await waitFor( () => expect( onBack ).toHaveBeenCalled() );
	} );

	test( 'stays on screen when the change fails', async () => {
		contactApi.get.mockResolvedValue( detail() );
		contactApi.bulk.mockResolvedValue( { results: [ { id: 7, ok: false, error: 'not_allowed' } ] } );
		const onBack = jest.fn();
		render( <ContactMessageDetail id={ 7 } onBack={ onBack } /> );

		fireEvent.click( await screen.findByRole( 'button', { name: 'Not spam' } ) );
		await screen.findByText( 'The message could not be changed.' );
		expect( onBack ).not.toHaveBeenCalled();
	} );

	test( 'a normal message keeps its actions', async () => {
		contactApi.get.mockResolvedValue( detail( { status: 'read', status_label: 'Read', fields: [] } ) );
		render( <ContactMessageDetail id={ 7 } onBack={ () => {} } /> );

		await screen.findByRole( 'button', { name: 'Mark unread' } );
		expect( screen.queryByRole( 'button', { name: 'Not spam' } ) ).toBeNull();
	} );
} );
