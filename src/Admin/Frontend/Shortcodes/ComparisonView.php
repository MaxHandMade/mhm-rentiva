<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Booking\Helpers\BookingConflicts;
use MHMRentiva\Admin\Booking\Helpers\NextFreeWindow;
use MHMRentiva\Admin\Booking\Helpers\SiteTime;
use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\RvsCardView;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks;
use MHMRentiva\Admin\Services\CompareService;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * What the vehicle comparison shows, as plain data.
 *
 * Reads the site, writes nothing and prints nothing; the template only prints
 * this. Prices and the booked note take the vehicle card's own path
 * (RvsCardView), so a comparison and a card can never name different figures.
 */
final class ComparisonView {

	/**
	 * Rows of the drawing's groups. A listed key wins over its category, because
	 * an admin category is not a drawing group: the deposit is a "details" field
	 * and reads under Features. Anything unknown reads under Details.
	 */
	public const GROUPS = array(
		'details'   => array(
			'categories' => array( 'details' ),
			'keys'       => array(),
		),
		'features'  => array(
			'categories' => array(),
			'keys'       => array( 'daily_km_limit', 'min_driver_age', 'min_license_years', 'vehicle_max_big_luggage', 'deposit', self::CANCELLATION_KEY ),
		),
		'equipment' => array(
			'categories' => array( 'features', 'equipment' ),
			'keys'       => array(),
		),
	);

	/** The row that comes from the site's cancellation setting, not from the admin's selection. */
	public const CANCELLATION_KEY = 'free_cancellation';

	/** At least this many vehicles make a comparison. */
	public const MIN_VEHICLES = 2;

	/** Rows in this order first; the rest of the selection follows as saved. */
	private const FIELD_ORDER = array(
		'price_per_day',
		'brand',
		'model',
		'availability',
		'available',
		'fuel_type',
		'transmission',
		'seats',
		'doors',
		'engine_size',
		'year',
		'color',
		'daily_km_limit',
		'min_driver_age',
		'min_license_years',
		'vehicle_max_big_luggage',
		'deposit',
		'mileage',
		'license_plate',
		'rating_average',
		'rating_count',
	);

	/** Numbers and sentences that come with their own format. */
	private const FORMATTED_KEYS = array( 'daily_km_limit', 'min_driver_age', 'min_license_years', 'vehicle_max_big_luggage' );

	private const EMPTY_TEXT = '–';

	/**
	 * @param array<string, mixed>  $atts        Shortcode attributes.
	 * @param list<int>             $vehicle_ids Vehicles in order, before the limit.
	 * @param bool                  $fixed       The ids are not the visitor's list (vehicle_ids, preview sample).
	 * @param array<string, string> $dates       Search context (logical keys); empty = read the request.
	 * @return array<string, mixed>
	 */
	public static function build(array $atts, array $vehicle_ids, bool $fixed, array $dates = array()): array
	{
		$max = self::max_vehicles($atts);
		$ids = array_slice(array_values(array_filter(array_map('intval', $vehicle_ids), array( CompareService::class, 'is_comparable' ))), 0, $max);

		$dates  = RvsCardView::dates($dates);
		$window = RvsCardView::window($dates);
		$count  = count($ids);
		$browse = VehicleCardLinks::with_window(ShortcodeUrlManager::get_page_url('rentiva_search_results'), $dates);
		$range  = null !== $window ? self::range_text($window) : '';

		return array(
			'has_vehicles' => $count >= self::MIN_VEHICLES,
			'count'        => $count,
			'max'          => $max,
			'fixed'        => $fixed,
			'layout'       => 'cards' === ( $atts['layout'] ?? 'table' ) ? 'cards' : 'table',
			'title'        => '' !== trim( (string) ( $atts['title'] ?? '' )) ? trim( (string) $atts['title']) : __('Compare vehicles', 'mhm-rentiva'),
			'class'        => trim( (string) ( $atts['class'] ?? '' )),
			'header'       => self::header($count, $max, $range),
			// The same line for the counts a removal can reach: the script holds no plural rule.
			'headers'      => self::headers($count, $max, $range),
			'range'        => $range,
			'show'         => array(
				'prices'  => self::flag($atts, array( 'show_prices' )),
				'images'  => self::flag($atts, array( 'show_images' )),
				'remove'  => self::flag($atts, array( 'show_remove_buttons' )),
				'booking' => self::flag($atts, array( 'show_booking_buttons', 'show_book_button' )),
			),
			'columns'      => self::columns($ids, $atts, $dates, $window),
			'groups'       => self::groups($ids),
			'can_add'      => ! $fixed && $count < $max && '' !== $browse,
			'add_url'      => $browse,
			'browse_url'   => '' !== $browse ? $browse : home_url('/'),
		);
	}

