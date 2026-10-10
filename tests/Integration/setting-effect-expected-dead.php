<?php
/**
 * Gate G1 (SettingHasEffectTest): the settings that change nothing in the render today.
 *
 * One row per (surface, tag, canonical key), from docs/superpowers/audits/2026-10-10-elementor-kopru-dilim-3/dead-settings.tsv
 * (status=dead; decision = the recommendation column; my_favorites/show_badges REMOVE by spec 3A §2.1),
 * minus the rows the gate measures as live (see the Task 0 report). Each task of the slice deletes its
 * own rows; a REMOVE row moves to setting-effect-removed.php when its key is removed. Empty and deleted at T10.
 *
 * @return list<array{surface:'block'|'elementor', tag:string, key:string, decision:'APPLY'|'REMOVE'}>
 */

declare(strict_types=1);

return array(
	// rentiva_my_bookings.
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'limit_results', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'limit_results', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'orderby', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'orderby', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_booking_dates', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_booking_dates', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_cancel_btn', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_cancel_btn', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_details_link', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_details_link', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_image', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_image', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_modify_btn', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_modify_btn', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_pagination', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_pagination', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_price', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_price', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_bookings', 'key' => 'show_status_toggle', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_bookings', 'key' => 'show_status_toggle', 'decision' => 'APPLY' ),

	// rentiva_my_favorites.
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'layout', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'layout', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'no_results_text', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'no_results_text', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'orderby', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'show_added_date', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'show_added_date', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'show_badges', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_my_favorites', 'key' => 'show_remove_button', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_my_favorites', 'key' => 'show_remove_button', 'decision' => 'REMOVE' ),

	// rentiva_payment_history.
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'limit', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'limit', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'limit_results', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'limit_results', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'order', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'order', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'orderby', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'orderby', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'show_booking_link', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'show_booking_link', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'show_date', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'show_date', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'show_invoice_download', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'show_invoice_download', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'show_pagination', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'show_pagination', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'show_payment_method', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'show_payment_method', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'show_transaction_id', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'show_transaction_id', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_payment_history', 'key' => 'status', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_payment_history', 'key' => 'status', 'decision' => 'APPLY' ),

	// rentiva_testimonials.
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'autoplay', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'autoplay', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'category', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'category', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'limit_items', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'limit_items', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'orderby', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'orderby', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'show_author_avatar', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'show_author_avatar', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'show_author_name', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'show_author_name', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'show_quotes', 'decision' => 'APPLY' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'show_quotes', 'decision' => 'APPLY' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_testimonials', 'key' => 'show_vehicle_name', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_testimonials', 'key' => 'show_vehicle_name', 'decision' => 'REMOVE' ),

	// rentiva_vehicle_rating_form.
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'max_photos', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'max_photos', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'min_review_length', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'min_review_length', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'require_booking', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'require_booking', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'require_login', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'require_login', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_category_ratings', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_category_ratings', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_photo_upload', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_photo_upload', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_recommend_toggle', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_recommend_toggle', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_star_rating', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_star_rating', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_text_review', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_text_review', 'decision' => 'REMOVE' ),
	array( 'surface' => 'block', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_vehicle_preview', 'decision' => 'REMOVE' ),
	array( 'surface' => 'elementor', 'tag' => 'rentiva_vehicle_rating_form', 'key' => 'show_vehicle_preview', 'decision' => 'REMOVE' ),

);
