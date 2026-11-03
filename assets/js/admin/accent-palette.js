/**
 * Browser twin of MHMRentiva\Core\Color\ColorMath::palette().
 *
 * A line-by-line translation: the admin preview must show the very tokens the
 * server prints. Correctness is checked against the same fixture the PHP side
 * uses (tests/fixtures/accent-palette-cases.json). Change both or neither.
 *
 * Browser: window.mhmRentivaAccentPalette. Jest: module.exports.
 */
( function ( root, factory ) {
	'use strict';
	var api = factory();
	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = api;
	}
	if ( root ) {
		root.mhmRentivaAccentPalette = api;
	}
}( typeof window !== 'undefined' ? window : null, function () {
	'use strict';

	var DEFAULT_ACCENT = '#2271b1';
	var TOKENS = [ 'bg', 'accent-strong', 'accent-deep', 'accent-100', 'sel-text', 'on-accent', 'accent-text', 'accent-text-hover' ];
	var DEFAULT_TOKENS = {
		'bg': '#f0f6fc',
		'accent-strong': '#2271b1',
		'accent-deep': '#135e96',
		'accent-100': '#dcecf9',
		'sel-text': '#0a3c63',
		'on-accent': '#ffffff',
		'accent-text': '#2271b1',
		'accent-text-hover': '#135e96'
	};
	var WHITE = '#ffffff';
	var BLACK = '#000000';
	var INK = '#1d2327';

	function hex2( n ) {
		var s = n.toString( 16 );
		return s.length < 2 ? '0' + s : s;
	}

	function channels( hex ) {
		return [ parseInt( hex.substr( 1, 2 ), 16 ), parseInt( hex.substr( 3, 2 ), 16 ), parseInt( hex.substr( 5, 2 ), 16 ) ];
	}

	// PHP trim(): the six ASCII characters only (String#trim also strips Unicode
	// spaces such as U+00A0). By char code: no control escapes.
	// Space, tab, LF, CR, NUL, vertical tab.
	var TRIM = String.fromCharCode( 32, 9, 10, 13, 0, 11 );

	function phpTrim( value ) {
		var start = 0;
		var end = value.length;
		while ( start < end && -1 !== TRIM.indexOf( value.charAt( start ) ) ) {
			start++;
		}
		while ( end > start && -1 !== TRIM.indexOf( value.charAt( end - 1 ) ) ) {
			end--;
		}
		return value.slice( start, end );
	}

	function normalize( value ) {
		if ( typeof value !== 'string' ) {
			return null;
		}
		var v = phpTrim( value ).toLowerCase();
		var m;
		if ( '' === v ) {
			return null;
		}
		if ( ( m = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec( v ) ) ) {
			v = '#' + m[ 1 ] + m[ 1 ] + m[ 2 ] + m[ 2 ] + m[ 3 ] + m[ 3 ];
		} else if ( ( m = /^#([0-9a-f]{6})ff$/.exec( v ) ) ) {
			v = '#' + m[ 1 ];
		} else if ( ( m = /^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([0-9.]+)\s*)?\)$/.exec( v ) ) ) {
			if ( undefined !== m[ 4 ] && 1 !== parseFloat( m[ 4 ] ) ) {
				return null;
			}
			var hex = '#';
			for ( var i = 1; i <= 3; i++ ) {
				var c = parseInt( m[ i ], 10 );
				if ( c > 255 ) {
					return null;
				}
				hex += hex2( c );
			}
			v = hex;
		}
		return /^#[0-9a-f]{6}$/.test( v ) ? v : null;
	}

	function luminance( hex ) {
		var out = channels( hex ).map( function ( channel ) {
			var c = channel / 255;
			return c <= 0.04045 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * out[ 0 ] + 0.7152 * out[ 1 ] + 0.0722 * out[ 2 ];
	}

	function contrast( a, b ) {
		var l1 = luminance( a );
		var l2 = luminance( b );
		return ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
	}

	function mix( a, b, percentA ) {
		var p = Math.max( 0, Math.min( 100, percentA ) );
		var ca = channels( a );
		var cb = channels( b );
		var hex = '#';
		for ( var i = 0; i < 3; i++ ) {
			hex += hex2( Math.round( ( ca[ i ] * p + cb[ i ] * ( 100 - p ) ) / 100 ) );
		}
		return hex;
	}

	function palette( accent ) {
		var a = normalize( accent ) || DEFAULT_ACCENT;
		var looped = [];
		var step;
		var i;

		if ( DEFAULT_ACCENT === a ) {
			return {
				tokens: Object.assign( {}, DEFAULT_TOKENS ),
				flags: { text_darkened: false, fill_low: false },
				looped: []
			};
		}

		// 1. Text on the accent: the first of white, ink, black that reaches 4.5.
		var on = BLACK;
		var candidates = [ WHITE, INK, BLACK ];
		for ( i = 0; i < candidates.length; i++ ) {
			if ( contrast( candidates[ i ], a ) >= 4.5 ) {
				on = candidates[ i ];
				break;
			}
		}

		// 2. Hover fill (R16).
		var deepOk = function ( d ) {
			return contrast( on, d ) >= 4.5 && contrast( a, d ) >= 1.3;
		};
		var first = WHITE === on ? BLACK : WHITE;
		var flipped = WHITE === first ? BLACK : WHITE;
		var deep = mix( a, first, 80 );
		if ( ! deepOk( deep ) ) {
			var turned = mix( a, flipped, 80 );
			if ( contrast( a, deep ) < 1.3 && deepOk( turned ) ) {
				deep = turned;
			} else {
				looped.push( 'accent-deep' );
				for ( step = 1; step <= 10; step++ ) {
					deep = mix( a, first, 80 - 4 * step );
					if ( deepOk( deep ) ) {
						break;
					}
				}
			}
		}

		// 3-4. Light fill and tinted page background.
		var accent100 = mix( a, WHITE, 15 );
		var bg = mix( a, WHITE, 5 );

		// 5. Accent as text: readable on white and on the tinted background.
		var textOk = function ( t ) {
			return contrast( t, WHITE ) >= 4.5 && contrast( t, bg ) >= 4.5;
		};
		var text = a;
		var textDarkened = false;
		if ( ! textOk( text ) ) {
			textDarkened = true;
			text = mix( a, BLACK, 80 );
			if ( ! textOk( text ) ) {
				looped.push( 'accent-text' );
				for ( step = 1; step <= 20; step++ ) {
					text = mix( a, BLACK, 80 - 4 * step );
					if ( textOk( text ) ) {
						break;
					}
				}
			}
		}

		// 6. Text hover (R5), then the near-black turn (R2).
		var hoverOk = function ( h ) {
			return contrast( h, accent100 ) >= 4.5 && contrast( h, WHITE ) >= 4.5 && contrast( h, bg ) >= 4.5;
		};
		var hover = mix( text, BLACK, 80 );
		if ( ! hoverOk( hover ) ) {
			looped.push( 'accent-text-hover' );
			for ( step = 1; step <= 20; step++ ) {
				hover = mix( text, BLACK, 80 - 4 * step );
				if ( hoverOk( hover ) ) {
					break;
				}
			}
		}
		if ( contrast( text, hover ) < 1.3 ) {
			var flippedHover = mix( text, WHITE, 80 );
			if ( hoverOk( flippedHover ) && contrast( text, flippedHover ) >= 1.3 ) {
				hover = flippedHover;
			}
		}

		// 7. Selected-day text on the light fill, 7:1.
		var sel = mix( text, BLACK, 55 );
		if ( contrast( sel, accent100 ) < 7.0 ) {
			looped.push( 'sel-text' );
			for ( step = 1; step <= 11; step++ ) {
				sel = mix( text, BLACK, 55 - 5 * step );
				if ( contrast( sel, accent100 ) >= 7.0 ) {
					break;
				}
			}
		}

		return {
			tokens: {
				'bg': bg,
				'accent-strong': a,
				'accent-deep': deep,
				'accent-100': accent100,
				'sel-text': sel,
				'on-accent': on,
				'accent-text': text,
				'accent-text-hover': hover
			},
			flags: { text_darkened: textDarkened, fill_low: contrast( a, WHITE ) < 3.0 },
			looped: looped
		};
	}

	return {
		DEFAULT_ACCENT: DEFAULT_ACCENT,
		DEFAULT_TOKENS: DEFAULT_TOKENS,
		TOKENS: TOKENS,
		normalize: normalize,
		luminance: luminance,
		contrast: contrast,
		mix: mix,
		palette: palette
	};
} ) );
