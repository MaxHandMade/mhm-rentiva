/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR C Task 3: booking-form.js on the rvs markup.
 *
 * Every lookup starts at the instance root ([data-rvs-booking-form]) and goes
 * through data-rvs-* hooks, so two forms on one page never touch each other.
 * The add-on quantities come from the steppers (rvs-stepper.js), the summary
 * takes the server-rendered breakdown (breakdown_html), the phone's band
 * toggles the summary, and the datepicker popup wears the rvs shell only while
 * a booking date field has it open (R6).
 */
const fs = require( 'fs' );
const path = require( 'path' );

describe( 'booking form on the rvs markup', () => {
	let $;
	let BookingForm;

	const strings = {
		show_summary: 'Show summary',
		hide_summary: 'Hide summary',
		processing: 'Processing...',
		select_this_vehicle: 'Select This Vehicle',
		alternative_vehicles: 'Alternative Vehicles',
		vehicle_unavailable_with_alternatives: 'Not available, try these:',
		per_day: '/day',
		deposit_amount: 'Deposit Amount',
		remaining_amount: 'Remaining Amount',
		total_amount: 'Total Amount',
		daily_price: 'Daily Price',
	};

	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '';
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.fn.datepicker = function () {
			return this;
		};
		$.datepicker = {
			parseDate: ( format, value ) => {
				const [ y, m, d ] = String( value ).split( '-' ).map( Number );
				return new Date( y, m - 1, d );
			},
			setDefaults: () => {},
		};
		window.mhmRentivaBookingForm = {
			config: { currency_symbol: '$', currency_position: 'left' },
			strings,
			ajax_url: '/ajax',
			ajaxUrl: '/ajax',
			nonce: 'n',
		};
		delete window.MHMRvsStepper;
		require( '../../assets/js/frontend/rvs-stepper.js' );
		require( '../../assets/js/frontend/booking-form.js' );
		BookingForm = window.MHMRentivaBookingForm;
	} );

	// The template's shape (templates/shortcodes/booking-form.php), trimmed to the hooks.
	const markup = ( uid, opts = {} ) => {
		const o = {
			vehicle: '5',
			deposit: '1',
			time: '<select data-rvs-pickup-time name="pickup_time"><option value="10:00" selected>10:00</option></select>',
			addons: '',
			select: '',
			...opts,
		};
		return `
		<div class="rvs-booking" data-rvs-booking-form data-rvs-deposit="${ o.deposit }"${ o.vehicle ? ` data-vehicle-id="${ o.vehicle }"` : '' }>
			<form class="rvs-booking__form" id="${ uid }-form">
				${ o.select }
				${ o.vehicle ? `<input type="hidden" name="vehicle_id" value="${ o.vehicle }">` : '' }
				<input type="text" name="pickup_date" data-rvs-pickup-date value="2030-05-10">
				${ o.time }
				<input type="text" name="dropoff_date" data-rvs-dropoff-date value="2030-05-13">
				<input type="hidden" name="dropoff_time" value="10:00" data-rvs-dropoff-time>
				<div class="rvs-booking__availability" data-rvs-availability hidden></div>
				${ o.addons }
				<div data-rvs-messages hidden></div>
				<aside class="rvs-booking__aside">
					<div class="rvs-booking__summary" id="${ uid }-summary" data-rvs-summary>
						<div class="rvs-booking__vehicle" data-rvs-summary-vehicle>
							<div class="rvs-booking__media"><img class="rvs-booking__img" src="/old.jpg" alt="Old car"></div>
							<h3 class="rvs-booking__vehicle-title">Old car</h3>
							<p class="rvs-booking__specs">5 seats</p>
						</div>
						<div class="rvs-booking__when">
							<p class="rvs-booking__dates" data-rvs-summary-dates></p>
							<a class="rvs-booking__change" href="/old-car/?mhmrentiva_pickup_date=2030-05-10" data-rvs-change>Change</a>
						</div>
						<div class="rvs-booking__breakdown" data-rvs-breakdown>
							<div class="rvs-booking__daily"><span>Daily Price</span><span data-rvs-daily>$100</span></div>
						</div>
					</div>
					<div class="rvs-booking__bar">
						<button type="button" data-rvs-summary-toggle aria-expanded="false" aria-controls="${ uid }-summary">
							<strong data-rvs-total>-</strong>
							<span>total<span data-rvs-bar-deposit hidden></span></span>
							<span data-rvs-summary-toggle-label>Show summary</span>
						</button>
						<input type="hidden" name="payment_type" value="deposit">
						<button type="button" class="rvs-booking__submit" data-rvs-submit>Make Booking</button>
					</div>
				</aside>
			</form>
		</div>`;
	};

	const stepper = ( id, min, max, value ) => `
		<div class="rvs-option rvs-option--qty" data-rvs-addon="${ id }">
			<div class="rvs-stepper" data-rvs-stepper="${ id }" data-min="${ min }" data-max="${ max }">
				<button type="button" data-rvs-step="-1">-</button>
				<output>${ value }</output>
				<button type="button" data-rvs-step="1">+</button>
				<input type="hidden" name="addons[]" value="${ id }"${ value === 0 ? ' disabled' : '' }>
				<input type="hidden" name="addon_qty[${ id }]" value="${ value }"${ value === 0 ? ' disabled' : '' }>
			</div>
		</div>`;

	const lockedBox = ( id ) => `
		<label class="rvs-option rvs-option--locked" data-rvs-addon="${ id }">
			<input type="checkbox" value="${ id }" checked disabled>
			<input type="hidden" name="addons[]" value="${ id }">
		</label>`;

	const box = ( id, checked ) => `
		<label class="rvs-option" data-rvs-addon="${ id }">
			<input type="checkbox" name="addons[]" value="${ id }"${ checked ? ' checked' : '' }>
		</label>`;

	const mount = ( html ) => {
		document.body.insertAdjacentHTML( 'beforeend', html );
		const roots = document.querySelectorAll( '[data-rvs-booking-form]' );
		return new BookingForm( $( roots[ roots.length - 1 ] ) );
	};

	const posted = ( call ) => new URLSearchParams( String( call[ 0 ].data ) );

	const lastCall = () => $.ajax.mock.calls[ $.ajax.mock.calls.length - 1 ];

	const breakdownHtml = ( deposit ) => `
		<div class="rvs-breakdown" data-rvs-breakdown>
			<dl class="rvs-breakdown__rows">
				<div class="rvs-breakdown__row rvs-breakdown__row--rental"><dt>Rental, 3 days</dt><dd><span class="amount">$300.00</span></dd></div>
				<div class="rvs-breakdown__row rvs-breakdown__row--addon"><dt>Child seat × 2</dt><dd><span class="amount">$60.00</span></dd></div>
			</dl>
			<div class="rvs-breakdown__total"><span class="rvs-breakdown__total-label">Total</span><span class="rvs-breakdown__total-value"><span class="rvs-breakdown__amount"><span class="amount">$360.00</span></span></span></div>
			${ deposit ? '<div class="rvs-breakdown__deposit"><span>Deposit (20%)</span><strong><span class="amount">$72.00</span></strong></div>' : '' }
		</div>`;

	const quote = ( extra ) => ( {
		vehicle_price: 100,
		days: 3,
		addon_total: 60,
		total_price: 360,
		deposit_amount: 72,
		remaining_amount: 288,
		payment_type: 'deposit',
		display_total: { amount: 360, html: '<span class="amount">$360.00</span>' },
		display_deposit: { amount: 72, html: '<span class="amount">$72.00</span>' },
		display_remaining: { amount: 288, html: '<span class="amount">$288.00</span>' },
		...extra,
	} );

	test( 'required stepper floors at one', () => {
		const form = mount( markup( 'rvs-bf-1', { addons: stepper( 21, 1, 3, 1 ) } ) );
		const $dec = form.container.find( '[data-rvs-step="-1"]' );

		expect( $dec.prop( 'disabled' ) ).toBe( true );
		$dec[ 0 ].click();

		const data = form.getFormData();
		expect( data.addons ).toEqual( [ 21 ] );
		expect( data.addon_qty ).toEqual( { 21: 1 } );
		expect( form.container.find( 'output' ).text() ).toBe( '1' );
	} );

	test( 'two instances keep separate state', () => {
		const first = mount( markup( 'rvs-bf-1', { addons: stepper( 11, 0, 3, 0 ) } ) );
		const second = mount( markup( 'rvs-bf-2', { addons: stepper( 11, 0, 3, 0 ) } ) );
		first.autoCalculatePrice = jest.fn();
		second.autoCalculatePrice = jest.fn();

		const inc = first.container.find( '[data-rvs-step="1"]' )[ 0 ];
		inc.click();
		inc.click();

		expect( first.autoCalculatePrice ).toHaveBeenCalled();
		expect( second.autoCalculatePrice ).not.toHaveBeenCalled();

		$.ajax = jest.fn();
		second.calculatePrice();
		const other = posted( lastCall() );
		expect( [ ...other.keys() ].some( ( k ) => k.startsWith( 'addon_qty' ) ) ).toBe( false );
		expect( other.getAll( 'addons[]' ) ).toEqual( [ '0' ] );

		first.calculatePrice();
		const mine = posted( lastCall() );
		expect( mine.getAll( 'addons[]' ) ).toEqual( [ '11' ] );
		expect( mine.get( 'addon_qty[11]' ) ).toBe( '2' );
	} );

	test( 'quote request sends quantities only for selected add-ons', () => {
		// 31: stepper at 2; 32: stepper at 0 (fields disabled); 33: ticked box;
		// 34: a quantity field whose addons[] is gone -- never sent alone.
		const addons = stepper( 31, 0, 3, 2 ) + stepper( 32, 0, 3, 0 ) + box( 33, true ) + box( 35, false ) +
			'<input type="hidden" name="addon_qty[34]" value="2">';
		const form = mount( markup( 'rvs-bf-1', { addons } ) );
		$.ajax = jest.fn();

		form.calculatePrice();
		const data = posted( lastCall() );
		const ids = data.getAll( 'addons[]' );
		const qtyKeys = [ ...data.keys() ].filter( ( k ) => k.startsWith( 'addon_qty[' ) ).map( ( k ) => k.slice( 10, -1 ) );

		expect( ids ).toEqual( [ '31', '33' ] );
		expect( qtyKeys ).toEqual( [ '31' ] );
		qtyKeys.forEach( ( k ) => expect( ids ).toContain( k ) );
	} );

	test( 'required add-on is in addons[] with the box disabled', () => {
		const form = mount( markup( 'rvs-bf-1', { addons: lockedBox( 41 ) + box( 42, false ) } ) );

		expect( form.container.find( 'input[type="checkbox"][value="41"]' ).prop( 'disabled' ) ).toBe( true );
		expect( form.getFormData().addons ).toEqual( [ 41 ] );
	} );

	test( 'breakdown_html replaces the summary rows', () => {
		const form = mount( markup( 'rvs-bf-1' ) );

		form.updatePriceDisplay( quote( { breakdown_html: breakdownHtml( true ) } ) );

		const $box = form.container.find( '[data-rvs-summary] > [data-rvs-breakdown]' );
		expect( $box.find( '[data-rvs-daily]' ).length ).toBe( 0 );
		expect( $box.find( '.rvs-breakdown__row' ).length ).toBe( 2 );
		expect( form.container.find( '[data-rvs-total] .amount' ).text() ).toBe( '$360.00' );
		const $bar = form.container.find( '[data-rvs-bar-deposit]' );
		expect( $bar.prop( 'hidden' ) ).toBe( false );
		expect( $bar.text() ).toContain( 'Deposit (20%)' );
		expect( $bar.find( '.amount' ).text() ).toBe( '$72.00' );
	} );

	test( 'a second quote does not nest the breakdown', () => {
		const form = mount( markup( 'rvs-bf-1' ) );

		form.updatePriceDisplay( quote( { breakdown_html: breakdownHtml( true ) } ) );
		form.updatePriceDisplay( quote( { breakdown_html: breakdownHtml( false ) } ) );

		expect( form.container.find( '[data-rvs-breakdown]' ).length ).toBe( 2 );
		expect( form.container.find( '[data-rvs-breakdown] [data-rvs-breakdown] [data-rvs-breakdown]' ).length ).toBe( 0 );
		expect( form.container.find( '.rvs-breakdown__row' ).length ).toBe( 2 );
		expect( form.container.find( '[data-rvs-bar-deposit]' ).prop( 'hidden' ) ).toBe( true );
	} );

	test( 'enable_deposit=0 hides deposit rows', () => {
		const form = mount( markup( 'rvs-bf-1', { deposit: '0' } ) );
		$.ajax = jest.fn();

		form.calculatePrice();
		expect( posted( lastCall() ).get( 'enable_deposit' ) ).toBe( '0' );

		// Even an answer that carries deposit amounts (the fallback path) shows none.
		form.updatePriceDisplay( quote() );
		expect( form.container.find( '.rvs-breakdown__deposit' ).length ).toBe( 0 );
		expect( form.container.find( '.rvs-breakdown__pickup' ).length ).toBe( 0 );
		expect( form.container.find( '[data-rvs-bar-deposit]' ).prop( 'hidden' ) ).toBe( true );
	} );

	test( 'a form with deposits on asks for them', () => {
		const form = mount( markup( 'rvs-bf-1' ) );
		$.ajax = jest.fn();

		form.calculatePrice();
		expect( posted( lastCall() ).get( 'enable_deposit' ) ).toBe( '1' );
	} );

	test( 'hidden time still reaches the quote', () => {
		const form = mount( markup( 'rvs-bf-1', { time: '<input type="hidden" name="pickup_time" value="12:00" data-rvs-pickup-time>' } ) );
		$.ajax = jest.fn();

		form.calculatePrice();
		expect( posted( lastCall() ).get( 'pickup_time' ) ).toBe( '12:00' );
		expect( form.getFormData().dropoff_time ).toBe( '10:00' );
	} );

	test( 'mobile summary toggle flips aria-expanded', () => {
		const form = mount( markup( 'rvs-bf-1' ) );
		const $toggle = form.container.find( '[data-rvs-summary-toggle]' );
		const $label = form.container.find( '[data-rvs-summary-toggle-label]' );

		$toggle[ 0 ].click();
		expect( $toggle.attr( 'aria-expanded' ) ).toBe( 'true' );
		expect( $label.text() ).toBe( 'Hide summary' );

		$toggle[ 0 ].click();
		expect( $toggle.attr( 'aria-expanded' ) ).toBe( 'false' );
		expect( $label.text() ).toBe( 'Show summary' );
	} );

	test( 'the global jQuery ajax settings are left alone', () => {
		const spy = jest.spyOn( $, 'ajaxSetup' );
		const form = mount( markup( 'rvs-bf-1' ) );
		$.ajax = jest.fn();
		form.calculatePrice();
		form.checkAvailability();

		expect( spy ).not.toHaveBeenCalled();
		expect( lastCall()[ 0 ].traditional ).toBe( true );
	} );

	const unavailableWith = ( alternatives ) => {
		$.ajax = jest.fn( ( options ) => options.success( {
			success: false,
			data: { ok: false, code: 'unavailable_with_alternatives', message: 'taken', alternatives },
		} ) );
	};

	const alternative = ( extra ) => ( {
		id: 9,
		title: 'Fiat Egea',
		price_per_day: 850,
		image: '/egea.jpg',
		seats: '5',
		transmission: 'manual',
		fuel_type: 'diesel',
		permalink: '/fiat-egea/?mhmrentiva_pickup_date=2030-05-10',
		...extra,
	} );

	test( 'alternative vehicles are rvs cards', () => {
		const form = mount( markup( 'rvs-bf-1' ) );
		unavailableWith( [ alternative(), alternative( { id: 10, title: 'Clio' } ) ] );

		form.checkAvailability();

		const $status = form.container.find( '[data-rvs-availability]' );
		expect( $status.attr( 'data-state' ) ).toBe( 'error' );
		expect( $status.prop( 'hidden' ) ).toBe( false );
		const $cards = $status.find( '.rvs-card-grid > .rvs-card' );
		expect( $cards.length ).toBe( 2 );
		expect( $cards.eq( 0 ).find( '.rvs-card__title' ).text() ).toBe( 'Fiat Egea' );
		expect( $cards.eq( 0 ).find( '.rvs-card__img' ).attr( 'src' ) ).toBe( '/egea.jpg' );
		expect( $cards.eq( 0 ).find( '.rvs-card__specs' ).text() ).toBe( '5 · manual · diesel' );
		expect( $cards.eq( 0 ).find( '.rvs-card__amount' ).text() ).toBe( '$850' );
		expect( $cards.eq( 0 ).find( 'button.rvs-card__select' ).text() ).toBe( 'Select This Vehicle' );
	} );

	test( 'alternative card values are text, never markup', () => {
		const form = mount( markup( 'rvs-bf-1' ) );
		const evil = '<img src=x onerror="window.__pwned=1">';
		unavailableWith( [ alternative( { title: evil, seats: evil, image: '" onerror="window.__pwned=1' } ) ] );

		form.checkAvailability();

		const $card = form.container.find( '.rvs-card' );
		expect( $card.find( '.rvs-card__title' ).text() ).toBe( evil );
		expect( $card.find( 'img' ).length ).toBe( 1 );
		expect( $card.find( '[onerror]' ).length ).toBe( 0 );
		expect( window.__pwned ).toBeUndefined();
	} );

	test( 'change link follows the chosen alternative', () => {
		const form = mount( markup( 'rvs-bf-1' ) );
		form.autoCalculatePrice = jest.fn();
		form.autoCheckAvailability = jest.fn();
		form.fetchAndApplyBlockedDates = jest.fn();
		unavailableWith( [ alternative() ] );

		form.checkAvailability();
		form.container.find( '.rvs-card__select' )[ 0 ].click();

		const $change = form.container.find( '[data-rvs-change]' );
		expect( $change.attr( 'href' ) ).toBe( '/fiat-egea/?mhmrentiva_pickup_date=2030-05-10' );
		expect( $change.prop( 'hidden' ) ).toBe( false );
		expect( form.container.attr( 'data-vehicle-id' ) ).toBe( '9' );
		expect( form.container.find( 'input[name="vehicle_id"]' ).val() ).toBe( '9' );
		expect( form.container.find( '.rvs-booking__vehicle-title' ).text() ).toBe( 'Fiat Egea' );
		expect( form.container.find( '.rvs-booking__img' ).attr( 'src' ) ).toBe( '/egea.jpg' );
		expect( form.getFormData().vehicle_id ).toBe( '9' );
	} );

	test( 'a suggested vehicle in the select is chosen there; one it does not list still becomes the vehicle', () => {
		const select = `<select name="vehicle_id" data-rvs-vehicle-select>
			<option value="">Select vehicle...</option>
			<option value="5" data-price="100" data-permalink="/old/">Old car</option>
			<option value="9" data-price="850" data-permalink="/fiat-egea/">Fiat Egea</option>
		</select>`;
		const form = mount( markup( 'rvs-bf-1', { select } ) );
		form.autoCalculatePrice = jest.fn();
		form.autoCheckAvailability = jest.fn();
		form.fetchAndApplyBlockedDates = jest.fn();

		form.selectAlternativeVehicle( alternative() );
		expect( form.container.find( '[data-rvs-vehicle-select]' ).val() ).toBe( '9' );
		expect( form.container.find( '[data-rvs-change]' ).attr( 'href' ) ).toBe( '/fiat-egea/?mhmrentiva_pickup_date=2030-05-10' );

		form.selectAlternativeVehicle( alternative( { id: 12, title: 'Unlisted', permalink: '/unlisted/' } ) );
		expect( form.getFormData().vehicle_id ).toBe( '12' );
		expect( form.container.find( '.rvs-booking__vehicle-title' ).text() ).toBe( 'Unlisted' );
		expect( form.container.find( '[data-rvs-change]' ).attr( 'href' ) ).toBe( '/unlisted/' );
	} );

	test( 'a vehicle chosen from the select fills the summary and its Change link', () => {
		const select = `<select name="vehicle_id" data-rvs-vehicle-select>
			<option value="">Select vehicle...</option>
			<option value="7" data-price="120" data-permalink="/clio/" data-image="/clio.jpg" data-features='["5 seats","Manual"]'>Clio ($120/day)</option>
		</select>`;
		const html = markup( 'rvs-bf-1', { vehicle: '', select } )
			.replace( 'data-rvs-summary-vehicle>', 'data-rvs-summary-vehicle hidden>' )
			.replace( '<img class="rvs-booking__img" src="/old.jpg" alt="Old car">', '<img class="rvs-booking__img" alt="" data-rvs-summary-image hidden>' )
			.replace( '<h3 class="rvs-booking__vehicle-title">Old car</h3>', '<h3 class="rvs-booking__vehicle-title" data-rvs-summary-title></h3>' )
			.replace( '<p class="rvs-booking__specs">5 seats</p>', '<p class="rvs-booking__specs" data-rvs-summary-specs hidden></p>' )
			.replace( /<a class="rvs-booking__change"[^>]*>/, '<a class="rvs-booking__change" data-rvs-change hidden>' );
		const form = mount( html );
		form.fetchAndApplyBlockedDates = jest.fn();
		form.autoCheckAvailability = jest.fn();
		form.autoCalculatePrice = jest.fn();

		form.container.find( '[data-rvs-vehicle-select]' ).val( '7' ).trigger( 'change' );

		expect( form.container.find( '[data-rvs-summary-vehicle]' ).prop( 'hidden' ) ).toBe( false );
		expect( form.container.find( '[data-rvs-summary-title]' ).text() ).toBe( 'Clio' );
		expect( form.container.find( '[data-rvs-summary-image]' ).attr( 'src' ) ).toBe( '/clio.jpg' );
		expect( form.container.find( '[data-rvs-summary-specs]' ).text() ).toBe( '5 seats · Manual' );
		expect( form.container.find( '[data-rvs-change]' ).attr( 'href' ) ).toBe( '/clio/' );
		expect( form.container.find( '[data-rvs-daily]' ).text() ).toBe( '$120' );
		expect( form.getFormData().vehicle_id ).toBe( '7' );
		expect( form.fetchAndApplyBlockedDates ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'the submit button shows its busy state while the booking is sent', () => {
		const form = mount( markup( 'rvs-bf-1' ) );
		form.validateForm = () => true;
		const pending = [];
		$.ajax = jest.fn( ( options ) => pending.push( options ) );
		const $btn = form.container.find( '[data-rvs-submit]' );

		form.submitForm();
		expect( $btn.prop( 'disabled' ) ).toBe( true );
		expect( $btn.attr( 'aria-busy' ) ).toBe( 'true' );
		expect( $btn.text() ).toBe( 'Processing...' );

		// A second press while busy sends nothing.
		form.submitForm();
		expect( pending.length ).toBe( 1 );

		pending[ 0 ].success( { success: false, data: { message: 'no' } } );
		pending[ 0 ].complete && pending[ 0 ].complete();
		expect( $btn.prop( 'disabled' ) ).toBe( false );
		expect( $btn.attr( 'aria-busy' ) ).toBeUndefined();
		expect( $btn.text() ).toBe( 'Make Booking' );
		expect( form.container.find( '[data-rvs-messages]' ).text() ).toBe( 'no' );
	} );

	test( 'an editor preview keeps the disabled submit disabled', () => {
		const form = mount( markup( 'rvs-bf-1' ).replace( 'data-rvs-submit>', 'data-rvs-submit disabled>' ) );
		form.availabilityBlocked = false;
		form.updateButtonState();

		expect( form.container.find( '[data-rvs-submit]' ).prop( 'disabled' ) ).toBe( true );
	} );

	test( 'datepicker popup carries the rvs shell only while the booking field is open', () => {
		// A stand-in for jQuery UI's single, shared popup (#ui-datepicker-div):
		// each input keeps its own options; 'widget' returns the shared div.
		document.body.insertAdjacentHTML( 'beforeend', '<div id="ui-datepicker-div"></div><input class="js-datepicker" id="search-date">' );
		const $widget = $( '#ui-datepicker-div' );
		$.fn.datepicker = function ( arg ) {
			if ( arg === 'widget' ) {
				return $widget;
			}
			if ( arg && typeof arg === 'object' ) {
				this.each( function () {
					$( this ).data( 'dp-options', arg ).addClass( 'hasDatepicker' );
				} );
			}
			return this;
		};
		const open = ( input ) => {
			const opts = $( input ).data( 'dp-options' );
			const inst = { input: $( input ), dpDiv: $widget, settings: opts };
			if ( opts.beforeShow ) {
				opts.beforeShow.call( input, input, inst );
			}
			return { close: () => opts.onClose && opts.onClose.call( input, '', inst ) };
		};
		window.mhmRentivaBookingForm.datepicker_options = { dateFormat: 'yy-mm-dd' };
		const form = mount( markup( 'rvs-bf-1' ) );
		require( '../../assets/js/core/datepicker-init.js' );

		const booking = open( form.container.find( '[data-rvs-pickup-date]' )[ 0 ] );
		expect( $widget.hasClass( 'rvs-shell' ) ).toBe( true );
		expect( $widget.hasClass( 'rvs-dp' ) ).toBe( true );

		booking.close();
		expect( $widget.hasClass( 'rvs-shell' ) ).toBe( false );
		expect( $widget.hasClass( 'rvs-dp' ) ).toBe( false );

		// datepicker-init.js binds the search field on focus, with its own options.
		const search = document.getElementById( 'search-date' );
		search.focus();
		expect( $( search ).data( 'dp-options' ) ).toBeDefined();
		open( search );
		expect( $widget.hasClass( 'rv-datepicker-skin' ) ).toBe( true );
		expect( $widget.hasClass( 'rvs-dp' ) ).toBe( false );
	} );

	test( 'datepicker popup sits above the phone summary band', () => {
		const css = fs.readFileSync( path.join( __dirname, '../../assets/css/frontend/rvs-booking.css' ), 'utf8' );
		const band = /\.rvs-booking__aside\s*\{[^}]*position:\s*fixed;[^}]*z-index:\s*(\d+)/.exec( css );
		const popup = /\.rvs-shell\.rvs-dp\s*\{[^}]*z-index:\s*(\d+)\s*!important/.exec( css );

		expect( band ).not.toBeNull();
		expect( popup ).not.toBeNull();
		expect( Number( popup[ 1 ] ) ).toBeGreaterThan( Number( band[ 1 ] ) );
	} );
} );
