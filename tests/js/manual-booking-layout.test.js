/**
 * @jest-environment jsdom
 */

/**
 * Admin screens PR 2: the new-booking script against the two-column markup
 * (canvas Yon-Yeni-Rezervasyon v116) — one fixed selected-vehicle slot, an
 * add-on total priced the way the server prices it, messages in the sidebar,
 * the deposit rows driven by the response, and no guest count (K5).
 */
describe( 'manual booking layout', () => {
	let $;

	const markup = () => `
		<div class="mhm-manual-booking-form"><div class="mhm-mb-layout">
			<div class="mhm-mb-main">
				<div class="mhm-form-card"><div class="mhm-form-card-body">
					<select id="mhmrentiva_manual_vehicle_id">
						<option value="">-</option>
						<option value="1" data-price="100">A</option>
						<option value="2" data-price="200">B</option>
						<option value="3" data-price="300">C</option>
					</select>
					<select id="mhmrentiva_manual_customer_id"><option value="7" selected>Ayşe</option><option value="new_customer">+</option></select>
					<div id="mhm-vehicle-info" class="mhm-vehicle-info mhm-hidden"></div>
					<div id="mhmrentiva_new_customer_fields" class="mhm-form-card mhm-form-card--nested">
						<div class="mhm-form-card-body"><input id="mhmrentiva_new_customer_first_name"></div>
					</div>
				</div></div>
				<div class="mhm-form-card"><div class="mhm-form-card-body">
					<input id="mhmrentiva_manual_pickup_date">
					<input id="mhmrentiva_manual_pickup_time">
					<input id="mhmrentiva_manual_dropoff_date">
					<input id="mhmrentiva_manual_dropoff_time">
				</div></div>
				<div class="mhm-form-card"><div class="mhm-addon-selection">
					<label><input type="checkbox" class="mhm-addon-checkbox" value="11" data-price="10" data-pricing-type="per_day">
						<input type="number" class="mhm-addon-qty" data-addon-id="11" value="2"></label>
					<label><input type="checkbox" class="mhm-addon-checkbox" value="12" data-price="50" data-pricing-type="per_booking"></label>
					<div class="mhm-addon-total mhm-hidden"><span class="mhm-addon-total-amount"></span></div>
				</div></div>
			</div>
			<div class="mhm-mb-aside">
				<select id="mhmrentiva_manual_payment_type"><option value="deposit" selected>Deposit</option><option value="full">Full</option></select>
				<select id="mhmrentiva_manual_payment_method"><option value="offline" selected>Offline</option></select>
				<select id="mhmrentiva_manual_booking_status"><option value="confirmed" selected>Confirmed</option></select>
				<textarea id="mhmrentiva_manual_notes"></textarea>
				<div class="mhm-booking-messages" aria-live="polite"></div>
				<div class="mhm-price-calculation mhm-hidden"><div class="mhm-price-details"></div></div>
				<div class="mhm-booking-actions">
					<button id="mhm-calculate-price">Calculate</button>
					<button id="mhm-create-booking">Create</button>
				</div>
			</div>
		</div></div>`;

	const load = ( html ) => {
		// Each load requires a fresh jQuery; the previous copy's delegated
		// handlers would otherwise stay on `document` and fire again.
		if ( $ ) {
			$( document ).off();
		}
		jest.resetModules();
		document.body.innerHTML = html;
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmManualBooking = {
			ajaxUrl: '/ajax',
			nonce: 'n',
			text: {},
			currency: '',
			currencyFormat: { position: 'left', decimals: 0, decimalSeparator: '.', thousandSeparator: '' },
		};
		global.mhmManualBooking = window.mhmManualBooking;
		$.ajax = jest.fn();
		require( '../../assets/js/admin/manual-booking-meta.js' );
		return new Promise( ( resolve ) => $( resolve ) );
	};

	// The script fills today/tomorrow on load; tests set their own dates after.
	const dates = ( pickup, pickupTime, dropoff, dropoffTime ) => {
		$( '#mhmrentiva_manual_pickup_date' ).val( pickup );
		$( '#mhmrentiva_manual_pickup_time' ).val( pickupTime );
		$( '#mhmrentiva_manual_dropoff_date' ).val( dropoff );
		$( '#mhmrentiva_manual_dropoff_time' ).val( dropoffTime );
	};

	const lastData = () => $.ajax.mock.calls[ $.ajax.mock.calls.length - 1 ][ 0 ].data;
	const digits = ( text ) => parseFloat( String( text ).replace( /[^0-9.]/g, '' ) );

	test( 'vehicle info box stays single across three changes', async () => {
		await load( markup() );
		$( '#mhmrentiva_new_customer_fields' ).removeClass( 'mhm-hidden' );

		[ '1', '2', '3' ].forEach( ( id ) => $( '#mhmrentiva_manual_vehicle_id' ).val( id ).trigger( 'change' ) );

		expect( $( '.mhm-vehicle-info' ) ).toHaveLength( 1 );
		expect( $( '#mhm-vehicle-info' ).hasClass( 'mhm-hidden' ) ).toBe( false );
		expect( $( '#mhm-vehicle-info' ).text() ).toContain( 'C' );
		expect( $( '#mhm-vehicle-info' ).text() ).not.toContain( 'B' );
		expect( $( '#mhmrentiva_new_customer_fields .mhm-vehicle-info' ) ).toHaveLength( 0 );
	} );

	test( 'addon total follows pricing type', async () => {
		await load( markup() );
		dates( '2099-04-01', '10:00', '2099-04-04', '10:00' );

		$( '.mhm-addon-checkbox' ).prop( 'checked', true ).first().trigger( 'change' );

		// 3 days: per_day 10 x 3 x 2 + per_booking 50 x 1.
		expect( digits( $( '.mhm-addon-total-amount' ).text() ) ).toBe( 110 );
	} );

	test( 'day count matches the server', async () => {
		await load( markup() );
		dates( '2099-04-01', '10:00', '2099-04-04', '09:00' );
		$( '.mhm-addon-checkbox[value="11"]' ).prop( 'checked', true ).trigger( 'change' );
		// 2 full days (the third is one hour short): 10 x 2 x 2.
		expect( digits( $( '.mhm-addon-total-amount' ).text() ) ).toBe( 40 );

		await load( markup() );
		dates( '2099-04-01', '10:00', '2099-04-01', '12:00' );
		$( '.mhm-addon-checkbox[value="11"]' ).prop( 'checked', true ).trigger( 'change' );
		// Same day: at least one day.
		expect( digits( $( '.mhm-addon-total-amount' ).text() ) ).toBe( 20 );
	} );

	test( 'deposit rows show only for deposit', async () => {
		await load( markup() );
		dates( '2099-04-01', '10:00', '2099-04-04', '10:00' );
		const reply = ( paymentType ) => ( opts ) => {
			opts.success( {
				success: true,
				data: { days: 3, price_per_day: 100, vehicle_total: 300, addon_total: 0, total_amount: 300, final_total: 300, deposit_amount: 60, remaining_amount: 240, payment_type: paymentType },
			} );
			// The script clears its in-flight state here; a real jqXHR always calls it.
			if ( opts.complete ) {
				opts.complete();
			}
		};

		$.ajax.mockImplementation( reply( 'deposit' ) );
		$( '#mhmrentiva_manual_vehicle_id' ).val( '1' );
		$( '#mhm-calculate-price' ).trigger( 'click' );
		expect( $( '.mhm-price-details' ).text() ).toContain( '240' );
		expect( $( '.mhm-price-details .mhm-price-item' ) ).toHaveLength( 6 );

		$.ajax.mockImplementation( reply( 'full' ) );
		$( '#mhm-calculate-price' ).trigger( 'click' );
		expect( $( '.mhm-price-details .mhm-price-item' ) ).toHaveLength( 4 );
	} );

	test( 'a slow earlier price answer cannot overwrite a newer one', async () => {
		await load( markup() );
		dates( '2099-04-01', '10:00', '2099-04-04', '10:00' );
		const pending = [];
		$.ajax.mockImplementation( ( opts ) => pending.push( opts ) );
		const answer = ( opts, paymentType, total ) => {
			opts.success( {
				success: true,
				data: { days: 3, price_per_day: 100, vehicle_total: total, addon_total: 0, total_amount: total, final_total: total, deposit_amount: 1, remaining_amount: 2, payment_type: paymentType },
			} );
			opts.complete();
		};

		$( '#mhmrentiva_manual_vehicle_id' ).val( '1' );
		$( '#mhm-calculate-price' ).trigger( 'click' ); // first: deposit
		// Input changes re-price directly (the button is disabled meanwhile).
		$( '#mhmrentiva_manual_payment_type' ).val( 'full' ).trigger( 'change' ); // second: full
		expect( pending ).toHaveLength( 2 );

		answer( pending[ 1 ], 'full', 999 ); // the newer answer arrives first
		answer( pending[ 0 ], 'deposit', 111 ); // then the stale one

		expect( $( '.mhm-price-details' ).text() ).toContain( '999' );
		expect( $( '.mhm-price-details' ).text() ).not.toContain( '111' );
		expect( $( '.mhm-price-details .mhm-price-item' ) ).toHaveLength( 4 );
	} );

	test( 'messages land in the aside and replace each other', async () => {
		await load( markup() );
		dates( '2099-04-05', '10:00', '2099-04-04', '10:00' );

		$( '#mhmrentiva_manual_pickup_date' ).trigger( 'change' );
		$( '#mhmrentiva_manual_dropoff_date' ).trigger( 'change' );

		expect( $( '.mhm-booking-messages .mhm-message' ) ).toHaveLength( 1 );
		expect( $( '.mhm-message' ) ).toHaveLength( 1 );
		expect( $( '.mhm-manual-booking-form > .mhm-message' ) ).toHaveLength( 0 );
	} );

	test( 'create request has no guests key', async () => {
		await load( markup() );
		dates( '2099-04-01', '10:00', '2099-04-04', '10:00' );
		$( '#mhmrentiva_manual_vehicle_id' ).val( '1' );

		$( '#mhm-create-booking' ).trigger( 'click' );

		expect( $.ajax ).toHaveBeenCalled();
		expect( lastData() ).not.toHaveProperty( 'guests' );
		expect( lastData().action ).toBe( 'mhmrentiva_create_manual_booking' );
	} );
} );
