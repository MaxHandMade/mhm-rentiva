<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\ShortcodePages;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode Page Actions (Business Logic)
 */
final class ShortcodePageActions {


	/**
	 * Get shortcode configurations.
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
				'description' => __( 'Unified vehicle and transfer search widget', 'mhm-rentiva' ),
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
			'rentiva_booking_confirmation'  => array(
				'title'       => __( 'Booking Confirmation', 'mhm-rentiva' ),
				'slug'        => 'booking-confirmation',
				'description' => __( 'Booking confirmation page - shows booking details and payment status', 'mhm-rentiva' ),
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
			'rentiva_vehicle_details'       => '[rentiva_vehicle_details vehicle_id="1"]',
			'rentiva_vehicles_grid'         => '[rentiva_vehicles_grid columns="3" limit="12"]',
			'rentiva_vehicles_list'         => '[rentiva_vehicles_list limit="10"]',
			'rentiva_booking_form'          => '[rentiva_booking_form vehicle_id="1"]',
			'rentiva_booking_confirmation'  => '[rentiva_booking_confirmation booking_id="1"]',
			'rentiva_vehicle_rating_form'   => '[rentiva_vehicle_rating_form vehicle_id="1"]',
			default                         => '[' . $shortcode . ']',
		};

		return sprintf( "<!-- wp:shortcode -->\n%s\n<!-- /wp:shortcode -->", $markup );
	}

	/**
	 * Reset all shortcode pages (Factory Reset).
	 * Deletes all pages and clears mappings.
	 */
	public function reset_pages(): bool {
		$shortcodes = array_keys( $this->get_config() );
		$settings   = get_option( 'mhm_rentiva_settings', array() );
		$changed    = false;

		foreach ( $shortcodes as $sc ) {
			$page_id = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_id( $sc );
			if ( $page_id ) {
				// Permanently delete post (bypass trash as requested by "Factory Reset" context)
				wp_delete_post( $page_id, true );
				$changed = true;
			}

			// Also check for manual URL overrides in settings
			$setting_key = $this->get_setting_key_for_sc( $sc );
			if ( $setting_key && isset( $settings[ $setting_key ] ) ) {
				unset( $settings[ $setting_key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( 'mhm_rentiva_settings', $settings );
			\MHMRentiva\Admin\Core\ShortcodeUrlManager::clear_cache();
		}

		return true;
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
			'rentiva_account_details'       => 'mhm_rentiva_account_details_url',
			'rentiva_vehicles_list'         => 'mhm_rentiva_vehicles_list_url',
			'rentiva_vehicles_grid'         => 'mhm_rentiva_vehicles_grid_url',
			'rentiva_unified_search'        => 'mhm_rentiva_unified_search_url',
			'rentiva_search_results'        => 'mhm_rentiva_search_results_url',
			'rentiva_contact'               => 'mhm_rentiva_contact_url',
			'rentiva_availability_calendar' => 'mhm_rentiva_availability_calendar_url',
			'rentiva_booking_confirmation'  => 'mhm_rentiva_booking_confirmation_url',
		);

		return $mapping[ $shortcode ] ?? null;
	}
}
