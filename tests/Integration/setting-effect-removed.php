<?php
/**
 * Gate G4 (RemovedSettingsAreGoneTest): settings removed by the slice.
 *
 * A task that removes a REMOVE row's key moves the row here from
 * setting-effect-expected-dead.php, unchanged; the test then checks the (tag, key)
 * is gone from all five layers. Rows stay after T10 deletes the expected-dead file.
 *
 * @return list<array{surface:'block'|'elementor', tag:string, key:string, decision:'REMOVE'}>
 */

declare(strict_types=1);

return array(
	// rentiva_contact (Task 1).
	array( 'surface' => 'block', 'tag' => 'rentiva_contact', 'key' => 'recipient_email', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_contact', 'key' => 'recipient_email', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_contact', 'key' => 'show_map', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_contact', 'key' => 'show_map', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_contact', 'key' => 'show_phone_field', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_contact', 'key' => 'show_phone_field', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_contact', 'key' => 'show_social_links', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_contact', 'key' => 'show_social_links', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_contact', 'key' => 'show_subject_field', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_contact', 'key' => 'show_subject_field', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_contact', 'key' => 'show_vehicle_select', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_contact', 'key' => 'show_vehicle_select', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_contact', 'key' => 'subject_prefix', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_contact', 'key' => 'subject_prefix', 'decision' => 'REMOVE' ),
	// rentiva_vehicles_list, vehicle card widget only (Task 2).
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'button_style', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'custom_rating', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'enable_animation', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'rating_position', 'decision' => 'REMOVE' ),
	// rentiva_featured_vehicles (Task 3).
	array( 'surface' => 'block', 'tag' => 'rentiva_featured_vehicles', 'key' => 'price_format', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_featured_vehicles', 'key' => 'price_format', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_featured_vehicles', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_featured_vehicles', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_featured_vehicles', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_featured_vehicles', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_featured_vehicles', 'key' => 'show_book_button', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_featured_vehicles', 'key' => 'title', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_featured_vehicles', 'key' => 'title', 'decision' => 'REMOVE' ),
	// rentiva_vehicles_grid (Task 3).
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_description', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_grid', 'key' => 'show_description', 'decision' => 'REMOVE' ),
	// rentiva_vehicles_list (Task 3).
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_list', 'key' => 'price_format', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'price_format', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_list', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicles_list', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicles_list', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	// rentiva_vehicle_comparison (Task 4).
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_category', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_category', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_features', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_features', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_fuel_type', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_fuel_type', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_rating', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_rating', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_seats', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_seats', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_technical_specs', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_technical_specs', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_transmission', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_comparison', 'key' => 'show_transmission', 'decision' => 'REMOVE' ),
	// rentiva_vehicle_details (Task 4).
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_breadcrumb', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_breadcrumb', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_share_btns', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_share_btns', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_technical_specs', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_technical_specs', 'decision' => 'REMOVE' ),
	// Not a table row: its only reader (the reviews gate) moved to show_reviews (exception 2);
	// the block toggle was rebound to show_booking (ruling T4-1, rule 2).
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_booking_form', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_details', 'key' => 'show_booking_form', 'decision' => 'REMOVE' ),
	// rentiva_search_results (Task 5).
	array( 'surface' => 'block', 'tag' => 'rentiva_search_results', 'key' => 'order', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_search_results', 'key' => 'order', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_search_results', 'key' => 'orderby', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_search_results', 'key' => 'orderby', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_search_results', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_search_results', 'key' => 'show_availability', 'decision' => 'REMOVE' ),
	// rentiva_unified_search (Task 5).
	array( 'surface' => 'block', 'tag' => 'rentiva_unified_search', 'key' => 'filter_categories', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_unified_search', 'key' => 'filter_categories', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_unified_search', 'key' => 'layout', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_unified_search', 'key' => 'layout', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_unified_search', 'key' => 'maxwidth', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_unified_search', 'key' => 'maxwidth', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_unified_search', 'key' => 'minwidth', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_unified_search', 'key' => 'minwidth', 'decision' => 'REMOVE' ),
	// rentiva_availability_calendar (Task 6).
	array( 'surface' => 'block', 'tag' => 'rentiva_availability_calendar', 'key' => 'integrate_pricing', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_availability_calendar', 'key' => 'integrate_pricing', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_booking_links', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_booking_links', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_seasonal_prices', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_seasonal_prices', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_today_btn', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_today_btn', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_week_numbers', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_availability_calendar', 'key' => 'show_week_numbers', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_availability_calendar', 'key' => 'start_date', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_availability_calendar', 'key' => 'start_date', 'decision' => 'REMOVE' ),
	// rentiva_booking_form (Task 6).
	array( 'surface' => 'block', 'tag' => 'rentiva_booking_form', 'key' => 'default_payment', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_booking_form', 'key' => 'default_payment', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_booking_form', 'key' => 'max_days', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_booking_form', 'key' => 'max_days', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_booking_form', 'key' => 'min_days', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_booking_form', 'key' => 'min_days', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_booking_form', 'key' => 'show_date_picker', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_booking_form', 'key' => 'show_date_picker', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_booking_form', 'key' => 'show_insurance', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_booking_form', 'key' => 'show_insurance', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_booking_form', 'key' => 'show_payment_options', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_booking_form', 'key' => 'show_payment_options', 'decision' => 'REMOVE' ),
);
