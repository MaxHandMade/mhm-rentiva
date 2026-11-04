import { render, fireEvent, screen, waitFor, within } from '@testing-library/react';

/**
 * The customers table on the ui-core 0.19 table pieces (spec v2.1 §7): a
 * caption names it, every column header has scope="col", aria-sort sits on
 * the sorted column only, the sort control is a real button, and selection
 * comes from the kit (mixed header box, no is-checked class on the row).
 */
jest.mock( '../../shared/api/rentiva', () => ( {
	rentivaApi: {
		customers: {
			getList: jest.fn(),
			bulkDelete: jest.fn( () => Promise.resolve( {} ) ),
		},
	},
} ) );

const ROWS = [
	{ id: 1, name: 'Ayşe Kaya', email: 'a@x.test', phone: '1', booking_count: 3, total_spent: '$30', status: 'active', badges: [] },
	{ id: 2, name: 'Burak Demir', email: 'b@x.test', phone: '2', booking_count: 1, total_spent: '$10', status: 'new', badges: [] },
	{ id: 3, name: 'Cem Ak', email: 'c@x.test', phone: '3', booking_count: 0, total_spent: '$0', status: 'vip', badges: [] },
];

function renderTable( props = {} ) {
	const CustomerTable = require( './components/CustomerTable' ).default;
	const handlers = {
		onSort: jest.fn(),
		onToggle: jest.fn(),
		onSelectAll: jest.fn(),
		onRowClick: jest.fn(),
	};
	const view = render(
		<CustomerTable
			items={ ROWS }
			sort={ { key: 'bookings', direction: 'desc' } }
			isSelected={ () => false }
			selectState="none"
			panelId={ null }
			{ ...handlers }
			{ ...props }
		/>
	);
	return { ...view, ...handlers };
}

describe( 'customer table semantics', () => {
	test( 'a hidden caption names the table; the screen class stays on it', () => {
		const { container } = renderTable();
		const table = container.querySelector( 'table' );
		const caption = table.querySelector( 'caption' );

		expect( caption.textContent ).toBe( 'Customers' );
		expect( caption.classList.contains( 'mhmui-table__caption--hidden' ) ).toBe( true );
		expect( table.classList.contains( 'rv-cust-table' ) ).toBe( true );
		expect( table.classList.contains( 'mhmui-table' ) ).toBe( true );
	} );

	test( 'every column header is scope=col; aria-sort only on the sorted column', () => {
		const { container } = renderTable();
		const heads = Array.from( container.querySelectorAll( 'thead th' ) );

		expect( heads ).toHaveLength( 6 );
		heads.forEach( ( th ) => expect( th.getAttribute( 'scope' ) ).toBe( 'col' ) );

		const bookings = screen.getByRole( 'button', { name: 'Bookings' } ).closest( 'th' );
		expect( bookings.getAttribute( 'aria-sort' ) ).toBe( 'descending' );
		[ 'Customer', 'Total Spent' ].forEach( ( name ) => {
			expect( screen.getByRole( 'button', { name } ).closest( 'th' ).hasAttribute( 'aria-sort' ) ).toBe( false );
		} );
	} );

	test( 'the sort control is a button that asks for its column', () => {
		const { onSort } = renderTable();
		fireEvent.click( screen.getByRole( 'button', { name: 'Customer' } ) );
		expect( onSort ).toHaveBeenCalledWith( 'name' );
	} );

	test( 'a partial selection shows a mixed header box; a click asks for all', () => {
		const { onSelectAll } = renderTable( { selectState: 'some', isSelected: ( id ) => id === 2 } );
		const all = screen.getByRole( 'checkbox', { name: 'Select all customers' } );

		expect( all.indeterminate ).toBe( true );
		expect( all.checked ).toBe( false );
		fireEvent.click( all );
		expect( onSelectAll ).toHaveBeenCalledWith( true );
	} );

	test( 'a row box toggles that row without opening it; the row carries no is-checked class', () => {
		const { onToggle, onRowClick } = renderTable( { selectState: 'some', isSelected: ( id ) => id === 2 } );
		const box = screen.getByRole( 'checkbox', { name: 'Burak Demir' } );

		expect( box.checked ).toBe( true );
		expect( box.closest( 'tr' ).classList.contains( 'is-checked' ) ).toBe( false );
		fireEvent.click( box );
		expect( onToggle ).toHaveBeenCalledWith( 2 );
		expect( onRowClick ).not.toHaveBeenCalled();
	} );
} );

describe( 'customers page sorting and selection', () => {
	beforeEach( () => {
		const { rentivaApi } = require( '../../shared/api/rentiva' );
		rentivaApi.customers.getList.mockReset();
		rentivaApi.customers.getList.mockImplementation( ( { page } ) => Promise.resolve( {
			items: page === 2 ? [ { ...ROWS[ 2 ], id: 4 } ] : ROWS.slice( 0, 2 ),
			total_pages: 2,
		} ) );
	} );

	test( 'a new column sorts descending first, a second click ascending', async () => {
		const { rentivaApi } = require( '../../shared/api/rentiva' );
		const CustomersPage = require( './CustomersPage' ).default;
		render( <CustomersPage /> );

		fireEvent.click( await screen.findByRole( 'button', { name: 'Customer' } ) );
		await waitFor( () => expect( rentivaApi.customers.getList ).toHaveBeenLastCalledWith(
			expect.objectContaining( { sort_by: 'name', sort_dir: 'desc', page: 1 } )
		) );

		fireEvent.click( await screen.findByRole( 'button', { name: 'Customer' } ) );
		await waitFor( () => expect( rentivaApi.customers.getList ).toHaveBeenLastCalledWith(
			expect.objectContaining( { sort_by: 'name', sort_dir: 'asc' } )
		) );
	} );

	test( 'a new sort clears the selection even when the same rows come back', async () => {
		// The rows' ids do not change, so the kit's pruning keeps them; only
		// the page's clear() on a new request empties the selection.
		const CustomersPage = require( './CustomersPage' ).default;
		const { container } = render( <CustomersPage /> );

		fireEvent.click( await screen.findByRole( 'checkbox', { name: 'Ayşe Kaya' } ) );
		await waitFor( () => expect( container.querySelector( '.rv-cust-bulk-bar' ) ).not.toBeNull() );

		fireEvent.click( screen.getByRole( 'button', { name: 'Customer' } ) );
		await screen.findByRole( 'checkbox', { name: 'Ayşe Kaya' } );

		await waitFor( () => expect( container.querySelector( '.rv-cust-bulk-bar' ) ).toBeNull() );
		expect( screen.getByRole( 'checkbox', { name: 'Ayşe Kaya' } ).checked ).toBe( false );
	} );

	test( 'moving to the next page carries no selection over', async () => {
		const CustomersPage = require( './CustomersPage' ).default;
		const { container } = render( <CustomersPage /> );

		fireEvent.click( await screen.findByRole( 'checkbox', { name: 'Ayşe Kaya' } ) );
		await waitFor( () => expect( container.querySelector( '.rv-cust-bulk-bar' ) ).not.toBeNull() );

		fireEvent.click( screen.getByRole( 'button', { name: 'Next →' } ) );
		await screen.findByRole( 'checkbox', { name: 'Cem Ak' } );

		expect( container.querySelector( '.rv-cust-bulk-bar' ) ).toBeNull();
		const all = within( container.querySelector( 'thead' ) ).getByRole( 'checkbox' );
		expect( all.checked ).toBe( false );
		expect( all.indeterminate ).toBe( false );
	} );
} );
