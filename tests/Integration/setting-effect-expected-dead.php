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
