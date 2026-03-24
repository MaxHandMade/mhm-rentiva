<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Services;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Favorites Service
 *
 * Handles all logic for user favorites (vehicles).
 * Single Source of Truth for favorites storage (user_meta).
 *
 * @since 1.3.3
 */
class FavoritesService {

	/**
	 * Meta key for storing favorites
	 */
	private const META_KEY = 'mhm_rentiva_favorites';

	/**
	 * Register service
	 */
	public static function register(): void {
		add_action( 'wp_ajax_mhm_rentiva_toggle_favorite', array( self::class, 'ajax_toggle_favorite' ) );
		add_action( 'wp_ajax_nopriv_mhm_rentiva_toggle_favorite', array( self::class, 'ajax_toggle_favorite' ) );
	}

	/**
	 * Add vehicle to favorites
	 *
	 * @param int $user_id User ID
	 * @param int $vehicle_id Vehicle ID
	 * @return bool Success
	 */
	public static function add( int $user_id, int $vehicle_id ): bool {
		if ( $user_id <= 0 || $vehicle_id <= 0 ) {
			return false;
		}

		$favorites = self::get_user_favorites( $user_id );
		if ( in_array( $vehicle_id, $favorites, true ) ) {
			return true; // Already favorite
		}

		$favorites[] = $vehicle_id;
		return (bool) update_user_meta( $user_id, self::META_KEY, array_values( array_unique( $favorites ) ) );
	}

	/**
	 * Remove vehicle from favorites
	 *
	 * @param int $user_id User ID
	 * @param int $vehicle_id Vehicle ID
	 * @return bool Success
	 */
	public static function remove( int $user_id, int $vehicle_id ): bool {
		if ( $user_id <= 0 || $vehicle_id <= 0 ) {
			return false;
		}

		$favorites = self::get_user_favorites( $user_id );
		$key       = array_search( $vehicle_id, $favorites, true );

		if ( $key === false ) {
			return true; // Already not favorite
		}

		unset( $favorites[ $key ] );
		return (bool) update_user_meta( $user_id, self::META_KEY, array_values( $favorites ) );
	}

	/**
	 * Get user favorites
	 *
	 * @param int $user_id User ID
	 * @return array<int> Array of vehicle IDs
	 */
	public static function get_user_favorites( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$favorites = get_user_meta( $user_id, self::META_KEY, true );

		if ( empty( $favorites ) || ! is_array( $favorites ) ) {
			return array();
		}

		return array_map( 'intval', $favorites );
	}

	/**
	 * Check if is favorite
	 *
	 * @param int $user_id User ID
	 * @param int $vehicle_id Vehicle ID
	 * @return bool
	 */
	public static function is_favorite( int $user_id, int $vehicle_id ): bool {
		if ( $user_id <= 0 || $vehicle_id <= 0 ) {
			return false;
		}

		$favorites = self::get_user_favorites( $user_id );
		return in_array( $vehicle_id, $favorites, true );
	}

	/**
	 * AJAX Handler for toggling favorite
	 */
	public static function ajax_toggle_favorite(): void {
		try {
			// 1. Verify Nonce
			// Accept either standard toggle nonce OR global booking nonce fallback (for flexibility)
			$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
			if ( empty( $nonce ) || (
				! wp_verify_nonce( $nonce, 'mhm_rentiva_toggle_favorite' ) &&
				! wp_verify_nonce( $nonce, 'mhm_rentiva_vehicles_list' )
			) ) {
				throw new \Exception( __( 'Security check failed', 'mhm-rentiva' ) );
			}

			// 2. Auth Check
			if ( ! is_user_logged_in() ) {
				throw new \Exception( __( 'You must be logged in to add to favorites', 'mhm-rentiva' ) );
			}

			// 3. Input Validation
			$vehicle_id = isset( $_POST['vehicle_id'] ) ? intval( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
			if ( $vehicle_id <= 0 ) {
				throw new \Exception( __( 'Invalid vehicle ID', 'mhm-rentiva' ) );
			}

			$user_id = get_current_user_id();
			$action  = '';
			$message = '';

			// 4. Logic
			if ( self::is_favorite( $user_id, $vehicle_id ) ) {
				self::remove( $user_id, $vehicle_id );
				$action  = 'removed';
				$message = __( 'Removed from favorites', 'mhm-rentiva' );
			} else {
				self::add( $user_id, $vehicle_id );
				$action  = 'added';
				$message = __( 'Added to favorites', 'mhm-rentiva' );
			}

			// 5. Response
			wp_send_json_success(
				array(
					'vehicle_id'  => $vehicle_id,
					'action'      => $action,
					'message'     => $message,
					'is_favorite' => ( $action === 'added' ),
					'count'       => count( self::get_user_favorites( $user_id ) ),
				)
			);
		} catch ( \Exception $e ) {
			// In testing environment, wp_die throws an exception which is caught here.
			// We must rethrow it to allow the test to finish cleanly.
			if ( strpos( get_class( $e ), 'WPAjaxDie' ) !== false ) {
				throw $e;
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}
}
