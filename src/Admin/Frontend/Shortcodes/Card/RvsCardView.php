<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Card;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Booking\Pricing\PriceDisplay;
use MHMRentiva\Admin\Booking\Pricing\RentalPricing;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * What the front-end standard's vehicle card shows, as plain data.
 *
 * The template (templates/partials/rvs-vehicle-card.php) only prints this. Every number is
 * the one the booking charges: with a date range the period total comes from
 * RentalPricing (required add-ons included), shown through WooCommerce's
 * shop display (PriceDisplay::for_shop()).
 */
final class RvsCardView {

	/**
	 * The specs line, in order: detail key => meta key.
	 */
	private const SPEC_META = array(
		'seats'        => '_mhmrentiva_seats',
		'transmission' => '_mhmrentiva_transmission',
		'fuel_type'    => '_mhmrentiva_fuel_type',
	);

	/**
	 * @param array<string, mixed>  $vehicle Card data (VehiclesList::get_vehicle_data_for_shortcode() shape).
	 * @param array<string, mixed>  $atts    Shortcode attributes, canonical names or their aliases.
	 * @param array<string, string> $dates   Search context (logical keys); empty = read the request.
	 * @return array<string, mixed>
	 */
	public static function from_vehicle(array $vehicle, array $atts, array $dates = array()): array
	{
		$vehicle_id = (int) ( $vehicle['id'] ?? 0 );
		$available  = (bool) ( $vehicle['availability']['is_available'] ?? true );
		$on         = static fn (string $key, string ...$aliases): bool => self::toggle($atts, $key, $aliases);

		$rating_count = (int) ( $vehicle['rating']['count'] ?? 0 );
		$rating_avg   = (float) ( $vehicle['rating']['average'] ?? 0 );

		$dates = self::dates($dates);

		return array(
			'id'          => $vehicle_id,
			'title'       => (string) ( $vehicle['title'] ?? '' ),
			'permalink'   => (string) ( $vehicle['permalink'] ?? '' ),
			'image'       => array(
				'id'   => (int) ( $vehicle['image']['id'] ?? 0 ),
				'url'  => (string) ( $vehicle['image']['url'] ?? '' ),
				'alt'  => (string) ( $vehicle['image']['alt'] ?? ( $vehicle['title'] ?? '' ) ),
				// The shortcode's image_size (a registered size name).
				'size' => self::image_size($atts),
			),
			'category'    => self::category_name($vehicle),
			'brand'       => self::toggle($atts, 'show_brand', array()) ? self::term_name($vehicle, 'brand') : '',
			'rating'      => array(
				'is_new'     => $rating_count <= 0,
				'average'    => $rating_avg,
				'count'      => $rating_count,
				'label'      => $rating_count > 0
					/* translators: %s: average rating, e.g. 4.8. */
					? sprintf(__('★ %s', 'mhm-rentiva'), number_format_i18n($rating_avg, 1))
					: __('New', 'mhm-rentiva'),
				// The review count is the card's show_rating_count setting (default off).
				'show_count' => $rating_count > 0 && self::toggle($atts, 'show_rating_count', array(), false),
			),
			'description' => self::toggle($atts, 'show_description', array(), false) ? (string) ( $vehicle['excerpt'] ?? '' ) : '',
			'specs'       => self::specs($vehicle_id, (array) ( $vehicle['features'] ?? array() ), (int) ( $atts['max_features'] ?? 0 )),
			'terms'       => self::terms($vehicle_id),
			'price'       => self::price($vehicle_id, $dates),
			'available'   => $available,
			// The status badge is its own setting (show_availability_status, default
			// off); `show_availability` is not read, as on the old card.
			'status'      => self::toggle($atts, 'show_availability_status', array(), false)
				? (string) ( $vehicle['availability']['text'] ?? ( $available ? __('Available', 'mhm-rentiva') : __('Out of Order', 'mhm-rentiva') ) )
				: '',
			'booking'     => array(
				// A transfer-only vehicle is not offered for rental (favourites can
				// hold one): no rental booking button, as on the old card.
				'show'  => $on('show_booking_button', 'show_book_button', 'show_booking_btn')
					&& ( $vehicle_id <= 0 || VehicleDataHelper::allows_service($vehicle_id, 'rental') ),
				'url'   => VehicleCardLinks::booking_url($vehicle, $atts, $dates),
				'label' => $available ? self::booking_label($atts) : __('Unavailable', 'mhm-rentiva'),
			),
			'favorite'    => array(
				'show'   => $on('show_favorite_button', 'show_favorite_btn', 'show_favorite'),
				'active' => self::is_favorite($vehicle, $vehicle_id),
			),
			'compare'     => array(
				'show'   => $on('show_compare_button', 'show_compare_btn'),
				'active' => self::is_compared($vehicle, $vehicle_id),
			),
			'show'        => array(
				'image'    => $on('show_image', 'show_images'),
				'title'    => $on('show_title'),
				'rating'   => $on('show_rating'),
				'specs'    => $on('show_specs', 'show_features'),
				'price'    => $on('show_price'),
				'category' => $on('show_category'),
			),
		);
	}

