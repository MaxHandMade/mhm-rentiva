<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Account;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Core\LocationContext;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Where a booking starts and ends, as the customer is shown it.
 *
 * A rental starts and ends where its vehicle stands today (no location is
 * stored on the booking). A transfer runs from its origin to its destination;
 * those locations belong to the add-on that sells transfers, so their names
 * come through the same `mhmrentiva_location_by_id` filter the booking
 * e-mails use. An end nobody can name is null.
 */
final class BookingPlaces
{
	/**
	 * @return array{transfer: bool, pickup: ?string, return: ?string}
	 */
	public static function for_booking(int $booking_id): array
	{
		if (BookingMeta::is_transfer_booking($booking_id)) {
			return array(
				'transfer' => true,
				'pickup'   => self::transfer_end( (int) get_post_meta($booking_id, '_mhmrentiva_transfer_origin_id', true)),
				'return'   => self::transfer_end( (int) get_post_meta($booking_id, '_mhmrentiva_transfer_destination_id', true)),
			);
		}

		$vehicle_id = (int) get_post_meta($booking_id, '_mhmrentiva_vehicle_id', true);
		$location   = $vehicle_id > 0 ? LocationContext::for_vehicle($vehicle_id) : null;
		$name       = null !== $location ? self::clean_name($location) : null;

		return array(
			'transfer' => false,
			'pickup'   => $name,
			'return'   => $name,
		);
	}

	/**
	 * "Origin → Destination" for a transfer whose two ends are known, '' otherwise.
	 *
	 * @param array{transfer: bool, pickup: ?string, return: ?string} $places
	 */
	public static function route_text(array $places): string
	{
		if (! $places['transfer'] || null === $places['pickup'] || null === $places['return']) {
			return '';
		}

		return $places['pickup'] . ' → ' . $places['return'];
	}

	private static function transfer_end(int $location_id): ?string
	{
		if ($location_id <= 0) {
			return null;
		}

		$location = apply_filters('mhmrentiva_location_by_id', null, $location_id);

		return is_object($location) ? self::clean_name($location) : null;
	}

	private static function clean_name(object $location): ?string
	{
		$name = trim( (string) ( $location->name ?? '' ));

		return '' !== $name ? $name : null;
	}
}
