/**
 * @jest-environment jsdom
 */
/**
 * The bookings calendar popup had pickup/return time slots that were never
 * filled: the cell carried the times, the template had the slots, and the
 * script read neither — so every booking showed "—" for its times.
 */
describe( 'booking calendar popup: pickup and return times', () => {
	const mount = async ( cellAttrs ) => {
		jest.resetModules();
		const $ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		$.fn.fadeIn = function () {
			return this.show();
		};
		window.mhmBookingPopup = { i18n: { pickup: 'Pickup', returnLabel: 'Return', total: 'Total', customer: 'Customer' } };
		document.body.innerHTML = `
			<table><tbody><tr><td class="day-cell booked" data-booking-popup ${ cellAttrs }></td></tr></tbody></table>
			<div id="mhm-booking-popup" style="display:none">
				<span id="popup-status-badge"></span><span class="mhm-popup-booking-id"></span>
				<div id="popup-single-view">
					<span id="popup-customer-name"></span><span id="popup-customer-email"></span><span id="popup-customer-phone"></span>
					<span id="popup-vehicle-title"></span><span id="popup-vehicle-plate"></span>
					<span id="popup-start-date">—</span><span id="popup-start-time">—</span>
					<span id="popup-end-date">—</span><span id="popup-end-time">—</span>
					<span id="popup-total-price"></span><span id="popup-created-date"></span>
				</div>
				<div id="popup-multi-view"><span id="popup-multi-count"></span><div id="popup-bookings-list"></div></div>
				<div id="popup-single-footer"><a id="popup-edit-booking"></a></div>
			</div>`;
		require( '../../assets/js/admin/booking-popup.js' );
		await new Promise( ( r ) => $( r ) );
		return $;
	};

	const json = ( list ) => `data-bookings='${ JSON.stringify( list ) }'`;

	const booking = ( id, extra ) => ( {
		booking_id: id,
		status: 'confirmed',
		status_label: 'Confirmed',
		start_date: 'November 20, 2026',
		end_date: 'November 24, 2026',
		total_price: '$237.60',
		...extra,
	} );

	it( 'shows the times of a single booking', async () => {
		const $ = await mount( json( [ booking( 115, { start_time: '9:00 am', end_time: '6:30 pm' } ) ] ) );
		$( '[data-booking-popup]' ).trigger( 'click' );
		expect( $( '#popup-start-time' ).text() ).toBe( '9:00 am' );
		expect( $( '#popup-end-time' ).text() ).toBe( '6:30 pm' );
		expect( $( '#popup-total-price' ).text() ).toBe( '$237.60' );
	} );

	it( 'clears the time slots when a booking has no time', async () => {
		const $ = await mount( json( [ booking( 115, {} ) ] ) );
		$( '[data-booking-popup]' ).trigger( 'click' );
		expect( $( '#popup-start-time' ).text() ).toBe( '' );
		expect( $( '#popup-end-time' ).text() ).toBe( '' );
	} );

	it( 'reads the times from the flat attributes when there is no list', async () => {
		const $ = await mount( 'data-booking-id="9" data-start-date="Nov 1" data-end-date="Nov 2" data-start-time="10:00" data-end-time="11:15"' );
		$( '[data-booking-popup]' ).trigger( 'click' );
		expect( $( '#popup-start-time' ).text() ).toBe( '10:00' );
		expect( $( '#popup-end-time' ).text() ).toBe( '11:15' );
	} );

	it( 'puts each booking time next to its date in the multi-booking list', async () => {
		const $ = await mount( json( [
			booking( 1, { start_time: '9:00 am', end_time: '6:30 pm' } ),
			booking( 2, { start_time: '<b>8</b>', end_time: '' } ),
		] ) );
		$( '[data-booking-popup]' ).trigger( 'click' );
		const text = $( '#popup-bookings-list' ).text();
		expect( text ).toContain( 'November 20, 2026 9:00 am' );
		expect( text ).toContain( 'November 24, 2026 6:30 pm' );
		expect( $( '#popup-bookings-list b' ).length ).toBe( 0 );
	} );
} );
