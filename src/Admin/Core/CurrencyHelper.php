<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Public/legacy hook names kept stable for compatibility.

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
 * @since 3.0.1
 */
final class CurrencyHelper {

	/**
	 * Get active currency position.
	 *
	 * WooCommerce setting is authoritative when available.
	 *
	 * @return string
	 */
	public static function get_currency_position(): string {
		if ( function_exists( 'get_option' ) ) {
			$wc_position = (string) get_option( 'woocommerce_currency_pos', '' );
			if ( $wc_position !== '' ) {
				return $wc_position;
			}
		}

		return (string) SettingsCore::get( 'mhm_rentiva_currency_position', 'right_space' );
	}

	/**
	 * Format numeric amount with project standard precision.
	 *
	 * @param float $amount   Numeric amount.
	 * @param int   $decimals Decimal precision.
	 * @return string
	 */
	public static function format_amount( float $amount, int $decimals = 0 ): string {
		$decimal_separator  = function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : ',';
		$thousand_separator = function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : '.';

		return number_format( $amount, max( 0, $decimals ), $decimal_separator, $thousand_separator );
	}

	/**
	 * Format price with active currency symbol and position.
	 *
	 * WooCommerce settings are used when available. HTML tags are stripped so this
	 * can be used safely in plain-text contexts and templates.
	 *
	 * @param float $amount   Numeric amount.
	 * @param int   $decimals Decimal precision.
	 * @return string
	 */
	public static function format_price( float $amount, int $decimals = 0 ): string {
		if ( function_exists( 'wc_price' ) ) {
			$formatted = (string) wc_price(
				$amount,
				array(
					'decimals' => max( 0, $decimals ),
				)
			);

			return trim( html_entity_decode( wp_strip_all_tags( $formatted ), ENT_QUOTES, 'UTF-8' ) );
		}

		$symbol   = self::get_currency_symbol();
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
	 * Get all supported currency codes and symbols
	 *
	 * This list must match exactly with SettingsCore::render_currency_field()
	 * Can be extended via 'mhm_rentiva_currency_symbols' filter hook
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
		 * add_filter('mhm_rentiva_currency_symbols', function($symbols) {
		 *     $symbols['BTC'] = '\u{20BF}';
		 *     $symbols['ETH'] = '\u{039E}';
		 *     return $symbols;
		 * });
		 */
		return apply_filters( 'mhm_rentiva_currency_symbols', $symbols );
	}

	/**
	 * Get currency symbol for the current setting
	 *
	 * @param string|null $currency_code Optional currency code. If not provided, uses setting value.
	 * @return string Currency symbol or currency code as fallback
	 */
	public static function get_currency_symbol( ?string $currency_code = null ): string {
		if ( $currency_code === null ) {
			// If WooCommerce is active, use WooCommerce currency.
			if ( function_exists( 'get_woocommerce_currency' ) ) {
				$currency_code = get_woocommerce_currency();
			} else {
				$currency_code = SettingsCore::get( 'mhm_rentiva_currency', 'USD' );
			}
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
			'₺'         => 'TRY',
			'₺'         => 'TRY',
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
		add_filter( 'mhm_rentiva/currency_symbol', array( self::class, 'filter_currency_symbol' ), 10, 1 );
	}

	/**
	 * Filter callback for mhm_rentiva/currency_symbol
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
	 * Can be extended via 'mhm_rentiva_currency_list' filter hook
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
		 * add_filter('mhm_rentiva_currency_list', function($currencies) {
		 *     $currencies['BTC'] = 'Bitcoin (' . "\u{20BF}" . ')';
		 *     $currencies['ETH'] = 'Ethereum (' . "\u{039E}" . ')';
		 *     return $currencies;
		 * });
		 */
		return apply_filters( 'mhm_rentiva_currency_list', $currencies );
	}
}
