<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use WP_Query;
use MHMRentiva\Admin\Vehicle\PostType\Vehicle as PT_Vehicle;

/**
 * My Favorites Shortcode
 *
 * Displays the current user's favorite vehicles.
 * [rentiva_my_favorites]
 */
class MyFavorites extends AbstractShortcode {

	public const SHORTCODE = 'rentiva_my_favorites';

	protected static function get_shortcode_tag(): string {
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string {
		// Reusing grid template for favorites
		return 'shortcodes/vehicles-grid';
	}

	protected static function get_default_attributes(): array {
		return array(
			'limit'             => '12',
			'columns'           => '3',
			'orderby'           => 'date',
			'order'             => 'DESC',
			'show_image'        => '1',
			'show_title'        => '1',
			'show_price'        => '1',
			'show_features'     => '1',
			'show_rating'       => '1',
			'show_booking_btn'  => '1',
			'show_favorite_btn' => '1',
			'show_badges'       => '1',
			'layout'            => 'grid',
			'no_results_text'   => __( 'You have no favorite vehicles yet.', 'mhm-rentiva' ),
		);
	}

	protected static function prepare_template_data( array $atts ): array {
		$user_id = get_current_user_id();

		// 1. Get Favorite IDs
		$favorite_ids = FavoritesService::get_user_favorites( $user_id );

		$vehicles = array();

		if ( empty( $favorite_ids ) ) {
			// No favorites
		} else {
			// 2. Query Vehicles
			$args = array(
				'post_type'      => PT_Vehicle::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $atts['limit'],
				'post__in'       => $favorite_ids,
				'orderby'        => 'post__in', // Keep order or use atts?
			);

			// If user explicitly asks for sort, use it. Otherwise keep post__in (added order)
			if ( isset( $atts['orderby'] ) && $atts['orderby'] !== 'date' ) {
				$args['orderby'] = $atts['orderby'];
				$args['order']   = $atts['order'];
			}

			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					// Use reusable helper from VehiclesList to ensure consistency
					$vehicles[] = VehiclesList::get_vehicle_data_for_shortcode( get_the_ID(), $atts );
				}
				wp_reset_postdata();
			}
		}

		$layout_class = ( $atts['layout'] === 'masonry' ) ? 'rv-vehicles-masonry' : 'rv-vehicles-grid';

		// Override 'no_vehicles' message if needed via template logic or pass it specifically
		// But vehicles-grid.php usually has generic "No vehicles found".
		// We might need to handle empty state in template or pass a custom message.
		// The grid template uses $i18n['no_vehicles'] usually.

		return array(
			'atts'            => $atts,
			'vehicles'        => $vehicles,
			'total_vehicles'  => count( $vehicles ),
			'has_vehicles'    => ! empty( $vehicles ),
			'layout_class'    => $layout_class,
			'columns_class'   => $layout_class . '--columns-' . $atts['columns'],
			'wrapper_class'   => 'mhm-my-favorites-container rv-my-favorites-wrapper ' . ( $atts['class'] ?? '' ),
			'no_results_text' => $atts['no_results_text'],
		);
	}

	protected static function get_css_files( array $atts = array() ): array {
		return array( 'assets/css/frontend/vehicles-grid.css' );
	}

	protected static function get_js_files( array $atts = array() ): array {
		return array( 'assets/js/frontend/vehicles-grid.js' );
	}

	protected static function get_js_dependencies(): array {
		return array( 'jquery', 'mhm-vehicle-interactions' );
	}
}
