/**
 * @jest-environment jsdom
 */

/**
 * Spec 3A Task 10a item 8 + PR F R7 (m4): with a redirect URL set, the permanent
 * success box is shown first and the page leaves only after a short delay.
 */
describe( 'contact form success redirect', () => {
	let assign;

	beforeEach( () => {
		jest.useFakeTimers();
		jest.resetModules();
		document.body.innerHTML = `
			<div class="rvs-contact" data-rvs-contact data-redirect-url="http://rentiva.localhost/thank-you/">
				<div class="rvs-contact__status" role="status"></div>
				<form>
					<div data-rvs-field><input id="n" name="name" value="A"></div>
					<div class="rvs-contact__actions"><button type="submit">Send</button></div>
				</form>
			</div>`;

		window.mhmContactForm = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			icons: {},
			messages: {
				submitting: 'Sending',
				error: 'Error',
				success_title: 'Received, request #%s',
				success_title_plain: 'Received',
				success_body: 'We will reply by email.',
			},
		};
		global.fetch = jest.fn().mockResolvedValue( {
			json: () => Promise.resolve( { success: true, data: { message_id: 2291 } } ),
		} );

		assign = jest.fn();
		delete window.location;
		window.location = { assign };

		require( '../../assets/js/frontend/contact-form.js' );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	const flush = async () => {
		for ( let i = 0; i < 5; i++ ) {
			await Promise.resolve();
		}
	};

	test( 'shows the success box, then redirects after a delay', async () => {
		document.querySelector( 'form' ).dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await flush();

		const box = document.querySelector( '.rvs-contact__status .rvs-notice--success' );
		expect( box ).not.toBeNull();
		expect( box.textContent ).toContain( 'Received, request #2291' );
		expect( box.textContent ).toContain( 'We will reply by email.' );
		expect( document.querySelector( 'form' ).hidden ).toBe( true );
		expect( assign ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( 1499 );
		expect( assign ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( 1 );
		expect( assign ).toHaveBeenCalledWith( 'http://rentiva.localhost/thank-you/' );
		expect( document.querySelector( '.rvs-notice--success' ) ).not.toBeNull();
	} );
} );
