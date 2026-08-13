<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}


use MHMRentiva\Admin\Settings\Core\SettingsCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currency Helper Class
 *
 * Centralized currency symbol management for the entire plugin.
 * All currency symbols must match the settings page currency list.
 *
 * PRECEDENCE — the one rule for the whole plugin
 * ----------------------------------------------
 * 1. WooCommerce is authoritative whenever it is ACTIVE and has an opinion — active is
 *    decided by `woocommerce_is_active()`, the one predicate. `woocommerce_currency_pos`
 *    decides placement, `woocommerce_currency` / `get_woocommerce_currency_symbol()`
 *    decide the symbol, and `wc_get_price_*_separator()` decide the separators. Note
 *    `woocommerce_currency_pos` is autoloaded and OUTLIVES WooCommerce, so its presence
 *    alone never means WooCommerce has an opinion.
 * 2. The plugin's own `mhmrentiva_currency_position` / `mhmrentiva_currency` options are
 *    a FALLBACK, consulted whenever WooCommerce is silent — inactive, or active with the
 *    option absent/empty. The
 *    Setup Wizard mirrors WooCommerce into them when WC is active, but that mirror is a
 *    convenience, never the source of truth — an UNSET plugin option must never produce a
 *    placement that contradicts WooCommerce.
 * 3. Last resort, with neither source available: `right_space`.
 *
 * Every surface that shows money to a human must route through `format_price()`; every
 * surface that hands raw parts to a client (REST payload, `wp_localize_script`) must take
 * them from `get_js_currency_payload()` or the accessors below. Do not re-implement
 * placement or separators anywhere else — see CurrencyPlacementParityTest.
 *
 * @since 3.0.1
 */
final class CurrencyHelper {

	/**
	 * The ONE "does WooCommerce have an opinion?" predicate.
	 *
	 * Every WooCommerce question in this plugin — placement, symbol, code,
	 * separators, precision, and the settings screen that offers the position
	 * dropdown — must be asked through here, and nowhere else.
	 *
	 * It used to be asked four different ways, and one of them misfired:
	 * `get_currency_position()` tested the OPTION (`woocommerce_currency_pos`)
	 * while everything else tested `function_exists()`. That option is autoloaded
	 * and survives WooCommerce's deactivation and uninstall, so on any site that
	 * once had WooCommerce and removed it, the settings screen offered the
	 * position dropdown and accepted a choice while this helper kept returning the
	 * stale WooCommerce value forever — the documented "plugin option applies when
	 * WooCommerce is silent" rule was unreachable.
	 *
	 * A `function_exists()` guard may still sit next to a specific WooCommerce
	 * call: that guards CALLABILITY, not policy. Policy is only ever this.
	 *
	 * @return bool
	 */
	public static function woocommerce_is_active(): bool {
		$is_active = function_exists( 'wc_price' )
			|| function_exists( 'get_woocommerce_currency' )
			|| class_exists( 'WooCommerce' );

		/**
		 * Filters whether WooCommerce owns currency presentation on this site.
		 *
		 * Answering `false` makes the plugin's own currency options authoritative
		 * even while WooCommerce is loaded; answering `true` is only meaningful
		 * for a shim that provides WooCommerce's formatting API under another name.
		 *
		 * @since 6.0.2
		 *
		 * @param bool $is_active Whether WooCommerce currency settings apply.
		 */
		return (bool) apply_filters( 'mhmrentiva_woocommerce_is_active', $is_active );
	}

	/**
	 * Get active currency position.
	 *
	 * WooCommerce setting is authoritative when active; the plugin option is the
	 * fallback. See the class docblock for the full precedence rule.
	 *
	 * @return string
	 */
	public static function get_currency_position(): string {
		if ( self::woocommerce_is_active() && function_exists( 'get_option' ) ) {
			$wc_position = (string) get_option( 'woocommerce_currency_pos', '' );
			if ( $wc_position !== '' ) {
				return $wc_position;
			}
		}

		return (string) SettingsCore::get( 'mhmrentiva_currency_position', 'right_space' );
	}

	/**
	 * Format numeric amount with project standard precision.
	 *
	 * @param float $amount   Numeric amount.
	 * @param int   $decimals Decimal precision.
	 * @return string
	 */
	public static function format_amount( float $amount, int $decimals = 0 ): string {
		return number_format(
			$amount,
			max( 0, $decimals ),
			self::get_decimal_separator(),
			self::get_thousand_separator()
		);
	}

	/**
	 * Active decimal separator, from WooCommerce when it has an opinion.
	 *
	 * @return string
	 */
	public static function get_decimal_separator(): string {
		if ( self::woocommerce_is_active() && function_exists( 'wc_get_price_decimal_separator' ) ) {
			return (string) wc_get_price_decimal_separator();
		}

		return ',';
	}

