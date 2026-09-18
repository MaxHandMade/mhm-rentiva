import { render } from '@testing-library/react';
import StatsCards from './components/StatsCards';

const stats = { total: 11, new_this_month: 0, new_trend: 0, active_90d: 3, avg_spend: '907.27' };

describe( 'customers stats strip', () => {
	test( 'renders four kit cards with icons and no legacy class', () => {
		const { container } = render( <StatsCards stats={ stats } currency="$" /> );

		expect( container.querySelectorAll( '.mhmui-stat-card' ) ).toHaveLength( 4 );
		expect( container.querySelector( '.rv-cust-kpi' ) ).toBeNull();
		expect( container.querySelectorAll( '.dashicons' ) ).toHaveLength( 4 );
	} );

	test( 'a rising trend becomes an up delta, a falling one a down delta, each marked once by the kit', () => {
		const up = render( <StatsCards stats={ { ...stats, new_trend: 5 } } currency="$" /> );
		const upDelta = up.container.querySelector( '.mhmui-stat-card__delta--up' );
		const upMarks = up.container.querySelectorAll( '.mhmui-stat-card__delta-mark' );

		expect( upDelta ).not.toBeNull();
		// Since ui-core 0.12.0 the kit renders the direction mark itself; the
		// consumer's delta.text must no longer carry the sign, or it duplicates.
		expect( upMarks ).toHaveLength( 1 );
		expect( upDelta.textContent.replace( upMarks[ 0 ].textContent, '' ) ).toBe( '5%' );

		const down = render( <StatsCards stats={ { ...stats, new_trend: -2 } } currency="$" /> );
		const downDelta = down.container.querySelector( '.mhmui-stat-card__delta--down' );
		const downMarks = down.container.querySelectorAll( '.mhmui-stat-card__delta-mark' );

		expect( downDelta ).not.toBeNull();
		expect( downMarks ).toHaveLength( 1 );
		expect( downDelta.textContent.replace( downMarks[ 0 ].textContent, '' ) ).toBe( '2%' );
	} );

	test( 'a flat trend stays a sub line, not a delta', () => {
		const flat = render( <StatsCards stats={ stats } currency="$" /> );
		expect( flat.container.querySelector( '[class*="__delta"]' ) ).toBeNull();
		expect( flat.container.querySelector( '.mhmui-stat-card__sub' ) ).not.toBeNull();
	} );
} );
