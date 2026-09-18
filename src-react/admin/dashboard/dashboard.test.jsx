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
		const label = container.querySelector( '.mhmui-stat-card__delta-sr' );

		expect( delta ).not.toBeNull();
		// Since ui-core 0.13.0 the kit renders the direction mark itself and the
		// consumer supplies an accessible name (delta.label) as hidden text --
		// so delta.textContent now carries mark + hidden label + plain text.
		// The mark must appear exactly once -- this is what caught the
		// duplicate-arrow bug when the kit started drawing its own mark -- and
		// the hidden label must carry the direction word, or up/down announce
		// identically to a screen reader (WCAG 1.4.1).
		expect( marks ).toHaveLength( 1 );
		expect( label ).not.toBeNull();
		expect( label.textContent.trim() ).toBe( 'increase' );
		expect(
			delta.textContent
				.replace( marks[ 0 ].textContent, '' )
				.replace( label.textContent, '' )
		).toBe( '%5 this month' );
	} );
} );
