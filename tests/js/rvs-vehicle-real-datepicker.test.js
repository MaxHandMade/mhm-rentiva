/**
 * @jest-environment jsdom
 */

/**
 * PR S Task 9 (D5): the vehicle page's booking card with the REAL jQuery UI
 * datepicker (WordPress's copy, tests/js/support/real-datepicker.js) -- the
 * earlier card tests used a stand-in, which is how a pickup past the return
 * date went unnoticed. A visitor picks a day in the popup; jQuery UI writes the
 * field and fires change, exactly as on the page. The markup is what
 * booking-card.php renders: the visible date in the site format, the hidden
 * Y-m-d value after it.
 */
const realDatepicker = require( './support/real-datepicker' );

describe( 'vehicle booking card with the real datepicker', () => {
	let jQuery;

	const page = `
		<div class="rvs-shell">
			<div class="rvs-vd" id="vd1" data-rvs-vehicle="7">
				<div data-rvs-vd-months></div>
				<aside class="rvs-vd__booking">
					<div class="rvs-vd__total" data-rvs-vd-priced><span data-rvs-vd-total>T0</span><span data-rvs-vd-days>3 days total</span></div>
					<form data-rvs-vd-window>
						<input id="vd1-pickup" class="js-datepicker" value="12/07/2027" data-rvs-pickup-date>
						<input type="hidden" name="pickup_date" value="2027-07-12" data-rvs-date-value>
						<select name="pickup_time" data-rvs-pickup-time><option value="10:00" selected>10:00</option></select>
						<input id="vd1-return" class="js-datepicker" value="15/07/2027" data-rvs-return-date>
						<input type="hidden" name="return_date" value="2027-07-15" data-rvs-date-value>
						<span data-rvs-return-time-display>10:00</span>
					</form>
					<div data-rvs-vd-breakdown>B0</div>
					<p data-rvs-vd-message role="status" hidden></p>
					<a href="/book?old" data-rvs-vd-continue>Continue</a>
					<div class="rvs-vd__bar">
						<button type="button" class="rvs-vd__bar-total" data-rvs-vd-priced><strong data-rvs-vd-total>T0</strong><span data-rvs-vd-days>3 days total</span></button>
					</div>
				</aside>
			</div>
		</div>
		<input id="outside" class="js-datepicker" name="elsewhere" value="2027-07-12">`;

	const boot = async ( minDays ) => {
		jest.resetModules();
		document.body.innerHTML = page;
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		realDatepicker.install( jQuery );
		// What DatepickerAssets localizes: every name list is present.
		window.mhmDatepickerL10n = {
			dateFormat: 'dd/mm/yy',
			altFormat: 'yy-mm-dd',
			closeText: 'Close',
			currentText: 'Today',
			monthNames: [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ],
			monthNamesShort: [ 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ],
			dayNames: [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ],
			dayNamesShort: [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ],
			dayNamesMin: [ 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' ],
			firstDay: 1,
		};
		window.mhmRentivaVehicle = {
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'n1',
			min_days: minDays,
			i18n: { error: 'Something went wrong', days_one: '%s day total', days_many: '%s days total' },
		};
		jQuery.ajax = jest.fn();
		require( '../../assets/js/frontend/rvs-dialog.js' );
		require( '../../assets/js/core/datepicker-init.js' );
		require( '../../assets/js/frontend/rvs-vehicle.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	const pickup = () => document.getElementById( 'vd1-pickup' );
	const ret = () => document.getElementById( 'vd1-return' );
	const posted = () => {
		const out = {};
		jQuery( '[data-rvs-vd-window]' ).serializeArray().forEach( ( item ) => {
			out[ item.name ] = item.value;
		} );
		return out;
	};

	afterEach( () => {
		jest.useRealTimers();
		if ( jQuery && jQuery.fn.datepicker ) {
			jQuery( '.hasDatepicker' ).datepicker( 'destroy' );
		}
		jQuery( '#ui-datepicker-div' ).remove();
		delete global.jQuery;
		delete global.$;
		delete window.mhmDatepickerL10n;
		delete window.mhmRentivaDatepicker;
		delete window.mhmRentivaVehicle;
		delete window.mhmRentivaRvsDialog;
	} );

	test( 'a pickup past the return moves the return to pickup + the minimum rental', async () => {
		await boot( 1 );
		jest.useFakeTimers();
		realDatepicker.pick( jQuery, pickup(), 2027, 7, 20 );

		expect( pickup().value ).toBe( '20/07/2027' );
		expect( ret().value ).toBe( '21/07/2027' );
		expect( posted() ).toMatchObject( { pickup_date: '2027-07-20', return_date: '2027-07-21' } );

		// The quote asks for the window the fields show.
		jest.advanceTimersByTime( 300 );
		const data = jQuery.ajax.mock.calls[ 0 ][ 0 ].data;
		expect( data.pickup_date ).toBe( '2027-07-20' );
		expect( data.return_date ).toBe( '2027-07-21' );
	} );

	test( 'the minimum rental counts: two days', async () => {
		await boot( 2 );
		realDatepicker.pick( jQuery, pickup(), 2027, 7, 20 );

		expect( ret().value ).toBe( '22/07/2027' );
		expect( posted().return_date ).toBe( '2027-07-22' );
	} );

	test( 'a later return than needed stays the visitor\'s', async () => {
		await boot( 1 );
		realDatepicker.pick( jQuery, pickup(), 2027, 7, 13 );

		expect( ret().value ).toBe( '15/07/2027' );
		expect( posted().return_date ).toBe( '2027-07-15' );
	} );

	test( 'the return popup offers no day before pickup + the minimum rental', async () => {
		await boot( 1 );
		realDatepicker.pick( jQuery, pickup(), 2027, 7, 20 );

		expect( () => realDatepicker.pick( jQuery, ret(), 2027, 7, 20 ) ).toThrow( /not selectable/ );
		realDatepicker.pick( jQuery, ret(), 2027, 7, 24 );
		expect( posted().return_date ).toBe( '2027-07-24' );
	} );

	test( 'the card popup wears the rvs skin; a field outside the shell does not', async () => {
		await boot( 1 );
		const $widget = () => jQuery( '#ui-datepicker-div' );

		jQuery( pickup() ).datepicker( 'show' );
		expect( $widget().hasClass( 'rvs-shell' ) ).toBe( true );
		expect( $widget().hasClass( 'rvs-dp' ) ).toBe( true );
		jQuery( pickup() ).datepicker( 'hide' );

		window.mhmRentivaDatepicker.init( '#outside' );
		jQuery( '#outside' ).datepicker( 'show' );
		expect( $widget().hasClass( 'rvs-dp' ) ).toBe( false );
		expect( $widget().hasClass( 'rvs-shell' ) ).toBe( false );
		jQuery( '#outside' ).datepicker( 'hide' );

		// Back on a card field: the skin again.
		jQuery( ret() ).datepicker( 'show' );
		expect( $widget().hasClass( 'rvs-dp' ) ).toBe( true );
	} );

	test( 'no total next to dates the server could not price', async () => {
		await boot( 1 );
		jest.useFakeTimers();
		jQuery( pickup() ).trigger( 'change' );
		jest.advanceTimersByTime( 300 );
		const options = jQuery.ajax.mock.calls[ 0 ][ 0 ];
		options.success( { success: true, data: {
			days: 0,
			priced: false,
			total_html: '',
			breakdown_html: '',
			continue_url: '/book?bad',
			available: false,
			message: 'Invalid date selection.',
		} } );
		options.complete();

		document.querySelectorAll( '[data-rvs-vd-priced]' ).forEach( ( el ) => {
			expect( el.hidden ).toBe( true );
		} );
		expect( document.querySelector( '[data-rvs-vd-message]' ).textContent ).toBe( 'Invalid date selection.' );

		// A priced answer shows them again.
		jQuery( pickup() ).trigger( 'change' );
		jest.advanceTimersByTime( 300 );
		const next = jQuery.ajax.mock.calls[ 1 ][ 0 ];
		next.success( { success: true, data: { days: 3, priced: true, total_html: 'T2', breakdown_html: '', continue_url: '/book', available: true, message: '' } } );
		next.complete();
		document.querySelectorAll( '[data-rvs-vd-priced]' ).forEach( ( el ) => {
			expect( el.hidden ).toBe( false );
		} );
		expect( document.querySelector( '[data-rvs-vd-days]' ).textContent ).toBe( '3 days total' );
	} );
} );