	/**
	 * The most vehicles shown: the attribute, inside what the list can hold.
	 *
	 * @param array<string, mixed> $atts
	 */
	public static function max_vehicles(array $atts): int
	{
		return max(self::MIN_VEHICLES, min( (int) ( $atts['max_vehicles'] ?? CompareService::max_items() ), CompareService::max_items()));
	}

	/**
	 * The selection in effect as key => category, Passive fields dropped: a field
	 * removed in Field Definitions must not appear in a comparison. Unknown
	 * categories pass through ungated. Ordered by FIELD_ORDER.
	 *
	 * @return array<string, string>
	 */
	public static function selected_keys(): array
	{
		$types    = array(
			'details'   => 'detail',
			'features'  => 'feature',
			'equipment' => 'equipment',
		);
		$selected = array();
		foreach (VehicleComparison::selected_fields_map() as $category => $fields) {
			if (! is_array($fields)) {
				continue;
			}
			$type = $types[ $category ] ?? '';
			foreach ($fields as $key) {
				$key = (string) $key;
				if (isset($selected[ $key ]) || ( '' !== $type && ! VehicleFeatureHelper::is_field_active($type, sanitize_key($key)) )) {
					continue;
				}
				$selected[ $key ] = (string) $category;
			}
		}

		$ordered = array();
		foreach (self::FIELD_ORDER as $key) {
			if (isset($selected[ $key ])) {
				$ordered[ $key ] = $selected[ $key ];
			}
		}

		return $ordered + $selected;
	}

	/**
	 * The selection's rows as key => label.
	 *
	 * @return array<string, string>
	 */
	public static function selected_rows(): array
	{
		$rows = array();
		foreach (array_keys(self::selected_keys()) as $key) {
			$rows[ $key ] = self::label($key);
		}

		return $rows;
	}

	/**
	 * The drawing's group a row belongs to.
	 */
	public static function group_of(string $key, string $category): string
	{
		foreach (self::GROUPS as $group => $rule) {
			if (in_array($key, $rule['keys'], true)) {
				return $group;
			}
		}
		foreach (self::GROUPS as $group => $rule) {
			if (in_array($category, $rule['categories'], true)) {
				return $group;
			}
		}

		return 'details';
	}

