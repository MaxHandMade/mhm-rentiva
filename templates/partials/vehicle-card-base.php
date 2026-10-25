<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound


/**
 * Vehicle Card Base Data Provider (SSOT)
 *
 * Extract, normalize, and prepare vehicle data for both Listing and Booking contexts.
 * IMPORTANT: This file MUST NOT contain HTML or output anything.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

// v1.0 APPROVED TOGGLES
// Toggles Normalization (Strict Boolean Conversion)
$normalize_toggle = function ($val) {
	if ($val === '0' || $val === 'false' || $val === 0 || $val === false) {
		return false;
	}
	return true;
};

// Toggle States
$show_image = $normalize_toggle($atts['show_image'] ?? true);
// Card items arrive as one list but carry a `type`. Vehicle *details* (seats,
// transmission, fuel, year …) read as a compact spec line, while features,
// equipment and taxonomy terms read as chips. They are toggled separately so a
// card can keep the at-a-glance specs without the chip cluster.
$show_specs       = $normalize_toggle($atts['show_specs'] ?? true);
$show_features    = $normalize_toggle($atts['show_features'] ?? false);
$show_price       = $normalize_toggle($atts['show_price'] ?? true);
$show_title       = $normalize_toggle($atts['show_title'] ?? true);
$show_rating      = $normalize_toggle($atts['show_rating'] ?? true);
$show_booking     = $normalize_toggle($atts['show_booking_button'] ?? ( $atts['show_book_button'] ?? true ));
$show_description = $normalize_toggle($atts['show_description'] ?? false);

// Opt-in inputs: only an explicit "on" switches them on. $normalize_toggle reads
// any unknown value -- an empty string included -- as on, which would show them
// on cards whose caller never asked.
$is_switched_on = static function ($val): bool {
	return in_array($val, array( true, 1, '1', 'yes', 'true', 'on' ), true);
};
// "4.5 (12)": the review count beside the average. Off unless asked for.
$show_rating_count = $is_switched_on($atts['show_rating_count'] ?? false);
// The availability text ("Available" / "Out of Order"). Only this key is read:
// the lists' show_availability is not (spec 3A §2.2 -- its defaults are on and
// would change every untouched card).
$show_availability_status = $is_switched_on($atts['show_availability_status'] ?? false);
// The instance's booking button label (see $booking_text below).
$button_text = trim( (string) ( $atts['button_text'] ?? '' ) );

// Data extraction with safe fallbacks
$vehicle_id    = $vehicle['id'] ?? 0;
$permalink     = $vehicle['permalink'] ?? '#';
$vehicle_title = $vehicle['title'] ?? '';
$excerpt       = $vehicle['excerpt'] ?? '';
$image_url     = $vehicle['image']['url'] ?? '';
$image_alt     = $vehicle['image']['alt'] ?? $vehicle_title;
$image_id      = (int) ( $vehicle['image']['id'] ?? 0 );
$price_raw     = $vehicle['price']['raw'] ?? 0;
$price_fmt     = $vehicle['price']['formatted'] ?? '';
$is_available  = $vehicle['availability']['is_available'] ?? true;
$status_text   = $vehicle['availability']['text'] ?? '';
$is_featured   = $vehicle['is_featured'] ?? false;
$is_favorite   = $vehicle['is_favorite'] ?? false;
$features      = $vehicle['features'] ?? array();

// A transfer-only vehicle is not offered for rental: its card (favourites,
// single-card widget) carries no rental booking button.
if ($show_booking && (int) $vehicle_id > 0 && ! \MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper::allows_service( (int) $vehicle_id, 'rental')) {
	$show_booking = false;
}

// Partition the card items (see $show_specs / $show_features above). Items with
// no type fall back to the chip list, which is how they rendered before.
$spec_items = array();
$chip_items = array();
foreach ($features as $card_item) {
	if (! is_array($card_item)) {
		continue;
	}
	if (( $card_item['type'] ?? '' ) === 'detail') {
		$spec_items[] = $card_item;
	} else {
		$chip_items[] = $card_item;
	}
}
$rating_avg   = (float) ( $vehicle['rating']['average'] ?? 0 );
$rating_count = intval($vehicle['rating']['count'] ?? 0);
$rating_stars = $vehicle['rating']['stars'] ?? '';

// Visibility Bridges
$show_fav      = $normalize_toggle($atts['show_favorite_button'] ?? ( $atts['show_favorite_btn'] ?? true ));
$show_compare  = $normalize_toggle($atts['show_compare_button'] ?? ( $atts['show_compare_btn'] ?? true ));
$show_category = $normalize_toggle($atts['show_category'] ?? true);
$show_brand    = $normalize_toggle($atts['show_brand'] ?? true);
$show_location = $normalize_toggle($atts['show_location'] ?? true);

// Taxonomy display names (safe defaults)
$category_raw = $vehicle['category'] ?? $vehicle['category_name'] ?? '';
$brand_raw    = $vehicle['brand'] ?? $vehicle['brand_name'] ?? '';

if (is_array($category_raw)) {
	$category_name = (string) ( $category_raw['name'] ?? $category_raw['slug'] ?? '' );
} else {
	$category_name = (string) $category_raw;
}

if (is_array($brand_raw)) {
	$brand_name = (string) ( $brand_raw['name'] ?? $brand_raw['slug'] ?? '' );
} else {
	$brand_name = (string) $brand_raw;
}
$location_name = (string) ( $vehicle['location_name'] ?? '' );

// A sample card in the favourites preview (MyFavorites sets `preview_neutral`)
// must not reveal the editing user's own lists: it shows the state the
// "My favourites" page always shows -- favourite, not compared.
$preview_neutral = ! empty($vehicle['preview_neutral']);

// Check Favorites Service
if ($preview_neutral) {
	$is_favorite = true;
} elseif (class_exists('\MHMRentiva\Admin\Services\FavoritesService')) {
	$current_user_id = get_current_user_id();
	if ($current_user_id) {
		$is_favorite = \MHMRentiva\Admin\Services\FavoritesService::is_favorite($current_user_id, $vehicle_id);
	}
}

// Check Compare Service
$is_in_compare = false;
if (! $preview_neutral && class_exists('\MHMRentiva\Admin\Services\CompareService')) {
	$is_in_compare = \MHMRentiva\Admin\Services\CompareService::is_in_compare($vehicle_id);
}

// URL Logic
$booking_base_url = $vehicle['booking_url'] ?? ( $atts['booking_url'] ?? '' );

// One implementation with the front-end standard's card (VehicleCardLinks):
// forwards the search context through the query_vars whitelist (never raw
// $_GET; SearchResults/BookingForm PUBLIC_QUERY_VARS, names via
// SearchResults::query_var()), falls back to the vehicle's own location, and
// gives an unavailable vehicle no link.
$booking_btn_url = \MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks::booking_url(
	array_merge(
		$vehicle,
		array(
			'id'           => $vehicle_id,
			'booking_url'  => $booking_base_url,
			'availability' => array( 'is_available' => $is_available ),
		)
	),
	$atts
);

// SVG Constraints
$allowed_svg_tags = array(
	'svg'      => array(
		'class'           => true,
		'viewBox'         => true,
		'viewbox'         => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
		'xmlns'           => true,
		'width'           => true,
		'height'          => true,
		'aria-hidden'     => true,
		'focusable'       => true,
		'role'            => true,
	),
	'path'     => array(
		'd'               => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
	),
	'g'        => array(
		'fill'   => true,
		'stroke' => true,
		'class'  => true,
	),
	'circle'   => array(
		'cx'           => true,
		'cy'           => true,
		'r'            => true,
		'fill'         => true,
		'stroke'       => true,
		'stroke-width' => true,
	),
	'rect'     => array(
		'x'            => true,
		'y'            => true,
		'width'        => true,
		'height'       => true,
		'rx'           => true,
		'ry'           => true,
		'fill'         => true,
		'stroke'       => true,
		'stroke-width' => true,
	),
	'line'     => array(
		'x1'             => true,
		'y1'             => true,
		'x2'             => true,
		'y2'             => true,
		'stroke'         => true,
		'stroke-width'   => true,
		'stroke-linecap' => true,
	),
	'polyline' => array(
		'points'          => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linecap'  => true,
		'stroke-linejoin' => true,
	),
	'polygon'  => array(
		'points'          => true,
		'fill'            => true,
		'stroke'          => true,
		'stroke-width'    => true,
		'stroke-linejoin' => true,
	),
);

// Template-level aliases (vehicle-card.php references these short names)
$allowed_svg = $allowed_svg_tags;
$btn_url     = $booking_btn_url;

// Booking button label (spec 3A §2.2 exception 3). An unavailable vehicle's
// button is disabled and always says so. Otherwise: the instance's button_text,
// then booking_btn_text -- an internal carrier with no editor control, filled by
// the grid/list shortcodes from the global mhmrentiva_text_book_now setting --
// then today's Book Now.
$booking_btn_text = trim( (string) ( $atts['booking_btn_text'] ?? '' ) );
if (! $is_available) {
	$booking_text = __('Unavailable', 'mhm-rentiva');
} elseif ('' !== $button_text) {
	$booking_text = $button_text;
} elseif ('' !== $booking_btn_text) {
	$booking_text = $booking_btn_text;
} else {
	$booking_text = __('Book Now', 'mhm-rentiva');
}