	/**
	 * A toggle read under its canonical name, then its aliases; $default when absent.
	 *
	 * @param array<string, mixed> $atts
	 * @param list<string>         $aliases
	 */
	private static function toggle(array $atts, string $key, array $aliases, bool $default = true): bool
	{
		foreach (array_merge(array( $key ), $aliases) as $name) {
			if (array_key_exists($name, $atts)) {
				$value = $atts[ $name ];
				if (is_bool($value)) {
					return $value;
				}
				return ! in_array(strtolower(trim( (string) $value )), array( '0', 'false', 'no', 'off', '' ), true);
			}
		}

		return $default;
	}

	/**
	 * Favourite state, as the old card base decides it: a sample card in the
	 * favourites preview (`preview_neutral`) shows the state that page always
	 * shows -- favourite -- without revealing the editor's own list; otherwise
	 * the signed-in user's favourites.
	 *
	 * @param array<string, mixed> $vehicle
	 */
	private static function is_favorite(array $vehicle, int $vehicle_id): bool
	{
		if (! empty($vehicle['preview_neutral'])) {
			return true;
		}
		$user_id = get_current_user_id();
		if ($user_id > 0 && $vehicle_id > 0 && class_exists(\MHMRentiva\Admin\Services\FavoritesService::class)) {
			return \MHMRentiva\Admin\Services\FavoritesService::is_favorite($user_id, $vehicle_id);
		}

		return (bool) ( $vehicle['is_favorite'] ?? false );
	}

	/**
	 * Compare state; never the editor's own list on a preview sample card.
	 *
	 * @param array<string, mixed> $vehicle
	 */
	private static function is_compared(array $vehicle, int $vehicle_id): bool
	{
		if (! empty($vehicle['preview_neutral']) || $vehicle_id <= 0 || ! class_exists(\MHMRentiva\Admin\Services\CompareService::class)) {
			return false;
		}

		return \MHMRentiva\Admin\Services\CompareService::is_in_compare($vehicle_id);
	}

