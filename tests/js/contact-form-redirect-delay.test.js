/**
 * @jest-environment jsdom
 */

/**
 * Spec 3A Task 10a item 8: with a redirect URL set, the success message is shown
 * first and the page leaves only after a short delay (it used to leave at once,
 * so the message was never visible).
 */
describe( 'contact form success redirect', () => {
	let assign;

	beforeEach( () => {
		jest.useFakeTimers();
		jest.resetModules();
		document.body.innerHTML = `
			<div class="rv-contact-form" data-redirect-url="http://rentiva.localhost/thank-you/">
				<form>
					<input name="name" value="A">
					<div class="rv-form-actions"><button type="submit" class="rv-submit-button">Send</button></div>
				</form>
			</div>`;

		window.jQuery = jest.fn();
		window.mhmContactForm = {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'n',
			icons: {},
			messages: { submitting: 'Sending', success: 'Sent!', error: 'Error' },
		};
		global.fetch = jest.fn().mockResolvedValue( {
			json: () => Promise.resolve( { success: true, data: { message: 'Thanks, we got it.' } } ),
		} );

		assign = jest.fn();
		delete window.location;
		window.location = { assign };

		require( '../../assets/js/frontend/contact-form.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	const flush = async () => {
		for ( let i = 0; i < 5; i++ ) {
			await Promise.resolve();
		}
	};

	test( 'shows the success message, then redirects after a delay', async () => {
		document.querySelector( 'form' ).dispatchEvent( new Event( 'submit', { cancelable: true } ) );
		await flush();

		const message = document.querySelector( '.rv-form-message.rv-success .rv-msg-text' );
		expect( message ).not.toBeNull();
		expect( message.textContent ).toBe( 'Thanks, we got it.' );
		expect( assign ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( 1499 );
		expect( assign ).not.toHaveBeenCalled();

		jest.advanceTimersByTime( 1 );
		expect( assign ).toHaveBeenCalledWith( 'http://rentiva.localhost/thank-you/' );
	} );
} );