	/**
	 * Active thousand separator, from WooCommerce when it has an opinion.
	 *
	 * @return string
	 */
	public static function get_thousand_separator(): string {
		if ( self::woocommerce_is_active() && function_exists( 'wc_get_price_thousand_separator' ) ) {
			return (string) wc_get_price_thousand_separator();
		}

		return '.';
	}

	/**
	 * Active decimal precision for money, from WooCommerce when it has an opinion.
	 *
	 * Surfaces that used to be bare `wc_price()` calls deferred to this; passing a
	 * literal `2` instead would diverge from the store on a 0-decimal currency
	 * (JPY, HUF). Pass this where the store's own precision is what is wanted.
	 *
	 * @return int
	 */
	public static function get_price_decimals(): int {
		if ( self::woocommerce_is_active() && function_exists( 'wc_get_price_decimals' ) ) {
			return max( 0, (int) wc_get_price_decimals() );
		}

		return 2;
	}

	/**
	 * Coerce a possibly already-formatted money value back to a plain float.
	 *
	 * A bare `(float)` cast is NOT safe on money in this plugin. An amount reaches
	 * a display surface as an int (kuruş), a float, a raw meta string
	 * (`"1500.00"`) or — when a producer formatted it too early — a locale string
	 * such as `"1.500,00"`. PHP casts that last one to `1.5`: a 1000x error in a
	 * customer-facing figure, and a silent one, because no error is raised.
	 *
	 * The right fix is always to keep the number numeric at the data end; this is
	 * the net under the display layer so that no consumer can be handed a
	 * formatted string and quietly print a wrong amount.
	 *
	 * Deliberately locale-INDEPENDENT: the producer may have formatted with the
	 * WordPress locale (`number_format_i18n()`) while this helper's separators
	 * come from WooCommerce, so reading the string through one fixed separator
	 * pair would trade one wrong number for another. The shape of the string
	 * decides instead — the rightmost of `.`/`,` is the decimal separator when
	 * both appear, and a lone separator followed by exactly three digits is
	 * grouping.
	 *
	 * Known and accepted ambiguity: `"1.500"` is read as one thousand five
	 * hundred, not as one and a half. Money in this plugin is stored with 0 or 2
	 * decimals, so a three-digit tail is always grouping in practice.
	 *
	 * @param mixed $value Raw amount from meta, an email context, or a request.
	 * @return float
	 */
	public static function to_amount( $value ): float {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}

		if ( ! is_string( $value ) ) {
			return 0.0;
		}

		$raw = trim( $value );
		if ( '' === $raw ) {
			return 0.0;
		}

		// A fully grouped integer ("1.500", "1,500", "1.500.000"). This is
		// `is_numeric()` in the `.` flavour and would cast to 1.5.
		if ( 1 === preg_match( '/^-?\d{1,3}(?:([.,])\d{3})(?:\1\d{3})*$/', $raw ) ) {
			return (float) str_replace( array( '.', ',' ), '', $raw );
		}

		// Machine format, straight from meta or a REST payload.
		if ( is_numeric( $raw ) ) {
			return (float) $raw;
		}

		$negative = str_contains( $raw, '-' );
		$digits   = (string) preg_replace( '/[^0-9.,]/', '', $raw );
		if ( '' === $digits ) {
			return 0.0;
		}

		$last_dot   = strrpos( $digits, '.' );
		$last_comma = strrpos( $digits, ',' );

		if ( false !== $last_dot && false !== $last_comma ) {
			// Both present: the rightmost one is the decimal separator.
			$decimal_at = max( $last_dot, $last_comma );
		} elseif ( false !== $last_dot || false !== $last_comma ) {
			$separator  = ( false !== $last_dot ) ? '.' : ',';
			$position   = ( false !== $last_dot ) ? $last_dot : $last_comma;
			$tail       = strlen( $digits ) - $position - 1;
			$is_group   = substr_count( $digits, $separator ) > 1 || 3 === $tail;
			$decimal_at = $is_group ? -1 : $position;
		} else {
			$decimal_at = -1;
		}

		if ( $decimal_at >= 0 ) {
			$integer  = (string) preg_replace( '/[^0-9]/', '', substr( $digits, 0, $decimal_at ) );
			$fraction = (string) preg_replace( '/[^0-9]/', '', substr( $digits, $decimal_at + 1 ) );
			$number   = ( '' === $integer ? '0' : $integer ) . '.' . ( '' === $fraction ? '0' : $fraction );
		} else {
			$number = (string) preg_replace( '/[^0-9]/', '', $digits );
		}

