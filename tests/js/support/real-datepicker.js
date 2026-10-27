/**
 * The real jQuery UI datepicker (the copy WordPress ships, wp-includes/js/jquery/ui)
 * loaded into a test's jQuery -- so a test drives the same widget a customer
 * does: show it, click a day, let jQuery UI fire its own change / onSelect.
 *
 * jQuery UI is not a dev dependency of the plugin; the dev stack's WordPress
 * checkout carries it. The path comes from WP_CORE_DIR, else the stack's
 * `wp/` folder next to `plugins/`. A missing file fails the test loudly
 * (a skipped test would hide the coverage it is there for).
 */
const fs = require( 'fs' );
const path = require( 'path' );

function uiDir() {
	const root = process.env.WP_CORE_DIR || path.resolve( __dirname, '../../../../../wp' );
	return path.join( root, 'wp-includes/js/jquery/ui' );
}

function install( $ ) {
	const dir = uiDir();
	[ 'core.js', 'datepicker.js' ].forEach( ( file ) => {
		const full = path.join( dir, file );
		if ( ! fs.existsSync( full ) ) {
			throw new Error( 'jQuery UI not found at ' + full + ' (set WP_CORE_DIR to a WordPress checkout).' );
		}
		// The files are UMD: without an AMD loader they call factory( jQuery ).
		// eslint-disable-next-line no-new-func
		new Function( 'jQuery', fs.readFileSync( full, 'utf8' ) )( $ );
	} );
	// jsdom lays nothing out, so jQuery's :visible (offset size / client rects)
	// calls every element hidden and the popup cannot place itself. Visible =
	// in the document and no `display: none` / [hidden] up the chain.
	$.expr.pseudos.visible = function ( elem ) {
		for ( let el = elem; el && el.nodeType === 1; el = el.parentElement ) {
			if ( el.hidden || el.ownerDocument.defaultView.getComputedStyle( el ).display === 'none' ) {
				return false;
			}
		}
		return !! ( elem && elem.isConnected );
	};
	// No fade: the popup is shown and hidden at once, so a test reads it
	// without waiting on jQuery's animation timers.
	$.datepicker.setDefaults( { showAnim: '' } );
	return $;
}

// The popup's day link for a day of the month on show.
function dayLink( $, day ) {
	return $( '#ui-datepicker-div td[data-handler="selectDay"] a' ).filter( function () {
		return $( this ).text().trim() === String( day );
	} ).first();
}

// Opens the field's popup on a month, then clicks the day there -- the way a
// visitor picks it (jQuery UI writes the field, then fires onSelect / change).
function pick( $, input, year, month, day ) {
	const $input = $( input );
	$input[ 0 ].focus();
	$input.datepicker( 'show' );
	// Step the open popup to the month with its own Prev / Next links.
	const inst = $.data( $input[ 0 ], 'datepicker' );
	for ( let guard = 0; guard < 48; guard++ ) {
		const shown = inst.drawYear * 12 + inst.drawMonth;
		const wanted = year * 12 + ( month - 1 );
		if ( shown === wanted ) {
			break;
		}
		$( '#ui-datepicker-div a[data-handler="' + ( shown < wanted ? 'next' : 'prev' ) + '"]' )[ 0 ].click();
	}
	const $link = dayLink( $, day );
	if ( ! $link.length ) {
		throw new Error( 'Day ' + day + ' is not selectable in ' + year + '-' + month );
	}
	$link[ 0 ].click();
}

module.exports = { install, pick, dayLink };
