<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Rvs;

use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Pricing\RentalPricing;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks;
use MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch;
use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Admin\Vehicle\Helpers\RatingHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * What the vehicle page shows, as plain data (Aşama 2 PR B).
 *
 * The template (templates/shortcodes/vehicle-details.php) only prints this, and the page's
 * two AJAX endpoints return partials built from the same arrays, so the first
 * render and a later date change cannot disagree. Every amount is the one the
 * booking charges (RentalPricing, required add-ons included); a condition or
 * highlight without data is left out rather than invented.
 */
final class VehicleDetailsView {

	/**
	 * Optional counts that are highlights even when not part of the admin's
	 * detail selection: they are opt-in by being filled (K11).
	 */
	private const K11_KEYS = array( 'daily_km_limit', 'min_driver_age', 'min_license_years', 'vehicle_max_big_luggage' );

	/**
	 * @param array<string, mixed>  $atts   Shortcode attributes.
	 * @param array<string, string> $window pickup_date, pickup_time, return_date, pickup_location; missing values fall back to the search strip's.
	 * @return array<string, mixed>
	 */
	public static function build(int $vehicle_id, array $atts, array $window): array
	{
		$vehicle  = get_post($vehicle_id);
		$title    = $vehicle ? (string) $vehicle->post_title : '';
		$category = self::category($vehicle_id);
		$rating   = RatingHelper::get_rating($vehicle_id);
		$window   = self::window($window);

		return array(
			'uid'         => wp_unique_id('rvs-vd-'),
			'vehicle_id'  => $vehicle_id,
			'title'       => $title,
			'content'     => $vehicle ? (string) $vehicle->post_content : '',
			'category'    => $category,
			'crumbs'      => self::crumbs($title, $category),
			'rating'      => (int) ( $rating['count'] ?? 0 ) > 0
				? array(
					'average' => (float) $rating['average'],
					'count'   => (int) $rating['count'],
				)
				: null,
			/**
			 * The dealer / pick-up line under the title. Lite has no dealer data; an
			 * add-on fills it.
			 *
			 * @param string $line       Plain text; empty draws nothing.
			 * @param int    $vehicle_id The vehicle.
			 */
			'dealer_line' => (string) apply_filters('mhmrentiva_vehicle_details_dealer_line', '', $vehicle_id),
			'gallery'     => self::gallery($vehicle_id, $title),
			'highlights'  => self::highlights($vehicle_id),
			'conditions'  => self::conditions($vehicle_id),
			'months'      => self::months($vehicle_id, $window['pickup_date'], $window),
			'reviews'     => self::reviews($vehicle_id, $rating),
			'booking'     => self::booking($vehicle_id, $atts, $window),
		);
	}

	/**
	 * The requested window, completed with the search strip's defaults
	 * (tomorrow 10:00 -> + max(3, minimum rental days)).
	 *
	 * @param array<string, string> $window
	 * @return array{pickup_date: string, pickup_time: string, return_date: string, pickup_location: string}
	 */
	public static function window(array $window): array
	{
		$defaults = UnifiedSearch::values();
		$date     = static fn($v): string => 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
		$pickup   = $date($window['pickup_date'] ?? '');
		$return   = $date($window['return_date'] ?? '');
		if ('' === $pickup || '' === $return || $return <= $pickup) {
			$pickup = (string) $defaults['pickup_date'];
			$return = (string) $defaults['return_date'];
		}
		$time = (string) ( $window['pickup_time'] ?? '' );

		return array(
			'pickup_date'     => $pickup,
			'pickup_time'     => 1 === preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : (string) $defaults['pickup_time'],
			'return_date'     => $return,
			'pickup_location' => ctype_digit( (string) ( $window['pickup_location'] ?? '' ) ) ? (string) $window['pickup_location'] : (string) $defaults['pickup_location'],
		);
	}

