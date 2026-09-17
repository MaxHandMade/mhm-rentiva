import { render } from '@testing-library/react';
import StatsCards from './components/StatsCards';

const stats = { total: 11, new_this_month: 0, new_trend: '+0%', active_90d: 3, avg_spend: '907.27' };

describe( 'customers stats strip', () => {
	test( 'renders four kit cards with icons and no legacy class', () => {
		const { container } = render( <StatsCards stats={ stats } currency="$" /> );

		expect( container.querySelectorAll( '.mhmui-stat-card' ) ).toHaveLength( 4 );
		expect( container.querySelector( '.rv-cust-kpi' ) ).toBeNull();
		expect( container.querySelectorAll( '.dashicons' ) ).toHaveLength( 4 );
	} );

	test( 'a rising trend becomes an up delta, a flat one stays a sub line', () => {
		const up = render( <StatsCards stats={ { ...stats, new_trend: '+5%' } } currency="$" /> );
		expect( up.container.querySelector( '.mhmui-stat-card__delta--up' ) ).not.toBeNull();

		const flat = render( <StatsCards stats={ stats } currency="$" /> );
		expect( flat.container.querySelector( '[class*="__delta"]' ) ).toBeNull();
		expect( flat.container.querySelector( '.mhmui-stat-card__sub' ) ).not.toBeNull();
	} );
} );
