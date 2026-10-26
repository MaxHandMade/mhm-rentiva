<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\BookingUrl;

/**
 * The site's rental pickup points, in one place.
 *
 * Every customer surface (search strip, search results, vehicle detail,
 * booking form) asks this class, so they all give the same answer:
 *
 * - 0 rental locations (Lite alone): no location UI or data anywhere.
 * - 1 rental location: no select; an info line "Pickup and return: X".
 * - 2+ rental locations: a select, and same-city pickup points as a separate
 *   search section.
 *
 * Single vs multi follows the number of rental locations; there is no
 * setting. The list comes from the `mhmrentiva_locations` hook (Pro's
 * provider: active rows with allow_rental = 1), never from the search facet
 * cache, which can be a day old.
 */
final class LocationContext {

	/**
	 * Request cache: the subscriber fingerprint it was built for, and the list.
	 *
	 * @var array{key: string, locations: list<object>}|null
	 */
	private static ?array $cache = null;

	/**
	 * Rental locations as {id:int, name:string, city:string}, provider order,
	 * ids > 0, first row wins on a duplicate id. Cached per request.
	 *
	 * @return list<object>
	 */
	public static function rental_locations(): array
	{
		$key = self::subscriber_key();
		if (null !== self::$cache && self::$cache['key'] === $key) {
			return self::$cache['locations'];
		}

		$locations = array();
		$seen      = array();
		$raw       = apply_filters('mhmrentiva_locations', array(), 'rental');
		foreach (is_array($raw) ? $raw : array() as $row) {
			$row = is_object($row) ? get_object_vars($row) : $row;
			if (! is_array($row)) {
				continue;
			}
			$id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : 0;
			if ($id <= 0 || isset($seen[ $id ])) {
				continue;
			}
			$seen[ $id ] = true;
			$locations[] = (object) array(
				'id'   => $id,
				'name' => trim(is_scalar($row['name'] ?? null) ? (string) $row['name'] : ''),
				'city' => trim(is_scalar($row['city'] ?? null) ? (string) $row['city'] : ''),
			);
		}

		self::$cache = array(
			'key'       => $key,
			'locations' => $locations,
		);
		return $locations;
	}

	/**
	 * Forget the request cache (tests; a provider that changed its list).
	 */
	public static function flush(): void
	{
		self::$cache = null;
	}

	/**
	 * Exactly one rental location.
	 */
	public static function is_single(): bool
	{
		return 1 === count(self::rental_locations());
	}

	/**
	 * The only rental location, or null when there are 0 or 2+.
	 */
	public static function single(): ?object
	{
		$locations = self::rental_locations();
		return 1 === count($locations) ? $locations[0] : null;
	}

	/**
	 * The vehicle's pickup point: vehicle -> vendor -> site default
	 * (BookingUrl::location_for). Null when that point is not a rental
	 * location; on a single-location site the one location is the fallback.
	 */
	public static function for_vehicle( int $vehicle_id ): ?object
	{
		$location = self::by_id(BookingUrl::location_for($vehicle_id));
		if (null !== $location) {
			return $location;
		}
		return self::single();
	}

	/**
	 * Fold a free-text city for comparison ("İstanbul", "ISTANBUL",
	 * "istanbul " all fold to "istanbul"). Order matters: the dot above that
	 * mb_strtolower() leaves on a lowered "İ" is removed AFTER lowering, so
	 * the fold is the same with and without intl.
	 *
	 * @param string    $city     Free-text city.
	 * @param bool|null $use_intl Null: use intl's Normalizer when loaded. Tests pass false to walk the no-intl path.
	 */
	public static function normalize_city( string $city, ?bool $use_intl = null ): string
	{
		$city = (string) preg_replace('/\s+/u', ' ', $city);
		$city = (string) preg_replace('/^\s+|\s+$/u', '', $city);
		if ('' === $city) {
			return '';
		}

		if (( $use_intl ?? true ) && class_exists('Normalizer')) {
			$decomposed = \Normalizer::normalize($city, \Normalizer::FORM_D);
			if (is_string($decomposed)) {
				$city = $decomposed;
			}
		}

		$city = function_exists('mb_strtolower') ? mb_strtolower($city, 'UTF-8') : strtolower($city);
		$city = str_replace("\u{0307}", '', $city);

		return str_replace("\u{0131}", 'i', $city);
	}

	/**
	 * Rental locations in the same city as any selected one, minus the
	 * selected ones, in provider order. A location without a city has no
	 * neighbours.
	 *
	 * @param list<int> $selected Selected location ids.
	 * @return list<int>
	 */
	public static function same_city_ids( array $selected ): array
	{
		$selected = array_map('intval', $selected);
		$cities   = array();
		foreach ($selected as $id) {
			$location = self::by_id($id);
			$city     = null !== $location ? self::normalize_city($location->city) : '';
			if ('' !== $city) {
				$cities[ $city ] = true;
			}
		}
		if (array() === $cities) {
			return array();
		}

		$ids = array();
		foreach (self::rental_locations() as $location) {
			if (in_array($location->id, $selected, true)) {
				continue;
			}
			if (isset($cities[ self::normalize_city($location->city) ])) {
				$ids[] = $location->id;
			}
		}
		return $ids;
	}

	/**
	 * Display names of the cities of these rental locations, one per folded
	 * city, in the order given.
	 *
	 * @param list<int> $ids Location ids.
	 * @return list<string>
	 */
	public static function cities_of( array $ids ): array
	{
		$cities = array();
		foreach ($ids as $id) {
			$location = self::by_id( (int) $id);
			if (null === $location || '' === $location->city) {
				continue;
			}
			$folded = self::normalize_city($location->city);
			if ('' !== $folded && ! isset($cities[ $folded ])) {
				$cities[ $folded ] = (string) preg_replace('/\s+/u', ' ', $location->city);
			}
		}
		return array_values($cities);
	}

	/**
	 * The requested pickup locations that count: none when the site has 0
	 * or 1 rental location (nothing to choose), otherwise the requested ids
	 * that are rental locations, first occurrence order.
	 *
	 * @param array<int|string, mixed> $raw Requested ids (query string values).
	 * @return list<int>
	 */
	public static function effective_selected( array $raw ): array
	{
		if (count(self::rental_locations()) < 2) {
			return array();
		}

		$ids = array();
		foreach ($raw as $value) {
			if (! is_scalar($value) || ! ctype_digit( (string) $value)) {
				continue;
			}
			$id = (int) $value;
			if ($id > 0 && ! in_array($id, $ids, true) && null !== self::by_id($id)) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * A rental location by id, or null.
	 */
	private static function by_id( int $id ): ?object
	{
		if ($id <= 0) {
			return null;
		}
		foreach (self::rental_locations() as $location) {
			if ($location->id === $id) {
				return $location;
			}
		}
		return null;
	}

	/**
	 * Fingerprint of the `mhmrentiva_locations` subscribers, so the request
	 * cache follows a subscriber added or removed mid-request.
	 */
	private static function subscriber_key(): string
	{
		global $wp_filter;

		$hook = $wp_filter['mhmrentiva_locations'] ?? null;
		if (! $hook instanceof \WP_Hook) {
			return '';
		}

		$key = '';
		foreach ($hook->callbacks as $priority => $callbacks) {
			$key .= $priority . ':' . implode(',', array_keys($callbacks)) . ';';
		}
		return $key;
	}
}
