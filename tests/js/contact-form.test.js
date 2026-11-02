/**
 * @jest-environment jsdom
 */

/**
 * PR F: topic switching, field errors under the field, the permanent success box,
 * the trap field, two instances on one page.
 */

const form = ( uid, extra = '' ) => `
	<div class="rvs-contact" data-rvs-contact data-form-type="general" ${ extra }>
		<div class="rvs-contact__status" role="status"></div>
		<form novalidate>
			<input type="hidden" name="recipient_token" value="t-${ uid }">
			<fieldset>
				<label><input type="radio" name="type" value="general" checked>General</label>
				<label><input type="radio" name="type" value="booking">My booking</label>
				<label><input type="radio" name="type" value="support">Support</label>
			</fieldset>
			<div data-rvs-field><label for="${ uid }-name">Name</label><input id="${ uid }-name" name="name" required value=""></div>
			<div data-rvs-field><label for="${ uid }-email">Email</label><input id="${ uid }-email" type="email" name="email" required value=""></div>
			<div data-rvs-field data-rvs-topics="booking" hidden>
				<select id="${ uid }-booking" name="booking_id" aria-describedby="${ uid }-booking-help" disabled><option value=""></option><option value="7" selected>#7</option></select>
				<span id="${ uid }-booking-help">help</span>
			</div>
			<div data-rvs-field data-rvs-required-topics="support">
				<label for="${ uid }-priority" data-rvs-label-required="Priority" data-rvs-label-optional="Priority (optional)">Priority (optional)</label>
				<select id="${ uid }-priority" name="priority"><option value=""></option><option value="low">Low</option></select>
			</div>
			<div data-rvs-field><textarea id="${ uid }-message" name="message" required></textarea></div>
			<div data-rvs-field>
				<input type="file" id="${ uid }-attachment" name="attachment" aria-describedby="${ uid }-attachment-help">
				<span data-rvs-file-name>No file chosen</span>
				<button type="button" data-rvs-file-remove hidden>Remove</button>
				<span id="${ uid }-attachment-help">types</span>
			</div>
			<div data-rvs-field><input type="checkbox" id="${ uid }-consent" name="consent" value="1" required></div>
			<input type="text" id="${ uid }-trap" name="rvs_leave_blank" tabindex="-1" autocomplete="off">
			<div class="rvs-contact__actions"><button type="submit">Send</button></div>
		</form>
	</div>`;

const flush = async () => {
	for ( let i = 0; i < 8; i++ ) {
		await Promise.resolve();
	}
};

const submit = ( el ) => el.dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );

const pick = ( el, value ) => {
	const radio = el.querySelector( `input[name="type"][value="${ value }"]` );
	radio.checked = true;
	radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );
};

const fillValid = ( el ) => {
	el.elements.name.value = 'Ada';
	el.elements.email.value = 'ada@example.com';
	el.elements.message.value = 'Hello';
	el.elements.consent.checked = true;
};

const setFile = ( input, name, size = 10 ) => {
	const file = new File( [ 'x' ], name );
	Object.defineProperty( file, 'size', { value: size } );
	Object.defineProperty( input, 'files', { value: [ file ], configurable: true } );
};

