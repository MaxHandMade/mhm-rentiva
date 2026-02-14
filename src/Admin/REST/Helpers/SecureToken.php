<?php declare(strict_types=1);
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.

namespace MHMRentiva\Admin\REST\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ SECURE TOKEN SYSTEM - JWT-like Secure Token System
 *
 * Secure token system that fixes Base64 decode security vulnerability
 */
final class SecureToken {

	/**
	 * Token expiry (default 24 hours)
	 */
	private const DEFAULT_EXPIRY_HOURS = 24;

	/**
	 * Create secure token
	 *
	 * @param array $payload Token payload
	 * @param int   $expiry_hours Token expiry (hours)
	 * @return string Secure token
	 */
	public static function create( array $payload, int $expiry_hours = self::DEFAULT_EXPIRY_HOURS ): string {
		// Add expiry to payload
		$payload['iat'] = time(); // Issued at
		$payload['exp'] = time() + ( $expiry_hours * 3600 ); // Expiry
		$payload['iss'] = get_site_url(); // Issuer

		// Build header
		$header = array(
			'typ' => 'JWT',
			'alg' => 'HS256',
			'kid' => 'mhm-rentiva-v1',
		);

		// Base64 URL encode
		$encoded_header  = self::base64url_encode( json_encode( $header ) );
		$encoded_payload = self::base64url_encode( json_encode( $payload ) );

		// Create signature
		$signature         = hash_hmac( 'sha256', $encoded_header . '.' . $encoded_payload, self::get_secret_key(), true );
		$encoded_signature = self::base64url_encode( $signature );

		return $encoded_header . '.' . $encoded_payload . '.' . $encoded_signature;
	}

	/**
	 * Verify secure token
	 *
	 * @param string $token Token to verify
	 * @return array|null Verified payload or null
	 */
	public static function verify( string $token ): ?array {
		if ( empty( $token ) ) {
			return null;
		}

		// Split token parts
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 3 ) {
			return null;
		}

		[$encoded_header, $encoded_payload, $encoded_signature] = $parts;

		// Verify signature
		$expected_signature         = hash_hmac( 'sha256', $encoded_header . '.' . $encoded_payload, self::get_secret_key(), true );
		$expected_encoded_signature = self::base64url_encode( $expected_signature );

		if ( ! hash_equals( $expected_encoded_signature, $encoded_signature ) ) {
			return null;
		}

		// Decode payload
		$payload = json_decode( self::base64url_decode( $encoded_payload ), true );
		if ( ! $payload ) {
			return null;
		}

		// Expiry check
		if ( isset( $payload['exp'] ) && time() > $payload['exp'] ) {
			return null;
		}

		// Issuer check
		if ( isset( $payload['iss'] ) && $payload['iss'] !== get_site_url() ) {
			return null;
		}

		return $payload;
	}

	/**
	 * Create customer token
	 *
	 * @param string $email Customer email
	 * @param string $name Customer name
	 * @param int    $booking_id Booking ID
	 * @param int    $expiry_hours Token expiry
	 * @return string Secure customer token
	 */
	public static function create_customer_token( string $email, string $name = '', int $booking_id = 0, int $expiry_hours = self::DEFAULT_EXPIRY_HOURS ): string {
		$payload = array(
			'email'      => $email,
			'name'       => $name,
			'booking_id' => $booking_id,
			'type'       => 'customer',
			'version'    => '1.0',
		);

		return self::create( $payload, $expiry_hours );
	}

	/**
	 * Verify customer token
	 *
	 * @param string $token Token to verify
	 * @param string $post_type Post type (default: vehicle_booking)
	 * @param string $email_meta_key Email meta key (default: _booking_customer_email)
	 * @return array|null Verified customer info or null
	 */
	public static function verify_customer_token( string $token, string $post_type = 'vehicle_booking', string $email_meta_key = '_booking_customer_email' ): ?array {
		$payload = self::verify( $token );
		if ( ! $payload || ( $payload['type'] ?? '' ) !== 'customer' ) {
			return null;
		}

		$email = $payload['email'] ?? '';
		if ( empty( $email ) ) {
			return null;
		}

		// Customer check (has made a reservation?)
		global $wpdb;
		$customer_check = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = %s
             AND p.post_status = 'publish'
             AND pm.meta_key = %s
             AND pm.meta_value = %s",
				$post_type,
				$email_meta_key,
				$email
			)
		);

		if ( ! $customer_check ) {
			return null;
		}

		return array(
			'email'       => $email,
			'name'        => $payload['name'] ?? '',
			'booking_id'  => $payload['booking_id'] ?? 0,
			'token'       => $token,
			'verified_at' => time(),
		);
	}

	/**
	 * Extract email from token (insecure, read‑only)
	 *
	 * @param string $token Token
	 * @return string|null Email or null
	 */
	public static function extract_email( string $token ): ?string {
		$payload = self::verify( $token );
		return $payload['email'] ?? null;
	}

	/**
	 * Check token expiry
	 *
	 * @param string $token Token
	 * @return bool Is token valid?
	 */
	public static function is_expired( string $token ): bool {
		$payload = self::verify( $token );
		return $payload === null;
	}

	/**
	 * Refresh token
	 *
	 * @param string $old_token Old token
	 * @param int    $expiry_hours New token expiry
	 * @return string|null New token or null
	 */
	public static function refresh( string $old_token, int $expiry_hours = self::DEFAULT_EXPIRY_HOURS ): ?string {
		$payload = self::verify( $old_token );
		if ( ! $payload ) {
			return null;
		}

		// Remove old time fields and create new token
		unset( $payload['iat'], $payload['exp'], $payload['iss'] );
		return self::create( $payload, $expiry_hours );
	}

	/** Base64 URL encode */
	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/** Base64 URL decode */
	private static function base64url_decode( string $data ): string {
		return base64_decode( str_pad( strtr( $data, '-_', '+/' ), strlen( $data ) % 4, '=', STR_PAD_RIGHT ) );
	}

	/** Get secret key */
	private static function get_secret_key(): string {
		// Retrieve key from database
		$key = get_option( 'mhm_rentiva_secret_key' );

		// Generate fallback if missing
		if ( ! $key ) {
			$key = wp_generate_password( 64, true, true );
			update_option( 'mhm_rentiva_secret_key', $key );
		}

		// Use WordPress salt
		$wp_secret = defined( 'SECRET_KEY' ) ? SECRET_KEY : 'mhm-rentiva-fallback-key';
		return $key . '_' . $wp_secret;
	}

	/** Get token security info */
	public static function get_security_info(): array {
		return array(
			'algorithm'                => 'HS256',
			'key_derivation'           => 'HMAC-SHA256',
			'signature_method'         => 'hash_hmac',
			'encoding'                 => 'Base64URL',
			'timing_attack_protection' => 'hash_equals',
			'secret_source'            => 'WordPress SECRET_KEY + custom key',
		);
	}
}
