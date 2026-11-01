<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Services;

use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Vehicle\Helpers\VehicleDataHelper;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compare Service
 *
 * Handles logic for vehicle comparison list.
 * Supports both User Meta (for logged-in users) and Cookies (for guests).
 *
 * @since 1.3.3
 */
class CompareService {

	/**
	 * Meta key/Cookie name
	 */
	private const STORAGE_KEY = 'mhmrentiva_compare';

	/**
	 * Max items in compare list
	 */
	private const MAX_ITEMS = 3;

	/**
	 * Cookie expiry (30 days)
	 */
	private const COOKIE_EXPIRY = 2592000;

	/**
	 * Static cache for current request
	 */
	private static ?array $cached_list = null;

	/**
	 * Register service actions
	 */
	public static function register(): void {
		add_action( 'wp_ajax_mhmrentiva_toggle_compare', array( self::class, 'ajax_toggle_compare' ) );
		add_action( 'wp_ajax_nopriv_mhmrentiva_toggle_compare', array( self::class, 'ajax_toggle_compare' ) );
	}

	/**
	 * Get compare list: the stored vehicles a visitor may still compare. The rest
	 * is only left out here, in memory, so rendering a page writes nothing (a
	 * cookie cannot be set once output has begun); add() stores the clean list.
	 *
	 * @return array<int> list of vehicle IDs
	 */
	public static function get_list(): array {
		if ( self::$cached_list !== null ) {
			return self::$cached_list;
		}

		$list = array();

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$meta    = get_user_meta( $user_id, self::STORAGE_KEY, true );
			if ( ! empty( $meta ) && is_array( $meta ) ) {
				$list = $meta;
			}
		} else {
			// DIRECT READ: filter_input(INPUT_COOKIE) is often unreliable in local dev/CGI environments.
			$cookie_val = isset( $_COOKIE[ self::STORAGE_KEY ] )
				? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::STORAGE_KEY ] ) )
				: '';

			if ( $cookie_val ) {
				// Try JSON decode directly
				$decoded = json_decode( wp_unslash( (string) $cookie_val ), true );
				if ( is_array( $decoded ) ) {
					$list = $decoded;
				}
			}
		}

		// A closed vehicle gives its seat to the next one, so filter before limiting.
		// A stored list never exceeds MAX_ITEMS (save_list); a guest's cookie can, and
		// each id costs a read, so the walk has a ceiling too.
		$comparable = array();
		foreach ( array_slice( array_values( array_unique( array_map( 'intval', $list ) ) ), 0, 2 * self::MAX_ITEMS ) as $vehicle_id ) {
			if ( self::is_comparable( $vehicle_id ) ) {
				$comparable[] = $vehicle_id;
				if ( count( $comparable ) >= self::MAX_ITEMS ) {
					break;
				}
			}
		}

		self::$cached_list = $comparable;
		return self::$cached_list;
	}

	/**
	 * Whether a vehicle can be in a comparison: published, publicly readable, in
	 * service and offered for rental.
	 *
	 * @param int $vehicle_id
	 * @return bool
	 */
	public static function is_comparable( int $vehicle_id ): bool {
		return $vehicle_id > 0
			&& VehicleDataHelper::is_publicly_readable( $vehicle_id )
			&& 'publish' === get_post_status( $vehicle_id )
			&& 'active' === VehicleDataHelper::get_status( $vehicle_id )
			&& VehicleDataHelper::allows_service( $vehicle_id, VehicleDataHelper::SERVICE_RENTAL );
	}

	/**
	 * Save compare list
	 *
	 * @param array $list
	 * @return bool
	 */
	private static function save_list( array $list ): bool {
		// Enforce limit
		if ( count( $list ) > self::MAX_ITEMS ) {
			$list = array_slice( $list, 0, self::MAX_ITEMS );
		}

		$list = array_values( array_unique( array_map( 'intval', $list ) ) );

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			update_user_meta( $user_id, self::STORAGE_KEY, $list );
		} else {
			// Set cookie for guest
			$json = wp_json_encode( $list );

			// Update $_COOKIE for current request consistency
			$_COOKIE[ self::STORAGE_KEY ] = $json;

			// Use fallbacks for environment constants
			$path   = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
			$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
			$secure = is_ssl();
			$expiry = time() + self::COOKIE_EXPIRY;

			// Set primary cookie
			setcookie( self::STORAGE_KEY, $json, $expiry, $path, $domain, $secure, true );

			// Set for site path if different (e.g. multi-site or subdirectory configs)
			if ( defined( 'SITECOOKIEPATH' ) && SITECOOKIEPATH !== $path ) {
				setcookie( self::STORAGE_KEY, $json, $expiry, SITECOOKIEPATH, $domain, $secure, true );
			}
		}

		// Sync static cache
		self::$cached_list = $list;

		return true;
	}

	/**
	 * Add to compare
	 *
	 * @param int $vehicle_id
	 * @return bool
	 * @throws \Exception if max limit reached or the vehicle cannot be compared
	 */
	public static function add( int $vehicle_id ): bool {
		if ( $vehicle_id <= 0 ) {
			return false;
		}

		$list = self::get_list();

		if ( in_array( $vehicle_id, $list, true ) ) {
			return true; // Already added
		}

		// get_list() would drop it: "added" would be untrue, and it could never be removed.
		if ( ! self::is_comparable( $vehicle_id ) ) {
			throw new \Exception( esc_html__( 'This vehicle cannot be compared.', 'mhm-rentiva' ) );
		}

		if ( count( $list ) >= self::MAX_ITEMS ) {
			$max_compare_items = (int) self::MAX_ITEMS;
			/* translators: %d: maximum number of vehicles that can be compared. */
			throw new \Exception( esc_html( sprintf( __( 'You can compare up to %d vehicles.', 'mhm-rentiva' ), $max_compare_items ) ) );
		}

		$list[] = $vehicle_id;
		return self::save_list( $list );
	}

	/**
	 * Remove from compare
	 *
	 * @param int $vehicle_id
	 * @return bool
	 */
	public static function remove( int $vehicle_id ): bool {
		$list = self::get_list();
		$key  = array_search( $vehicle_id, $list, true );

		if ( $key === false ) {
			return true; // Already removed
		}

		unset( $list[ $key ] );
		return self::save_list( $list );
	}

	/**
	 * Check if in compare list
	 *
	 * @param int $vehicle_id
	 * @return bool
	 */
	public static function is_in_compare( int $vehicle_id ): bool {
		return in_array( $vehicle_id, self::get_list(), true );
	}

	/**
	 * How many vehicles the comparison holds at most.
	 */
	public static function max_items(): int {
		return self::MAX_ITEMS;
	}

	/**
	 * The compared vehicles with their titles (the results page's band).
	 *
	 * @return list<array{id: int, title: string}>
	 */
	public static function items(): array {
		$items = array();
		foreach ( self::get_list() as $id ) {
			// A guest's list is a cookie: only published vehicles are named, as
			// VehicleComparison shows them -- never a draft's or another type's title.
			$post = get_post( (int) $id );
			if ( ! $post instanceof \WP_Post || 'mhmrentiva_vehicle' !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			// Plain text: the_title's texturized entities (&#8217; …) decoded too.
			$title = html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( '' !== $title ) {
				$items[] = array(
					'id'    => (int) $post->ID,
					'title' => $title,
				);
			}
		}

		return $items;
	}

	/**
	 * The submitted toggle request, or null when it carries no nonce that
	 * authorises it.
	 *
	 * The compare toggle is reachable from the vehicle card and from the
	 * vehicles list screen, which mint different nonces; either is accepted, one
	 * early return each. The verified payload travels back with the verdict so
	 * the nonce check and the superglobal access stay in one scope.
	 */
	private static function verified_toggle_request(): ?VerifiedRequest {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

		if ( wp_verify_nonce( $nonce, 'mhmrentiva_toggle_compare' ) ) {
			return VerifiedRequest::from( $_POST );
		}
		if ( wp_verify_nonce( $nonce, 'mhmrentiva_vehicles_list' ) ) {
			return VerifiedRequest::from( $_POST );
		}

		return null;
	}

	/**
	 * AJAX: Toggle Compare
	 */
	public static function ajax_toggle_compare(): void {
		try {
			// 1. Verify Nonce
			$request = self::verified_toggle_request();
			if ( null === $request ) {
				throw new \Exception( __( 'Security check failed', 'mhm-rentiva' ) );
			}

			// 2. Input
			$vehicle_id = $request->int( 'vehicle_id' );
			if ( $vehicle_id <= 0 ) {
				throw new \Exception( __( 'Invalid vehicle ID', 'mhm-rentiva' ) );
			}

			// 3. Logic
			$action  = '';
			$message = '';
			$list    = self::get_list();

			if ( in_array( $vehicle_id, $list, true ) ) {
				self::remove( $vehicle_id );
				$action  = 'removed';
				$message = __( 'Removed from comparison', 'mhm-rentiva' );
			} else {
				self::add( $vehicle_id );
				$action  = 'added';
				$message = __( 'Added to comparison', 'mhm-rentiva' );
			}

			// 4. Response
			wp_send_json_success(
				array(
					'vehicle_id'    => $vehicle_id,
					'action'        => $action,
					'message'       => $message,
					'is_in_compare' => ( $action === 'added' ),
					'count'         => count( self::get_list() ),
					// The results page's comparison band redraws from these.
					'items'         => self::items(),
					'max'           => self::MAX_ITEMS,
				)
			);
		} catch ( \Exception $e ) {
			// Rethrow test exception
			if ( strpos( get_class( $e ), 'WPAjaxDie' ) !== false ) {
				throw $e;
			}
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
}