	/**
	 * The groups with their rows; an empty group is left out, as is a row empty
	 * for every vehicle. The price is no row: every column carries it.
	 *
	 * @param list<int> $ids
	 * @return list<array{key: string, label: string, rows: list<array<string, mixed>>}>
	 */
	public static function groups(array $ids): array
	{
		$selected = self::selected_keys();
		$rows     = array_fill_keys(array_keys(self::GROUPS), array());

		foreach ($selected as $key => $category) {
			if ('price_per_day' === $key || 'currency_symbol' === $key) {
				continue;
			}
			$cells = array();
			foreach ($ids as $id) {
				$cells[] = self::cell($id, $key, $category) + array( 'vehicle_id' => $id );
			}
			$row = self::row($key, self::label($key), $cells);
			if (null !== $row) {
				$rows[ self::group_of($key, $category) ][] = $row;
			}
		}

		// An admin who chose to compare nothing compares nothing.
		if (array() !== $selected) {
			$row = self::cancellation_row($ids);
			if (null !== $row) {
				$rows['features'][] = $row;
			}
		}

		$labels = array(
			'details'   => __('Details', 'mhm-rentiva'),
			'features'  => __('Features', 'mhm-rentiva'),
			'equipment' => _x('Equipment', 'comparison group: what the vehicle is fitted with', 'mhm-rentiva'),
		);
		$groups = array();
		foreach ($rows as $group => $group_rows) {
			if (array() !== $group_rows) {
				$groups[] = array(
					'key'   => $group,
					'label' => $labels[ $group ],
					'rows'  => $group_rows,
				);
			}
		}

		return $groups;
	}

	/**
	 * One vehicle's value of a row. `value` is what the row compares: it does not
	 * depend on the language or the number format; `empty` says the vehicle has
	 * no value (a row empty for every vehicle is not drawn).
	 *
	 * @return array{text: string, value: string, empty: bool}
	 */
	public static function cell(int $vehicle_id, string $key, string $category): array
	{
		if ('deposit' === $key) {
			$rate = DepositCalculator::resolve_rate($vehicle_id);

			// No deposit is a fact too, in the vehicle card's words: the whole total is paid.
			return $rate > 0 ? self::filled(CurrencyHelper::format_percentage($rate), (string) $rate) : self::filled(__('Full payment', 'mhm-rentiva'), '0');
		}

		$in_lists = self::in_vehicle_lists($vehicle_id, $key);
		if (in_array($category, array( 'features', 'equipment' ), true)) {
			return self::yes_no($in_lists || self::is_truthy(get_post_meta($vehicle_id, '_mhmrentiva_' . $key, true)));
		}

		$raw = get_post_meta($vehicle_id, '_mhmrentiva_' . $key, true);
		if ($in_lists && self::is_blank($raw)) {
			return self::yes_no(true);
		}

		return self::format($key, $raw);
	}

	/**
	 * A stored detail value as a cell. A number is a number, never a tick: one
	 * large suitcase reads "1 large suitcase".
	 *
	 * @param mixed $raw
	 * @return array{text: string, value: string, empty: bool}
	 */
	public static function format(string $key, $raw): array
	{
		if (in_array($key, self::FORMATTED_KEYS, true)) {
			$formatted = VehicleFeatureHelper::format_detail_value($key, $raw);
			if (null !== $formatted) {
				return self::filled( (string) $formatted['text'], (string) (int) $raw);
			}

			// No limit is a fact a customer compares; no age or luggage is just empty.
			return 'daily_km_limit' === $key
				? array(
					'text'  => __('Unlimited', 'mhm-rentiva'),
					'value' => 'unlimited',
					'empty' => true,
				)
				: self::blank();
		}

		if (is_array($raw)) {
			$raw = implode(', ', array_filter(array_map('strval', array_filter($raw, 'is_scalar'))));
		}
		if (self::is_blank($raw)) {
			return self::blank();
		}

		$text = trim( (string) $raw);
		if ('fuel_type' === $key) {
			return self::filled(VehicleDataHelper::get_fuel_type_label($text), $text);
		}
		if ('transmission' === $key) {
			return self::filled(VehicleDataHelper::get_transmission_label($text), $text);
		}
		if (is_bool($raw) || in_array(strtolower($text), array( 'yes', 'true', 'on', 'no', 'false', 'off' ), true)) {
			return self::yes_no(self::is_truthy($raw));
		}

		return self::filled($text, $text);
	}

