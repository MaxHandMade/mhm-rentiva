<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value,WordPress.DB.SlowDBQuery.slow_db_query_tax_query,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Verified review helper intentionally performs bounded booking/review checks.



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Booking\Core\Status;

/**
 * Class VerifiedReviewHelper
 *
 * Determines whether a vehicle review is "verified" — meaning the review author
 * has a qualifying booking (confirmed/in_progress/completed) for the reviewed vehicle.
 *
 * Batch method prevents N+1 queries when rendering review lists.
 * Per-vehicle transient cache (1-hour TTL) for performance.
 *
 * @package MHMRentiva\Admin\Vehicle\Helpers
 * @since 1.3.0
 */
class VerifiedReviewHelper {

	/**
	 * Transient key prefix for verified review IDs cache.
	 *
	 * @var string
	 */
	private const CACHE_PREFIX = 'mhm_rentiva_verified_reviews_';

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Booking statuses that qualify for "verified" badge.
	 *
	 * @var array<string>
	 */
	private const VALID_STATUSES = array(
		Status::CONFIRMED,
		Status::IN_PROGRESS,
		Status::COMPLETED,
	);

	/**
	 * Register hooks for cache invalidation on booking status changes.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'mhm_rentiva_booking_status_changed',
			array( self::class, 'on_booking_status_changed' ),
			10,
			3
		);
	}

	/**
	 * Check if a single review (comment) is verified.
	 *
	 * Checks admin override first, then booking match.
	 *
	 * @since 1.3.0
	 * @param int $comment_id Comment ID.
	 * @param int $vehicle_id Vehicle ID.
	 * @param int $user_id    User ID of the review author (0 for guests).
	 * @return bool
	 */
	public static function is_verified( int $comment_id, int $vehicle_id, int $user_id ): bool {
		// 1. Admin override: comment meta mhm_verified_review = 1
		$override = get_comment_meta( $comment_id, 'mhm_verified_review', true );
		if ( $override === '1' || $override === 1 ) {
			return true;
		}

		// 2. Batch check (uses cache)
		$verified_ids = self::get_verified_comment_ids_for_vehicle( $vehicle_id );

		return in_array( $comment_id, $verified_ids, true );
	}

	/**
	 * Batch method: get all verified comment IDs for a vehicle.
	 *
	 * This is the primary method used in templates to avoid N+1 queries.
	 * Results are cached per-vehicle with a 1-hour TTL.
	 *
	 * @since 1.3.0
	 * @param int $vehicle_id Vehicle ID.
	 * @return array<int> List of verified comment IDs.
	 */
	public static function get_verified_comment_ids_for_vehicle( int $vehicle_id ): array {
		if ( $vehicle_id <= 0 ) {
			return array();
		}

		// Check transient cache
		$cache_key = self::CACHE_PREFIX . $vehicle_id;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		// Build verified IDs
		$verified_ids = self::query_verified_comment_ids( $vehicle_id );

		// Store in transient
		set_transient( $cache_key, $verified_ids, self::CACHE_TTL );

		return $verified_ids;
	}

