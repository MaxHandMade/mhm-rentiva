/**
 * Currency formatting driven entirely by WooCommerce settings.
 * PHP passes wc_get_price_decimal_separator(), wc_get_price_thousand_separator(),
 * wc_get_price_decimals(), and woocommerce_currency_pos via window.mhmRentivaAdmin.
 *
 * The implementation now lives in mhm/ui-core; this file binds it to Rentiva's
 * localized settings, so the seven call sites keep importing fmtAmount/fmtMoney
 * from here unchanged.
 *
 * The settings are read per call, not once at module load, because that is what
 * the previous implementation did: a module-scope read would capture whatever
 * window.mhmRentivaAdmin held at bundle-evaluation time.
 */
import { createFormatter } from '../../vendor/mhm/ui-core/src-react/format';

const formatter = () => createFormatter( window.mhmRentivaAdmin ?? {} );

export function fmtAmount( n, decimals ) {
	return formatter().fmtAmount( n, decimals );
}

export function fmtMoney( n, symbol, decimals ) {
	return formatter().fmtMoney( n, symbol, decimals );
}
