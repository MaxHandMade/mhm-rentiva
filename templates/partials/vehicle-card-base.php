<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.Security.NonceVerification.Recommended


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
$show_image       = $normalize_toggle($atts['show_image'] ?? true);
$show_features    = $normalize_toggle($atts['show_features'] ?? true);
$show_price       = $normalize_toggle($atts['show_price'] ?? true);
$show_title       = $normalize_toggle($atts['show_title'] ?? true);
$show_rating      = $normalize_toggle($atts['show_rating'] ?? true);
$show_booking     = $normalize_toggle($atts['show_booking_button'] ?? ($atts['show_book_button'] ?? true));
$booking_text     = $atts['booking_btn_text'] ?? __('Book Now', 'mhm-rentiva');
$show_description = $normalize_toggle($atts['show_description'] ?? false);

// Data extraction with safe fallbacks
$vehicle_id    = $vehicle['id'] ?? 0;
$permalink     = $vehicle['permalink'] ?? '#';
$vehicle_title = $vehicle['title'] ?? '';
$excerpt       = $vehicle['excerpt'] ?? '';
$image_url     = $vehicle['image']['url'] ?? '';
$image_alt     = $vehicle['image']['alt'] ?? $vehicle_title;
$image_id      = (int) ($vehicle['image']['id'] ?? 0);
$price_raw     = $vehicle['price']['raw'] ?? 0;
$price_fmt     = $vehicle['price']['formatted'] ?? '';
$is_available  = $vehicle['availability']['is_available'] ?? true;
$status_text   = $vehicle['availability']['text'] ?? '';
$is_featured   = $vehicle['is_featured'] ?? false;
$is_favorite   = $vehicle['is_favorite'] ?? false;
$features      = $vehicle['features'] ?? array();
$rating_avg    = (float) ($vehicle['rating']['average'] ?? 0);
$rating_count  = intval($vehicle['rating']['count'] ?? 0);
$rating_stars  = $vehicle['rating']['stars'] ?? '';

// Visibility Bridges
$show_fav      = $normalize_toggle($atts['show_favorite_button'] ?? ($atts['show_favorite_btn'] ?? true));
$show_compare  = $normalize_toggle($atts['show_compare_button'] ?? ($atts['show_compare_btn'] ?? true));
$show_category = $normalize_toggle($atts['show_category'] ?? true);
$show_brand    = $normalize_toggle($atts['show_brand'] ?? true);
$show_location = $normalize_toggle($atts['show_location'] ?? true);

// Taxonomy display names (safe defaults)
$category_raw = $vehicle['category'] ?? $vehicle['category_name'] ?? '';
$brand_raw    = $vehicle['brand'] ?? $vehicle['brand_name'] ?? '';

if (is_array($category_raw)) {
	$category_name = (string) ($category_raw['name'] ?? $category_raw['slug'] ?? '');
} else {
	$category_name = (string) $category_raw;
}

if (is_array($brand_raw)) {
	$brand_name = (string) ($brand_raw['name'] ?? $brand_raw['slug'] ?? '');
} else {
	$brand_name = (string) $brand_raw;
}
$location_name = (string) ($vehicle['location_name'] ?? '');

// Service type badge — rental / transfer / both.
$service_type = $vehicle_id > 0 ? (string) get_post_meta($vehicle_id, '_rentiva_vehicle_service_type', true) : '';

// Vendor badge — check if vehicle author has rentiva_vendor role.
$is_vendor_vehicle = false;
if ($vehicle_id > 0) {
	$vehicle_author_id = (int) get_post_field('post_author', $vehicle_id);
	if ($vehicle_author_id > 0) {
		$author_user = get_userdata($vehicle_author_id);
		if ($author_user && in_array('rentiva_vendor', (array) $author_user->roles, true)) {
			$is_vendor_vehicle = true;
		}
	}
}

// Check Favorites Service
if (class_exists('\MHMRentiva\Admin\Services\FavoritesService')) {
	$current_user_id = get_current_user_id();
	if ($current_user_id) {
		$is_favorite = \MHMRentiva\Admin\Services\FavoritesService::is_favorite($current_user_id, $vehicle_id);
	}
}

// Check Compare Service
$is_in_compare = false;
if (class_exists('\MHMRentiva\Admin\Services\CompareService')) {
	$is_in_compare = \MHMRentiva\Admin\Services\CompareService::is_in_compare($vehicle_id);
}

// URL Logic
$booking_base_url = $vehicle['booking_url'] ?? ($atts['booking_url'] ?? '');

// Forward search context params to booking URL if present in current request.
$search_params = array( 'vehicle_id' => $vehicle_id );
$forward_keys  = array( 'pickup_location', 'pickup_date', 'pickup_time', 'return_date', 'return_time' );
foreach ( $forward_keys as $key ) {
	if ( isset( $_GET[ $key ] ) && $_GET[ $key ] !== '' ) {
		$search_params[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}
}
// If no pickup_location was forwarded from search, fall back to the vehicle's own location.
if ( empty( $search_params['pickup_location'] ) ) {
	$vehicle_loc_id = (int) ( $vehicle['location_id'] ?? 0 );
	if ( $vehicle_loc_id > 0 ) {
		$search_params['pickup_location'] = $vehicle_loc_id;
	}
}
$booking_btn_url = add_query_arg( $search_params, $booking_base_url );

if (! $is_available) {
	$booking_btn_url = 'javascript:void(0);';
}

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
$allowed_svg  = $allowed_svg_tags;
$btn_url      = $booking_btn_url;
$booking_text = $is_available
	? __('Book Now', 'mhm-rentiva')
	: __('Unavailable', 'mhm-rentiva');
