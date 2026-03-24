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
}
