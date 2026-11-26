import { render } from '@testing-library/react';
import DashboardPage from './DashboardPage';
import StatsCards from './components/StatsCards';

jest.mock(
	'@wordpress/components',
	() => ( { Spinner: () => require( '@wordpress/element' ).createElement( 'span', { 'data-testid': 'spinner' } ) } ),
	{ virtual: true }
);
jest.mock( '@wordpress/api-fetch', () => jest.fn( () => Promise.resolve( {} ) ), { virtual: true } );

/**
 * Manager-lens #3 (product decisions 2026-11-17):
 * - the disabled "Last 30 days" selector promised a range the cards never
 *   used -- it is gone;
 * - the money card is the value of booked rentals, not revenue collected,
 *   and says so.
 */
describe( 'dashboard says what its figures are', () => {
	beforeEach( () => {
		window.mhmRentivaDashboard = {
			metrics: { total_bookings: 1, bookings_this_month: 1, total_revenue: 10, monthly_revenue: 10 },
			payments_summary: { pending_total: 0, deposit_blocked: 0 },
		};
		global.fetch = jest.fn( () => Promise.resolve( { ok: true, json: () => Promise.resolve( {} ) } ) );
	} );

	afterEach( () => {
		delete window.mhmRentivaDashboard;
	} );

	test( 'no date range selector, and no title of its own (PHP prints it)', () => {
		const { container } = render( <DashboardPage /> );

		expect( container.querySelector( 'select' ) ).toBeNull();
		expect( container.querySelector( 'h1' ) ).toBeNull();
		expect( container.textContent ).not.toContain( 'Last 30 days' );
	} );

	test( 'the money card is the bookings value, not revenue', () => {
		const { container } = render( <StatsCards metrics={ { total_revenue: 10, monthly_revenue: 10 } } deltas={ {} } currency="$" /> );

		expect( container.textContent ).toContain( 'Bookings Value' );
		expect( container.textContent ).not.toContain( 'Total Revenue' );
	} );
} );
