<?php
/**
 * A customer's phone and address, from one place.
 *
 * @package MHM_Rentiva
 */

declare( strict_types=1 );

namespace MHMRentiva\Admin\Customers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single reader for "what is this customer's phone / address".
 *
 * The same account carried its phone under several keys, and every screen read
 * a different one in a different order: the Customers screen read only
 * `mhmrentiva_phone`, the booking form preferred `billing_phone`, the manual
 * booking box read `mhmrentiva_phone` then a bare `phone`. A customer who had
 * checked out through WooCommerce therefore read "Phone -" on Customers while
 * the booking form knew the number.
 *
 * The order, first non-empty wins:
 *
 *   1. Rentiva's own field (`mhmrentiva_phone` / `mhmrentiva_address`) -- what
 *      My Account's profile form, the admin edit screen and the manual booking
 *      box write, so an edit made in Rentiva is what Rentiva shows.
 *   2. WooCommerce billing (`billing_phone`; `billing_address_1`,
 *      `billing_address_2`, `billing_postcode billing_city`) -- what checkout
 *      writes, and required there. Then a bare `phone` key, which booking
 *      views used to fall back to although nothing here writes it.
 *   3. `mhmrentiva_customer_contact` -- an add-on may fill what is still empty
 *      (Pro: the phone a vendor gave on their application).
 *
 * It only reads. Copying a value from one key into another is how the phones
 * drifted apart in the first place; a copy is a fourth value to drift.
 */
final class CustomerContact {

	/**
	 * The customer's phone, or '' when none is known.
	 *
	 * @param int $user_id Account.
	 * @return string
	 */
	public static function phone( int $user_id ): string {
		return self::for_user( $user_id )['phone'];
	}

	/**
	 * The customer's address as one line, or '' when none is known.
	 *
	 * @param int $user_id Account.
	 * @return string
	 */
	public static function address( int $user_id ): string {
		return self::for_user( $user_id )['address'];
	}

	/**
	 * Both fields, resolved and filtered.
	 *
	 * @param int $user_id Account.
	 * @return array{phone: string, address: string}
	 */
	public static function for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array(
				'phone'   => '',
				'address' => '',
			);
		}

		$found = array(
			'phone'   => self::first_non_empty(
				array(
					self::meta( $user_id, 'mhmrentiva_phone' ),
					self::meta( $user_id, 'billing_phone' ),
					// A bare `phone` key: five booking views fell back to it, and
					// nothing in Rentiva writes it -- kept last for accounts another
					// plugin filled.
					self::meta( $user_id, 'phone' ),
				)
			),
			'address' => self::first_non_empty(
				array(
					self::meta( $user_id, 'mhmrentiva_address' ),
					self::billing_address( $user_id ),
				)
			),
		);

		/**
		 * Fill in a customer's contact details Lite could not find.
		 *
		 * Receives what Lite resolved; return the same shape. Meant for filling
		 * a gap -- a value Lite found is the customer's own and should stand.
		 *
		 * @param array{phone: string, address: string} $found   Resolved so far.
		 * @param int                                   $user_id Account.
		 */
		$filtered = apply_filters( 'mhmrentiva_customer_contact', $found, $user_id );

		// A misbehaving callback costs the add-on its contribution, not the
		// screen its phone column.
		if ( ! is_array( $filtered ) ) {
			return $found;
		}

		return array(
			'phone'   => is_scalar( $filtered['phone'] ?? null ) ? trim( (string) $filtered['phone'] ) : $found['phone'],
			'address' => is_scalar( $filtered['address'] ?? null ) ? trim( (string) $filtered['address'] ) : $found['address'],
		);
	}

	/**
	 * WooCommerce's billing address on one line: street, street 2, "postcode city".
	 *
	 * @param int $user_id Account.
	 * @return string
	 */
	private static function billing_address( int $user_id ): string {
		$locality = trim( self::meta( $user_id, 'billing_postcode' ) . ' ' . self::meta( $user_id, 'billing_city' ) );

		$parts = array_filter(
			array(
				self::meta( $user_id, 'billing_address_1' ),
				self::meta( $user_id, 'billing_address_2' ),
				$locality,
			),
			static fn( string $part ): bool => '' !== $part
		);

		return implode( ', ', $parts );
	}

	/**
	 * @param string[] $candidates In order of preference.
	 * @return string
	 */
	private static function first_non_empty( array $candidates ): string {
		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * A user meta value as a trimmed string ('' for anything non-scalar).
	 *
	 * @param int    $user_id Account.
	 * @param string $key     Meta key.
	 * @return string
	 */
	private static function meta( int $user_id, string $key ): string {
		$value = get_user_meta( $user_id, $key, true );

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
