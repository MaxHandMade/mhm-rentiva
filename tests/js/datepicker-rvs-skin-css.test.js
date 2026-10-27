/**
 * PR S Task 9 item 1: one datepicker look for every customer date field.
 *
 * The rvs skin (assets/css/rvs/rvs-datepicker.css) dresses the popup of the
 * search strip, the vehicle card and the booking form. The old skin
 * (datepicker-custom.css, all `!important`) still loads with the shared init
 * for fields outside the standard, so none of its look rules may reach a popup
 * wearing `.rvs-dp` -- otherwise its floats, margins and hover lift win.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const read = ( rel ) => fs.readFileSync( path.join( __dirname, '../../', rel ), 'utf8' ).replace( /\/\*[\s\S]*?\*\//g, '' );

// [ selector list, body ] for each rule of a flat stylesheet.
const rules = ( css ) => {
	const out = [];
	const re = /([^{}]+)\{([^{}]*)\}/g;
	let m;
	while ( ( m = re.exec( css ) ) ) {
		out.push( [ m[ 1 ].trim(), m[ 2 ] ] );
	}
	return out;
};

const rule = ( css, selector ) => {
	const found = rules( css ).find( ( [ sel ] ) => sel.split( ',' ).map( ( s ) => s.trim() ).includes( selector ) );
	return found ? found[ 1 ] : null;
};

const decl = ( body, prop ) => {
	const m = new RegExp( '(?:^|;|\\s)' + prop + '\\s*:\\s*([^;]+)' ).exec( body || '' );
	return m ? m[ 1 ].trim() : null;
};

describe( 'datepicker skins', () => {
	test( 'the old skin leaves an rvs popup alone (only show/hide reach it)', () => {
		const css = read( 'assets/css/frontend/datepicker-custom.css' );
		// Rules that are not about the look of the popup.
		const allowed = [
			'.rv-shortcode-wrapper',
			'[class*="rv-"]',
			'.ui-datepicker[style*="display: block"]',
			'.ui-datepicker[style*="display:block"]',
		];
		const leaks = [];
		rules( css ).forEach( ( [ list, body ] ) => {
			list.split( ',' ).map( ( s ) => s.trim() ).forEach( ( sel ) => {
				if ( allowed.includes( sel ) ) {
					return;
				}
				// The one bare `.ui-datepicker` rule left is the hide-by-default.
				if ( sel === '.ui-datepicker' && /^\s*display:\s*none\s*!important;\s*$/.test( body ) ) {
					return;
				}
				if ( ! sel.startsWith( '.ui-datepicker:not(.rvs-dp)' ) ) {
					leaks.push( sel );
				}
			} );
		} );
		expect( leaks ).toEqual( [] );
	} );

	test( 'Today and Close: one button model, symmetric, no hover jump', () => {
		const css = read( 'assets/css/rvs/rvs-datepicker.css' );
		const pane = rule( css, '.rvs-shell.rvs-dp .ui-datepicker-buttonpane' );
		const button = rule( css, '.rvs-shell.rvs-dp .ui-datepicker-buttonpane button' );
		const hover = rule( css, '.rvs-shell.rvs-dp .ui-datepicker-buttonpane button:hover' );

		expect( decl( pane, 'display' ) ).toBe( 'flex' );
		expect( decl( pane, 'justify-content' ) ).toBe( 'space-between' );
		// Inset comes from the popup's own padding: the pane adds none sideways.
		expect( decl( pane, 'padding' ) ).toMatch( /^\S+ 0 0$/ );

		expect( decl( button, 'float' ) ).toBe( 'none' );
		expect( decl( button, 'margin' ) ).toBe( '0' );
		expect( decl( button, 'border' ) ).toBe( '1px solid var(--rvs-line)' );
		expect( decl( button, 'min-height' ) ).toBe( '44px' );
		expect( decl( button, 'transform' ) ).toBe( 'none' );
		// No rule styles one of the two buttons apart.
		expect( css ).not.toMatch( /ui-datepicker-(current|close)\b/ );

		expect( decl( hover, 'transform' ) ).toBe( 'none' );
		expect( decl( hover, 'border-color' ) ).toBe( 'var(--rvs-line)' );
	} );

	test( 'the booking form sheet no longer carries its own copy of the skin', () => {
		expect( read( 'assets/css/frontend/rvs-booking.css' ) ).not.toMatch( /\.rvs-dp/ );
	} );
} );
