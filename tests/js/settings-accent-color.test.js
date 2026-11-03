/**
 * @jest-environment jsdom
 */

/**
 * Settings > Frontend > Colors: the accent picker previews the palette.
 *
 * PHP decides and prints every sentence; the script only switches between the
 * printed labels, repaints the inert preview with the twin palette, shows the
 * automatic colour on the (disabled) picker and toggles the two notices.
 * Markup mirrors FrontendColorsSection::render_fields().
 */
const TOKENS = [ 'bg', 'accent-strong', 'accent-deep', 'accent-100', 'sel-text', 'on-accent', 'accent-text', 'accent-text-hover' ];

describe( 'settings accent color picker', () => {
	let $;
	let twin;

	beforeAll( () => {
		$ = require( 'jquery' );
		global.jQuery = $;
		window.jQuery = $;
		twin = require( '../../assets/js/admin/accent-palette.js' );
	} );

	beforeEach( () => {
		window.mhmRentivaAccentPalette = twin;
		// Fake Iris: wraps the input once, puts the swatch button before it and
		// keeps the options; 'color' only writes the input. The stub fires no
		// change event, but real Iris does for every valid colour (before it writes
		// the input): Iris's own change fires too; the double paint ends in the
		// same state.
		$.fn.wpColorPicker = function ( arg, value ) {
			return this.each( function () {
				const $el = $( this );
				if ( 'color' === arg ) {
					$el.val( value );
					return;
				}
				if ( ! $el.data( 'fakeIris' ) ) {
					$el.wrap( '<div class="wp-picker-container"></div>' );
					$el.before( '<button type="button" class="wp-color-result"></button>' );
					$el.data( 'fakeIris', arg || {} );
				}
			} );
		};
	} );

	afterEach( () => {
		window.mhmRentivaAccentPalette = twin;
		delete $.fn.wpColorPicker;
		document.body.innerHTML = '';
	} );

	function markup( mode, value, autoSource ) {
		document.body.innerHTML =
			'<form><div class="mhm-rentiva-accent" data-auto-hex="#0067ff" data-custom-source="Custom color">' +
			'<fieldset class="mhm-rentiva-accent-mode">' +
			'<label><input type="radio" name="mhmrentiva_settings[mhmrentiva_accent_mode]" value="auto"' + ( 'auto' === mode ? ' checked' : '' ) + ' /> A</label>' +
			'<label><input type="radio" name="mhmrentiva_settings[mhmrentiva_accent_mode]" value="custom"' + ( 'custom' === mode ? ' checked' : '' ) + ' /> C</label>' +
			'</fieldset>' +
			'<p><input type="text" name="mhmrentiva_settings[mhmrentiva_accent_color]" class="mhm-rentiva-accent-color" id="mhm-rentiva-accent-color" /></p>' +
			'<p class="mhm-rentiva-accent-source" aria-live="polite" data-template="Source: %s">' +
			'<span class="mhm-rentiva-accent-swatch" aria-hidden="true"></span> ' +
			'<span class="mhm-rentiva-accent-source-text">PHP line</span> ' +
			'<code class="mhm-rentiva-accent-hex">#php</code></p>' +
			'<div class="mhm-rentiva-accent-notices" aria-live="polite">' +
			'<p class="notice mhm-rentiva-accent-notice--text" data-template="Too light; text uses %s." hidden>x</p>' +
			'<p class="notice mhm-rentiva-accent-notice--fill" hidden>Hard to tell from white.</p>' +
			'</div>' +
			'<div class="rvs-shell mhm-rentiva-accent-preview" inert aria-hidden="true"></div>' +
			'</div></form>';
		document.querySelector( '.mhm-rentiva-accent' ).setAttribute( 'data-auto-source', autoSource || 'theme setting (Astra)' );
		document.querySelector( '.mhm-rentiva-accent-color' ).value = value;
	}

	async function boot() {
		jest.isolateModules( () => {
			require( '../../assets/js/admin/settings-accent-color.js' );
		} );
		await new Promise( ( r ) => $( r ) );
	}

	const q = ( s ) => document.querySelector( s );
	const input = () => q( '.mhm-rentiva-accent-color' );
	const button = () => q( '.wp-color-result' );
	const line = () => q( '.mhm-rentiva-accent-source-text' ).textContent;
	const textNotice = () => q( '.mhm-rentiva-accent-notice--text' );
	const fillNotice = () => q( '.mhm-rentiva-accent-notice--fill' );

	function previewTokens() {
		const style = q( '.mhm-rentiva-accent-preview' ).style;
		const out = {};
		TOKENS.forEach( ( name ) => {
			out[ name ] = style.getPropertyValue( '--rvs-' + name );
		} );
		return out;
	}

	function setMode( mode ) {
		const radio = q( 'input[value="' + mode + '"]' );
		radio.checked = true;
		$( radio ).trigger( 'change' );
	}

	// Iris hands the new colour to `change` in ui.color before the input holds it.
	function pick( hex ) {
		$( input() ).data( 'fakeIris' ).change.call( input(), $.Event( 'irischange' ), { color: { toString: () => hex } } );
		input().value = hex;
	}

	function type( value ) {
		input().value = value;
		$( input() ).trigger( 'input' );
	}

	test( 'shows the automatic colour on the disabled picker', async () => {
		markup( 'auto', '' );
		await boot();

		expect( button().style.backgroundColor ).toBe( 'rgb(0, 103, 255)' );
		expect( q( '.wp-picker-container' ).classList.contains( 'is-disabled' ) ).toBe( true );
		expect( input().disabled ).toBe( true );
		expect( input().value ).toBe( '' );
		// On load PHP has already painted the preview and the line.
		expect( previewTokens()[ 'accent-strong' ] ).toBe( '' );
		expect( line() ).toBe( 'PHP line' );
	} );

	test( 'seeds an empty custom picker with the automatic colour', async () => {
		markup( 'auto', '' );
		await boot();

		setMode( 'custom' );

		expect( input().value ).toBe( '#0067ff' );
		expect( input().disabled ).toBe( false );
		expect( q( '.wp-picker-container' ).classList.contains( 'is-disabled' ) ).toBe( false );
	} );

	test( 'repaints the preview with the twin palette on a pick', async () => {
		markup( 'custom', '' );
		await boot();

		pick( '#cc0000' );

		expect( previewTokens() ).toEqual( twin.palette( '#cc0000' ).tokens );
		expect( line() ).toBe( 'Source: Custom color' );
		expect( q( '.mhm-rentiva-accent-hex' ).textContent ).toBe( '#cc0000' );
		expect( q( '.mhm-rentiva-accent-swatch' ).style.backgroundColor ).toBe( 'rgb(204, 0, 0)' );
		expect( textNotice().hidden ).toBe( true );
		expect( fillNotice().hidden ).toBe( true );
	} );

	test( 'toggles the notices with the flags', async () => {
		markup( 'custom', '#cc0000' );
		await boot();

		pick( '#ffee58' );
		expect( textNotice().hidden ).toBe( false );
		expect( fillNotice().hidden ).toBe( false );
		expect( textNotice().textContent ).toContain( '#7a722a' );
		expect( textNotice().textContent ).toBe( 'Too light; text uses #7a722a.' );

		pick( '#cc0000' );
		expect( textNotice().hidden ).toBe( true );
		expect( fillNotice().hidden ).toBe( true );
	} );

	test( 'restores the custom palette after a round trip to Automatic without saving', async () => {
		markup( 'custom', '#cc0000' );
		await boot();

		setMode( 'auto' );
		expect( previewTokens() ).toEqual( twin.palette( '#0067ff' ).tokens );
		expect( line() ).toBe( 'Source: theme setting (Astra)' );
		expect( button().style.backgroundColor ).toBe( 'rgb(0, 103, 255)' );
		expect( input().value ).toBe( '#cc0000' );

		setMode( 'custom' );
		expect( previewTokens() ).toEqual( twin.palette( '#cc0000' ).tokens );
		expect( line() ).toBe( 'Source: Custom color' );
		expect( button().style.backgroundColor ).toBe( 'rgb(204, 0, 0)' );
		expect( input().value ).toBe( '#cc0000' );
	} );

	test( 'falls back to the automatic palette for an empty or invalid custom value', async () => {
		markup( 'custom', '#cc0000' );
		await boot();
		const automatic = twin.palette( '#0067ff' ).tokens;

		[ 'red', '#12' ].forEach( ( value ) => {
			pick( '#cc0000' );
			expect( () => type( value ) ).not.toThrow();
			expect( previewTokens() ).toEqual( automatic );
			expect( line() ).toBe( 'Source: theme setting (Astra)' );
		} );

		pick( '#cc0000' );
		input().value = '';
		expect( () => $( input() ).data( 'fakeIris' ).clear.call( input(), $.Event( 'click' ) ) ).not.toThrow();
		expect( previewTokens() ).toEqual( automatic );
		expect( line() ).toBe( 'Source: theme setting (Astra)' );
		expect( q( '.mhm-rentiva-accent-hex' ).textContent ).toBe( '#0067ff' );
	} );

	test( 'writes text only', async () => {
		markup( 'custom', '#cc0000', '<img src=x onerror=alert(1)>' );
		await boot();

		setMode( 'auto' );

		const source = q( '.mhm-rentiva-accent-source' );
		expect( source.querySelector( 'img' ) ).toBeNull();
		expect( line() ).toBe( 'Source: <img src=x onerror=alert(1)>' );
	} );

	test( 'reads the palette from window', async () => {
		markup( 'custom', '#cc0000' );
		delete window.mhmRentivaAccentPalette;

		await expect( boot() ).resolves.toBeUndefined();
		expect( () => pick( '#ffee58' ) ).not.toThrow();
		expect( () => setMode( 'auto' ) ).not.toThrow();
		expect( previewTokens()[ 'accent-strong' ] ).toBe( '' );
		expect( line() ).toBe( 'PHP line' );
	} );

	test( 'keeps the custom value on an Automatic save', async () => {
		markup( 'auto', '#cc0000' );
		await boot();
		expect( input().disabled ).toBe( true );

		q( 'form' ).dispatchEvent( new Event( 'submit', { cancelable: true, bubbles: true } ) );

		expect( input().disabled ).toBe( false );
		expect( input().value ).toBe( '#cc0000' );
	} );
} );
