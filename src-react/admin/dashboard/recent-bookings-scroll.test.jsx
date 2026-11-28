import { render } from '@testing-library/react';

jest.mock( '@wordpress/components', () => ( { Spinner: () => null } ), { virtual: true } );
jest.mock( '../../shared/api/rentiva', () => ( { rentivaApi: { dashboard: { getRecentBookings: jest.fn() } } } ) );

import RecentBookings from './components/RecentBookings';

const initial = {
	items: [
		{ id: 7, vehicle_title: 'Volkswagen Golf 1.5 TSI R-Line', customer_name: 'Mehmet Kaya', pickup_date: '28 Kas 2026', total_price: 12500, status: 'confirmed' },
	],
	total_pages: 1,
};

describe( 'recent bookings table on a narrow screen', () => {
	test( 'the table scrolls inside the kit scroll box, not the page', () => {
		const { container } = render(
			<RecentBookings initial={ initial } metrics={ {} } currency="₺" adminUrl="/wp-admin/" />
		);

		const table = container.querySelector( 'table.rv-bookings-table' );

		expect( table ).not.toBeNull();
		expect( table.parentElement.classList.contains( 'mhmui-table-wrap' ) ).toBe( true );
	} );
} );