	/**
	 * Query verified comment IDs for a vehicle.
	 *
	 * Single optimized query: finds all approved review comments for this vehicle
	 * whose authors have a qualifying booking.
	 *
	 * @since 1.3.0
	 * @param int $vehicle_id Vehicle ID.
	 * @return array<int> List of verified comment IDs.
	 */
	private static function query_verified_comment_ids( int $vehicle_id ): array {
		global $wpdb;

		// Step 1: Get all approved review comments for this vehicle
		$comments = get_comments(
			array(
				'post_id' => $vehicle_id,
				'status'  => 'approve',
				'fields'  => 'ids',
			)
		);

		if ( empty( $comments ) ) {
			return array();
		}

		// Step 2: Check for admin overrides first
		$override_ids = array();
		foreach ( $comments as $comment_id ) {
			$override = get_comment_meta( (int) $comment_id, 'mhm_verified_review', true );
			if ( $override === '1' || $override === 1 ) {
				$override_ids[] = (int) $comment_id;
			}
		}

		// Step 3: Collect unique user IDs and emails from comments
		$user_ids             = array();
		$emails               = array();
		$comment_map_by_user  = array(); // user_id => [comment_ids]
		$comment_map_by_email = array(); // email => [comment_ids]

		foreach ( $comments as $comment_id ) {
			$comment = get_comment( (int) $comment_id );
			if ( ! $comment ) {
				continue;
			}

			$uid   = (int) $comment->user_id;
			$email = strtolower( trim( $comment->comment_author_email ) );

			if ( $uid > 0 ) {
				$user_ids[]                    = $uid;
				$comment_map_by_user[ $uid ]   = $comment_map_by_user[ $uid ] ?? array();
				$comment_map_by_user[ $uid ][] = (int) $comment_id;
			}

			if ( ! empty( $email ) ) {
				$emails[]                         = $email;
				$comment_map_by_email[ $email ]   = $comment_map_by_email[ $email ] ?? array();
				$comment_map_by_email[ $email ][] = (int) $comment_id;
			}
		}

		if ( empty( $user_ids ) && empty( $emails ) ) {
			return $override_ids;
		}

		// Step 4: find qualifying bookings.
		//
		// The user-id match and the e-mail match run as two separate statements
		// rather than one query whose JOINs and WHERE clause are glued together
		// from PHP fragments. Each statement is a literal, so its
		// parameterisation is readable where the query is built. The result set
		// is unchanged: Step 5 credited every returned row additively and
		// de-duplicated at the end, so a booking that matched on both a user id
		// and an e-mail is still credited for both -- it simply arrives as one
		// row in each statement instead of one row carrying both columns. The
		// LEFT JOINs become INNER JOINs for the same reason: each statement now
		// requires its own match, and an unmatched row could never satisfy the
		// IN () test anyway.
		$matched_user_ids = array();
		$matched_emails   = array();

		if ( ! empty( $user_ids ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking status lives in postmeta; the caller caches the whole result in a transient.
			$matched_user_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT CAST(pm_user.meta_value AS UNSIGNED)
					 FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = '_mhm_vehicle_id'
					 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
					 INNER JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = '_mhm_customer_user_id'
					 WHERE p.post_type = 'vehicle_booking'
					   AND p.post_status = 'publish'
					   AND pm_vid.meta_value = %d
					   AND pm_user.meta_value IN (" . implode( ',', array_fill( 0, count( $user_ids ), '%d' ) ) . ')
					   AND pm_status.meta_value IN (' . implode( ',', array_fill( 0, count( self::VALID_STATUSES ), '%s' ) ) . ')',
					array_merge( array( $vehicle_id ), $user_ids, self::VALID_STATUSES )
				)
			);
		}

		if ( ! empty( $emails ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking status lives in postmeta; the caller caches the whole result in a transient.
			$matched_emails = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT LOWER(pm_email.meta_value)
					 FROM {$wpdb->posts} p
					 INNER JOIN {$wpdb->postmeta} pm_vid ON p.ID = pm_vid.post_id AND pm_vid.meta_key = '_mhm_vehicle_id'
					 INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
					 INNER JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_contact_email'
					 WHERE p.post_type = 'vehicle_booking'
					   AND p.post_status = 'publish'
					   AND pm_vid.meta_value = %d
					   AND LOWER(pm_email.meta_value) IN (" . implode( ',', array_fill( 0, count( $emails ), '%s' ) ) . ')
					   AND pm_status.meta_value IN (' . implode( ',', array_fill( 0, count( self::VALID_STATUSES ), '%s' ) ) . ')',
					array_merge( array( $vehicle_id ), array_map( 'strtolower', $emails ), self::VALID_STATUSES )
				)
			);
		}

		if ( empty( $matched_user_ids ) && empty( $matched_emails ) ) {
			return $override_ids;
		}

		// Step 5: Map results back to comment IDs
		$verified_from_booking = array();

		foreach ( $matched_user_ids as $matched_uid ) {
			$matched_uid = (int) $matched_uid;

			if ( $matched_uid > 0 && isset( $comment_map_by_user[ $matched_uid ] ) ) {
				$verified_from_booking = array_merge( $verified_from_booking, $comment_map_by_user[ $matched_uid ] );
			}
		}

		foreach ( $matched_emails as $matched_email ) {
			$matched_email = strtolower( trim( (string) $matched_email ) );

			if ( '' !== $matched_email && isset( $comment_map_by_email[ $matched_email ] ) ) {
				$verified_from_booking = array_merge( $verified_from_booking, $comment_map_by_email[ $matched_email ] );
			}
		}

		// Merge overrides + booking matches, deduplicate
		$all_verified = array_unique( array_merge( $override_ids, $verified_from_booking ) );

		return array_values( $all_verified );
	}

	/**
	 * Invalidate the verified review cache for a specific vehicle.
	 *
	 * @since 1.3.0
	 * @param int $vehicle_id Vehicle ID.
	 * @return void
	 */
	public static function invalidate_cache( int $vehicle_id ): void {
		if ( $vehicle_id > 0 ) {
			delete_transient( self::CACHE_PREFIX . $vehicle_id );
		}
	}

	/**
	 * Handle booking status change — invalidate the related vehicle's verified review cache.
	 *
	 * Hooked to: mhm_rentiva_booking_status_changed
	 *
	 * @since 1.3.0
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old booking status.
	 * @param string $new_status New booking status.
	 * @return void
	 */
	public static function on_booking_status_changed( int $booking_id, string $old_status, string $new_status ): void {
		$vehicle_id = (int) get_post_meta( $booking_id, '_mhm_vehicle_id', true );
		if ( $vehicle_id > 0 ) {
			self::invalidate_cache( $vehicle_id );
		}
	}
}