describe( 'contact form', () => {
	let first;
	let second;

	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = form( 'a' ) + form( 'b' );
		window.mhmContactForm = {
			ajaxUrl: '/ajax',
			nonce: 'n',
			maxFileSize: 100,
			allowedFileTypes: [ 'jpg', 'pdf' ],
			icons: { warning: '<svg class="warn"></svg>' },
			messages: {
				submitting: 'Sending',
				error: 'Network error',
				success_title: 'Received, request #%s',
				success_title_plain: 'Received',
				success_body: 'We will reply.',
				required_fields: 'Fill it',
				invalid_email: 'Bad email',
				file_type: 'Bad type',
				file_size: 'Too big',
				required: { name: 'Write name', email: 'Write email', message: 'Write message', priority: 'Pick priority', consent: 'Confirm' },
			},
		};
		global.fetch = jest.fn().mockResolvedValue( {
			json: () => Promise.resolve( { success: true, data: { message_id: 42 } } ),
		} );
		require( '../../assets/js/frontend/contact-form.js' );
		[ first, second ] = document.querySelectorAll( 'form' );
	} );

	describe( 'topic switching', () => {
		test( 'the booking field appears and is enabled only on its topic', () => {
			const booking = first.elements.booking_id;
			expect( booking.closest( '[data-rvs-topics]' ).hidden ).toBe( true );
			expect( booking.disabled ).toBe( true );

			pick( first, 'booking' );
			expect( booking.closest( '[data-rvs-topics]' ).hidden ).toBe( false );
			expect( booking.disabled ).toBe( false );
			expect( first.closest( '[data-rvs-contact]' ).dataset.formType ).toBe( 'booking' );

			pick( first, 'general' );
			expect( booking.closest( '[data-rvs-topics]' ).hidden ).toBe( true );
			expect( booking.disabled ).toBe( true );
		} );

		test( 'a booking chosen earlier is not sent with another topic', async () => {
			fillValid( first );
			pick( first, 'booking' );
			pick( first, 'general' );
			submit( first );
			await flush();

			const body = global.fetch.mock.calls[ 0 ][ 1 ].body;
			expect( body.has( 'booking_id' ) ).toBe( false );
			expect( body.get( 'type' ) ).toBe( 'general' );
		} );

		test( 'requirement and label follow the topic', () => {
			const priority = first.elements.priority;
			const label = priority.closest( '[data-rvs-field]' ).querySelector( 'label' );
			pick( first, 'support' );
			expect( priority.required ).toBe( true );
			expect( label.textContent ).toBe( 'Priority' );
			pick( first, 'general' );
			expect( priority.required ).toBe( false );
			expect( label.textContent ).toBe( 'Priority (optional)' );
		} );
	} );

	describe( 'errors', () => {
		test( 'client errors sit under their fields; focus goes to the first; nothing is sent', async () => {
			first.elements.email.value = 'ayse@ornek';
			submit( first );
			await flush();

			expect( global.fetch ).not.toHaveBeenCalled();
			const name = first.elements.name;
			expect( name.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
			const nameError = document.getElementById( name.getAttribute( 'aria-describedby' ) );
			expect( nameError.textContent ).toBe( 'Write name' );
			expect( nameError.parentElement ).toBe( name.closest( '[data-rvs-field]' ) );
			expect( document.activeElement ).toBe( name );
			expect( document.getElementById( first.elements.email.getAttribute( 'aria-describedby' ) ).textContent ).toBe( 'Bad email' );
			expect( document.getElementById( first.elements.consent.getAttribute( 'aria-describedby' ) ).textContent ).toBe( 'Confirm' );
		} );

		test( 'an existing description is kept, and comes back when the error clears', () => {
			fillValid( first );
			const input = first.elements.attachment;
			setFile( input, 'virus.exe' );
			submit( first );

			expect( input.getAttribute( 'aria-describedby' ) ).toBe( 'a-attachment-help a-attachment-error' );
			expect( document.getElementById( 'a-attachment-error' ).textContent ).toBe( 'Bad type' );

			first.querySelector( '[data-rvs-file-remove]' ).click();
			expect( input.getAttribute( 'aria-describedby' ) ).toBe( 'a-attachment-help' );
			expect( input.hasAttribute( 'aria-invalid' ) ).toBe( false );
		} );

		test( 'the chosen file is named next to its remove button, and the name goes with it', () => {
			const input = first.elements.attachment;
			const name = first.querySelector( '[data-rvs-file-name]' );
			setFile( input, 'photo.png' );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			expect( name.textContent ).toBe( 'photo.png' );
			expect( first.querySelector( '[data-rvs-file-remove]' ).hidden ).toBe( false );

			first.querySelector( '[data-rvs-file-remove]' ).click();
			expect( name.textContent ).toBe( 'No file chosen' );
		} );

		test( 'a file over the limit is refused', () => {
			fillValid( first );
			setFile( first.elements.attachment, 'big.pdf', 101 );
			submit( first );
			expect( document.getElementById( 'a-attachment-error' ).textContent ).toBe( 'Too big' );
		} );

		test( 'typing clears that field\'s error only', () => {
			submit( first );
			first.elements.name.value = 'A';
			first.elements.name.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			expect( first.elements.name.hasAttribute( 'aria-invalid' ) ).toBe( false );
			expect( first.elements.email.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		} );

		test( 'server field errors go under the field', async () => {
			global.fetch.mockResolvedValue( {
				json: () => Promise.resolve( { success: false, data: { message: 'Fix', errors: { email: 'Server says no' } } } ),
			} );
			fillValid( first );
			submit( first );
			await flush();

			expect( document.getElementById( 'a-email-error' ).textContent ).toBe( 'Server says no' );
			expect( document.activeElement ).toBe( first.elements.email );
			expect( first.querySelector( '.rvs-notice--alert' ) ).toBeNull();
		} );

		test( 'an error without a field, or a network failure, shows one alert; the form stays usable', async () => {
			global.fetch.mockResolvedValue( { json: () => Promise.resolve( { success: false, data: { message: 'Too many' } } ) } );
			fillValid( first );
			submit( first );
			await flush();
			expect( first.querySelector( '.rvs-notice--alert' ).textContent ).toBe( 'Too many' );
			expect( first.querySelector( '[type="submit"]' ).disabled ).toBe( false );

			global.fetch.mockRejectedValue( new Error( 'down' ) );
			submit( first );
			await flush();
			expect( first.querySelectorAll( '.rvs-notice--alert' ) ).toHaveLength( 1 );
			expect( first.querySelector( '.rvs-notice--alert' ).textContent ).toBe( 'Network error' );
			expect( first.hidden ).toBe( false );
		} );

		test( 'message text is never parsed as markup', async () => {
			global.fetch.mockResolvedValue( { json: () => Promise.resolve( { success: false, data: { message: '<img src=x>' } } ) } );
			fillValid( first );
			submit( first );
			await flush();
			expect( first.querySelector( '.rvs-notice--alert img' ) ).toBeNull();
		} );
	} );

	describe( 'success', () => {
		test( 'the form hides, the box stays with the request number and takes focus', async () => {
			jest.useFakeTimers();
			fillValid( first );
			submit( first );
			await flush();
			jest.advanceTimersByTime( 60000 );
			jest.useRealTimers();

			const box = first.closest( '[data-rvs-contact]' ).querySelector( '.rvs-contact__status .rvs-notice--success' );
			expect( box.querySelector( 'strong' ).textContent ).toBe( 'Received, request #42' );
			expect( box.textContent ).toContain( 'We will reply.' );
			expect( first.hidden ).toBe( true );
			expect( document.activeElement ).toBe( box );
		} );

		test( 'the form\'s success message setting wins, as text', async () => {
			first.closest( '[data-rvs-contact]' ).dataset.successMessage = '<b>Thanks</b>';
			fillValid( first );
			submit( first );
			await flush();
			const box = document.querySelector( '.rvs-notice--success' );
			expect( box.textContent ).toContain( '<b>Thanks</b>' );
			expect( box.querySelector( 'b' ) ).toBeNull();
		} );

		test( 'no number is claimed when the server gives none', async () => {
			global.fetch.mockResolvedValue( { json: () => Promise.resolve( { success: true, data: { message_id: 0 } } ) } );
			fillValid( first );
			submit( first );
			await flush();
			expect( document.querySelector( '.rvs-notice--success strong' ).textContent ).toBe( 'Received' );
		} );
	} );

	test( 'the trap field goes out empty, with the action and nonce', async () => {
		fillValid( first );
		submit( first );
		await flush();

		const body = global.fetch.mock.calls[ 0 ][ 1 ].body;
		expect( body.get( 'rvs_leave_blank' ) ).toBe( '' );
		expect( body.get( 'action' ) ).toBe( 'mhmrentiva_submit_contact_form' );
		expect( body.get( 'nonce' ) ).toBe( 'n' );
		expect( body.get( 'recipient_token' ) ).toBe( 't-a' );
	} );

	describe( 'two instances', () => {
		test( 'topic, errors and success stay in their own form', async () => {
			pick( first, 'booking' );
			expect( second.elements.booking_id.disabled ).toBe( true );
			expect( second.closest( '[data-rvs-contact]' ).dataset.formType ).toBe( 'general' );

			submit( first );
			expect( first.querySelectorAll( '.rvs-error' ).length ).toBeGreaterThan( 0 );
			expect( second.querySelectorAll( '.rvs-error' ) ).toHaveLength( 0 );

			fillValid( second );
			submit( second );
			await flush();
			expect( second.hidden ).toBe( true );
			expect( first.hidden ).toBe( false );
			expect( global.fetch.mock.calls[ 0 ][ 1 ].body.get( 'recipient_token' ) ).toBe( 't-b' );
			expect( first.closest( '[data-rvs-contact]' ).querySelector( '.rvs-notice--success' ) ).toBeNull();
		} );

		test( 'no id is shared after errors on both', () => {
			submit( first );
			submit( second );
			const ids = Array.from( document.querySelectorAll( '[id]' ) ).map( ( el ) => el.id );
			expect( new Set( ids ).size ).toBe( ids.length );
		} );
	} );
} );