	/**
	 * Two months from the one holding $from: booked days and the selected window.
	 *
	 * @param array<string, string> $window
	 * @return list<array<string, mixed>>
	 */
	public static function months(int $vehicle_id, string $from, array $window): array
	{
		$first = \DateTimeImmutable::createFromFormat('!Y-m-d', $from, new \DateTimeZone('UTC'));
		if (false === $first) {
			$first = new \DateTimeImmutable('first day of this month', new \DateTimeZone('UTC'));
		}
		$first  = $first->modify('first day of this month');
		$second = $first->modify('+1 month');
		$booked = VehicleDetails::booked_dates($vehicle_id, $first->format('Y-m-d'), $second->format('Y-m-t'));
		$today  = wp_date('Y-m-d');
		$range  = array( (string) ( $window['pickup_date'] ?? '' ), (string) ( $window['return_date'] ?? '' ) );
		$range  = array_values(array_filter($range));

		$months = array();
		foreach (array( $first, $second ) as $month) {
			$months[] = RvsCalendar::month( (int) $month->format('Y'), (int) $month->format('n'), $booked, (string) $today, $range);
		}
		return $months;
	}

	/**
	 * The booking card: period total, breakdown, deposit, continue link.
	 *
	 * @param array<string, mixed>  $atts
	 * @param array<string, string> $window Completed window (self::window()).
	 * @return array<string, mixed>
	 */
	public static function booking(int $vehicle_id, array $atts, array $window): array
	{
		$parsed = RvsCardView::window($window);
		$start  = $parsed[0] ?? 0;
		$end    = $parsed[1] ?? 0;
		$quote  = RentalPricing::quote($vehicle_id, $start, $end, RvsCardView::required_addon_ids());

		$rate    = DepositCalculator::resolve_rate($vehicle_id);
		$deposit = $rate > 0 ? round($quote->full_amount * $rate / 100, 2) : 0.0;

		$check     = Util::check_availability($vehicle_id, $window['pickup_date'], $window['pickup_time'], $window['return_date'], $window['pickup_time']);
		$available = ! empty($check['ok']);

		$hours = (int) SettingsCore::get('mhmrentiva_booking_cancellation_deadline_hours', 24);

		return array(
			'window'       => $window,
			'days'         => $quote->days,
			'breakdown'    => RvsBreakdown::from_quote($quote, $deposit),
			'deposit_text' => $rate > 0
				/* translators: %s: deposit percentage, e.g. "10%". */
				? sprintf(__('Only the deposit now (%s)', 'mhm-rentiva'), CurrencyHelper::format_percentage($rate))
				: __('Paid in full when booking', 'mhm-rentiva'),
			'cancel_text'  => $hours > 0
				/* translators: %d: hours before pickup. */
				? sprintf(_n('Free cancellation up to %d hour before pickup', 'Free cancellation up to %d hours before pickup', $hours, 'mhm-rentiva'), $hours)
				: '',
			'continue_url' => VehicleCardLinks::booking_url(
				array(
					'id'           => $vehicle_id,
					'booking_url'  => ShortcodeUrlManager::get_page_url('rentiva_booking_form'),
					'location_id'  => (int) get_post_meta($vehicle_id, MetaKeys::VEHICLE_LOCATION_ID, true),
					'availability' => array( 'is_available' => true ),
				),
				$atts,
				array(
					'pickup_date'     => $window['pickup_date'],
					'pickup_time'     => $window['pickup_time'],
					'return_date'     => $window['return_date'],
					'return_time'     => $window['pickup_time'],
					'pickup_location' => $window['pickup_location'],
				)
			),
			'available'    => $available,
			'message'      => $available ? '' : (string) ( $check['message'] ?? '' ),
			'locations'    => self::locations(),
		);
	}

	/**
	 * @return array{name: string, url: string}|null
	 */
	private static function category(int $vehicle_id): ?array
	{
		$terms = get_the_terms($vehicle_id, 'mhmrentiva_vehicle_category');
		if (! is_array($terms) || array() === $terms) {
			return null;
		}
		$link = get_term_link($terms[0]);
		return array(
			'name' => (string) $terms[0]->name,
			'url'  => is_wp_error($link) ? '' : (string) $link,
		);
	}

