/**
 * Currency formatting driven entirely by WooCommerce settings.
 * PHP passes wc_get_price_decimal_separator(), wc_get_price_thousand_separator(),
 * wc_get_price_decimals(), and woocommerce_currency_pos via window.mhmRentivaAdmin.
 */

function getWcFormat() {
	const admin = window.mhmRentivaAdmin ?? {};
	return {
		decimalSep:  admin.decimalSep  ?? ',',
		thousandSep: admin.thousandSep ?? '.',
		numDecimals: admin.numDecimals  ?? 2,
		// Last-resort default matches CurrencyHelper's: `right_space`. Keep the two
		// in step — a different default here would be a second placement rule.
		currencyPos: admin.currencyPosition ?? 'right_space',
	};
}

export function fmtAmount( n, decimals ) {
	const { decimalSep, thousandSep, numDecimals } = getWcFormat();
	const dec = decimals ?? numDecimals;
	const fixed = Number( n ?? 0 ).toFixed( dec );
	const [ int, decPart ] = fixed.split( '.' );
	const intFormatted = int.replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );
	return dec > 0 ? `${ intFormatted }${ decimalSep }${ decPart }` : intFormatted;
}

export function fmtMoney( n, symbol, decimals ) {
	const { currencyPos } = getWcFormat();
	const amount = fmtAmount( n, decimals );
	switch ( currencyPos ) {
		case 'left':        return `${ symbol }${ amount }`;
		case 'left_space':  return `${ symbol } ${ amount }`;
		case 'right':       return `${ amount }${ symbol }`;
		case 'right_space':
		default:            return `${ amount } ${ symbol }`;
	}
}
