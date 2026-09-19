import { render } from '@testing-library/react';
import StatsBar from './components/StatsBar';

describe( 'shortcode pages stats bar', () => {
	test( 'renders three kit cards with icons and drops the legacy classes', () => {
		const { container } = render( <StatsBar stats={ { total: 26, active: 24, missing: 2 } } /> );

		expect( container.querySelectorAll( '.mhmui-stat-card' ) ).toHaveLength( 3 );
		expect( container.querySelector( '.rv-scp-kpi' ) ).toBeNull();
		expect( container.querySelectorAll( '.dashicons' ) ).toHaveLength( 3 );
		expect( container.querySelector( '[class*="mhmui-stat-card--"]' ) ).toBeNull();
	} );

	test( 'renders nothing before the first REST answer', () => {
		const { container } = render( <StatsBar stats={ null } /> );

		expect( container.firstChild ).toBeNull();
	} );
} );