	/**
	 * "Rental cars / SUV / Hyundai Tucson".
	 *
	 * @param array{name: string, url: string}|null $category
	 * @return list<array{label: string, url: string}>
	 */
	private static function crumbs(string $title, ?array $category): array
	{
		$crumbs = array(
			array(
				'label' => __('Rental cars', 'mhm-rentiva'),
				'url'   => (string) ShortcodeUrlManager::get_page_url('rentiva_search_results'),
			),
		);
		if (null !== $category) {
			$crumbs[] = array(
				'label' => $category['name'],
				'url'   => $category['url'],
			);
		}
		$crumbs[] = array(
			'label' => $title,
			'url'   => '',
		);
		return $crumbs;
	}

	/**
	 * Featured image first, then the gallery, without duplicates.
	 *
	 * @return list<array{id: int, full: string, large: string, thumb: string, alt: string}>
	 */
	private static function gallery(int $vehicle_id, string $title): array
	{
		$ids      = array();
		$featured = (int) get_post_thumbnail_id($vehicle_id);
		if ($featured > 0) {
			$ids[] = $featured;
		}
		foreach (VehicleDetails::gallery_ids($vehicle_id) as $id) {
			if (! in_array($id, $ids, true)) {
				$ids[] = $id;
			}
		}

		$images = array();
		foreach ($ids as $id) {
			$large = (string) wp_get_attachment_image_url($id, 'large');
			if ('' === $large) {
				continue;
			}
			$alt      = (string) get_post_meta($id, '_wp_attachment_image_alt', true);
			$images[] = array(
				'id'    => $id,
				'full'  => (string) wp_get_attachment_image_url($id, 'full'),
				'large' => $large,
				'thumb' => (string) wp_get_attachment_image_url($id, 'medium_large'),
				'alt'   => '' !== $alt ? $alt : $title,
			);
		}
		return $images;
	}

	/**
	 * The admin's selected detail fields, any K11 count that has a value, then
	 * the vehicle's active features (value "Yes").
	 *
	 * @return list<array{key: string, label: string, value: string}>
	 */
	private static function highlights(int $vehicle_id): array
	{
		$out  = array();
		$seen = array();
		foreach (VehicleFeatureHelper::collect_detail_items($vehicle_id) as $item) {
			$is_detail = VehicleFeatureHelper::TYPE_DETAIL === $item['type'];
			$out[]     = array(
				'key'   => (string) $item['key'],
				'label' => $is_detail ? (string) $item['label'] : (string) $item['text'],
				'value' => $is_detail ? (string) $item['text'] : __('Yes', 'mhm-rentiva'),
			);
			$seen[]    = $item['key'];
		}

		$labels = \MHMRentiva\Admin\Vehicle\Settings\VehicleSettings::get_all_available_details();
		$keys   = array(
			'daily_km_limit'          => MetaKeys::DAILY_KM_LIMIT,
			'min_driver_age'          => MetaKeys::MIN_DRIVER_AGE,
			'min_license_years'       => MetaKeys::MIN_LICENSE_YEARS,
			'vehicle_max_big_luggage' => MetaKeys::LUGGAGE_LARGE,
		);
		foreach (self::K11_KEYS as $key) {
			if (in_array($key, $seen, true)) {
				continue;
			}
			$formatted = VehicleFeatureHelper::format_detail_value($key, get_post_meta($vehicle_id, $keys[ $key ], true));
			if (null !== $formatted) {
				$out[]  = array(
					'key'   => $key,
					'label' => (string) ( $labels[ $key ] ?? $key ),
					'value' => $formatted['text'],
				);
				$seen[] = $key;
			}
		}

		// The vehicle's own features, as the old page listed them: a Passive one
		// (removed in Field Definitions) does not render, a taxonomy-backed key
		// passes through (D5).
		$map      = VehicleFeatureHelper::get_available_fields_map();
		$features = get_post_meta($vehicle_id, '_mhmrentiva_features', true);
		foreach (is_array($features) ? $features : array() as $feature) {
			$key = is_array($feature) ? sanitize_key( (string) ( $feature['key'] ?? $feature['slug'] ?? '' )) : sanitize_key( (string) $feature);
			if ('' === $key || in_array($key, $seen, true)) {
				continue;
			}
			$type = 0 === strpos($key, 'tax_') ? VehicleFeatureHelper::TYPE_TAXONOMY : VehicleFeatureHelper::TYPE_FEATURE;
			if (! VehicleFeatureHelper::is_field_active($type, $key)) {
				continue;
			}
			$out[]  = array(
				'key'   => $key,
				'label' => (string) ( $map[ $type ][ $key ]['label'] ?? ucfirst(str_replace(array( 'tax_', '_' ), array( '', ' ' ), $key)) ),
				'value' => __('Yes', 'mhm-rentiva'),
			);
			$seen[] = $key;
		}
		return $out;
	}

