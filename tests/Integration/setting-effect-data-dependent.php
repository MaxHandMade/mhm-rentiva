<?php
/**
 * Gate G1 (SettingHasEffectTest): live settings whose effect needs data the base fixture lacks.
 *
 * Every row is rendered with its named fixture (ShortcodeFixtures::setting_probe()) and must change the
 * render there; no row is an untested exemption. From dead-settings.tsv status 'live (data-dependent)',
 * plus the rows the Task 0 measurement added (reason says so).
 *
 * @return list<array{surface:'block'|'elementor', tag:string, key:string, fixture:string, reason:string}>
 */

declare(strict_types=1);

return array(
	// rentiva_booking_form.
	array( 'surface' => 'block', 'tag' => 'rentiva_booking_form', 'key' => 'show_vehicle_selector', 'fixture' => 'no_vehicle_preselected', 'reason' => 'the vehicle selector renders only when the form is not bound to a vehicle' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_booking_form', 'key' => 'show_vehicle_selector', 'fixture' => 'no_vehicle_preselected', 'reason' => 'the vehicle selector renders only when the form is not bound to a vehicle' ),

	// rentiva_featured_vehicles.
	array( 'surface' => 'block', 'tag' => 'rentiva_featured_vehicles', 'key' => 'category', 'fixture' => 'category_slugs', 'reason' => 'the category filter matches term slugs; sentinel values match nothing' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_featured_vehicles', 'key' => 'category', 'fixture' => 'category_slugs', 'reason' => 'the category filter matches term slugs; sentinel values match nothing' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_featured_vehicles', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_featured_vehicles', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_featured_vehicles', 'key' => 'view_all_text', 'fixture' => 'view_all_url', 'reason' => 'the link text renders only next to a view-all link; added by Task 0 (not in the table)' ),

	// rentiva_my_bookings.
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'hide_nav', 'fixture' => 'without_woocommerce', 'reason' => 'AccountRenderer::get_navigation() returns [] whenever WooCommerce is active' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'hide_nav', 'fixture' => 'without_woocommerce', 'reason' => 'AccountRenderer::get_navigation() returns [] whenever WooCommerce is active' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_image', 'fixture' => 'without_woocommerce', 'reason' => 'the Img column renders only on a page that is not integrated (bookings.php), and get_navigation() returns [] whenever WooCommerce is active; added by Task 7a (table: extend ! $is_integrated with show_image)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_image', 'fixture' => 'without_woocommerce', 'reason' => 'the Img column renders only on a page that is not integrated (bookings.php), and get_navigation() returns [] whenever WooCommerce is active; added by Task 7a (table: extend ! $is_integrated with show_image)' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_pagination', 'fixture' => 'one_booking_per_page', 'reason' => 'the pager renders only when there is more than one page; added by Task 7a' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_pagination', 'fixture' => 'one_booking_per_page', 'reason' => 'the pager renders only when there is more than one page; added by Task 7a' ),

	// rentiva_my_favorites.
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'order', 'fixture' => 'favorites_sorted_by_name', 'reason' => 'order applies to the name and price sorts; the default \'added\' is the saved order (Task 7b)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'order', 'fixture' => 'favorites_sorted_by_name', 'reason' => 'order applies to the name and price sorts; the default \'added\' is the saved order (Task 7b)' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'no_results_text', 'fixture' => 'no_favorites', 'reason' => 'the text is the empty state, shown only without favourites; added by Task 7b' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'no_results_text', 'fixture' => 'no_favorites', 'reason' => 'the text is the empty state, shown only without favourites; added by Task 7b' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),

	// rentiva_payment_history.
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'hide_nav', 'fixture' => 'without_woocommerce', 'reason' => 'AccountRenderer::get_navigation() returns [] whenever WooCommerce is active' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'hide_nav', 'fixture' => 'without_woocommerce', 'reason' => 'AccountRenderer::get_navigation() returns [] whenever WooCommerce is active' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'show_pagination', 'fixture' => 'one_booking_per_page', 'reason' => 'the pager renders only when there is more than one page; added by Task 7b' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'show_pagination', 'fixture' => 'one_booking_per_page', 'reason' => 'the pager renders only when there is more than one page; added by Task 7b' ),

	// rentiva_search_results.
	array( 'surface' => 'block', 'tag' => 'rentiva_search_results', 'key' => 'show_pagination', 'fixture' => 'one_result_per_page', 'reason' => 'the pager renders only when there is more than one page' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_search_results', 'key' => 'show_pagination', 'fixture' => 'one_result_per_page', 'reason' => 'the pager renders only when there is more than one page' ),

	// rentiva_unified_search.
	array( 'surface' => 'block', 'tag' => 'rentiva_unified_search', 'key' => 'location_required', 'fixture' => 'search_locations', 'reason' => 'the location pickers render only when a location provider (add-on) lists locations' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_unified_search', 'key' => 'location_required', 'fixture' => 'search_locations', 'reason' => 'the location pickers render only when a location provider (add-on) lists locations' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_unified_search', 'key' => 'show_dropoff_location', 'fixture' => 'search_locations', 'reason' => 'the location pickers render only when a location provider (add-on) lists locations' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_unified_search', 'key' => 'show_dropoff_location', 'fixture' => 'search_locations', 'reason' => 'the location pickers render only when a location provider (add-on) lists locations' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_unified_search', 'key' => 'show_location_select', 'fixture' => 'search_locations', 'reason' => 'the location pickers render only when a location provider (add-on) lists locations; added by Task 0 (live on the dev site, where Pro provides locations)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_unified_search', 'key' => 'show_location_select', 'fixture' => 'search_locations', 'reason' => 'the location pickers render only when a location provider (add-on) lists locations; added by Task 0 (live on the dev site, where Pro provides locations)' ),

	// rentiva_vehicle_comparison.
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'layout', 'fixture' => 'catalog', 'reason' => 'needs two compared vehicles with data; the base catalog fixture has them' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'layout', 'fixture' => 'catalog', 'reason' => 'needs two compared vehicles with data; the base catalog fixture has them' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_prices', 'fixture' => 'catalog', 'reason' => 'needs two compared vehicles with data; the base catalog fixture has them' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_prices', 'fixture' => 'catalog', 'reason' => 'needs two compared vehicles with data; the base catalog fixture has them' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'title', 'fixture' => 'catalog', 'reason' => 'needs two compared vehicles with data; the base catalog fixture has them' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'title', 'fixture' => 'catalog', 'reason' => 'needs two compared vehicles with data; the base catalog fixture has them' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'vehicle_ids', 'fixture' => 'vehicle_id_lists', 'reason' => 'a comparison needs two vehicles; a single id renders the same empty table' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'vehicle_ids', 'fixture' => 'vehicle_id_lists', 'reason' => 'a comparison needs two vehicles; a single id renders the same empty table' ),

	// rentiva_vehicle_details.
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_details', 'key' => 'similar_vehicles_limit', 'fixture' => 'vehicle_without_category', 'reason' => 'similar vehicles are the featured ones of the shown vehicle category; each catalog vehicle is alone in its category, so a limit has nothing to cut (added by Task 4)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_details', 'key' => 'similar_vehicles_limit', 'fixture' => 'vehicle_without_category', 'reason' => 'similar vehicles are the featured ones of the shown vehicle category; each catalog vehicle is alone in its category, so a limit has nothing to cut (added by Task 4)' ),

	// rentiva_vehicles_grid.
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_grid', 'key' => 'category', 'fixture' => 'category_slugs', 'reason' => 'the category filter matches term slugs; sentinel values match nothing' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_grid', 'key' => 'category', 'fixture' => 'category_slugs', 'reason' => 'the category filter matches term slugs; sentinel values match nothing' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_grid', 'key' => 'featured', 'fixture' => 'one_vehicle_not_featured', 'reason' => 'featured-only changes the list only when some vehicle is not featured (and, for the vehicle card, when no vehicle is chosen)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_grid', 'key' => 'featured', 'fixture' => 'one_vehicle_not_featured', 'reason' => 'featured-only changes the list only when some vehicle is not featured (and, for the vehicle card, when no vehicle is chosen)' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type, not the default detail fields' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_grid', 'key' => 'view_all_text', 'fixture' => 'view_all_url', 'reason' => 'the link text renders only next to a view-all link; added by Task 0 (not in the table)' ),

	// rentiva_vehicles_list.
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_list', 'key' => 'category', 'fixture' => 'category_slugs', 'reason' => 'the category filter matches term slugs; sentinel values match nothing' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'category', 'fixture' => 'category_slugs', 'reason' => 'the category filter matches term slugs; sentinel values match nothing' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_list', 'key' => 'featured', 'fixture' => 'one_vehicle_not_featured', 'reason' => 'featured-only changes the list only when some vehicle is not featured (and, for the vehicle card, when no vehicle is chosen)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'featured', 'fixture' => 'one_vehicle_not_featured', 'reason' => 'featured-only changes the list only when some vehicle is not featured (and, for the vehicle card, when no vehicle is chosen)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'ids', 'fixture' => 'card_without_vehicle', 'reason' => 'the vehicle card widget maps its chosen vehicle to ids with limit 1, so list filters and sort pick its vehicle only when none is chosen; added by Task 0 (the table judged these per key, live through the list widget)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'min_rating', 'fixture' => 'card_without_vehicle', 'reason' => 'the vehicle card widget maps its chosen vehicle to ids with limit 1, so list filters and sort pick its vehicle only when none is chosen; added by Task 0 (the table judged these per key, live through the list widget)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'min_reviews', 'fixture' => 'card_without_vehicle', 'reason' => 'the vehicle card widget maps its chosen vehicle to ids with limit 1, so list filters and sort pick its vehicle only when none is chosen; added by Task 0 (the table judged these per key, live through the list widget)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'order', 'fixture' => 'card_without_vehicle', 'reason' => 'the vehicle card widget maps its chosen vehicle to ids with limit 1, so list filters and sort pick its vehicle only when none is chosen; added by Task 0 (the table judged these per key, live through the list widget)' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_list', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type; added by Task 0 (not in the table)' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'show_features', 'fixture' => 'card_feature_items', 'reason' => 'show_features toggles card items of the feature type; added by Task 0 (not in the table)' ),
);
