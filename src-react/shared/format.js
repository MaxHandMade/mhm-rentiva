/**
 * Turkish number / money formatters.
 * Matches PHP: number_format( $n, $dec, ',', '.' )
 *   decimal separator → comma
 *   thousands separator → dot
 */

export function fmtAmount( n, decimals = 2 ) {
	const fixed  = Number( n ?? 0 ).toFixed( decimals );
	const [int, dec] = fixed.split( '.' );
	const intFormatted = int.replace( /\B(?=(\d{3})+(?!\d))/g, '.' );
	return decimals > 0 ? `${ intFormatted },${ dec }` : intFormatted;
}

export function fmtMoney( n, currency, decimals = 2 ) {
	return `${ currency }${ fmtAmount( n, decimals ) }`;
}
