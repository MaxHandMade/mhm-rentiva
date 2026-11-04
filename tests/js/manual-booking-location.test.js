/**
 * @jest-environment jsdom
 */

/**
 * Admin screens PR 2b: the new customer's optional country and state (spec
 * K6). The state control follows the chosen country the way WooCommerce
 * checkout does -- a list is a select, an empty list hides the field, no list
 * is free text -- and a state never travels with a country it does not belong to.
 */
describe( 'manual booking customer location', () => {
	let $;

	const locations = () => ( {
		base: 'TR',
		countries: { TR: 'Turkey', DE: 'Germany', FR: 'France', GB: 'United Kingdom' },
		states: {
			TR: { TR34: 'İstanbul', TR06: 'Ankara' },
			DE: { 'DE-BE': 'Berlin', 'DE-BY': 'Bavaria' },
		},
		noState: [ 'FR' ],
		labels: { state: 'State', region: 'State / region', regionPlaceholder: 'State or region', selectState: 'Select a state...' },
	} );

	const markup = () => `
		<div class="mhm-manual-booking-form">
			<select id="mhmrentiva_manual_vehicle_id"><option value="1" selected data-price="100">A</option></select>
			<select id="mhmrentiva_manual_customer_id">
				<option value="7" selected>Ayşe</option><option value="new_customer">+</option>
			</select>
			<div id="mhmrentiva_new_customer_fields" class="mhm-hidden">
				<input id="mhmrentiva_new_customer_first_name" value="Mehmet">
				<input id="mhmrentiva_new_customer_last_name" value="Demir">
				<input id="mhmrentiva_new_customer_email" value="m@example.com">
				<input id="mhmrentiva_new_customer_phone" value="0532">
				<div class="mhm-field-group">
					<select id="mhmrentiva_new_customer_country" name="mhmrentiva_new_customer_country">
						<option value="">Select a country...</option>
						<option value="TR" selected>Turkey</option><option value="DE">Germany</option>
						<option value="FR">France</option><option value="GB">United Kingdom</option>
					</select>
				</div>
				<div id="mhmrentiva_new_customer_state_field" class="mhm-field-group">
					<label for="mhmrentiva_new_customer_state"><span id="mhmrentiva_new_customer_state_label">State</span> <span class="mhm-field-optional">(optional)</span></label>
					<select id="mhmrentiva_new_customer_state" name="mhmrentiva_new_customer_state">
						<option value="">Select a state...</option><option value="TR34">İstanbul</option><option value="TR06">Ankara</option>
					</select>
				</div>
			</div>
			<input id="mhmrentiva_manual_pickup_date"><input id="mhmrentiva_manual_pickup_time">
			<input id="mhmrentiva_manual_dropoff_date"><input id="mhmrentiva_manual_dropoff_time">
			<select id="mhmrentiva_manual_payment_type"><option value="deposit" selected>D</option></select>
			<select id="mhmrentiva_manual_payment_method"><option value="offline" selected>O</option></select>
			<select id="mhmrentiva_manual_booking_status"><option value="confirmed" selected>C</option></select>
			<textarea id="mhmrentiva_manual_notes"></textarea>
			<div class="mhm-booking-messages"></div>
			<div class="mhm-price-calculation mhm-hidden"><div class="mhm-price-details"></div></div>
			<button id="mhm-calculate-price">Calculate</button>
			<button id="mhm-create-booking">Create</button>
		</div>`;

	const load = ( data = locations() ) => {
		if ( $ ) {
			$( document ).off();
		}
		jest.resetModules();
		document.body.innerHTML = markup();
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmManualBooking = { ajaxUrl: '/ajax', nonce: 'n', text: {}, currency: '', currencyFormat: {} };
		global.mhmManualBooking = window.mhmManualBooking;
		window.mhmManualBookingLocations = data;
		$.ajax = jest.fn();
		require( '../../assets/js/admin/manual-booking-meta.js' );
		return new Promise( ( resolve ) => $( resolve ) );
	};

	const pickCountry = ( code ) => $( '#mhmrentiva_new_customer_country' ).val( code ).trigger( 'change' );
	const chooseNewCustomer = () => $( '#mhmrentiva_manual_customer_id' ).val( 'new_customer' ).trigger( 'change' );
	const createData = () => {
		$( '#mhmrentiva_manual_pickup_date' ).val( '2026-12-27' );
		$( '#mhmrentiva_manual_dropoff_date' ).val( '2026-12-29' );
		$.ajax.mockClear();
		$( '#mhm-create-booking' ).trigger( 'click' );
		const call = $.ajax.mock.calls.find( ( c ) => c[ 0 ].data && c[ 0 ].data.action === 'mhmrentiva_create_manual_booking' );
		return call ? call[ 0 ].data : null;
	};

	test( 'TR to DE swaps the state list and clears the choice', async () => {
		await load();
		$( '#mhmrentiva_new_customer_state' ).val( 'TR34' );
		pickCountry( 'DE' );

		const $state = $( '#mhmrentiva_new_customer_state' );
		expect( $state.is( 'select' ) ).toBe( true );
		expect( $state.find( 'option[value="DE-BE"]' ).length ).toBe( 1 );
		expect( $state.find( 'option[value="TR34"]' ).length ).toBe( 0 );
		expect( $state.val() ).toBe( '' );
		expect( $( '#mhmrentiva_new_customer_state_label' ).text() ).toBe( 'State' );
	} );

	test( 'DE to FR hides the state field and sends no state', async () => {
		await load();
		chooseNewCustomer();
		pickCountry( 'DE' );
		$( '#mhmrentiva_new_customer_state' ).val( 'DE-BE' );
		pickCountry( 'FR' );

		expect( $( '#mhmrentiva_new_customer_state_field' ).hasClass( 'mhm-hidden' ) ).toBe( true );
		const data = createData();
		expect( data.new_customer_country ).toBe( 'FR' );
		expect( data.new_customer_state ).toBe( '' );
	} );

	test( 'FR to GB shows an empty free-text field with the region label', async () => {
		await load();
		pickCountry( 'FR' );
		pickCountry( 'GB' );

		const $state = $( '#mhmrentiva_new_customer_state' );
		expect( $( '#mhmrentiva_new_customer_state_field' ).hasClass( 'mhm-hidden' ) ).toBe( false );
		expect( $state.is( 'input[type="text"]' ) ).toBe( true );
		expect( $state.attr( 'name' ) ).toBe( 'mhmrentiva_new_customer_state' );
		expect( $state.val() ).toBe( '' );
		expect( $state.attr( 'placeholder' ) ).toBe( 'State or region' );
		expect( $( '#mhmrentiva_new_customer_state_label' ).text() ).toBe( 'State / region' );
		expect( $( '.mhm-field-optional' ).length ).toBe( 1 );
	} );

	test( 'a state picked for TR is never sent with DE', async () => {
		await load();
		chooseNewCustomer();
		$( '#mhmrentiva_new_customer_state' ).val( 'TR34' );
		pickCountry( 'DE' );

		const data = createData();
		expect( data.new_customer_country ).toBe( 'DE' );
		expect( data.new_customer_state ).toBe( '' );
	} );

	test( 'the chosen country and state travel with a new customer', async () => {
		await load();
		chooseNewCustomer();
		$( '#mhmrentiva_new_customer_state' ).val( 'TR34' );

		const data = createData();
		expect( data.new_customer_country ).toBe( 'TR' );
		expect( data.new_customer_state ).toBe( 'TR34' );
	} );

	test( 'an existing customer sends no country or state', async () => {
		await load();
		$( '#mhmrentiva_new_customer_state' ).val( 'TR34' );

		const data = createData();
		expect( data ).not.toBeNull();
		expect( 'new_customer_country' in data ).toBe( false );
		expect( 'new_customer_state' in data ).toBe( false );
	} );

	test( 'opening the new-customer card keeps country and state optional', async () => {
		await load();
		chooseNewCustomer();

		[ 'first_name', 'last_name', 'email', 'phone' ].forEach( ( f ) => {
			expect( $( `#mhmrentiva_new_customer_${ f }` ).prop( 'required' ) ).toBe( true );
		} );
		expect( $( '#mhmrentiva_new_customer_country' ).prop( 'required' ) ).toBe( false );
		expect( $( '#mhmrentiva_new_customer_state' ).prop( 'required' ) ).toBe( false );

		pickCountry( 'GB' );
		chooseNewCustomer();
		expect( $( '#mhmrentiva_new_customer_state' ).prop( 'required' ) ).toBe( false );
	} );

	test( 'a state name is put in as text, never as markup', async () => {
		const data = locations();
		data.states.DE[ 'DE-XX' ] = '<b>x</b>';
		await load( data );
		pickCountry( 'DE' );

		const $state = $( '#mhmrentiva_new_customer_state' );
		expect( $state.find( 'b' ).length ).toBe( 0 );
		expect( $state.find( 'option[value="DE-XX"]' ).text() ).toBe( '<b>x</b>' );
	} );
} );
