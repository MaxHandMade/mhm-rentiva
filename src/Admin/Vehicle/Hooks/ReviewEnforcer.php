<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReviewEnforcer
 *
 * Enforces strict rules for vehicle reviews:
 * 1. Mandatory rating (1-5 stars) for all vehicle comments.
 * 2. One review per user/guest per vehicle.
 *
 * @package MHMRentiva\Admin\Vehicle\Hooks
 * @since 1.2.2
 */
class ReviewEnforcer {

	/**
	 * Register hooks
	 */
	public static function register(): void {
		// Use preprocess_comment to block invalid comments before they reach the DB.
		// Priority 1 to run very early.
		add_filter( 'preprocess_comment', array( self::class, 'enforce_vehicle_constraints' ), 1 );
	}

	/**
	 * Enforce vehicle review constraints
	 *
	 * @param array $commentdata Comment data.
	 * @return array Validated comment data.
	 */
	public static function enforce_vehicle_constraints( array $commentdata ): array {
		$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );

		if ( ! $post_id ) {
			return $commentdata;
		}

		$post_type = get_post_type( $post_id );

		if ( $post_type !== 'vehicle' ) {
			return $commentdata;
		}

		// This filter runs inside WordPress core's own comment submission pipeline
		// (wp_handle_comment_submission -> wp_new_comment). Public comment forms
		// carry no nonce -- core does not add one -- so there is none for this hook
		// to verify. Everything read below is used only to REJECT the submission;
		// this method persists nothing, and the rating that is ultimately stored is
		// written by core from $commentdata['comment_meta'].
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core comment pipeline has no nonce to verify; read-only validation, nothing is persisted here.
		$submitted = (array) wp_unslash( $_POST );

		// 1. Mandatory Rating Check
		$rating = 0;
		if ( isset( $submitted['rating'] ) ) {
			$rating = (int) $submitted['rating'];
		} elseif ( isset( $submitted['mhm_rating'] ) ) {
			$rating = (int) $submitted['mhm_rating'];
		} elseif ( isset( $commentdata['comment_meta']['rating'] ) ) {
			$rating = (int) $commentdata['comment_meta']['rating'];
		}

		if ( $rating < 1 || $rating > 5 ) {
			$msg = esc_html__( 'Error: You must provide a valid rating (1-5 stars) for this vehicle.', 'mhm-rentiva' );
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'PHP_SAPI' ) && PHP_SAPI === 'cli' ) ) {
				if ( class_exists( 'WP_CLI' ) ) {
					\WP_CLI::error( esc_html( wp_strip_all_tags( $msg ) ) );
				} else {
					throw new \Exception( esc_html( wp_strip_all_tags( $msg ) ) );
				}
			} else {
				wp_die( esc_html( $msg ), esc_html__( 'Review Error', 'mhm-rentiva' ), array( 'response' => 400 ) );
			}
		}

		// 2. One Review Per User Check
		$user_id      = (int) ( $commentdata['user_id'] ?? 0 );
		$author_email = sanitize_email( $commentdata['comment_author_email'] ?? '' );

		// If user is editing their own existing comment via AJAX/Admin, the comment_ID might be passed.
		$existing_comment_id = isset( $submitted['comment_ID'] ) ? (int) $submitted['comment_ID'] : 0;

		if ( ! $existing_comment_id ) {
			$args = array(
				'post_id' => $post_id,
				'number'  => 1,
				'status'  => array( 'approve', 'hold' ),
			);

			if ( $user_id > 0 ) {
				$args['user_id'] = $user_id;
			} elseif ( ! empty( $author_email ) ) {
				$args['author_email'] = $author_email;
			}

			$existing_comments = get_comments( $args );

			if ( ! empty( $existing_comments ) ) {
				$msg = esc_html__( 'Error: You have already reviewed this vehicle. Please edit your existing review instead.', 'mhm-rentiva' );
				if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'PHP_SAPI' ) && PHP_SAPI === 'cli' ) ) {
					if ( class_exists( 'WP_CLI' ) ) {
						\WP_CLI::error( esc_html( wp_strip_all_tags( $msg ) ) );
					} else {
						throw new \Exception( esc_html( wp_strip_all_tags( $msg ) ) );
					}
				} else {
					wp_die( esc_html( $msg ), esc_html__( 'Duplicate Review', 'mhm-rentiva' ), array( 'response' => 409 ) );
				}
			}
		}

		// 3. Normalize Comment Type
		$commentdata['comment_type'] = 'review';

		return $commentdata;
	}
}
