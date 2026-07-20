<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\ShortcodePages;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\ShortcodeUrlManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode Page Actions (Business Logic)
 */
final class ShortcodePageActions {


	/**
	 * Get shortcode configurations, minus any this build cannot render.
	 *
	 * This is the second registry that has to respect the Lite/add-on extension boundary --
	 * BlockRegistry was the first. An unregistered shortcode does not vanish, it
	 * degrades to its own literal source text, so offering a page for one is not a
	 * cosmetic fault: create_page() publishes the raw shortcode as page content
	 * and the visitor reads "[rentiva_vendor_apply]" on a live page.
	 *
	 * Availability is probed with shortcode_exists() rather than a second
	 * hardcoded list of add-on tags, deliberately. ShortcodeServiceProvider already
	 * drops absent add-on extension points from the one real registry, so asking that registry
	 * what exists keeps this list honest automatically -- a duplicated tag list
	 * here would be free to rot out of sync, which is precisely how this defect
	 * survived the carve.
	 *
	 * Timing is safe: ShortcodeServiceProvider::register() registers immediately
	 * at plugin load, while every consumer of this method (the REST controller and
	 * the admin page) runs later, in an admin or REST request.
	 */
	public function get_config(): array {
		$config = array(
			'rentiva_my_bookings'           => array(
				'title'       => __( 'My Bookings', 'mhm-rentiva' ),
				'slug'        => 'my-bookings',
				'description' => __( 'All user bookings', 'mhm-rentiva' ),
			),
			'rentiva_my_favorites'          => array(
				'title'       => __( 'My Favorites', 'mhm-rentiva' ),
				'slug'        => 'my-favorites',
				'description' => __( 'User favorite vehicles', 'mhm-rentiva' ),
			),
			'rentiva_payment_history'       => array(
				'title'       => __( 'Payment History', 'mhm-rentiva' ),
				'slug'        => 'payment-history',
				'description' => __( 'User payment history', 'mhm-rentiva' ),
			),

			'rentiva_booking_form'          => array(
				'title'       => __( 'Booking Form', 'mhm-rentiva' ),
				'slug'        => 'booking-form',
				'description' => __( 'Detailed booking form - with all booking options', 'mhm-rentiva' ),
			),
			'rentiva_unified_search'        => array(
				'title'       => __( 'Unified Search', 'mhm-rentiva' ),
				'slug'        => 'unified-search',
				// Describes what this build renders: UnifiedSearch hard-gates the
				// transfer tab off when the Transfer seam is absent, so promising
				// transfer search here would describe a tab the user never sees.
				'description' => __( 'Vehicle search widget - date, location and category filters', 'mhm-rentiva' ),
			),
			'rentiva_search_results'        => array(
				'title'       => __( 'Search Results', 'mhm-rentiva' ),
				'slug'        => 'search-results',
				'description' => __( 'Vehicle search results page - detailed results with sidebar filters', 'mhm-rentiva' ),
			),
			'rentiva_vehicle_comparison'    => array(
				'title'       => __( 'Vehicle Comparison', 'mhm-rentiva' ),
				'slug'        => 'vehicle-comparison',
				'description' => __( 'Vehicle comparison page - multiple vehicles can be compared', 'mhm-rentiva' ),
			),
			'rentiva_testimonials'          => array(
				'title'       => __( 'Customer Reviews', 'mhm-rentiva' ),
				'slug'        => 'customer-reviews',
				'description' => __( 'Customer reviews and ratings', 'mhm-rentiva' ),
			),
			'rentiva_availability_calendar' => array(
				'title'       => __( 'Availability Calendar', 'mhm-rentiva' ),
				'slug'        => 'availability-calendar',
				'description' => __( 'Vehicle availability calendar - which vehicles are available on which dates', 'mhm-rentiva' ),
			),
			'rentiva_vehicle_details'       => array(
				'title'       => __( 'Vehicle Details', 'mhm-rentiva' ),
				'slug'        => 'vehicle-details',
				'description' => __( 'Single vehicle details page - shows vehicle information, images and booking form', 'mhm-rentiva' ),
			),
			'rentiva_vehicles_grid'         => array(
				'title'       => __( 'Vehicles Grid', 'mhm-rentiva' ),
				'slug'        => 'vehicles-grid',
				'description' => __( 'Vehicles displayed in grid layout - multiple vehicles in grid format', 'mhm-rentiva' ),
			),
			'rentiva_vehicles_list'         => array(
				'title'       => __( 'Vehicles List', 'mhm-rentiva' ),
				'slug'        => 'vehicles-list',
				'description' => __( 'Vehicles displayed in list layout - multiple vehicles in list format', 'mhm-rentiva' ),
			),
			'rentiva_contact'               => array(
				'title'       => __( 'Contact Form', 'mhm-rentiva' ),
				'slug'        => 'contact-form',
				'description' => __( 'Contact form page - customers can send messages to admin', 'mhm-rentiva' ),
			),
			'rentiva_messages'              => array(
				'title'       => __( 'Messages', 'mhm-rentiva' ),
				'slug'        => 'my-messages',
				'description' => __( 'User messages and notifications', 'mhm-rentiva' ),
			),
			'rentiva_vehicle_rating_form'   => array(
				'title'       => __( 'Vehicle Rating Form', 'mhm-rentiva' ),
				'slug'        => 'vehicle-rating-form',
				'description' => __( 'Vehicle rating and review form - customers can rate and review vehicles', 'mhm-rentiva' ),
			),
			'rentiva_transfer_search'       => array(
				'title'       => __( 'Transfer Search', 'mhm-rentiva' ),
				'slug'        => 'transfer-search',
				'description' => __( 'VIP transfer booking search form - airport and point-to-point transfers', 'mhm-rentiva' ),
			),
			'rentiva_transfer_results'      => array(
				'title'       => __( 'Transfer Results', 'mhm-rentiva' ),
				'slug'        => 'transfer-results',
				'description' => __( 'Transfer search results page - displays available transfer options', 'mhm-rentiva' ),
			),
			'rentiva_featured_vehicles'     => array(
				'title'       => __( 'Featured Vehicles', 'mhm-rentiva' ),
				'slug'        => 'featured-vehicles',
				'description' => __( 'Featured vehicles showcase - highlights premium or recommended vehicles', 'mhm-rentiva' ),
			),
			'rentiva_vendor_apply'          => array(
				'title'       => __( 'Vendor Application', 'mhm-rentiva' ),
				'slug'        => 'vendor-apply',
				'description' => __( 'Vendor application form - apply to become a vehicle rental vendor', 'mhm-rentiva' ),
			),
			'rentiva_vehicle_submit'        => array(
				'title'       => __( 'Vehicle Submission', 'mhm-rentiva' ),
				'slug'        => 'vehicle-submit',
				'description' => __( 'Vendor vehicle submission form - vendors can add their vehicles', 'mhm-rentiva' ),
			),
			'rentiva_vendor_directory'      => array(
				'title'       => __( 'Vendor Directory', 'mhm-rentiva' ),
				'slug'        => 'demo-vendor-directory',
				'description' => __( 'Public directory of all active vendors - searchable and filterable list', 'mhm-rentiva' ),
			),
			'rentiva_vendor_profile'        => array(
				'title'       => __( 'Vendor Profile', 'mhm-rentiva' ),
				'slug'        => 'demo-vendor-profile',
				'description' => __( 'Public vendor profile page - shows vendor vehicles, reviews and contact info', 'mhm-rentiva' ),
			),
			'rentiva_vendor_bookings'       => array(
				'title'       => __( 'Vendor Bookings', 'mhm-rentiva' ),
				'slug'        => 'demo-vendor-bookings',
				'description' => __( 'Vendor booking management - incoming and completed reservations', 'mhm-rentiva' ),
			),
			'rentiva_vendor_ledger'         => array(
				'title'       => __( 'Vendor Ledger', 'mhm-rentiva' ),
				'slug'        => 'demo-vendor-ledger',
				'description' => __( 'Vendor earnings ledger - commission history and net payout balance', 'mhm-rentiva' ),
			),
			'rentiva_user_dashboard'        => array(
				'title'       => __( 'User Dashboard', 'mhm-rentiva' ),
				'slug'        => 'demo-user-dashboard',
				// The dashboard's vendor branch is unreachable in this build -- only
				// the Pro onboarding flow grants the rentiva_vendor role -- so the
				// customer summary is the only thing this description can promise.
				'description' => __( 'Customer dashboard - booking, favorite and account summary', 'mhm-rentiva' ),
			),
			'rentiva_popular_routes'        => array(
				'title'       => __( 'Popular Routes', 'mhm-rentiva' ),
				'slug'        => 'demo-popular-routes',
				'description' => __( 'Most popular transfer routes - widget for home or transfer landing pages', 'mhm-rentiva' ),
			),
		);

		/*
		 * Ask the registry what it REGISTERED -- not WordPress what EXISTS.
		 *
		 * shortcode_exists() was the wrong question and shipped that way. When an
		 * extension is not active and its seam closes, ShortcodeServiceProvider drops the entry and then
		 * deliberately re-registers the tag as `__return_empty_string`, so that
		 * pages already carrying [rentiva_transfer_search] render nothing instead of
		 * printing their own raw source text at visitors. That silencing shim is a
		 * real registration, so shortcode_exists() answers YES for precisely the
		 * tags this build must NOT offer -- and the Shortcode Pages tool listed
		 * every closed add-on extension point as "Aktif", offering to create pages that could only
		 * ever render blank.
		 *
		 * get_registered_shortcodes() is populated only by process_registration(),
		 * i.e. only for seams that passed both the class check and the
		 * extension/registration check. The silencer bypasses it by design, which makes it the one list
		 * that means "this build can really render this".
		 *
		 * Still no duplicated tag list here: this defers to the same single registry
		 * as before, just via a question the silencer cannot answer falsely.
		 */
		$registered = \MHMRentiva\Admin\Core\ShortcodeServiceProvider::instance()
			->get_registered_shortcodes();

		$config = array_filter(
			$config,
			static function ( string $shortcode ) use ( $registered ): bool {
				return array_key_exists( $shortcode, $registered );
			},
			ARRAY_FILTER_USE_KEY
		);

		ksort( $config );
		return $config;
	}

