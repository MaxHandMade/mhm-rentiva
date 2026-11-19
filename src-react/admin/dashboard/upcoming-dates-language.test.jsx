import { render } from '@testing-library/react';
import UpcomingOperations from './components/UpcomingOperations';

jest.mock( '@wordpress/components', () => ( { Spinner: () => null } ), { virtual: true } );
jest.mock( '../../shared/api/rentiva', () => ( { rentivaApi: { dashboard: { getUpcoming: jest.fn() } } } ) );

/**
 * Upcoming Operations dated its rows in the BROWSER's language: an English
 * admin screen on a Turkish browser read "20 Kas". The admin page's own
 * language (<html lang>, the admin user's locale) decides, the way the
 * blocked-dates chips already do.
 */
describe( 'Upcoming Operations dates follow the admin page language', () => {
	const initial = {
		total_pages: 1,
		items: [
			{ id: 1, display_id: 7, type: 'rental', status: 'confirmed', status_label: 'Confirmed',
				vehicle_title: 'Toyota Corolla', start_date: '2026-11-20', start_time: '09:00', end_date: '2026-11-24' },
		],
	};
	const expected = ( y, m, d, lang ) => new Date( y, m - 1, d ).toLocaleDateString( lang, { day: '2-digit', month: 'short' } );

	afterEach( () => {
		document.documentElement.removeAttribute( 'lang' );
	} );

	it( 'formats the dates in the page language, not the browser language', () => {
		document.documentElement.setAttribute( 'lang', 'de-DE' );
		const { container } = render( <UpcomingOperations initial={ initial } /> );
		const text = container.querySelector( '.mhm-upcoming-ops__dates' ).textContent;
		expect( text ).toContain( expected( 2026, 11, 20, 'de-DE' ) + ' 09:00' );
		expect( text ).toContain( expected( 2026, 11, 24, 'de-DE' ) );
	} );

	it( 'survives a WordPress locale that is not a valid language tag', () => {
		// Core ships pt_PT_ao90; <html lang> becomes "pt-PT-ao90", which
		// Intl rejects with a RangeError. The widget must still render.
		document.documentElement.setAttribute( 'lang', 'pt-PT-ao90' );
		const { container } = render( <UpcomingOperations initial={ initial } /> );
		expect( container.querySelector( '.mhm-upcoming-ops__dates' ).textContent ).toContain( expected( 2026, 11, 20, 'pt-PT' ) );
	} );

	it( 'reads a date-only value as that calendar day, not as UTC midnight', () => {
		document.documentElement.setAttribute( 'lang', 'en-US' );
		const { container } = render( <UpcomingOperations initial={ initial } /> );
		expect( container.querySelector( '.mhm-upcoming-ops__dates' ).textContent ).toContain( expected( 2026, 11, 20, 'en-US' ) );
	} );
} );
