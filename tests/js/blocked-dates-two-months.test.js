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
		const fp = ( selector, opts ) => {
			options = opts;
			return { setDate() {}, clear() {}, destroy() {}, selectedDates: [] };
		};
		fp.formatDate = ( d ) => d.toISOString().slice( 0, 10 );
		global.flatpickr = fp;
		window.flatpickr = fp;
		window.mhmBlockedDatesL10n = {};
		window.matchMedia = ( query ) => ( { matches: desktop && query.includes( 'min-width' ), media: query } );

		document.body.innerHTML = `
			<form id="post">
				<div class="blocked-dates-body">
					<div class="blocked-dates-calendar-col"><div id="mhmrentiva_blocked_dates_picker"></div></div>
				</div>
				<span id="mhm-blocked-count-badge"><span id="mhm-blocked-count-num"></span></span>
				<button type="button" id="mhm-clear-all-blocked"></button>
				<div id="mhm-blocked-dates-chips"><div id="mhm-blocked-empty"></div></div>
				<input type="hidden" id="mhmrentiva_blocked_dates_value" value="[]">
				<input type="hidden" id="mhmrentiva_blocked_dates_notes_value">
			</form>`;
		Object.defineProperty( document.querySelector( '.blocked-dates-body' ), 'clientWidth', { value: boxWidth } );

		require( '../../assets/js/admin/blocked-dates.js' );
		await new Promise( ( r ) => $( r ) );
		return { $, options };
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
} );
