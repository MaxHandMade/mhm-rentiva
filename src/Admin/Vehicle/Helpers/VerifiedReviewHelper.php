<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Helpers;

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

		// Step 4: Single query to find qualifying bookings
		$status_placeholders = implode( ',', array_fill( 0, count( self::VALID_STATUSES ), '%s' ) );

		// Build conditions for user_id match OR email match
		$conditions = array();
		$params     = array();

		// Vehicle ID condition (always)
		$params[] = $vehicle_id;

		if ( ! empty( $user_ids ) ) {
			$uid_placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
			$conditions[]     = "pm_user.meta_value IN ($uid_placeholders)";
			$params           = array_merge( $params, $user_ids );
		}

		if ( ! empty( $emails ) ) {
			$email_placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
			$conditions[]       = "pm_email.meta_value IN ($email_placeholders)";
			$params             = array_merge( $params, $emails );
		}

		// Status params
		$params = array_merge( $params, self::VALID_STATUSES );

		// Build query with LEFT JOINs for both user_id and email matching
		$user_join  = '';
		$email_join = '';
		$where_or   = array();

		if ( ! empty( $user_ids ) ) {
			$uid_placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
			$user_join        = "LEFT JOIN {$wpdb->postmeta} pm_user
				ON p.ID = pm_user.post_id AND pm_user.meta_key = '_mhm_customer_user_id'";
			$where_or[]       = "pm_user.meta_value IN ($uid_placeholders)";
		}

		if ( ! empty( $emails ) ) {
			$email_placeholders = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
			$email_join         = "LEFT JOIN {$wpdb->postmeta} pm_email
				ON p.ID = pm_email.post_id AND pm_email.meta_key = '_mhm_contact_email'";
			$where_or[]         = "LOWER(pm_email.meta_value) IN ($email_placeholders)";
		}

		$or_clause = '(' . implode( ' OR ', $where_or ) . ')';

		// Reset params for clean prepare
		$query_params   = array();
		$query_params[] = $vehicle_id; // pm_vid.meta_value

		if ( ! empty( $user_ids ) ) {
			$query_params = array_merge( $query_params, $user_ids );
		}
		if ( ! empty( $emails ) ) {
			$query_params = array_merge( $query_params, array_map( 'strtolower', $emails ) );
		}
		$query_params = array_merge( $query_params, self::VALID_STATUSES );

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "
			SELECT DISTINCT
				CASE
					WHEN pm_user.meta_value IS NOT NULL THEN CAST(pm_user.meta_value AS UNSIGNED)
					ELSE 0
				END AS matched_user_id,
				CASE
					WHEN pm_email.meta_value IS NOT NULL THEN LOWER(pm_email.meta_value)
					ELSE ''
				END AS matched_email
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_vid
				ON p.ID = pm_vid.post_id AND pm_vid.meta_key = '_mhm_vehicle_id'
			INNER JOIN {$wpdb->postmeta} pm_status
				ON p.ID = pm_status.post_id AND pm_status.meta_key = '_mhm_status'
			{$user_join}
			{$email_join}
			WHERE p.post_type = 'vehicle_booking'
				AND p.post_status = 'publish'
				AND pm_vid.meta_value = %d
				AND {$or_clause}
				AND pm_status.meta_value IN ({$status_placeholders})
		";
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared and placeholders are bound safely.
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );

		if ( empty( $results ) ) {
			return $override_ids;
		}

		// Step 5: Map results back to comment IDs
		$verified_from_booking = array();

		foreach ( $results as $row ) {
			$matched_uid   = (int) $row->matched_user_id;
			$matched_email = strtolower( trim( $row->matched_email ) );

			if ( $matched_uid > 0 && isset( $comment_map_by_user[ $matched_uid ] ) ) {
				$verified_from_booking = array_merge( $verified_from_booking, $comment_map_by_user[ $matched_uid ] );
			}

			if ( ! empty( $matched_email ) && isset( $comment_map_by_email[ $matched_email ] ) ) {
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
