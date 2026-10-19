import { render } from '@testing-library/react';
import { setLocaleData, resetLocaleData } from '@wordpress/i18n';
import StatsCards from './components/StatsCards';

/**
 * Plan 0.4: the card sub-lines glued a number in front of a translated phrase
 * (`${n} ${__('this month')}`), so Turkish read "5 bu ay" / "7 toplam" and no
 * translator could move the number. They are one sprintf pattern each now.
 */
describe( 'dashboard sub-lines follow the translation word order', () => {
	afterEach( () => resetLocaleData( undefined, 'mhm-rentiva' ) );

	test( 'the number goes where the translation puts it', () => {
		setLocaleData(
			{
				'': {},
				'dashboard KPI delta line\u0004%s this month': [ 'bu ay %s' ],
				'%s total': [ 'toplam %s' ],
			},
			'mhm-rentiva'
		);

		const { container } = render(
			<StatsCards
				metrics={ { total_bookings: 42, bookings_this_month: 5, total_revenue: 0, monthly_revenue: 0, available_vehicles: 6, total_vehicles: 7, total_customers_this_month: 2 } }
				deltas={ {} }
				currency="$"
			/>
		);
		const text = container.textContent;

		expect( text ).toContain( 'bu ay 5' );
		expect( text ).not.toContain( '5 bu ay' );
		expect( text ).toContain( 'toplam 7' );
		expect( text ).not.toContain( '7 toplam' );
	} );
} );