	/**
	 * Create a page for a shortcode.
	 */
	public function create_page( string $shortcode ): ?int {
		$config = $this->get_config();
		$info   = $config[ $shortcode ] ?? null;
		if ( ! $info ) {
			return null;
		}

		$content = $this->get_shortcode_markup( $shortcode );

		$page_id = wp_insert_post(
			array(
				'post_title'   => $info['title'],
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
				'post_name'    => sanitize_title( $info['slug'] ),
				'post_excerpt' => $info['description'],
			)
		);

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_mhm_shortcode', $shortcode );
			update_post_meta( $page_id, '_mhm_auto_created', true );
			return (int) $page_id;
		}

		return null;
	}

	/**
	 * Delete a shortcode page.
	 */
	public function delete_page( int $page_id ): bool {
		if ( $page_id <= 0 ) {
			return false;
		}

		$result = wp_trash_post( $page_id );
		if ( $result ) {
			ShortcodeUrlManager::clear_cache();
			return true;
		}

		return false;
	}

	/**
	 * Get shortcode markup.
	 */
	private function get_shortcode_markup( string $shortcode ): string {
		$markup = match ( $shortcode ) {
			'rentiva_vehicle_comparison'    => '[rentiva_vehicle_comparison vehicle_ids="1,2,3"]',
			'rentiva_availability_calendar' => '[rentiva_availability_calendar vehicle_id="1"]',
			'rentiva_vehicle_details'       => '[rentiva_vehicle_details]',
			'rentiva_vehicles_grid'         => '[rentiva_vehicles_grid columns="3" limit="12"]',
			'rentiva_vehicles_list'         => '[rentiva_vehicles_list limit="10"]',
			'rentiva_booking_form'          => '[rentiva_booking_form vehicle_id="1"]',
			'rentiva_vehicle_rating_form'   => '[rentiva_vehicle_rating_form vehicle_id="1"]',
			default                         => '[' . $shortcode . ']',
		};

		return sprintf( "<!-- wp:shortcode -->\n%s\n<!-- /wp:shortcode -->", $markup );
	}

	/**
	 * Reset all shortcode pages (Factory Reset).
	 * Deletes all pages and clears mappings.
	 *
	 * @return int Number of pages deleted.
	 */
	public function reset_pages(): int {
		$shortcodes    = array_keys( $this->get_config() );
		$settings      = get_option( 'mhm_rentiva_settings', array() );
		$deleted_count = 0;

		foreach ( $shortcodes as $sc ) {
			$page_id = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id( $sc );
			if ( $page_id ) {
				wp_delete_post( $page_id, true );
				++$deleted_count;
			}

			$setting_key = $this->get_setting_key_for_sc( $sc );
			if ( $setting_key && isset( $settings[ $setting_key ] ) ) {
				unset( $settings[ $setting_key ] );
			}
		}

		update_option( 'mhm_rentiva_settings', $settings );
		\MHMRentiva\Admin\Core\ShortcodeUrlManager::clear_cache();

		return $deleted_count;
	}

	/**
	 * Scan all published pages for shortcode usage.
	 *
	 * @return array{ scanned_pages: int, results: list<array{slug: string, label: string, found_in: list<array{page_id: int, page_title: string, page_url: string}>}> }
	 */
	// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded admin-only debug scan; no user-facing cache needed.
	public static function debug_search(): array {
		global $wpdb;

		$all_pages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_content FROM {$wpdb->posts}
				 WHERE post_type = %s
				 AND post_status = %s
				 AND post_content LIKE %s
				 ORDER BY post_date DESC",
				'page',
				'publish',
				'%[%'
			)
		);
		// phpcs:enable

		$config  = ( new self() )->get_config();
		$results = array();

		foreach ( $config as $slug => $info ) {
			$found_in = array();
			foreach ( $all_pages as $page ) {
				if ( preg_match( '/\[' . preg_quote( $slug, '/' ) . '(\]| |=)/', (string) $page->post_content ) ) {
					$found_in[] = array(
						'page_id'    => (int) $page->ID,
						'page_title' => esc_html( (string) $page->post_title ),
						'page_url'   => esc_url( (string) get_permalink( $page->ID ) ),
					);
				}
			}
			$results[] = array(
				'slug'     => $slug,
				'label'    => $info['title'],
				'found_in' => $found_in,
			);
		}

		return array(
			'scanned_pages' => count( $all_pages ),
			'results'       => $results,
		);
	}

	/**
	 * Helper to get setting key for shortcode (DRY from ShortcodeUrlManager)
	 */
	private function get_setting_key_for_sc( string $shortcode ): ?string {
		$mapping = array(
			'rentiva_booking_form'          => 'mhm_rentiva_booking_url',
			'rentiva_my_bookings'           => 'mhm_rentiva_my_bookings_url',
			'rentiva_my_favorites'          => 'mhm_rentiva_my_favorites_url',
			'rentiva_payment_history'       => 'mhm_rentiva_payment_history_url',
			'rentiva_messages'              => 'mhm_rentiva_messages_url',
			'rentiva_vehicles_list'         => 'mhm_rentiva_vehicles_list_url',
			'rentiva_vehicles_grid'         => 'mhm_rentiva_vehicles_grid_url',
			'rentiva_unified_search'        => 'mhm_rentiva_unified_search_url',
			'rentiva_search_results'        => 'mhm_rentiva_search_results_url',
			'rentiva_contact'               => 'mhm_rentiva_contact_url',
			'rentiva_availability_calendar' => 'mhm_rentiva_availability_calendar_url',
		);

		return $mapping[ $shortcode ] ?? null;
	}
}
