import { render } from '@testing-library/react';
import StatsCards from './components/StatsCards';

const metrics = {
	total_bookings: 42,
	bookings_this_month: 3,
	total_revenue: 900,
	monthly_revenue: 120,
	available_vehicles: 6,
	total_vehicles: 7,
	total_customers_this_month: 2,
};

describe( 'dashboard stats strip', () => {
	test( 'renders kit cards, never the legacy shared card', () => {
		const { container } = render(
			<StatsCards metrics={ metrics } deltas={ {} } currency="$" />
		);

		expect( container.querySelectorAll( '.mhmui-stat-card' ) ).toHaveLength( 4 );
		expect( container.querySelector( '.mhm-stat-card' ) ).toBeNull();
		expect( container.querySelector( '.mhmui-stats-grid' ).style.getPropertyValue( '--mhmui-columns' ) ).toBe( '4' );
	} );

	test( 'every card carries an icon (K2) and none carries a tone (K3)', () => {
		const { container } = render(
			<StatsCards metrics={ metrics } deltas={ {} } currency="$" />
		);

		expect( container.querySelectorAll( '.dashicons' ) ).toHaveLength( 4 );
		expect( container.querySelector( '[class*="mhmui-stat-card--"]' ) ).toBeNull();
	} );

	test( 'a percentage delta becomes the kit delta line in its direction, marked once by the kit', () => {
		const { container } = render(
			<StatsCards
				metrics={ metrics }
				deltas={ { bookings: { direction: 'up', format: 'pct', value: 5 } } }
				currency="$"
			/>
		);
		const delta = container.querySelector( '.mhmui-stat-card__delta--up' );
		const marks = container.querySelectorAll( '.mhmui-stat-card__delta-mark' );

		expect( delta ).not.toBeNull();
		// Since ui-core 0.12.0 the kit renders the direction mark itself; the
		// consumer's delta.text must be plain, or the arrow appears twice.
		expect( marks ).toHaveLength( 1 );
		expect( delta.textContent.replace( marks[ 0 ].textContent, '' ) ).toBe( '%5 this month' );
	} );
} );
