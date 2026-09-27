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

jest.mock( '../../shared/api/rentiva', () => ( {
	rentivaApi: { customers: { getDetail: jest.fn() } },
} ) );

describe( 'customer panel booking rows', () => {
	test( 'a booking outside the total names its status and strikes its amount; a counted one does neither', async () => {
		const { rentivaApi } = require( '../../shared/api/rentiva' );
		const CustomerPanel  = require( './components/CustomerPanel' ).default;
		const { findByText } = require( '@testing-library/react' ).screen;

		rentivaApi.customers.getDetail.mockResolvedValue( {
			recent_bookings: [
				{ id: 1, reference: 'BK-000001', vehicle: 'BMW', date: '16.08.2026', amount: '$6,185.00', status: 'cancelled', status_label: 'Cancelled', counted: false },
				{ id: 2, reference: 'BK-000002', vehicle: 'Clio', date: '17.08.2026', amount: '$100.00', status: 'confirmed', status_label: 'Confirmed', counted: true },
			],
		} );

		const row = { id: 7, name: 'Akif', email: 'a@example.com', status: 'none', booking_count: 2, total_spent: '$100.00' };
		render( <CustomerPanel panelId={ 7 } row={ row } adminUrl="/wp-admin/" onClose={ () => {} } /> );

		expect( await findByText( 'Cancelled' ) ).toBeTruthy();

		const cancelledAmount = await findByText( '$6,185.00' );
		const countedAmount   = await findByText( '$100.00', { selector: '.rv-cust-panel__booking-amount' } );
		expect( cancelledAmount.className ).toContain( 'is-uncounted' );
		expect( countedAmount.className ).not.toContain( 'is-uncounted' );
		expect( document.body.textContent ).not.toContain( 'Confirmed' );
	} );
} );

describe( 'customer badges', () => {
	test( 'a badge with a url is a link whose click does not open the row; one without is text', () => {
		const CustomerBadges = require( './components/CustomerBadges' ).default;
		const onRow          = jest.fn();
		const { container }  = render(
			<div onClick={ onRow }>
				<CustomerBadges badges={ [
					{ key: 'vendor', label: 'Vendor', url: 'http://example.org/wp-admin/admin.php?page=mhm-rentiva-vendors&tab=vendors&vendor=150' },
					{ key: 'other', label: 'Other', url: '' },
				] } />
			</div>
		);

		const link = container.querySelector( 'a.rv-cust-badge.is-badge-vendor' );
		expect( link ).not.toBeNull();
		expect( link.textContent ).toBe( 'Vendor' );
		link.dispatchEvent( new MouseEvent( 'click', { bubbles: true, cancelable: true } ) );
		expect( onRow ).not.toHaveBeenCalled();

		expect( container.querySelector( 'span.rv-cust-badge.is-badge-other' ).textContent ).toBe( 'Other' );
	} );

	test( 'no badges renders nothing', () => {
		const CustomerBadges = require( './components/CustomerBadges' ).default;
		const { container }  = render( <CustomerBadges badges={ [] } /> );
		expect( container.innerHTML ).toBe( '' );
	} );
} );
