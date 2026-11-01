/**
 * @jest-environment jsdom
 */

/**
 * PR D Task 4: the booking detail's cancel dialog is the shared rvs dialog
 * (S4, m18). The request keeps its contract (action, nonce, booking_id,
 * reason).
 */
describe( 'booking cancellation dialog', () => {
	const page = `
		<div class="rvs-shell">
			<main id="content">
				<button type="button" id="open" data-rvs-cancel-open data-booking-id="42" aria-haspopup="dialog" aria-controls="dlg">Cancel booking</button>
			</main>
			<div class="rvs-dialog rvs-account-cancel" id="dlg" data-rvs-cancel-dialog data-booking-id="42" aria-describedby="dlg-desc" hidden>
				<div class="rvs-dialog__panel">
					<div class="rvs-dialog__body">
						<h2 class="rvs-dialog__title" id="dlg-title">Cancel booking #1042?</h2>
						<p id="dlg-desc">You are within the free cancellation period.</p>
						<div class="rvs-field">
							<label for="dlg-reason">Reason (optional)</label>
							<select class="rvs-input" id="dlg-reason" data-rvs-cancel-reason>
								<option value="">-</option>
								<option value="plans">My plans changed</option>
								<option value="other_car">I found another car</option>
								<option value="other">Other</option>
							</select>
						</div>
						<div class="rvs-field" data-rvs-cancel-other hidden>
							<label for="dlg-other">Tell us more</label>
							<textarea class="rvs-input" id="dlg-other"></textarea>
						</div>
						<p class="rvs-error" data-rvs-cancel-message role="alert" hidden></p>
						<button type="button" id="keep" data-rvs-cancel-keep>Keep booking</button>
						<button type="button" id="confirm" data-rvs-cancel-confirm>Cancel booking</button>
					</div>
				</div>
			</div>
		</div>`;

	let dialogApi;

	// Both scripts listen on document (delegation): load them once, swap the
	// markup per test.
	beforeAll( () => {
		document.body.innerHTML = page;
		window.mhmRentivaCancellation = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n-cancel',
			i18n: { cancelling: 'Cancelling...', error: 'An error occurred. Please try again.' },
		};
		delete window.mhmRentivaRvsDialog;
		jest.isolateModules( () => {
			require( '../../assets/js/frontend/rvs-dialog.js' );
			require( '../../assets/js/frontend/booking-cancellation.js' );
		} );
		dialogApi = window.mhmRentivaRvsDialog;
		jest.spyOn( dialogApi, 'open' );
	} );

	const el = ( id ) => document.getElementById( id );
	const click = ( node ) => node.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
	const choose = ( value ) => {
		el( 'dlg-reason' ).value = value;
		el( 'dlg-reason' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};

	beforeEach( () => {
		document.body.innerHTML = page;
		dialogApi.open.mockClear();
		global.fetch = jest.fn( () => Promise.resolve( { json: () => Promise.resolve( { success: false, data: { message: 'Nope' } } ) } ) );
	} );

	afterEach( () => {
		if ( dialogApi && dialogApi.current() ) {
			dialogApi.close( dialogApi.current() );
		}
		delete global.fetch;
	} );

	test( 'opens through mhmRentivaRvsDialog.open with focus on "Keep booking"', () => {
		el( 'open' ).focus();
		click( el( 'open' ) );

		expect( dialogApi.open ).toHaveBeenCalledTimes( 1 );
		const [ target, options ] = dialogApi.open.mock.calls[ 0 ];
		expect( target ).toBe( el( 'dlg' ) );
		expect( options.opener ).toBe( el( 'open' ) );
		expect( options.labelledBy ).toBe( 'dlg-title' );
		expect( options.initialFocus ).toBe( el( 'keep' ) );
		expect( el( 'dlg' ).hidden ).toBe( false );
		expect( el( 'dlg' ).classList.contains( 'is-open' ) ).toBe( true );
		expect( el( 'dlg' ).getAttribute( 'role' ) ).toBe( 'dialog' );
		expect( document.activeElement ).toBe( el( 'keep' ) );
	} );

	test( '"Other" reveals the description field', () => {
		click( el( 'open' ) );
		const other = document.querySelector( '[data-rvs-cancel-other]' );

		expect( other.hidden ).toBe( true );
		choose( 'other' );
		expect( other.hidden ).toBe( false );
		choose( 'plans' );
		expect( other.hidden ).toBe( true );
	} );

	test( 'sends action, nonce, booking_id and the reason (label + text)', async () => {
		click( el( 'open' ) );
		choose( 'other' );
		el( 'dlg-other' ).value = 'Flight cancelled';
		click( el( 'confirm' ) );
		await Promise.resolve();

		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		const [ url, init ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wp-admin/admin-ajax.php' );
		expect( init.method ).toBe( 'POST' );
		const body = new URLSearchParams( init.body.toString() );
		expect( body.get( 'action' ) ).toBe( 'mhmrentiva_cancel_booking' );
		expect( body.get( 'nonce' ) ).toBe( 'n-cancel' );
		expect( body.get( 'booking_id' ) ).toBe( '42' );
		expect( body.get( 'reason' ) ).toBe( 'Other: Flight cancelled' );
	} );

	test( 'a plain reason is sent as its label', async () => {
		click( el( 'open' ) );
		choose( 'plans' );
		click( el( 'confirm' ) );
		await Promise.resolve();
		expect( new URLSearchParams( global.fetch.mock.calls[ 0 ][ 1 ].body.toString() ).get( 'reason' ) ).toBe( 'My plans changed' );
	} );

	test( 'a refused cancellation shows the server message and re-enables the button', async () => {
		click( el( 'open' ) );
		click( el( 'confirm' ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		const message = document.querySelector( '[data-rvs-cancel-message]' );
		expect( message.hidden ).toBe( false );
		expect( message.textContent ).toBe( 'Nope' );
		expect( el( 'confirm' ).disabled ).toBe( false );
	} );

	describe( 'after confirming', () => {
		const realLocation = window.location;
		let answer;

		beforeEach( () => {
			delete window.location;
			window.location = { reload: jest.fn() };
			global.fetch = jest.fn( () => new Promise( ( resolve ) => {
				answer = ( body ) => resolve( { json: () => Promise.resolve( body ) } );
			} ) );
		} );

		afterEach( () => {
			window.location = realLocation;
		} );

		const settled = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		test( 'the button is busy and the dialog stays open until the answer; a second click sends nothing', async () => {
			click( el( 'open' ) );
			click( el( 'confirm' ) );

			expect( el( 'confirm' ).disabled ).toBe( true );
			expect( el( 'confirm' ).getAttribute( 'aria-busy' ) ).toBe( 'true' );
			expect( el( 'confirm' ).textContent ).toBe( 'Cancelling...' );
			expect( el( 'keep' ).disabled ).toBe( true );

			click( el( 'confirm' ) );
			document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
			click( el( 'dlg' ) );

			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
			expect( dialogApi.current() ).toBe( el( 'dlg' ) );

			answer( { success: false, data: { message: 'Nope' } } );
			await settled();
		} );

		test( 'success closes the dialog and reloads at once, with no way to submit again', async () => {
			jest.useFakeTimers();
			click( el( 'open' ) );
			click( el( 'confirm' ) );
			answer( { success: true, data: { message: 'Cancelled' } } );
			jest.useRealTimers();
			await settled();

			expect( dialogApi.current() ).toBe( null );
			expect( el( 'dlg' ).hidden ).toBe( true );
			expect( window.location.reload ).toHaveBeenCalledTimes( 1 );

			click( el( 'confirm' ) );
			expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		} );

		test( 'a refusal keeps the dialog open with the server message, and it can be retried or closed', async () => {
			click( el( 'open' ) );
			click( el( 'confirm' ) );
			answer( { success: false, data: { message: 'Too late' } } );
			await settled();

			expect( dialogApi.current() ).toBe( el( 'dlg' ) );
			expect( document.querySelector( '[data-rvs-cancel-message]' ).textContent ).toBe( 'Too late' );
			expect( el( 'confirm' ).getAttribute( 'aria-busy' ) ).toBe( 'false' );
			expect( el( 'confirm' ).textContent ).toBe( 'Cancel booking' );
			expect( el( 'keep' ).disabled ).toBe( false );
			expect( window.location.reload ).not.toHaveBeenCalled();

			click( el( 'confirm' ) );
			expect( global.fetch ).toHaveBeenCalledTimes( 2 );
			answer( { success: false, data: { message: 'Too late' } } );
			await settled();

			document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );
			expect( dialogApi.current() ).toBe( null );
		} );

		test( 'a network failure keeps the dialog open with the generic message', async () => {
			global.fetch = jest.fn( () => Promise.reject( new Error( 'offline' ) ) );
			click( el( 'open' ) );
			click( el( 'confirm' ) );
			await settled();

			expect( dialogApi.current() ).toBe( el( 'dlg' ) );
			expect( document.querySelector( '[data-rvs-cancel-message]' ).textContent ).toBe( 'An error occurred. Please try again.' );
			expect( el( 'confirm' ).disabled ).toBe( false );
		} );
	} );

	test( 'Escape closes and focus returns to the cancel button', () => {
		el( 'open' ).focus();
		click( el( 'open' ) );
		expect( document.activeElement ).toBe( el( 'keep' ) );

		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true } ) );

		expect( dialogApi.current() ).toBe( null );
		expect( el( 'dlg' ).hidden ).toBe( true );
		expect( el( 'dlg' ).classList.contains( 'is-open' ) ).toBe( false );
		expect( document.activeElement ).toBe( el( 'open' ) );
	} );

	test( '"Keep booking" closes and resets the reason', () => {
		click( el( 'open' ) );
		choose( 'other' );
		click( el( 'keep' ) );

		expect( dialogApi.current() ).toBe( null );
		expect( el( 'dlg-reason' ).value ).toBe( '' );
		expect( document.querySelector( '[data-rvs-cancel-other]' ).hidden ).toBe( true );
		expect( document.activeElement ).toBe( el( 'open' ) );
	} );
} );
