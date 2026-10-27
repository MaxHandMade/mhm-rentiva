/**
 * A stand-in for the parts of jQuery UI's datepicker the shared init
 * (assets/js/core/datepicker-init.js) uses -- jQuery UI is not a dev dependency.
 *
 * formatDate/parseDate understand the tokens dd d mm MM yy and literals;
 * $.fn.datepicker marks the input (hasDatepicker), records its options and
 * accepts ( 'option', name, value ).
 */
const MONTHS = [ 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ];
const TOKENS = /yy|dd|d|MM|mm/g;

function install( $ ) {
	const pad = ( n ) => String( n ).padStart( 2, '0' );
	const calls = [];
	$.datepicker = {
		setDefaults: () => {},
		formatDate( format, date, settings ) {
			const names = ( settings && settings.monthNames ) || MONTHS;
			return format.replace( TOKENS, ( t ) => ( {
				yy: String( date.getFullYear() ),
				dd: pad( date.getDate() ),
				d: String( date.getDate() ),
				mm: pad( date.getMonth() + 1 ),
				MM: names[ date.getMonth() ],
			}[ t ] ) );
		},
		parseDate( format, text, settings ) {
			const names = ( settings && settings.monthNames ) || MONTHS;
			const order = [];
			const source = format.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ).replace( TOKENS, ( t ) => {
				order.push( t );
				return t === 'MM' ? '([^\\d\\s]+)' : '(\\d+)';
			} );
			const m = new RegExp( '^' + source + '$' ).exec( text );
			if ( ! m ) {
				throw new Error( 'Invalid date' );
			}
			const parts = {};
			order.forEach( ( t, i ) => {
				parts[ t ] = m[ i + 1 ];
			} );
			const month = parts.MM !== undefined ? names.indexOf( parts.MM ) : +parts.mm - 1;
			if ( month < 0 ) {
				throw new Error( 'Unknown month' );
			}
			return new Date( +parts.yy, month, +( parts.dd || parts.d ) );
		},
	};
	$.fn.datepicker = function ( first, ...rest ) {
		calls.push( { el: this.get( 0 ), args: [ first, ...rest ] } );
		return this.addClass( 'hasDatepicker' );
	};
	return calls;
}

module.exports = { install, MONTHS };
