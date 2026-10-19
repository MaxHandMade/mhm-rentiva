/**
 * @jest-environment jsdom
 */

/**
 * The vehicle info panel (updateVehicleDisplay) inserts the AJAX features with .html().
 * feature.text is the fuel/transmission label, or the raw stored value when it has no label
 * (VehicleDataHelper returns unknown keys unchanged) -- so it must be escaped like the title
 * and status text next to it. The svg is server-built markup and stays HTML.
 */
describe( 'availability calendar: feature text is text, not markup', () => {
	let $;

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmRentivaAvailability = { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'n', strings: {}, messages: {} };
		$.post = jest.fn( () => $.Deferred().promise() );
		require( '../../assets/js/frontend/availability-calendar.js' );
	} );

	test( 'a stored value with markup is shown as text', () => {
		document.body.innerHTML = `
			<div class="rv-availability-calendar" data-vehicle-id="0" data-vehicle-price="0" data-start-month="2026-10" data-months-to-show="1">
				<div class="rv-vehicle-features"></div>
			</div>`;
		const calendar = new window.AvailabilityCalendar( $( '.rv-availability-calendar' ) );

		calendar.updateVehicleDisplay( {
			features: [
				{ svg: '<svg class="rv-icon"></svg>', text: '<img src="x" onerror="window.__rvInjected=1">' },
				{ svg: '<svg class="rv-icon"></svg>', text: 'Automatic' },
			],
		} );

		const panel = document.querySelector( '.rv-vehicle-features' );
		expect( panel.querySelector( 'img' ) ).toBeNull();
		expect( panel.querySelectorAll( '.rv-feature-text' )[ 0 ].textContent ).toBe( '<img src="x" onerror="window.__rvInjected=1">' );
		expect( panel.querySelectorAll( '.rv-feature-text' )[ 1 ].textContent ).toBe( 'Automatic' );
		expect( panel.querySelectorAll( 'svg.rv-icon' ) ).toHaveLength( 2 );
	} );
} );
