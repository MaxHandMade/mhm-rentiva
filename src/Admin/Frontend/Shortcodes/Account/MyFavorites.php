<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Account;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;
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

	protected static function get_shortcode_tag(): string
	{
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string
	{
		// Reusing grid template for favorites
		return 'shortcodes/vehicles-grid';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'limit'                    => '12',
			'columns'                  => '3',
			// Spec 3A Task 7b: added (the saved order), name or price.
			'orderby'                  => 'added',
			'order'                    => 'DESC',
			'show_image'               => '1',
			'show_title'               => '1',
			'show_price'               => '1',
			'show_features'            => '1',
			'show_rating'              => '1',
			'show_booking_button'      => '1',
			'show_favorite_button'     => '1',
			'show_availability_status' => '0',
			'layout'                   => 'grid',
			// Empty: the grid template's own empty state, as today (Rule 1).
			'no_results_text'          => '',
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		$user_id = get_current_user_id();

		// 1. Get Favorite IDs
		$favorite_ids = FavoritesService::get_user_favorites($user_id);

		$vehicles = array();

		if (! empty($favorite_ids)) {
			$mode  = self::sort_mode( (string) ( $atts['orderby'] ?? 'added' ));
			$order = 'ASC' === strtoupper( (string) ( $atts['order'] ?? 'DESC' )) ? 'ASC' : 'DESC';
			$limit = (int) ( $atts['limit'] ?? 12 );

			// 2. Query Vehicles: the saved order (post__in) unless sorted by name.
			$args = array(
				'post_type'      => PT_Vehicle::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'post__in'       => $favorite_ids,
				'orderby'        => 'post__in',
			);
			if ('name' === $mode) {
				$args['orderby'] = 'title';
				$args['order']   = $order;
			} elseif ('price' === $mode) {
				// The saved list is small; sorted in PHP so a vehicle without a
				// price is kept (Review Focus 1), then cut to the limit.
				$args['posts_per_page'] = -1;
			}

			$vehicle_posts = ( new WP_Query($args) )->posts;
			if ('price' === $mode) {
				$vehicle_posts = self::sort_by_price($vehicle_posts, $order);
				if ($limit > 0) {
					$vehicle_posts = array_slice($vehicle_posts, 0, $limit);
				}
			}

			foreach ($vehicle_posts as $vehicle_post) {
				// The global post, as the_post() set it before (card helpers may read it).
				$GLOBALS['post'] = $vehicle_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored by wp_reset_postdata() below.
				setup_postdata($vehicle_post);
				// Use reusable helper from VehiclesList to ensure consistency
				$vehicles[] = VehiclesList::get_vehicle_data_for_shortcode( (int) $vehicle_post->ID, $atts);
			}
			wp_reset_postdata();
		}

		$layout_class = ( ( $atts['layout'] ?? 'grid' ) === 'masonry' ) ? 'rv-vehicles-masonry' : 'rv-vehicles-grid';
		// The card layout (block/Elementor: grid | list); anything else is grid.
		$card_layout = 'list' === ( $atts['layout'] ?? 'grid' ) ? 'list' : 'grid';
		// A list card takes the whole row: one column.
		$columns = 'list' === $card_layout ? 1 : ( $atts['columns'] ?? 3 );

		return array(
			'atts'            => $atts,
			'vehicles'        => $vehicles,
			'total_vehicles'  => count($vehicles),
			'has_vehicles'    => ! empty($vehicles),
			'layout_class'    => $layout_class,
			'columns_class'   => $layout_class . '--columns-' . $columns,
			'card_layout'     => $card_layout,
			'wrapper_class'   => 'mhm-rentiva-my-favorites mhm-my-favorites-container rv-my-favorites-wrapper ' . ( $atts['class'] ?? '' ),
			'no_results_text' => (string) ( $atts['no_results_text'] ?? '' ),
		);
	}

	/**
	 * The sort a saved `orderby` asks for: added (the saved order), name or price.
	 * 'date' and 'title' are the values the Elementor control saved before
	 * (spec 3A Task 7b); anything else is the saved order.
	 */
	public static function sort_mode(string $orderby): string
	{
		$map = array(
			'name'  => 'name',
			'title' => 'name',
			'price' => 'price',
		);

		return $map[ $orderby ] ?? 'added';
	}

	/**
	 * Vehicles by daily price; a vehicle without a price goes last in either
	 * order. "Price" is the house's (VehicleDataHelper::get_price_per_day(): a
	 * positive daily price; missing, empty or 0 is none). Stable: equal prices,
	 * and the unpriced ones, keep the saved order.
	 *
	 * @param array<int,\WP_Post> $vehicle_posts Vehicles in saved order.
	 * @return array<int,\WP_Post>
	 */
	private static function sort_by_price(array $vehicle_posts, string $order): array
	{
		$sign  = 'ASC' === $order ? 1 : -1;
		$price = static function (\WP_Post $vehicle): ?float {
			$daily = VehicleDataHelper::get_price_per_day( (int) $vehicle->ID);
			return $daily > 0 ? $daily : null;
		};
		usort(
			$vehicle_posts,
			static function (\WP_Post $a, \WP_Post $b) use ($price, $sign): int {
				$pa = $price($a);
				$pb = $price($b);
				if (null === $pa || null === $pb) {
					return ( null === $pa ) <=> ( null === $pb );
				}
				return $sign * ( $pa <=> $pb );
			}
		);

		return $vehicle_posts;
	}

	protected static function get_css_files(array $atts = array()): array
	{
		return array( 'assets/css/frontend/vehicles-grid.css' );
	}

	protected static function get_js_files(array $atts = array()): array
	{
		return array( 'assets/js/frontend/vehicles-grid.js' );
	}

	protected static function get_js_dependencies(): array
	{
		// Reuses vehicles-grid.js, which calls MHMRentivaToast.* directly --
		// declared here even though mhm-rentiva-vehicle-interactions already
		// pulls toast in transitively, so this handle stays correct even if
		// that transitive chain ever changes.
		return array( 'jquery', 'mhm-rentiva-vehicle-interactions', 'mhm-rentiva-toast' );
	}
}