	/**
	 * Deposit, free cancellation, security deposit and fuel notes -- from data only.
	 *
	 * @return list<array{title: string, text: string}>
	 */
	private static function conditions(int $vehicle_id): array
	{
		$conditions   = array();
		$rate         = DepositCalculator::resolve_rate($vehicle_id);
		$conditions[] = $rate > 0
			? array(
				/* translators: %s: deposit percentage, e.g. "10%". */
				'title' => sprintf(__('Deposit %s', 'mhm-rentiva'), CurrencyHelper::format_percentage($rate)),
				'text'  => __('Paid when booking; the rest at pickup.', 'mhm-rentiva'),
			)
			: array(
				'title' => __('Full payment', 'mhm-rentiva'),
				'text'  => __('Paid in full when booking.', 'mhm-rentiva'),
			);

		$hours = (int) SettingsCore::get('mhmrentiva_booking_cancellation_deadline_hours', 24);
		if ($hours > 0) {
			$conditions[] = array(
				'title' => __('Free cancellation', 'mhm-rentiva'),
				/* translators: %d: hours before pickup. */
				'text'  => sprintf(_n('Up to %d hour before pickup.', 'Up to %d hours before pickup.', $hours, 'mhm-rentiva'), $hours),
			);
		}

		$notes = array(
			'mhmrentiva_booking_security_deposit_note' => __('Security deposit', 'mhm-rentiva'),
			'mhmrentiva_booking_fuel_policy_note'      => __('Fuel policy', 'mhm-rentiva'),
		);
		foreach ($notes as $key => $title) {
			$text = trim( (string) SettingsCore::get($key, ''));
			if ('' !== $text) {
				$conditions[] = array(
					'title' => $title,
					'text'  => $text,
				);
			}
		}
		return $conditions;
	}

	/**
	 * Average, count and the 5..1 distribution, or null without reviews.
	 *
	 * @param array<string, mixed> $rating RatingHelper::get_rating().
	 * @return array{average: float, count: int, distribution: list<array{star: int, count: int, pct: int}>}|null
	 */
	private static function reviews(int $vehicle_id, array $rating): ?array
	{
		$count = (int) ( $rating['count'] ?? 0 );
		if ($count <= 0) {
			return null;
		}
		$distribution = RatingHelper::get_distribution($vehicle_id);
		$total        = max(1, array_sum($distribution));
		$rows         = array();
		foreach (array( 5, 4, 3, 2, 1 ) as $star) {
			$n      = (int) ( $distribution[ $star ] ?? 0 );
			$rows[] = array(
				'star'  => $star,
				'count' => $n,
				'pct'   => (int) round($n * 100 / $total),
			);
		}
		return array(
			'average'      => (float) ( $rating['average'] ?? 0 ),
			'count'        => $count,
			'distribution' => $rows,
		);
	}

	/**
	 * Rental pick-up locations an add-on provides (none in Lite).
	 *
	 * @return list<array{id: int, label: string}>
	 */
	private static function locations(): array
	{
		$out = array();
		foreach ( (array) apply_filters('mhmrentiva_locations', array(), 'rental') as $loc) {
			if (is_object($loc) && isset($loc->id, $loc->name)) {
				$city  = isset($loc->city) ? trim( (string) $loc->city) : '';
				$out[] = array(
					'id'    => (int) $loc->id,
					'label' => '' !== $city ? (string) $loc->name . ' · ' . $city : (string) $loc->name,
				);
			}
		}
		return $out;
	}
}
