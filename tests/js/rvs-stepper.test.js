/**
 * @jest-environment jsdom
 */

/**
 * Aşama 2 PR C: the shared quantity stepper -- 0..max (1..max for a required
 * add-on), a zero never reaches the request (both hidden fields disabled),
 * buttons disabled at the limits with focus kept on the stepper, one
 * `rvs:stepper-change` per change.
 */
describe( 'rvs stepper', () => {
	const stepper = ( id, value, min, max ) => `
		<div class="rvs-option">
			<span id="seat-${ id }">Child seat</span>
			<div class="rvs-stepper" role="group" aria-labelledby="seat-${ id }" data-rvs-stepper="${ id }" data-min="${ min }" data-max="${ max }">
				<button type="button" data-rvs-step="-1" aria-label="Decrease">-</button>
				<output aria-live="polite">${ value }</output>
				<button type="button" data-rvs-step="1" aria-label="Increase">+</button>
				<input type="hidden" name="addons[]" value="${ id }">
				<input type="hidden" name="addon_qty[${ id }]" value="${ value }">
			</div>
		</div>`;

	let root;
	let events;

	const boot = ( html ) => {
		jest.resetModules();
		delete window.MHMRvsStepper;
		document.body.innerHTML = `<form id="f">${ html }</form>`;
		root = document.getElementById( 'f' );
		events = [];
		root.addEventListener( 'rvs:stepper-change', ( e ) => events.push( e.detail ) );
		require( '../../assets/js/frontend/rvs-stepper.js' );
		window.MHMRvsStepper.init( root );
	};

	const parts = ( id ) => {
		const el = root.querySelector( `[data-rvs-stepper="${ id }"]` );
		return {
			el,
			dec: el.querySelector( '[data-rvs-step="-1"]' ),
			inc: el.querySelector( '[data-rvs-step="1"]' ),
			out: el.querySelector( 'output' ),
			addon: el.querySelector( 'input[name="addons[]"]' ),
			qty: el.querySelector( `input[name="addon_qty[${ id }]"]` ),
		};
	};

	test( 'stepper never sends a zero quantity', () => {
		boot( stepper( 5, 2, 0, 3 ) );
		const s = parts( 5 );

		s.dec.click();
		expect( s.out.textContent ).toBe( '1' );
		expect( s.qty.value ).toBe( '1' );
		expect( s.qty.disabled ).toBe( false );
		expect( s.addon.disabled ).toBe( false );

		s.dec.click();
		expect( s.out.textContent ).toBe( '0' );
		expect( s.qty.disabled ).toBe( true );
		expect( s.addon.disabled ).toBe( true );
		expect( events ).toEqual( [ { id: '5', value: 1 }, { id: '5', value: 0 } ] );

		// What a form submission would carry: nothing for this add-on.
		const sent = Array.from( new FormData( root ).keys() );
		expect( sent ).not.toContain( 'addons[]' );
		expect( sent ).not.toContain( 'addon_qty[5]' );

		// Back up: both fields return.
		s.inc.click();
		expect( s.qty.disabled ).toBe( false );
		expect( s.addon.disabled ).toBe( false );
		expect( new FormData( root ).get( 'addon_qty[5]' ) ).toBe( '1' );
	} );

	test( 'a stepper that starts at zero sends nothing', () => {
		boot( stepper( 6, 0, 0, 2 ) );
		const s = parts( 6 );

		expect( s.qty.disabled ).toBe( true );
		expect( s.addon.disabled ).toBe( true );
		expect( s.dec.disabled ).toBe( true );
		expect( events ).toEqual( [] );
	} );

	test( 'stepper respects max and min', () => {
		boot( stepper( 7, 2, 0, 3 ) + stepper( 8, 2, 1, 4 ) );
		const a = parts( 7 );
		const b = parts( 8 );

		a.inc.click();
		expect( a.out.textContent ).toBe( '3' );
		expect( a.inc.disabled ).toBe( true );
		a.inc.click();
		expect( a.out.textContent ).toBe( '3' );

		b.dec.click();
		expect( b.out.textContent ).toBe( '1' );
		expect( b.dec.disabled ).toBe( true );
		b.dec.click();
		expect( b.out.textContent ).toBe( '1' );
		// A required add-on at its floor still sends its one.
		expect( b.qty.disabled ).toBe( false );
		expect( b.addon.disabled ).toBe( false );

		// Neither stepper moved the other; no event for the refused presses.
		expect( events ).toEqual( [ { id: '7', value: 3 }, { id: '8', value: 1 } ] );
	} );

	test( 'focus stays on the stepper at the limits', () => {
		boot( stepper( 9, 1, 0, 3 ) );
		const s = parts( 9 );

		s.dec.focus();
		s.dec.click();
		expect( s.dec.disabled ).toBe( true );
		expect( document.activeElement ).toBe( s.inc );

		s.inc.click();
		s.inc.click();
		expect( s.out.textContent ).toBe( '2' );
		expect( document.activeElement ).toBe( s.inc );
		s.inc.click();
		expect( s.inc.disabled ).toBe( true );
		expect( document.activeElement ).toBe( s.dec );
		expect( document.activeElement ).not.toBe( document.body );
	} );

	test( 'init binds each stepper once', () => {
		boot( stepper( 10, 1, 0, 3 ) );
		window.MHMRvsStepper.init( root );
		window.MHMRvsStepper.init( document );
		const s = parts( 10 );

		s.inc.click();
		expect( s.out.textContent ).toBe( '2' );
		expect( events ).toHaveLength( 1 );
	} );

	test( 'a value outside the range is pulled in on init', () => {
		boot( stepper( 11, 9, 0, 3 ) );
		const s = parts( 11 );

		expect( s.out.textContent ).toBe( '3' );
		expect( s.qty.value ).toBe( '3' );
		expect( s.inc.disabled ).toBe( true );
	} );
} );