	/**
	 * A row from its cells, the drawing's rule: a cell differs when its value
	 * occurs once in the row. Null when no vehicle has a value.
	 *
	 * @param list<array{text: string, value: string, empty: bool}> $cells
	 * @return array{key: string, label: string, all_same: bool, cells: list<array<string, mixed>>}|null
	 */
	public static function row(string $key, string $label, array $cells): ?array
	{
		if (array() === array_filter($cells, static fn (array $cell): bool => ! $cell['empty'])) {
			return null;
		}

		$counts = array_count_values(array_map(static fn (array $cell): string => $cell['value'], $cells));
		$many   = count($cells) > 1;

		foreach ($cells as $index => $cell) {
			$cells[ $index ]['different'] = $many && 1 === $counts[ $cell['value'] ];
		}

		return array(
			'key'      => $key,
			'label'    => $label,
			'all_same' => 1 === count($counts),
			'cells'    => $cells,
		);
	}

	/**
	 * The virtual "Free cancellation" row, from the site's setting (the vehicle
	 * card's source); off = no row.
	 *
	 * @param list<int> $ids
	 * @return array<string, mixed>|null
	 */
	private static function cancellation_row(array $ids): ?array
	{
		$hours = (int) SettingsCore::get('mhmrentiva_booking_cancellation_deadline_hours', 24);
		if ($hours <= 0) {
			return null;
		}

		/* translators: %d: number of hours before pickup. */
		$text  = sprintf(_n('%d hour before', '%d hours before', $hours, 'mhm-rentiva'), $hours);
		$cells = array();
		foreach ($ids as $id) {
			$cells[] = self::filled($text, (string) $hours) + array( 'vehicle_id' => $id );
		}

		return self::row(self::CANCELLATION_KEY, __('Free cancellation', 'mhm-rentiva'), $cells);
	}

	/**
	 * @param list<int>                 $ids
	 * @param array<string, mixed>      $atts
	 * @param array<string, string>     $dates
	 * @param array{0: int, 1: int}|null $window
	 * @return list<array<string, mixed>>
	 */
	private static function columns(array $ids, array $atts, array $dates, ?array $window): array
	{
		$booked   = array();
		$earliest = array();
		$days     = 0;
		if (null !== $window && array() !== $ids) {
			$booked   = BookingConflicts::booked_vehicle_ids($window[0], $window[1], $ids);
			$earliest = array() !== $booked ? NextFreeWindow::earliest($booked, $window[0], $window[1]) : array();
			$days     = SiteTime::rental_days($window[0], $window[1]);
		}

		$card_atts = array( 'image_size' => 'medium' );
		$columns   = array();
		foreach ($ids as $id) {
			$vehicle = VehiclesList::get_vehicle_data_for_shortcode($id, $card_atts);
			// is_comparable() let it in; the window's own answer is the booked note.
			$vehicle['availability'] = array( 'is_available' => true );

			$is_booked = in_array($id, $booked, true);
			$extra     = $is_booked ? array(
				'booked' => array(
					'earliest' => $earliest[ $id ] ?? null,
					'days'     => $days,
				),
			) : array();
			$card      = RvsCardView::from_vehicle($vehicle, $card_atts, $dates, $extra);
			$price     = $card['price'];

			$columns[] = array(
				'id'            => $id,
				'title'         => $card['title'],
				'permalink'     => $card['permalink'],
				'image'         => $card['image'],
				'has_price'     => $price['has_price'],
				'is_total'      => $price['is_total'],
				'price_html'    => $price['primary_html'] . $price['suffix'],
				'days_label'    => self::days_label($price),
				'booked'        => $card['booked'],
				'booked_note'   => $card['booked_note'],
				// A booked car goes to the vehicle page on its next free window, as on its card.
				'booking_url'   => $card['booked'] ? $card['booked_url'] : $card['booking']['url'],
				'booking_label' => $card['booked']
					? __('See from that date', 'mhm-rentiva')
					: _x('Select', 'comparison: choose this vehicle', 'mhm-rentiva'),
				'remove'        => self::flag($atts, array( 'show_remove_buttons' )),
				/* translators: %s: vehicle name. */
				'remove_label'  => sprintf(__('Remove %s from comparison', 'mhm-rentiva'), $card['title']),
			);
		}

		return $columns;
	}

