/**
 * @jest-environment jsdom
 */

/**
 * PR S Task 9 fix round (I2): the search strip with the REAL jQuery UI
 * datepicker. The strip and the vehicle card keep the return after pickup
 * through one rule (window.mhmRentivaDatepicker.constrainReturn); this drives
 * the strip the way a visitor does -- open the popup, click a day -- on the
 * markup unified-search.php renders (visible date in the site format, hidden
 * Y-m-d value after it).
 */
const realDatepicker = require( './support/real-datepicker' );

describe( 'search strip with the real datepicker', () => {
	let jQuery;

	const strip = `
		<div class="rvs-shell">
			<div class="rvs-search" data-rvs-search>
				<form>
					<input id="s-pickup" class="js-datepicker" value="12/07/2027" data-rvs-pickup-date>
					<input type="hidden" name="mhmrentiva_pickup_date" value="2027-07-12" data-rvs-date-value>
					<select data-rvs-pickup-time><option value="10:00" selected>10:00</option></select>
					<input id="s-return" class="js-datepicker" value="15/07/2027" data-rvs-return-date>
					<input type="hidden" name="mhmrentiva_return_date" value="2027-07-15" data-rvs-date-value>
				</form>
			</div>
		</div>`;

	const boot = async ( minDays ) => {
		jest.resetModules();
		document.body.innerHTML = strip;
		jQuery = require( 'jquery' );
		global.jQuery = jQuery;
		global.$ = jQuery;
		window.jQuery = jQuery;
		realDatepicker.install( jQuery );
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
		window.mhmUnifiedSearch = { settings: { minRentalDays: minDays } };
		// The page's order: the strip script, then the shared init (footer).
		require( '../../assets/js/frontend/unified-search.js' );
		require( '../../assets/js/core/datepicker-init.js' );
		await new Promise( ( resolve ) => jQuery( resolve ) );
	};

	const ret = () => document.getElementById( 's-return' );
	const submitted = () => {
		const out = {};
		jQuery( 'form' ).serializeArray().forEach( ( item ) => {
			out[ item.name ] = item.value;
		} );
		return out;
	};

	afterEach( () => {
		if ( jQuery && jQuery.fn.datepicker ) {
			jQuery( '.hasDatepicker' ).datepicker( 'destroy' );
		}
		jQuery( '#ui-datepicker-div' ).remove();
		delete global.jQuery;
		delete global.$;
		delete window.mhmDatepickerL10n;
		delete window.mhmRentivaDatepicker;
		delete window.mhmUnifiedSearch;
	} );

	test( 'a pickup past the return moves the return to pickup + the minimum rental', async () => {
		await boot( 2 );
		realDatepicker.pick( jQuery, document.getElementById( 's-pickup' ), 2027, 7, 20 );

		expect( ret().value ).toBe( '22/07/2027' );
		expect( submitted() ).toEqual( { mhmrentiva_pickup_date: '2027-07-20', mhmrentiva_return_date: '2027-07-22' } );
	} );

	test( 'the return popup offers no day before pickup + the minimum rental', async () => {
		await boot( 1 );
		realDatepicker.pick( jQuery, document.getElementById( 's-pickup' ), 2027, 7, 20 );

		expect( () => realDatepicker.pick( jQuery, ret(), 2027, 7, 20 ) ).toThrow( /not selectable/ );
		realDatepicker.pick( jQuery, ret(), 2027, 7, 21 );
		expect( submitted().mhmrentiva_return_date ).toBe( '2027-07-21' );
	} );

	test( 'the strip and the card share one rule', async () => {
		await boot( 1 );
		expect( typeof window.mhmRentivaDatepicker.constrainReturn ).toBe( 'function' );
	} );
} );
