<?php
/**
 * One normalisation of an e-mail address for the privacy tools.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every exporter and eraser compares addresses through this class, so a
 * request typed "Guest@Example.TEST " finds what the booking form stored as
 * "guest@example.test" (and the other way round).
 */
final class EmailMatch {

	/**
	 * Lower-case, trimmed address; '' when it is not an e-mail address.
	 *
	 * A '' result means "run no query": an empty e-mail branch must never
	 * reach SQL, where it would match every row with an empty value.
	 *
	 * @param string $email Raw address.
	 * @return string
	 */
	public static function normalise( string $email ): string {
		$email = strtolower( trim( $email ) );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Keyed hash of the address, stored on a booking while its identity is
	 * being anonymised so a half-written identity stays findable (spec T4).
	 * Not reversible without the site's salts.
	 *
	 * @param string $email Raw address.
	 * @return string '' when the address is not valid.
	 */
	public static function subject_hash( string $email ): string {
		$email = self::normalise( $email );

		return '' === $email ? '' : wp_hash( 'mhmrentiva-privacy|' . $email );
	}

	/**
	 * A LIKE pattern that finds the address as a whole JSON string.
	 *
	 * The address is JSON-encoded first (quotes included, and "/" escaped the
	 * way wp_json_encode() stores it), then escaped for LIKE. Keeping the
	 * quotes stops "a@example.test" from matching "ba@example.test". The
	 * pattern only nominates candidates: callers decode the JSON and compare
	 * the field before acting.
	 *
	 * @param string $email Raw address.
	 * @return string '' when the address is not valid.
	 */
	public static function json_like_needle( string $email ): string {
		global $wpdb;

		$email = self::normalise( $email );
		if ( '' === $email ) {
			return '';
		}

		$json = wp_json_encode( $email );
		if ( false === $json ) {
			return '';
		}

		return '%' . $wpdb->esc_like( $json ) . '%';
	}
}
