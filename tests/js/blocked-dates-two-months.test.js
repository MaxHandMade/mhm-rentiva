/**
 * @jest-environment jsdom
 */
/**
 * The blocked-dates calendar shows this month and the next on a desktop
 * screen when the box is wide enough for two months, and one month on a
 * phone or in a narrow box.
 */
describe( 'blocked dates: one or two months', () => {
	const mount = async ( { desktop, boxWidth } ) => {
		jest.resetModules();
		const $ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;

		let options = null;
		const calls = [];
		const fp = ( selector, opts ) => {
			options = opts;
			calls.push( { showMonths: opts.showMonths, defaultDate: opts.defaultDate.slice() } );
			return { setDate() {}, clear() {}, destroy: jest.fn(), selectedDates: [] };
		};
		fp.formatDate = ( d ) => d.toISOString().slice( 0, 10 );
		global.flatpickr = fp;
		window.flatpickr = fp;
		window.mhmBlockedDatesL10n = {};
		const screen = { desktop };
		window.matchMedia = ( query ) => ( { matches: screen.desktop && query.includes( 'min-width' ), media: query } );

		document.body.innerHTML = `
			<form id="post">
				<div class="blocked-dates-body">
					<div class="blocked-dates-calendar-col"><div id="mhmrentiva_blocked_dates_picker"></div></div>
				</div>
				<span id="mhm-blocked-count-badge"><span id="mhm-blocked-count-num"></span></span>
				<button type="button" id="mhm-clear-all-blocked"></button>
				<div id="mhm-blocked-dates-chips"><div id="mhm-blocked-empty"></div></div>
				<input type="hidden" id="mhmrentiva_blocked_dates_value" value='["2099-12-01"]'>
				<input type="hidden" id="mhmrentiva_blocked_dates_notes_value">
			</form>`;
		const box = { width: boxWidth };
		Object.defineProperty( document.querySelector( '.blocked-dates-body' ), 'clientWidth', { get: () => box.width } );

		require( '../../assets/js/admin/blocked-dates.js' );
		await new Promise( ( r ) => $( r ) );
		return { $, options, calls, screen, box };
	};

	const resize = async ( $ ) => {
		jest.useFakeTimers();
		$( window ).trigger( 'resize' );
		jest.advanceTimersByTime( 200 );
		jest.useRealTimers();
	};

	it( 'shows two months on a desktop screen with a wide box', async () => {
		const { $, options } = await mount( { desktop: true, boxWidth: 1000 } );
		expect( options.showMonths ).toBe( 2 );
		expect( $( '.blocked-dates-calendar-col' ).hasClass( 'is-two-months' ) ).toBe( true );
	} );

	it( 'shows one month on a phone', async () => {
		const { $, options } = await mount( { desktop: false, boxWidth: 1000 } );
		expect( options.showMonths ).toBe( 1 );
		expect( $( '.blocked-dates-calendar-col' ).hasClass( 'is-two-months' ) ).toBe( false );
	} );

	it( 'shows one month when the box is narrower than two months', async () => {
		const { options } = await mount( { desktop: true, boxWidth: 500 } );
		expect( options.showMonths ).toBe( 1 );
	} );

	it( 'goes back to one month when the window narrows, keeping the selection', async () => {
		const { $, calls, screen } = await mount( { desktop: true, boxWidth: 1000 } );
		screen.desktop = false;
		await resize( $ );
		expect( calls.map( ( c ) => c.showMonths ) ).toEqual( [ 2, 1 ] );
		expect( calls[ 1 ].defaultDate ).toEqual( [ '2099-12-01' ] );
		expect( $( '.blocked-dates-calendar-col' ).hasClass( 'is-two-months' ) ).toBe( false );
	} );

	it( 'does not redraw when the month count stays the same', async () => {
		const { $, calls, box } = await mount( { desktop: true, boxWidth: 1000 } );
		box.width = 900;
		await resize( $ );
		expect( calls ).toHaveLength( 1 );
	} );
} );
