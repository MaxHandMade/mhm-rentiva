/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR G (K12): the manual booking screen posts `addon_qty[<id>]` for
 * the ticked add-ons that have a quantity field, and a quantity change asks
 * for a new price.
 */
describe( 'manual booking add-on quantities', () => {
	let $;

	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = `
			<div class="mhm-manual-booking-form">
				<select id="mhmrentiva_manual_vehicle_id"><option value="5" selected>Car</option></select>
				<input id="mhmrentiva_manual_pickup_date" value="2099-04-01">
				<input id="mhmrentiva_manual_pickup_time" value="10:00">
				<input id="mhmrentiva_manual_dropoff_date" value="2099-04-03">
				<input id="mhmrentiva_manual_dropoff_time" value="10:00">
				<select id="mhmrentiva_manual_payment_type"><option value="full" selected>Full</option></select>
				<label><input type="checkbox" class="mhm-addon-checkbox" value="11" data-price="10" checked>
					<input type="number" class="mhm-addon-qty" data-addon-id="11" value="3"></label>
				<label><input type="checkbox" class="mhm-addon-checkbox" value="12" data-price="10">
					<input type="number" class="mhm-addon-qty" data-addon-id="12" value="2"></label>
				<button id="mhm-calculate-price">Calculate</button>
			</div>`;
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmManualBooking = { ajaxUrl: '/ajax', nonce: 'n', text: {}, currency: '' };
		global.mhmManualBooking = window.mhmManualBooking;
		$.ajax = jest.fn();
		require( '../../assets/js/admin/manual-booking-meta.js' );
	} );

	const lastData = () => $.ajax.mock.calls[ $.ajax.mock.calls.length - 1 ][ 0 ].data;

	test( 'price request sends addon_qty for ticked add-ons only', async () => {
		await new Promise( ( resolve ) => $( resolve ) );
		$( '#mhmrentiva_manual_pickup_date' ).val( '2099-04-01' );
		$( '#mhmrentiva_manual_dropoff_date' ).val( '2099-04-03' );

		$( '#mhm-calculate-price' ).trigger( 'click' );

		const data = lastData();
		expect( data.selected_addons ).toEqual( [ '11' ] );
		expect( data.addon_qty ).toEqual( { 11: 3 } );
	} );

	test( 'quantity change re-prices', async () => {
		await new Promise( ( resolve ) => $( resolve ) );
		$( '#mhmrentiva_manual_pickup_date' ).val( '2099-04-01' );
		$( '#mhmrentiva_manual_dropoff_date' ).val( '2099-04-03' );
		$.ajax.mockClear();

		$( '.mhm-addon-qty[data-addon-id="11"]' ).val( '2' ).trigger( 'change' );

		expect( $.ajax ).toHaveBeenCalled();
		expect( lastData().addon_qty ).toEqual( { 11: 2 } );
	} );
} );
