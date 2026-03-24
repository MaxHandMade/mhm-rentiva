<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\Utilities\I18nHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Type Validator
 *
 * Central class for type safety and validation
 */
final class TypeValidator {


	/**
	 * Validate and return integer value
	 */
	public static function validateInt( mixed $value, int $default = 0 ): int {
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && is_numeric( $value ) ) {
			return (int) $value;
		}

		return $default;
	}

	/**
	 * Validate and return string value
	 */
	public static function validateString( mixed $value, string $default = '' ): string {
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (string) $value;
		}

		return $default;
	}

	/**
	 * Validate and return array value
	 */
	public static function validateArray( mixed $value, array $default = array() ): array {
		if ( is_array( $value ) ) {
			return $value;
		}

		return $default;
	}

	/**
	 * Validate and return boolean value
	 */
	public static function validateBool( mixed $value, bool $default = false ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( 'true', '1', 'yes', 'on' ), true );
		}

		if ( is_numeric( $value ) ) {
			return (bool) $value;
		}

		return $default;
	}

	/**
	 * Validate and return float value
	 */
	public static function validateFloat( mixed $value, float $default = 0.0 ): float {
		if ( is_float( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return (float) $value;
		}

		return $default;
	}

	/**
	 * Return non-null value
	 */
	public static function validateNotNull( mixed $value, mixed $default = null ): mixed {
		return $value !== null ? $value : $default;
	}

	/**
	 * Validate Post ID
	 */
	public static function validatePostId( mixed $value ): ?int {
		$id = self::validateInt( $value );
		return $id > 0 && get_post( $id ) ? $id : null;
	}

	/**
	 * Validate User ID
	 */
	public static function validateUserId( mixed $value ): ?int {
		$id = self::validateInt( $value );
		return $id > 0 && get_user_by( 'id', $id ) ? $id : null;
	}

	/**
	 * Validate email address
	 */
	public static function validateEmail( mixed $value ): ?string {
		$email = self::validateString( $value );
		return is_email( $email ) ? $email : null;
	}

	/**
	 * Validate URL
	 */
	public static function validateUrl( mixed $value ): ?string {
		$url = self::validateString( $value );
		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : null;
	}

	/**
	 * Validate date string
	 */
	public static function validateDate( mixed $value, string $format = 'Y-m-d' ): ?string {
		$date = self::validateString( $value );
		if ( empty( $date ) ) {
			return null;
		}

		$d = \DateTime::createFromFormat( $format, $date );
		return $d && $d->format( $format ) === $date ? $date : null;
	}

	/**
	 * Validate enum value
	 */
	public static function validateEnum( mixed $value, array $allowed_values, mixed $default = null ): mixed {
		if ( in_array( $value, $allowed_values, true ) ) {
			return $value;
		}

		return $default;
	}

	/**
	 * Validate all values in array
	 */
	public static function validateArrayOf( mixed $value, string $type, mixed $default = array() ): array {
		$array     = self::validateArray( $value, $default );
		$validated = array();

		foreach ( $array as $item ) {
			switch ( $type ) {
				case 'int':
					$validated[] = self::validateInt( $item );
					break;
				case 'string':
					$validated[] = self::validateString( $item );
					break;
				case 'bool':
					$validated[] = self::validateBool( $item );
					break;
				case 'float':
					$validated[] = self::validateFloat( $item );
					break;
				default:
					$validated[] = $item;
			}
		}

		return $validated;
	}

	/**
	 * Validate WordPress post array
	 */
	public static function validatePostArray( mixed $value ): array {
		$array = self::validateArray( $value );

		// Check required fields
		$required_fields = array( 'id', 'name', 'email' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $array[ $field ] ) ) {
				$array[ $field ] = '';
			}
		}

		return $array;
	}

	/**
	 * Validate WordPress user array
	 */
	public static function validateUserArray( mixed $value ): array {
		$array = self::validateArray( $value );

		// Check required fields
		$required_fields = array( 'id', 'name', 'email' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $array[ $field ] ) ) {
				$array[ $field ] = '';
			}
		}

		return $array;
	}

	/**
	 * Type assertion - runtime type check
	 */
	public static function assertType( mixed $value, string $expected_type ): mixed {
		$actual_type = gettype( $value );

		if ( $actual_type !== $expected_type ) {
			throw new \TypeError(
				esc_html(
					sprintf(
						/* translators: 1: expected type, 2: actual type */
						__( 'Expected type %1$s, got %2$s', 'mhm-rentiva' ),
						$expected_type,
						$actual_type
					)
				)
			);
		}

		return $value;
	}

	/**
	 * Safe type casting
	 */
	public static function safeCast( mixed $value, string $target_type ): mixed {
		switch ( $target_type ) {
			case 'int':
				return self::validateInt( $value );
			case 'string':
				return self::validateString( $value );
			case 'bool':
				return self::validateBool( $value );
			case 'float':
				return self::validateFloat( $value );
			case 'array':
				return self::validateArray( $value );
			default:
				return $value;
		}
	}
}