	/**
	 * "3 days" under a total, "per day" under a daily price.
	 *
	 * @param array<string, mixed> $price RvsCardView price.
	 */
	private static function days_label(array $price): string
	{
		if (empty($price['has_price'])) {
			return '';
		}
		if (empty($price['is_total'])) {
			return (string) $price['secondary'];
		}

		$days = (int) $price['days'];

		/* translators: %d: number of rental days. */
		return sprintf(_n('%d day', '%d days', $days, 'mhm-rentiva'), $days);
	}

	/**
	 * "3 vehicles · up to 3 · Prices for 21 October – 24 October".
	 */
	private static function header(int $count, int $max, string $range): string
	{
		$parts = array(
			/* translators: %d: number of vehicles being compared. */
			sprintf(_n('%d vehicle', '%d vehicles', $count, 'mhm-rentiva'), $count),
			/* translators: %d: the most vehicles that can be compared. */
			sprintf(__('up to %d', 'mhm-rentiva'), $max),
		);
		if ('' !== $range) {
			/* translators: %s: the rental dates, e.g. "21 October – 24 October". */
			$parts[] = sprintf(__('Prices for %s', 'mhm-rentiva'), $range);
		}

		return implode(' · ', $parts);
	}

	/**
	 * @return array<int, string> Count => header line.
	 */
	private static function headers(int $count, int $max, string $range): array
	{
		$headers = array();
		for ($n = self::MIN_VEHICLES; $n <= $count; $n++) {
			$headers[ $n ] = self::header($n, $max, $range);
		}

		return $headers;
	}

	/**
	 * @param array{0: int, 1: int} $window
	 */
	private static function range_text(array $window): string
	{
		$format = (string) get_option('date_format');

		/* translators: 1: pickup date, 2: return date. */
		return sprintf(__('%1$s – %2$s', 'mhm-rentiva'), (string) wp_date($format, $window[0]), (string) wp_date($format, $window[1]));
	}

