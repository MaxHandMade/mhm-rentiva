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
		).toBe( '5% · 3 this month' );
	} );

	/*
	 * The kit renders a card's delta line OR its sub line, never both, so the
	 * this-month figure the two cards used to carry in `sub` vanished whenever a
	 * delta rendered. It is folded into the delta text instead (product decision
	 * 2026-09-18). Split the delta line into its three parts -- the aria-hidden
	 * mark, the hidden accessible label, the visible text -- and pin each one.
	 */
	const deltaParts = ( container, direction ) => {
		const delta = container.querySelector( `.mhmui-stat-card__delta--${ direction }` );
		const marks = delta.querySelectorAll( '.mhmui-stat-card__delta-mark' );
		const label = delta.querySelector( '.mhmui-stat-card__delta-sr' );
		return {
			marks,
			label: label ? label.textContent.trim() : null,
			text: delta.textContent
				.replace( marks[ 0 ].textContent, '' )
				.replace( label ? label.textContent : '', '' ),
		};
	};

	// A store money format distinct from the kit's defaults, so the assertions
	// below prove the folded amount goes through the card's own formatter.
	const withStoreFormat = ( fn ) => {
		window.mhmRentivaAdmin = { decimalSep: '.', thousandSep: ',', numDecimals: 2, currencyPosition: 'left' };
		try {
			fn();
		} finally {
			delete window.mhmRentivaAdmin;
		}
	};
	const revenueMetrics = { ...metrics, total_revenue: 98765.4, monthly_revenue: 1234.5 };

	test( 'a rising revenue delta carries the this-month amount, formatted like the card value', () => {
		withStoreFormat( () => {
			const { container } = render(
				<StatsCards
					metrics={ revenueMetrics }
					deltas={ { revenue: { direction: 'up', format: 'pct', value: 12 } } }
					currency="$"
				/>
			);
			const parts = deltaParts( container, 'up' );
			const card = container.querySelector( '.mhmui-stat-card__delta--up' ).closest( '.mhmui-stat-card' );

			expect( container.querySelectorAll( '.mhmui-stat-card__delta' ) ).toHaveLength( 1 );
			expect( card.querySelector( '.mhmui-stat-card__value' ).textContent ).toBe( '$98,765.40' );
			expect( parts.marks ).toHaveLength( 1 );
			expect( parts.marks[ 0 ].textContent ).toBe( '↑' );
			expect( parts.label ).toBe( 'increase' );
			expect( parts.text ).toBe( '12% · $1,234.50 this month' );
		} );
	} );

	test( 'a flat trend gets its own line and still carries the this-month figure', () => {
		const { container } = render(
			<StatsCards
				metrics={ metrics }
				deltas={ { bookings: { direction: 'none', format: 'pct', value: 0 } } }
				currency="$"
			/>
		);
		const parts = deltaParts( container, 'flat' );

		expect( parts.marks ).toHaveLength( 1 );
		expect( parts.marks[ 0 ].textContent ).toBe( '→' );
		expect( parts.label ).toBe( 'no change' );
		expect( parts.text ).toBe( '0% · 3 this month' );
	} );

	test( 'with no previous month to compare, the delta is the this-month figure itself, formatted like the card', () => {
		withStoreFormat( () => {
			const { container } = render(
				<StatsCards
					metrics={ revenueMetrics }
					deltas={ { revenue: { direction: 'up', format: 'abs', value: 1235 } } }
					currency="$"
				/>
			);
			const parts = deltaParts( container, 'up' );

			expect( parts.marks ).toHaveLength( 1 );
			expect( parts.label ).toBe( 'increase' );
			expect( parts.text ).toBe( '$1,234.50 this month' );
		} );
	} );

	test( 'the customers card folds no figure, and its percent goes through the catalogue', () => {
		const { container } = render(
			<StatsCards
				metrics={ metrics }
				deltas={ { customers: { direction: 'down', format: 'pct', value: 50 } } }
				currency="$"
			/>
		);
		const parts = deltaParts( container, 'down' );

		// English order in the source msgid: a hard-coded "%50" was Turkish order
		// shown to every locale.
		expect( parts.marks ).toHaveLength( 1 );
		expect( parts.label ).toBe( 'decrease' );
		expect( parts.text ).toBe( '50% this month' );
	} );

	test( 'the customers card with no previous month shows the count, formatted, with no sign of its own', () => {
		const { container } = render(
			<StatsCards
				metrics={ metrics }
				deltas={ { customers: { direction: 'up', format: 'abs', value: 1234 } } }
				currency="$"
			/>
		);
		const parts = deltaParts( container, 'up' );

		expect( parts.marks ).toHaveLength( 1 );
		expect( parts.text ).toBe( '1.234 this month' );
	} );
} );
