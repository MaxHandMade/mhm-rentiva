<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Card;

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The "book this vehicle" link a card points at.
 *
 * One implementation for the old card base (templates/partials/vehicle-card-base.php)
 * and the front-end standard's card, so both forward the same search context.
 */
final class VehicleCardLinks {

	/**
	 * Search context keys forwarded to the booking form (logical names).
	 */
	public const FORWARD_KEYS = array( 'pickup_location', 'pickup_date', 'pickup_time', 'return_date', 'return_time' );

	/**
	 * Booking URL for a vehicle: the booking page with the vehicle id and the
	 * search context.
	 *
	 * The context comes from $dates when given (an AJAX re-render, where the
	 * request's query vars are not parsed), otherwise from the request's
	 * whitelisted query vars (SearchResults / BookingForm PUBLIC_QUERY_VARS).
	 * Without a forwarded pickup location the vehicle's own location is used.
	 * An unavailable vehicle gets no link.
	 *
	 * @param array<string, mixed>  $vehicle Card data (id, booking_url, location_id, availability).
	 * @param array<string, mixed>  $atts    Shortcode attributes (booking_url fallback).
	 * @param array<string, string> $dates   Logical key => value, overrides the request.
	 */
	public static function booking_url(array $vehicle, array $atts, array $dates = array()): string
	{
		if (! ( $vehicle['availability']['is_available'] ?? true )) {
			return 'javascript:void(0);';
		}

		$vehicle_id = (int) ( $vehicle['id'] ?? 0 );
		$base_url   = (string) ( $vehicle['booking_url'] ?? ( $atts['booking_url'] ?? '' ) );

		$params = array( SearchResults::query_var('vehicle_id') => $vehicle_id );
		foreach (self::FORWARD_KEYS as $key) {
			$value = array_key_exists($key, $dates) ? $dates[ $key ] : get_query_var(SearchResults::query_var($key), null);
			if (null !== $value && '' !== $value) {
				$params[ SearchResults::query_var($key) ] = sanitize_text_field(wp_unslash( (string) $value ));
			}
		}

		if (empty($params[ SearchResults::query_var('pickup_location') ])) {
			$location_id = (int) ( $vehicle['location_id'] ?? 0 );
			if ($location_id > 0) {
				$params[ SearchResults::query_var('pickup_location') ] = $location_id;
			}
		}

		return add_query_arg($params, $base_url);
	}
}