	private static function in_vehicle_lists(int $vehicle_id, string $key): bool
	{
		foreach (array( '_mhmrentiva_features', '_mhmrentiva_equipment' ) as $meta) {
			$list = maybe_unserialize(get_post_meta($vehicle_id, $meta, true));
			if (is_array($list) && in_array($key, $list, true)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed $raw
	 */
	private static function is_blank($raw): bool
	{
		return null === $raw || false === $raw || '' === $raw || array() === $raw;
	}

	/**
	 * @param mixed $raw
	 */
	private static function is_truthy($raw): bool
	{
		return is_bool($raw) ? $raw : in_array(strtolower(trim( (string) ( is_scalar($raw) ? $raw : '' ))), array( '1', 'yes', 'true', 'on' ), true);
	}

	/**
	 * @return array{text: string, value: string, empty: bool}
	 */
	private static function yes_no(bool $has): array
	{
		return self::filled(
			$has
				? _x('Yes', 'comparison: the vehicle has this', 'mhm-rentiva')
				: _x('No', 'comparison: the vehicle does not have this', 'mhm-rentiva'),
			$has ? '1' : '0'
		);
	}

	/**
	 * @return array{text: string, value: string, empty: bool}
	 */
	private static function filled(string $text, string $value): array
	{
		return array(
			'text'  => $text,
			'value' => $value,
			'empty' => false,
		);
	}

	/**
	 * @return array{text: string, value: string, empty: bool}
	 */
	private static function blank(): array
	{
		return array(
			'text'  => self::EMPTY_TEXT,
			'value' => '',
			'empty' => true,
		);
	}

	/**
	 * An attribute under its name or alias; on by default.
	 *
	 * @param array<string, mixed> $atts
	 * @param list<string>         $names
	 */
	private static function flag(array $atts, array $names): bool
	{
		foreach ($names as $name) {
			if (array_key_exists($name, $atts)) {
				return in_array(strtolower(trim( (string) $atts[ $name ])), array( '1', 'true', 'yes', 'on' ), true);
			}
		}

		return true;
	}

	/**
	 * A row's label: the field's own (Field Definitions), else a known name.
	 */
	private static function label(string $key): string
	{
		foreach (VehicleFeatureHelper::get_available_fields_map() as $fields) {
			if (isset($fields[ $key ]['label'])) {
				return $fields[ $key ]['label'];
			}
		}

		$normalized = strtolower(str_replace(' ', '_', trim($key)));
		$labels     = array(
			'availability'     => __('Availability', 'mhm-rentiva'),
			'available'        => __('Available', 'mhm-rentiva'),
			'brand'            => __('Brand', 'mhm-rentiva'),
			'model'            => __('Model', 'mhm-rentiva'),
			'price_per_day'    => __('Daily Price', 'mhm-rentiva'),
			'fuel_type'        => __('Fuel Type', 'mhm-rentiva'),
			'transmission'     => __('Transmission', 'mhm-rentiva'),
			'seats'            => __('Seats', 'mhm-rentiva'),
			'doors'            => __('Doors', 'mhm-rentiva'),
			'engine_size'      => __('Engine Size', 'mhm-rentiva'),
			'year'             => __('Model Year', 'mhm-rentiva'),
			'mileage'          => __('Mileage', 'mhm-rentiva'),
			'color'            => __('Color', 'mhm-rentiva'),
			'deposit'          => __('Deposit', 'mhm-rentiva'),
			'license_plate'    => __('License Plate', 'mhm-rentiva'),
			'rating_average'   => __('Rating Average', 'mhm-rentiva'),
			'rating_count'     => __('Rating Count', 'mhm-rentiva'),
			'gallery_images'   => __('Gallery Images', 'mhm-rentiva'),
			'air_conditioning' => __('Air Conditioning', 'mhm-rentiva'),
			'gps'              => __('GPS', 'mhm-rentiva'),
			'bluetooth'        => __('Bluetooth', 'mhm-rentiva'),
			'usb_port'         => __('USB Port', 'mhm-rentiva'),
			'sunroof'          => __('Sunroof', 'mhm-rentiva'),
			'power_steering'   => __('Power Steering', 'mhm-rentiva'),
			'central_locking'  => __('Central Locking', 'mhm-rentiva'),
			'cruise_control'   => __('Cruise Control', 'mhm-rentiva'),
			'airbags'          => __('Airbags', 'mhm-rentiva'),
			'abs_brakes'       => __('ABS Brakes', 'mhm-rentiva'),
			'abs'              => __('ABS Brakes', 'mhm-rentiva'),
			'fog_lights'       => __('Fog Lights', 'mhm-rentiva'),
			'parking_sensors'  => __('Parking Sensors', 'mhm-rentiva'),
			'backup_camera'    => __('Backup Camera', 'mhm-rentiva'),
			'leather_seats'    => __('Leather Seats', 'mhm-rentiva'),
			'heated_seats'     => __('Heated Seats', 'mhm-rentiva'),
			'electric_windows' => __('Electric Windows', 'mhm-rentiva'),
			'electric_mirrors' => __('Electric Mirrors', 'mhm-rentiva'),
			'power_mirrors'    => __('Power Mirrors', 'mhm-rentiva'),
			'alloy_wheels'     => __('Alloy Wheels', 'mhm-rentiva'),
			'roof_rack'        => __('Roof Rack', 'mhm-rentiva'),
			'navigation'       => __('Navigation', 'mhm-rentiva'),
		);

		return $labels[ $normalized ] ?? ucfirst(str_replace('_', ' ', $normalized));
	}
}
