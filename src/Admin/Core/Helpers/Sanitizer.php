<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central Sanitization Helper
 */
final class Sanitizer {

	/**
	 * Safe sanitize text field that handles null values
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function text_field_safe( $value ): string {
		// Null check FIRST - before any processing
		if ( $value === null ) {
			return '';
		}
		// Empty string check
		if ( $value === '' ) {
			return '';
		}
		// Convert to string if not already - prevents strlen() errors
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return '';
		}
		// Now safe to call WordPress core function
		return sanitize_text_field( (string) $value );
	}

	/**
	 * One line a person wrote: no markup, every run of white space one space.
	 *
	 * For prose -- titles, labels, subjects, notes. sanitize_text_field() also
	 * drops what looks like an encoded byte, and "%10 indirim" is how Turkish
	 * writes ten per cent. Values that are a key, a date, a status or a token
	 * stay with sanitize_text_field(), and the output is escaped as before.
	 *
	 * @param mixed $value
	 */
	public static function line( $value ): string {
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', self::lines( $value ) ) );
	}

	/**
	 * Same as line(), line breaks kept.
	 *
	 * @param mixed $value
	 */
	public static function lines( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		// sanitize_textarea_field() without its removal of "%" + two hex digits.
		$text = wp_check_invalid_utf8( (string) $value );
		if ( str_contains( $text, '<' ) ) {
			$text = wp_strip_all_tags( wp_pre_kses_less_than( $text ) );
			// A line break closed up later must not leave a working tag.
			$text = str_replace( "<\n", "&lt;\n", $text );
		}

		return trim( $text );
	}
}
