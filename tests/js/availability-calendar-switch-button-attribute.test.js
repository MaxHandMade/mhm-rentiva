/**
 * @jest-environment jsdom
 */

/**
 * getSwitchVehicleButtonHtml() rebuilt the "Choose another vehicle" button from the existing
 * button's data-vehicles. .attr() returns the decoded JSON, and it was written back into a
 * single-quoted attribute raw -- a vehicle title with an apostrophe (Pro: vendors set it) broke
 * the attribute (and the JSON) and could add attributes. The rebuilt button must carry the same
 * JSON and nothing else.
 */
describe( 'availability calendar: switch-vehicle button keeps its data', () => {
	let $;

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmRentivaAvailability = { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'n', strings: {}, messages: {} };
		$.post = jest.fn( () => $.Deferred().promise() );
		require( '../../assets/js/frontend/availability-calendar.js' );
	} );

	test( 'a title with quotes survives the rebuild as data', () => {
		const vehicles = JSON.stringify( [
			{ id: 7, title: "Driver's x' autofocus onfocus='window.__rvInjected=1' x='" },
			{ id: 8, title: 'Plain "quoted" title' },
		] );
		document.body.innerHTML = '<div class="rv-availability-calendar" data-vehicle-id="7" data-vehicle-price="0" data-start-month="2026-10" data-months-to-show="1"><button class="rv-switch-vehicle-btn"></button></div>';
		$( '.rv-switch-vehicle-btn' ).attr( 'data-vehicles', vehicles );
		const calendar = new window.AvailabilityCalendar( $( '.rv-availability-calendar' ) );

		const holder = document.createElement( 'div' );
		holder.innerHTML = calendar.getSwitchVehicleButtonHtml();
		const rebuilt = holder.querySelector( 'button' );

		expect( rebuilt ).not.toBeNull();
		expect( rebuilt.getAttribute( 'data-vehicles' ) ).toBe( vehicles );
		expect( rebuilt.hasAttribute( 'onfocus' ) ).toBe( false );
		expect( rebuilt.hasAttribute( 'autofocus' ) ).toBe( false );
		expect( JSON.parse( rebuilt.getAttribute( 'data-vehicles' ) )[ 0 ].id ).toBe( 7 );
	} );
} );
