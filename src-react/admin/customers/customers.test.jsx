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
		const upLabel = up.container.querySelector( '.mhmui-stat-card__delta-sr' );

		expect( upDelta ).not.toBeNull();
		// Since ui-core 0.13.0 the kit renders the direction mark itself and the
		// consumer supplies an accessible name (delta.label) as hidden text; the
		// mark must appear exactly once -- this is what caught the duplicate-
		// arrow bug when the kit started drawing its own mark -- and the hidden
		// label must carry the direction word, or up/down announce identically
		// to a screen reader (WCAG 1.4.1).
		expect( upMarks ).toHaveLength( 1 );
		expect( upLabel ).not.toBeNull();
		expect( upLabel.textContent.trim() ).toBe( 'increase' );
		expect(
			upDelta.textContent
				.replace( upMarks[ 0 ].textContent, '' )
				.replace( upLabel.textContent, '' )
		).toBe( '5%' );

		const down = render( <StatsCards stats={ { ...stats, new_trend: -2 } } currency="$" /> );
		const downDelta = down.container.querySelector( '.mhmui-stat-card__delta--down' );
		const downMarks = down.container.querySelectorAll( '.mhmui-stat-card__delta-mark' );
		const downLabel = down.container.querySelector( '.mhmui-stat-card__delta-sr' );

		expect( downDelta ).not.toBeNull();
		expect( downMarks ).toHaveLength( 1 );
		expect( downLabel ).not.toBeNull();
		expect( downLabel.textContent.trim() ).toBe( 'decrease' );
		expect(
			downDelta.textContent
				.replace( downMarks[ 0 ].textContent, '' )
				.replace( downLabel.textContent, '' )
		).toBe( '2%' );
	} );

	test( 'a flat trend gets its own delta line, marked once by the kit, instead of losing the number', () => {
		const flat = render( <StatsCards stats={ stats } currency="$" /> );
		const flatDelta = flat.container.querySelector( '.mhmui-stat-card__delta--flat' );
		const flatMarks = flat.container.querySelectorAll( '.mhmui-stat-card__delta-mark' );
		const flatLabel = flat.container.querySelector( '.mhmui-stat-card__delta-sr' );

		// Since ui-core 0.13.0 `flat` renders its OWN delta line (not a `sub`
		// line) -- a zero trend must still print, not silently disappear.
		expect( flatDelta ).not.toBeNull();
		expect( flatDelta.getAttribute( 'data-direction' ) ).toBe( 'flat' );
		expect( flatMarks ).toHaveLength( 1 );
		expect( flatMarks[ 0 ].textContent ).toBe( '→' );
		expect( flatLabel ).not.toBeNull();
		expect( flatLabel.textContent.trim() ).toBe( 'no change' );
		expect(
			flatDelta.textContent
				.replace( flatMarks[ 0 ].textContent, '' )
				.replace( flatLabel.textContent, '' )
		).toBe( '0%' );
	} );
} );
