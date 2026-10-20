/**
 * @jest-environment jsdom
 */

/**
 * The email preview result was headed by a hard-coded `'<h3>Subject: '`, so a
 * Turkish site showed an English "Subject:" heading over a Turkish e-mail. The
 * label now comes from the localized payload, and the subject itself is text:
 * it can carry an operator-typed override, and it used to be concatenated into
 * markup.
 */
describe( 'email preview: heading follows the site language', () => {
	let $;

	beforeAll( async () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		window.mhmrentiva_email_templates_vars = {
			ajax_url: '/wp-admin/admin-ajax.php',
			processing: 'İşleniyor...',
			error_occurred: 'Bir hata oluştu. Lütfen tekrar deneyin.',
			strings: {
				subject: 'Konu:',
				loading: 'Yükleniyor...',
				preview: 'Ön izleme',
			},
		};

		document.body.innerHTML =
			'<select id="mhm-preview-template-key"><option value="booking_cancelled" selected>x</option></select>' +
			'<button id="mhm-preview-btn" data-nonce="n">Ön izleme</button>' +
			'<div id="mhm-preview-result-container"></div>';

		$.ajax = ( opts ) => {
			opts.success( {
				success: true,
				data: { subject: 'Rezervasyon #9999 iptal edildi <img src=x onerror=window.__inj=1>', html: '<p>gövde</p>' },
			} );
			opts.complete && opts.complete();
		};

		require( '../../assets/js/admin/email-templates.js' );
		await new Promise( ( r ) => $( r ) );
	} );

	test( 'the subject heading uses the localized label, not "Subject:"', () => {
		$( '#mhm-preview-btn' ).trigger( 'click' );

		const heading = document.querySelector( '#mhm-preview-result-container h3' );
		expect( heading ).not.toBeNull();
		expect( heading.textContent ).toBe( 'Konu: Rezervasyon #9999 iptal edildi <img src=x onerror=window.__inj=1>' );
		expect( heading.querySelector( 'img' ) ).toBeNull();
		expect( document.body.innerHTML ).not.toContain( 'Subject:' );
	} );

	test( 'no English literal is left in the preview loader', () => {
		const src = require( 'fs' ).readFileSync( require( 'path' ).join( __dirname, '../../assets/js/admin/email-templates.js' ), 'utf8' );
		expect( src ).not.toMatch( /Subject: '/ );
		expect( src ).not.toMatch( /Loading preview\.\.\./ );
		expect( src ).not.toMatch( /'Error loading preview'/ );
		expect( src ).not.toMatch( /'Connection error\.'/ );
	} );
} );
