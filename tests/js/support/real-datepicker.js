/**
 * The real jQuery UI datepicker loaded into a test's jQuery -- so a test drives
 * the same widget a customer does: show it, click a day, let jQuery UI fire
 * its own change / onSelect.
 *
 * Source: the `jquery-ui` dev dependency, pinned to the exact version
 * WordPress core ships in wp-includes/js/jquery/ui (1.13.3 as of WP 6.9), so
 * the suite runs in CI without a WordPress checkout. WP_CORE_DIR, when set,
 * overrides it with that checkout's own core.js + datepicker.js. A missing
 * file fails the test loudly (a skipped test would hide the coverage it is
 * there for).
 */
const fs = require( 'fs' );
const path = require( 'path' );

function sources() {
	if ( process.env.WP_CORE_DIR ) {
		const dir = path.join( process.env.WP_CORE_DIR, 'wp-includes/js/jquery/ui' );
		return [ path.join( dir, 'core.js' ), path.join( dir, 'datepicker.js' ) ];
	}
	return [ require.resolve( 'jquery-ui/dist/jquery-ui.js' ) ];
}

function install( $ ) {
	sources().forEach( ( full ) => {
		if ( ! fs.existsSync( full ) ) {
			throw new Error( 'jQuery UI not found at ' + full + ' (WP_CORE_DIR must point at a WordPress checkout).' );
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
