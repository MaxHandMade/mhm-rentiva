/**
 * @jest-environment jsdom
 */

/**
 * PR F: errors under their fields, the counter, the lasting answer, edit in place,
 * delete through the shared dialog, two instances on a page.
 */

const instance = ( uid, extra = '' ) => `
	<div class="rvs-reviews" data-rvs-rating data-vehicle-id="${ uid }">
		<div class="rvs-reviews__list">
			<article class="rvs-review" data-comment-id="${ uid }0">
				<button type="button" id="${ uid }-edit" data-rvs-review-edit data-rating="3" data-comment="Old &quot;text&quot; &lt;b&gt;">Edit</button>
				<button type="button" id="${ uid }-delete" data-rvs-review-delete>Delete</button>
			</article>
		</div>
		<div class="rvs-reviews__status" data-rvs-rating-status role="status"></div>
		<form data-rvs-rating-form novalidate ${ extra }>
			<h3 data-rvs-rating-title>Rate this vehicle</h3>
			<input type="hidden" name="nonce" value="form-nonce">
			<fieldset id="${ uid }-rating" data-rvs-field data-rvs-rating-stars>
				<legend>Your rating</legend>
				<input type="radio" name="rating" value="1"><input type="radio" name="rating" value="2">
				<input type="radio" name="rating" value="3"><input type="radio" name="rating" value="4">
				<input type="radio" name="rating" value="5">
			</fieldset>
			<div data-rvs-field>
				<textarea name="comment" id="${ uid }-comment" aria-describedby="${ uid }-count" data-min-length="5" data-max-length="20"></textarea>
				<span id="${ uid }-count" data-rvs-rating-count aria-live="polite"></span>
			</div>
			<div data-rvs-rating-alert role="alert" hidden></div>
			<button type="submit">Submit review</button>
			<button type="button" data-rvs-rating-cancel hidden>Cancel</button>
		</form>
		<div class="rvs-dialog" id="${ uid }-delete-dlg" data-rvs-review-dialog hidden>
			<h2 id="${ uid }-delete-dlg-title">Delete?</h2>
			<p data-rvs-review-dialog-message role="alert" hidden></p>
			<button type="button" data-rvs-review-dialog-keep>Keep</button>
			<button type="button" data-rvs-review-dialog-confirm>Delete</button>
		</div>
	</div>`;

const flush = async () => {
	for ( let i = 0; i < 8; i++ ) {
		await Promise.resolve();
	}
};

const answer = ( body ) => Promise.resolve( { json: () => Promise.resolve( body ) } );

function load( html ) {
	document.body.innerHTML = html;
	jest.isolateModules( () => {
		require( '../../assets/js/frontend/rvs-dialog.js' );
		require( '../../assets/js/frontend/vehicle-rating-form.js' );
	} );
}

const root = ( uid ) => document.querySelector( `[data-vehicle-id="${ uid }"]` );
const formOf = ( uid ) => root( uid ).querySelector( 'form' );
const submit = ( uid ) => formOf( uid ).dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );
const fillIn = ( uid, stars, text ) => {
	formOf( uid ).querySelector( `input[value="${ stars }"]` ).checked = true;
	const area = formOf( uid ).querySelector( 'textarea' );
	area.value = text;
	area.dispatchEvent( new Event( 'input', { bubbles: true } ) );
};