	/**
	 * The search context: the given keys, else the request's whitelisted query vars.
	 *
	 * @param array<string, string> $dates
	 * @return array<string, string>
	 */
	private static function dates(array $dates): array
	{
		if (array() !== $dates) {
			return $dates;
		}

		$out = array();
		foreach (VehicleCardLinks::FORWARD_KEYS as $key) {
			$value = get_query_var(SearchResults::query_var($key), '');
			if ('' !== $value && null !== $value) {
				$out[ $key ] = sanitize_text_field(wp_unslash( (string) $value ));
			}
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $vehicle
	 */
	private static function category_name(array $vehicle): string
	{
		return self::term_name($vehicle, 'category');
	}

	/**
	 * The card data's `category` / `brand`: a name, or a term array.
	 *
	 * @param array<string, mixed> $vehicle
	 */
	private static function term_name(array $vehicle, string $key): string
	{
		$raw = $vehicle[ $key ] ?? ( $vehicle[ $key . '_name' ] ?? '' );

		return is_array($raw) ? (string) ( $raw['name'] ?? ( $raw['slug'] ?? '' ) ) : (string) $raw;
	}

	/**
	 * @param array<string, mixed> $atts
	 */
	private static function image_size(array $atts): string
	{
		$size = isset($atts['image_size']) && is_string($atts['image_size']) ? sanitize_key($atts['image_size']) : '';

		return '' !== $size ? $size : 'medium_large';
	}

	/**
	 * "5 People · Automatic · Hybrid", straight from the vehicle's meta; a
	 * vehicle without that meta falls back to its first three prepared
	 * features (the card data's `features`). $max (the shortcode's
	 * max_features) caps the parts; 0 = no cap.
	 *
	 * @param array<int, array<string, mixed>> $features
	 */
	private static function specs(int $vehicle_id, array $features = array(), int $max = 0): string
	{
		$parts = self::specs_from_meta($vehicle_id);
		if (array() === $parts) {
			foreach (array_slice($features, 0, 3) as $feature) {
				$text = trim(wp_strip_all_tags( (string) ( $feature['text'] ?? '' ) ));
				if ('' !== $text) {
					$parts[] = $text;
				}
			}
		}

		if ($max > 0) {
			$parts = array_slice($parts, 0, $max);
		}

		return implode(' · ', $parts);
	}

	/**
	 * @return list<string>
	 */
	private static function specs_from_meta(int $vehicle_id): array
	{
		$parts = array();
		foreach (self::SPEC_META as $key => $meta) {
			$raw = get_post_meta($vehicle_id, $meta, true);
			if ('' === $raw || null === $raw) {
				continue;
			}
			$formatted = VehicleFeatureHelper::format_detail_value($key, $raw);
			if (null !== $formatted && '' !== (string) $formatted['text']) {
				$parts[] = (string) $formatted['text'];
			}
		}

		return $parts;
	}

	/**
	 * "Deposit 10% · Free cancellation (24 h)": the deposit this vehicle
	 * charges and the site's cancellation window.
	 */
	private static function terms(int $vehicle_id): string
	{
		$rate  = DepositCalculator::resolve_rate($vehicle_id);
		$hours = (int) SettingsCore::get('mhmrentiva_booking_cancellation_deadline_hours', 24);

		$parts   = array();
		$parts[] = $rate > 0
			/* translators: %s: deposit percentage as written in the site's language, e.g. "10%" or "%10". */
			? sprintf(__('Deposit %s', 'mhm-rentiva'), CurrencyHelper::format_percentage($rate))
			: __('Full payment', 'mhm-rentiva');
		if ($hours > 0) {
			/* translators: %d: number of hours before pickup. */
			$parts[] = sprintf(_n('Free cancellation (%d hour)', 'Free cancellation (%d hours)', $hours, 'mhm-rentiva'), $hours);
		}
		// The vehicle's daily km limit, when it has one (Aşama 2 K11).
		$km = VehicleFeatureHelper::format_detail_value('daily_km_limit', get_post_meta($vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::DAILY_KM_LIMIT, true));
		if (null !== $km) {
			$parts[] = $km['text'];
		}

		return implode(' · ', $parts);
	}

	/**
	 * Period total with a date range, daily price without; nothing invented
	 * for a vehicle without a price.
	 *
	 * @param array<string, string> $dates
	 * @return array{has_price: bool, is_total: bool, primary_html: string, secondary: string, days: int}
	 */
	private static function price(int $vehicle_id, array $dates): array
	{
		$daily = VehicleDataHelper::get_price_per_day($vehicle_id);
		if ($daily <= 0) {
			return array(
				'has_price'    => false,
				'is_total'     => false,
				'primary_html' => esc_html__('Price on request', 'mhm-rentiva'),
				'secondary'    => '',
				'days'         => 0,
			);
		}

		$daily_html = PriceDisplay::for_shop(RentalPricing::quote_for_days($vehicle_id, 1, 0)->rental_amount)['html'];

		$window = self::window($dates);
		if (null !== $window) {
			$quote = RentalPricing::quote($vehicle_id, $window[0], $window[1], self::required_addon_ids());
			$total = PriceDisplay::for_shop($quote->full_amount);

			return array(
				'has_price'    => true,
				'is_total'     => true,
				'primary_html' => $total['html'],
				/* translators: 1: number of days, 2: formatted daily price. */
				'secondary'    => sprintf(_n('%1$d day total · %2$s/day', '%1$d days total · %2$s/day', $quote->days, 'mhm-rentiva'), $quote->days, wp_strip_all_tags($daily_html)),
				'days'         => $quote->days,
			);
		}

		return array(
			'has_price'    => true,
			'is_total'     => false,
			'primary_html' => $daily_html,
			'secondary'    => __('per day', 'mhm-rentiva'),
			'days'         => 0,
		);
	}

	/**
	 * Start/end timestamps from the search context, normalised as the booking
	 * form does (return time = pickup time), or null without a full range.
	 *
	 * @param array<string, string> $dates
	 * @return array{0: int, 1: int}|null
	 */
	public static function window(array $dates): ?array
	{
		$pickup = (string) ( $dates['pickup_date'] ?? '' );
		$return = (string) ( $dates['return_date'] ?? '' );
		if ('' === $pickup || '' === $return) {
			return null;
		}
		$time   = (string) ( $dates['pickup_time'] ?? '' );
		$parsed = Util::parse_datetimes($pickup, $time, $return, $time);

		return is_wp_error($parsed) ? null : array( (int) $parsed['start_ts'], (int) $parsed['end_ts'] );
	}

	/**
	 * Services the operator marked required: a booking cannot leave them out,
	 * so the period total includes them.
	 *
	 * @return list<int>
	 */
	public static function required_addon_ids(): array
	{
		$ids = array();
		foreach (AddonManager::get_available_addons() as $addon) {
			if (! empty($addon['required'])) {
				$ids[] = (int) $addon['id'];
			}
		}

		return $ids;
	}

	/**
	 * The booking button's label -- the old card's rule, kept: the shortcode's
	 * own text, else the `booking_btn_text` carrier (the operator's global
	 * "Book now" text), else that global text, else "Book Now".
	 *
	 * @param array<string, mixed> $atts
	 */
	private static function booking_label(array $atts): string
	{
		foreach (array( 'button_text', 'booking_btn_text' ) as $key) {
			$text = trim( (string) ( $atts[ $key ] ?? '' ) );
			if ('' !== $text) {
				return $text;
			}
		}

		$setting = trim( (string) SettingsCore::get('mhmrentiva_text_book_now', '') );

		return '' !== $setting ? $setting : __('Book Now', 'mhm-rentiva');
	}
}
