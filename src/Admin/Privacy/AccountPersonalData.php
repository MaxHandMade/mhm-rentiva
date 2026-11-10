<?php
/**
 * Rentiva's account meta in the core privacy tools.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `mhm-rentiva-account` exporter and eraser (5a spec T7).
 *
 * Only the meta Rentiva writes; WordPress exports the account itself and
 * WooCommerce the billing fields. The account is never deleted (core's
 * contract), and the `mhmrentiva_customer` flag stays: it records a role,
 * not a personal detail.
 */
final class AccountPersonalData {

	public const GROUP_ID = 'mhm-rentiva-account';

	/**
	 * Erased keys, in export order.
	 */
	private const KEYS = array(
		'mhmrentiva_phone',
		'mhmrentiva_address',
		'mhmrentiva_favorites',
		'mhmrentiva_favorite_vehicles',
		'mhmrentiva_compare',
		'mhmrentiva_last_login',
		'mhmrentiva_last_activity',
		'mhmrentiva_welcome_email',
		'mhmrentiva_booking_notifications',
		'mhmrentiva_marketing_emails',
		'_mhmrentiva_welcome_sent',
	);

	/**
	 * Notification choices whose '0' means "off" (Mailer::deliver()).
	 */
	private const OPT_OUT_KEYS = array(
		'mhmrentiva_welcome_email',
		'mhmrentiva_booking_notifications',
		'mhmrentiva_marketing_emails',
	);

	/**
	 * Core exporter callback.
	 *
	 * @param string $email Requested address.
	 * @param int    $page  Unused: one item at most.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		unset( $page );
		$user = self::user( $email );
		if ( null === $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$labels = array(
			'mhmrentiva_phone'                 => __( 'Phone', 'mhm-rentiva' ),
			'mhmrentiva_address'               => __( 'Address', 'mhm-rentiva' ),
			'mhmrentiva_favorites'             => __( 'Favourite vehicles', 'mhm-rentiva' ),
			'mhmrentiva_favorite_vehicles'     => __( 'Favourite vehicles', 'mhm-rentiva' ),
			'mhmrentiva_compare'               => __( 'Vehicles to compare', 'mhm-rentiva' ),
			'mhmrentiva_last_login'            => __( 'Last login', 'mhm-rentiva' ),
			'mhmrentiva_last_activity'         => __( 'Last activity', 'mhm-rentiva' ),
			'mhmrentiva_welcome_email'         => __( 'Welcome e-mail', 'mhm-rentiva' ),
			'mhmrentiva_booking_notifications' => __( 'Booking notifications', 'mhm-rentiva' ),
			'mhmrentiva_marketing_emails'      => __( 'Marketing e-mails', 'mhm-rentiva' ),
			'_mhmrentiva_welcome_sent'         => __( 'Welcome e-mail sent', 'mhm-rentiva' ),
		);

		$data = array();
		foreach ( self::KEYS as $key ) {
			$value = self::display( $key, get_user_meta( $user->ID, $key, true ) );
			if ( '' !== $value ) {
				$data[] = array(
					'name'  => $labels[ $key ],
					'value' => $value,
				);
			}
		}

		if ( array() === $data ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		return array(
			'data' => array(
				array(
					'group_id'    => self::GROUP_ID,
					'group_label' => __( 'Rental account', 'mhm-rentiva' ),
					'item_id'     => 'account-' . $user->ID,
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	/**
	 * Core eraser callback.
	 *
	 * @param string $email Requested address.
	 * @param int    $page  Unused: one account at most.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		$user = self::user( $email );
		if ( null === $user ) {
			return $result;
		}

		foreach ( self::KEYS as $key ) {
			if ( ! metadata_exists( 'user', $user->ID, $key ) ) {
				continue;
			}
			// A stored '0' is the person's "do not e-mail me". Mailer treats a
			// missing value as allowed, so erasing it would sign them back up --
			// also for the active bookings the booking eraser keeps. Keep the
			// opt-out; a '1' goes (the default says the same).
			if ( in_array( $key, self::OPT_OUT_KEYS, true ) && '0' === (string) get_user_meta( $user->ID, $key, true ) ) {
				continue;
			}
			delete_user_meta( $user->ID, $key );
			if ( EmailMatch::meta_exists_now( 'user', $user->ID, $key ) ) {
				$result['items_retained'] = true;
				/* translators: %s: internal meta key. */
				$result['messages'][] = sprintf( __( 'Account detail %s could not be erased.', 'mhm-rentiva' ), $key );
			} else {
				$result['items_removed'] = true;
			}
		}

		return $result;
	}

	/**
	 * The account carrying the address.
	 *
	 * @param string $email Requested address.
	 * @return \WP_User|null
	 */
	private static function user( string $email ): ?\WP_User {
		$email = EmailMatch::normalise( $email );
		$user  = '' !== $email ? get_user_by( 'email', $email ) : false;

		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * A stored value as export text: vehicle IDs become vehicle titles.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Stored value.
	 * @return string
	 */
	private static function display( string $key, $value ): string {
		if ( in_array( $key, array( 'mhmrentiva_favorites', 'mhmrentiva_favorite_vehicles', 'mhmrentiva_compare' ), true ) ) {
			$titles = array();
			foreach ( (array) $value as $id ) {
				$title = (int) $id > 0 ? get_the_title( (int) $id ) : '';
				if ( '' !== $title ) {
					$titles[] = $title;
				}
			}

			return implode( ', ', $titles );
		}

		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