describe( 'vehicle rating form', () => {
	beforeEach( () => {
		delete window.mhmRentivaRvsDialog;
		window.sessionStorage.clear();
		window.mhmVehicleRating = {
			ajaxUrl: '/ajax',
			nonce: 'script-nonce',
			strings: {
				select_rating: 'Choose a rating.',
				comment_short: 'At least %1$s.',
				comment_long: 'At most %1$s.',
				count_min: '%1$s / %2$s, at least %3$s',
				count: '%1$s / %2$s',
				send_failed: 'Not sent',
				connection: 'Connection lost',
				sending: 'Sending...',
				update: 'Update review',
				edit_heading: 'Edit your review',
				deleted_title: 'Deleted',
				deleted: 'Removed.',
			},
		};
		global.fetch = jest.fn();
		// jsdom cannot navigate and says so on the console; the reload itself is the browser's.
		jest.spyOn( console, 'error' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	test( 'counter speaks the trimmed length and the limits', () => {
		load( instance( 'a' ) );
		const count = formOf( 'a' ).querySelector( '[data-rvs-rating-count]' );
		expect( count.textContent ).toBe( '0 / 20, at least 5' );
		fillIn( 'a', 4, '  abc  ' );
		expect( count.textContent ).toBe( '3 / 20, at least 5' );
	} );

	test( 'empty submit: errors under the fields, focus on the first radio, nothing sent', () => {
		load( instance( 'a' ) );
		submit( 'a' );

		expect( fetch ).not.toHaveBeenCalled();
		const stars = formOf( 'a' ).querySelector( '[data-rvs-rating-stars]' );
		expect( stars.querySelector( '.rvs-error' ).textContent ).toBe( 'Choose a rating.' );
		expect( stars.getAttribute( 'aria-describedby' ) ).toBe( 'a-rating-error' );
		const area = formOf( 'a' ).querySelector( 'textarea' );
		expect( area.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect( area.getAttribute( 'aria-describedby' ) ).toBe( 'a-count a-comment-error' );
		expect( document.activeElement ).toBe( stars.querySelector( 'input' ) );
	} );

	test( 'an error leaves when the field is fixed and restores aria-describedby', () => {
		load( instance( 'a' ) );
		submit( 'a' );
		fillIn( 'a', 4, 'Long enough' );

		const area = formOf( 'a' ).querySelector( 'textarea' );
		expect( area.hasAttribute( 'aria-invalid' ) ).toBe( false );
		expect( area.getAttribute( 'aria-describedby' ) ).toBe( 'a-count' );
		expect( formOf( 'a' ).querySelector( 'textarea ~ .rvs-error' ) ).toBeNull();
	} );

	test( 'too long is refused', () => {
		load( instance( 'a' ) );
		fillIn( 'a', 4, 'x'.repeat( 21 ) );
		submit( 'a' );
		expect( fetch ).not.toHaveBeenCalled();
		expect( formOf( 'a' ).querySelector( '.rvs-error' ).textContent ).toBe( 'At most 20.' );
	} );

	test( 'a server refusal shows the alert and keeps the text', async () => {
		load( instance( 'a' ) );
		fetch.mockReturnValue( answer( { success: false, data: { message: 'Wait <b>a</b> moment' } } ) );
		fillIn( 'a', 4, 'Long enough' );
		submit( 'a' );
		await flush();

		const alert = formOf( 'a' ).querySelector( '[data-rvs-rating-alert]' );
		expect( alert.hidden ).toBe( false );
		expect( alert.textContent ).toContain( 'Wait <b>a</b> moment' );
		expect( alert.querySelector( 'b' ) ).toBeNull();
		expect( formOf( 'a' ).querySelector( 'textarea' ).value ).toBe( 'Long enough' );
		expect( formOf( 'a' ).querySelector( '[type="submit"]' ).disabled ).toBe( false );
	} );

	test( 'a lost connection keeps the text too', async () => {
		load( instance( 'a' ) );
		fetch.mockReturnValue( Promise.reject( new Error( 'down' ) ) );
		fillIn( 'a', 4, 'Long enough' );
		submit( 'a' );
		await flush();

		expect( formOf( 'a' ).querySelector( '[data-rvs-rating-alert]' ).textContent ).toContain( 'Connection lost' );
		expect( formOf( 'a' ).querySelector( 'textarea' ).value ).toBe( 'Long enough' );
	} );

	test( 'posts the form nonce and vehicle', async () => {
		load( instance( 'a' ) );
		fetch.mockReturnValue( answer( { success: true, data: { reload: false, title: 'T', message: 'M' } } ) );
		fillIn( 'a', 4, 'Long enough' );
		submit( 'a' );
		await flush();

		const body = fetch.mock.calls[ 0 ][ 1 ].body;
		expect( body.get( 'action' ) ).toBe( 'mhmrentiva_submit_rating' );
		expect( body.get( 'nonce' ) ).toBe( 'form-nonce' );
		expect( body.get( 'vehicle_id' ) ).toBe( 'a' );
		expect( body.get( 'rating' ) ).toBe( '4' );
	} );

	test( 'no reload: the answer stays on the page, form empty, focus on the box', async () => {
		load( instance( 'a' ) );
		fetch.mockReturnValue( answer( { success: true, data: { reload: false, title: 'We received it', message: 'Soon <i>visible</i>' } } ) );
		fillIn( 'a', 4, 'Long enough' );
		submit( 'a' );
		await flush();

		const status = root( 'a' ).querySelector( '[data-rvs-rating-status]' );
		expect( status.querySelector( 'strong' ).textContent ).toBe( 'We received it' );
		expect( status.querySelector( 'i' ) ).toBeNull();
		expect( document.activeElement ).toBe( status );
		expect( formOf( 'a' ).querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'reload answer survives the reload, once, for its vehicle only', async () => {
		load( instance( 'a' ) );
		fetch.mockReturnValue( answer( { success: true, data: { reload: true, title: 'Published', message: 'In the list' } } ) );
		fillIn( 'a', 4, 'Long enough' );
		submit( 'a' );
		await flush();

		// jsdom cannot navigate: the second load below stands in for the reload.
		load( instance( 'b' ) );
		expect( root( 'b' ).querySelector( '[data-rvs-rating-status]' ).textContent ).toBe( '' );

		load( instance( 'a' ) );
		expect( root( 'a' ).querySelector( '[data-rvs-rating-status] strong' ).textContent ).toBe( 'Published' );

		load( instance( 'a' ) );
		expect( root( 'a' ).querySelector( '[data-rvs-rating-status]' ).textContent ).toBe( '' );
	} );

	test( 'a form printed with the own review counts its text and needs no cancel', () => {
		load( instance( 'a' ).replace( '></textarea>', '>Already written</textarea>' ) );
		const form = formOf( 'a' );
		expect( form.querySelector( '[data-rvs-rating-count]' ).textContent ).toBe( '15 / 20, at least 5' );
		expect( form.querySelector( '[data-rvs-rating-cancel]' ).hidden ).toBe( true );
	} );

	test( 'edit opens the hidden form with the old values; cancel puts it back', () => {
		load( instance( 'a', 'hidden' ) );
		const form = formOf( 'a' );
		const opener = document.getElementById( 'a-edit' );
		opener.click();

		expect( form.hidden ).toBe( false );
		expect( form.querySelector( 'textarea' ).value ).toBe( 'Old "text" <b>' );
		expect( form.querySelector( 'input[value="3"]' ).checked ).toBe( true );
		expect( form.querySelector( '[type="submit"]' ).textContent ).toBe( 'Update review' );
		expect( form.querySelector( '[data-rvs-rating-title]' ).textContent ).toBe( 'Edit your review' );
		expect( document.activeElement ).toBe( form.querySelector( 'textarea' ) );

		form.querySelector( '[data-rvs-rating-cancel]' ).click();
		expect( form.hidden ).toBe( true );
		expect( form.querySelector( 'textarea' ).value ).toBe( '' );
		expect( form.querySelector( '[type="submit"]' ).textContent ).toBe( 'Submit review' );
		expect( document.activeElement ).toBe( opener );
	} );

	test( 'delete asks in the dialog, focus on Keep; Keep sends nothing and returns focus', () => {
		load( instance( 'a' ) );
		const opener = document.getElementById( 'a-delete' );
		const dialog = root( 'a' ).querySelector( '[data-rvs-review-dialog]' );
		opener.focus();
		opener.click();

		expect( dialog.hidden ).toBe( false );
		expect( dialog.classList.contains( 'is-open' ) ).toBe( true );
		expect( dialog.getAttribute( 'role' ) ).toBe( 'dialog' );
		expect( dialog.getAttribute( 'aria-labelledby' ) ).toBe( 'a-delete-dlg-title' );
		expect( document.activeElement ).toBe( dialog.querySelector( '[data-rvs-review-dialog-keep]' ) );

		dialog.querySelector( '[data-rvs-review-dialog-keep]' ).click();
		expect( dialog.hidden ).toBe( true );
		expect( fetch ).not.toHaveBeenCalled();
		expect( document.activeElement ).toBe( opener );
	} );

	test( 'confirmed delete posts the comment and leaves the answer for the reload', async () => {
		load( instance( 'a' ) );
		fetch.mockReturnValue( answer( { success: true, data: {} } ) );
		document.getElementById( 'a-delete' ).click();
		root( 'a' ).querySelector( '[data-rvs-review-dialog-confirm]' ).click();
		await flush();

		const body = fetch.mock.calls[ 0 ][ 1 ].body;
		expect( body.get( 'action' ) ).toBe( 'mhmrentiva_delete_rating' );
		expect( body.get( 'comment_id' ) ).toBe( 'a0' );
		expect( body.get( 'nonce' ) ).toBe( 'script-nonce' );
		expect( JSON.parse( window.sessionStorage.getItem( 'mhmRentivaRatingNotice' ) ).title ).toBe( 'Deleted' );
	} );

	test( 'a refused delete says so inside the dialog', async () => {
		load( instance( 'a' ) );
		fetch.mockReturnValue( answer( { success: false, data: { message: 'Permission denied' } } ) );
		document.getElementById( 'a-delete' ).click();
		const dialog = root( 'a' ).querySelector( '[data-rvs-review-dialog]' );
		dialog.querySelector( '[data-rvs-review-dialog-confirm]' ).click();
		await flush();

		const message = dialog.querySelector( '[data-rvs-review-dialog-message]' );
		expect( message.hidden ).toBe( false );
		expect( message.textContent ).toBe( 'Permission denied' );
		expect( dialog.querySelector( '[data-rvs-review-dialog-confirm]' ).disabled ).toBe( false );
		window.mhmRentivaRvsDialog.close( dialog );
	} );

	test( 'two instances stay separate', () => {
		load( instance( 'a' ) + instance( 'b' ) );
		submit( 'a' );

		expect( formOf( 'a' ).querySelector( '.rvs-error' ) ).not.toBeNull();
		expect( formOf( 'b' ).querySelector( '.rvs-error' ) ).toBeNull();
		document.getElementById( 'b-edit' ).click();
		expect( formOf( 'b' ).querySelector( 'textarea' ).value ).toBe( 'Old "text" <b>' );
		expect( formOf( 'a' ).querySelector( 'textarea' ).value ).toBe( '' );
	} );
} );