		if ( ! is_numeric( $number ) ) {
			return 0.0;
		}

		$amount = (float) $number;

		return $negative ? -$amount : $amount;
	}

	/**
	 * Format price with active currency symbol and position.
	 *
	 * WooCommerce settings are used when available. HTML tags are stripped so this
	 * can be used safely in plain-text contexts and templates.
	 *
	 * `$currency` overrides only the SYMBOL, for records that carry a currency of
	 * their own (a refund, a payment log row). Placement and separators still come
	 * from the one rule in the class docblock — a stored currency code never gets
	 * to imply a different layout.
	 *
	 * @param float       $amount   Numeric amount.
	 * @param int         $decimals Decimal precision.
	 * @param string|null $currency Optional currency code for this amount.
	 * @return string
	 */
	public static function format_price( float $amount, int $decimals = 0, ?string $currency = null ): string {
		if ( self::woocommerce_is_active() && function_exists( 'wc_price' ) ) {
			$args = array( 'decimals' => max( 0, $decimals ) );
			if ( $currency !== null && $currency !== '' ) {
				$args['currency'] = $currency;
			}

			$formatted = (string) wc_price( $amount, $args );

			return trim( html_entity_decode( wp_strip_all_tags( $formatted ), ENT_QUOTES, 'UTF-8' ) );
		}

		$symbol   = self::get_currency_symbol( ( $currency !== null && $currency !== '' ) ? $currency : null );
		$position = self::get_currency_position();
		$number   = self::format_amount( $amount, $decimals );

		switch ( $position ) {
			case 'left':
				return $symbol . $number;
			case 'left_space':
				return $symbol . ' ' . $number;
			case 'right':
				return $number . $symbol;
			case 'right_space':
			default:
				return $number . ' ' . $symbol;
		}
	}

	/**
	 * Get the active currency CODE under the same precedence as the symbol.
	 *
	 * WooCommerce owns the code when active; the plugin option is the fallback.
	 *
	 * @return string ISO-ish currency code, e.g. 'USD'.
	 */
	public static function get_currency_code(): string {
		if ( self::woocommerce_is_active() && function_exists( 'get_woocommerce_currency' ) ) {
			return (string) get_woocommerce_currency();
		}

		return (string) SettingsCore::get( 'mhmrentiva_currency', 'USD' );
	}

	/**
	 * Canonical currency parts for clients that format on their own.
	 *
	 * REST payloads and `wp_localize_script()` calls must take their currency data from
	 * here rather than reading `mhmrentiva_currency_position` directly, so a client-side
	 * formatter lands on exactly the placement `format_price()` would have produced.
	 *
	 * @return array{currency: string, symbol: string, position: string, decimals: int, decimalSeparator: string, thousandSeparator: string}
	 */
	public static function get_js_currency_payload(): array {
		return array(
			'currency'          => self::get_currency_code(),
			'symbol'            => self::get_currency_symbol(),
			'position'          => self::get_currency_position(),
			'decimals'          => self::get_price_decimals(),
			'decimalSeparator'  => self::get_decimal_separator(),
			'thousandSeparator' => self::get_thousand_separator(),
		);
	}

	/**
	 * Get all supported currency codes and symbols
	 *
	 * This list must match exactly with SettingsCore::render_currency_field()
	 * Can be extended via 'mhmrentiva_currency_symbols' filter hook
	 *
	 * @return array<string, string> Currency code => Symbol mapping
	 */
	public static function get_all_currency_symbols(): array {
		$symbols = array(
			'TRY'  => "\u{20BA}",
			'USD'  => '$',
			'EUR'  => "\u{20AC}",
			'GBP'  => "\u{00A3}",
			'JPY'  => "\u{00A5}",
			'CAD'  => 'C$',
			'AUD'  => 'A$',
			'CHF'  => 'CHF',
			'CNY'  => "\u{00A5}",
			'INR'  => "\u{20B9}",
			'BRL'  => 'R$',
			'RUB'  => "\u{20BD}",
			'KRW'  => "\u{20A9}",
			'MXN'  => '$',
			'SGD'  => 'S$',
			'HKD'  => 'HK$',
			'NZD'  => 'NZ$',
			'SEK'  => 'kr',
			'NOK'  => 'kr',
			'DKK'  => 'kr',
			'PLN'  => "z\u{0142}",
			'CZK'  => "K\u{010D}",
			'HUF'  => 'Ft',
			'RON'  => 'lei',
			'BGN'  => "\u{043B}\u{0432}",
			'HRK'  => 'kn',
			'RSD'  => "\u{0434}\u{0438}\u{043D}",
			'UAH'  => "\u{20B4}",
			'BYN'  => 'Br',
			'KZT'  => "\u{20B8}",
			'UZS'  => 'so\'m',
			'KGS'  => "\u{0441}\u{043E}\u{043C}",
			'TJS'  => 'SM',
			'TMT'  => 'T',
			'AZN'  => "\u{20BC}",
			'GEL'  => "\u{20BE}",
			'AMD'  => "\u{058F}",
			'AED'  => "\u{062F}.\u{0625}",
			'SAR'  => "\u{0631}.\u{0633}",
			'QAR'  => "\u{0631}.\u{0642}",
			'KWD'  => "\u{062F}.\u{0643}",
			'BHD'  => "\u{062F}.\u{0628}",
			'OMR'  => "\u{0631}.\u{0639}.",
			'JOD'  => "\u{062F}.\u{0623}",
			'LBP'  => "\u{0644}.\u{0644}",
			'EGP'  => "\u{00A3}",
			'ILS'  => "\u{20AA}",
			// Legacy aliases (for backward compatibility)
			'TL'   => "\u{20BA}",
			'LIRA' => "\u{20BA}",
		);

		/**
		 * Filter: Allow addons and third-party plugins to add custom currency symbols
		 *
		 * @param array<string, string> $symbols Currency code => Symbol mapping
		 * @return array Modified currency symbols array
		 *
		 * @example
		 * add_filter('mhmrentiva_currency_symbols', function($symbols) {
		 *     $symbols['BTC'] = '\u{20BF}';
		 *     $symbols['ETH'] = '\u{039E}';
		 *     return $symbols;
		 * });
		 */
		return apply_filters( 'mhmrentiva_currency_symbols', $symbols );
	}

	/**
	 * Get currency symbol for the current setting
	 *
	 * @param string|null $currency_code Optional currency code. If not provided, uses setting value.
	 * @return string Currency symbol or currency code as fallback
	 */
	public static function get_currency_symbol( ?string $currency_code = null ): string {
		if ( $currency_code === null ) {
			// When WooCommerce is active, use its symbol directly — WC owns the symbol map.
			if ( self::woocommerce_is_active() && function_exists( 'get_woocommerce_currency_symbol' ) ) {
				return html_entity_decode( get_woocommerce_currency_symbol(), ENT_HTML5, 'UTF-8' );
			}
			$currency_code = SettingsCore::get( 'mhmrentiva_currency', 'USD' );
		}

		$currency_code = strtoupper( trim( $currency_code ) );
		$currency_code = self::normalize_currency_code( $currency_code );
		$symbols       = self::get_all_currency_symbols();

		return $symbols[ $currency_code ] ?? $currency_code;
	}

	/**
	 * Normalize potentially malformed or legacy currency values to canonical code.
	 *
	 * @param string $currency_code Raw currency code or symbol.
	 * @return string Normalized currency code.
	 */
	private static function normalize_currency_code( string $currency_code ): string {
		$aliases = array(
			"\u{20BA}"  => 'TRY',
			'TL'        => 'TRY',
			'TL_SYMBOL' => 'TRY',
			'LIRA'      => 'TRY',
		);

		return $aliases[ $currency_code ] ?? $currency_code;
	}

	/**
	 * Get currency symbol for a specific currency code
	 *
	 * @param string $currency_code Currency code (e.g., 'USD', 'EUR')
	 * @return string Currency symbol
	 */
	public static function get_symbol_for_currency( string $currency_code ): string {
		return self::get_currency_symbol( $currency_code );
	}

	/**
	 * Check if a currency code is supported
	 *
	 * @param string $currency_code Currency code to check
	 * @return bool True if supported
	 */
	public static function is_currency_supported( string $currency_code ): bool {
		$currency_code = strtoupper( trim( $currency_code ) );
		$symbols       = self::get_all_currency_symbols();

		return isset( $symbols[ $currency_code ] );
	}

	/**
	 * Register WordPress filter hooks
	 * This should be called during plugin initialization
	 */
	public static function register_hooks(): void {
		// Register filter for template usage.
		add_filter( 'mhmrentiva_currency_symbol', array( self::class, 'filter_currency_symbol' ), 10, 1 );
	}

	/**
	 * Filter callback for mhmrentiva_currency_symbol
	 *
	 * @param string $default_symbol Default symbol (ignored, we use settings)
	 * @return string Currency symbol from settings
	 */
	public static function filter_currency_symbol( string $default_symbol = '' ): string {
		return self::get_currency_symbol();
	}

	/**
	 * Get currency list for dropdowns (code => display name with symbol)
	 *
	 * This matches SettingsCore::render_currency_field() format
	 * Can be extended via 'mhmrentiva_currency_list' filter hook
	 *
	 * @return array<string, string> Currency code => Display name mapping
	 */
	public static function get_currency_list_for_dropdown(): array {
		$currencies = array(
			'TRY' => 'Turkish Lira (' . "\u{20BA}" . ')',
			'USD' => 'US Dollar ($)',
			'EUR' => 'Euro (' . "\u{20AC}" . ')',
			'GBP' => 'British Pound (' . "\u{00A3}" . ')',
			'JPY' => 'Japanese Yen (' . "\u{00A5}" . ')',
			'CAD' => 'Canadian Dollar (C$)',
			'AUD' => 'Australian Dollar (A$)',
			'CHF' => 'Swiss Franc (CHF)',
			'CNY' => 'Chinese Yuan (' . "\u{00A5}" . ')',
			'INR' => 'Indian Rupee (' . "\u{20B9}" . ')',
			'BRL' => 'Brazilian Real (R$)',
			'RUB' => 'Russian Ruble (' . "\u{20BD}" . ')',
			'KRW' => 'South Korean Won (' . "\u{20A9}" . ')',
			'MXN' => 'Mexican Peso ($)',
			'SGD' => 'Singapore Dollar (S$)',
			'HKD' => 'Hong Kong Dollar (HK$)',
			'NZD' => 'New Zealand Dollar (NZ$)',
			'SEK' => 'Swedish Krona (kr)',
			'NOK' => 'Norwegian Krone (kr)',
			'DKK' => 'Danish Krone (kr)',
			'PLN' => 'Polish Zloty (' . "z\u{0142}" . ')',
			'CZK' => 'Czech Koruna (' . "K\u{010D}" . ')',
			'HUF' => 'Hungarian Forint (Ft)',
			'RON' => 'Romanian Leu (lei)',
			'BGN' => 'Bulgarian Lev (' . "\u{043B}\u{0432}" . ')',
			'HRK' => 'Croatian Kuna (kn)',
			'RSD' => 'Serbian Dinar (' . "\u{0434}\u{0438}\u{043D}" . ')',
			'UAH' => 'Ukrainian Hryvnia (' . "\u{20B4}" . ')',
			'BYN' => 'Belarusian Ruble (Br)',
			'KZT' => 'Kazakhstani Tenge (' . "\u{20B8}" . ')',
			'UZS' => 'Uzbekistani Som (so\'m)',
			'KGS' => 'Kyrgyzstani Som (' . "\u{0441}\u{043E}\u{043C}" . ')',
			'TJS' => 'Tajikistani Somoni (SM)',
			'TMT' => 'Turkmenistani Manat (T)',
			'AZN' => 'Azerbaijani Manat (' . "\u{20BC}" . ')',
			'GEL' => 'Georgian Lari (' . "\u{20BE}" . ')',
			'AMD' => 'Armenian Dram (' . "\u{058F}" . ')',
			'AED' => 'UAE Dirham (' . "\u{062F}.\u{0625}" . ')',
			'SAR' => 'Saudi Riyal (' . "\u{0631}.\u{0633}" . ')',
			'QAR' => 'Qatari Riyal (' . "\u{0631}.\u{0642}" . ')',
			'KWD' => 'Kuwaiti Dinar (' . "\u{062F}.\u{0643}" . ')',
			'BHD' => 'Bahraini Dinar (' . "\u{062F}.\u{0628}" . ')',
			'OMR' => 'Omani Rial (' . "\u{0631}.\u{0639}." . ')',
			'JOD' => 'Jordanian Dinar (' . "\u{062F}.\u{0623}" . ')',
			'LBP' => 'Lebanese Pound (' . "\u{0644}.\u{0644}" . ')',
			'EGP' => 'Egyptian Pound (' . "\u{00A3}" . ')',
			'ILS' => 'Israeli Shekel (' . "\u{20AA}" . ')',
		);

		/**
		 * Filter: Allow addons and third-party plugins to add custom currencies to dropdown
		 *
		 * @param array<string, string> $currencies Currency code => Display name mapping
		 * @return array Modified currency list array
		 *
		 * @example
		 * add_filter('mhmrentiva_currency_list', function($currencies) {
		 *     $currencies['BTC'] = 'Bitcoin (' . "\u{20BF}" . ')';
		 *     $currencies['ETH'] = 'Ethereum (' . "\u{039E}" . ')';
		 *     return $currencies;
		 * });
		 */
		return apply_filters( 'mhmrentiva_currency_list', $currencies );
	}
}
