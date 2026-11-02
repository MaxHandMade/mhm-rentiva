<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Settings\Comments\CommentsSettings;

/**
 * Class ReviewEnforcer
 *
 * Enforces strict rules for vehicle reviews:
 * 1. Mandatory rating (1-5 stars) for all vehicle comments.
 * 2. One review per user/guest per vehicle.
 * 3. The plugin's moderation setting holds a new review.
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
		add_filter( 'pre_comment_approved', array( self::class, 'apply_moderation_setting' ), 10, 2 );
	}

	/**
	 * The plugin's moderation setting decides for a new vehicle review.
	 *
	 * Core's wp_new_comment() ignores a comment_approved it is handed and asks
	 * wp_allow_comment(), so with core moderation off a review was published with
	 * auto_approve off. Off holds the review. On leaves core's answer alone: a site
	 * that moderates everything, or a moderation-word hit, still holds it. A spam,
	 * trash or error verdict is never softened.
	 *
	 * @param int|string|\WP_Error $approved    Core's verdict.
	 * @param array<string, mixed> $commentdata Comment data.
	 * @return int|string|\WP_Error
	 */
	public static function apply_moderation_setting( $approved, array $commentdata ) {
		if ( is_wp_error( $approved ) || 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}

		if ( 'review' !== ( $commentdata['comment_type'] ?? '' )
			|| 'mhmrentiva_vehicle' !== get_post_type( (int) ( $commentdata['comment_post_ID'] ?? 0 ) ) ) {
			return $approved;
		}

		$settings = CommentsSettings::get_settings();

		return empty( $settings['approval']['auto_approve'] ) ? 0 : $approved;
	}

	/**
	 * One review per user or guest per vehicle. Only reviews count: a plain comment
	 * is not a review, on any surface.
	 *
	 * @param int    $post_id      Vehicle ID.
	 * @param int    $user_id      Author's user ID, 0 for a guest.
	 * @param string $author_email Guest's address.
	 */
	public static function has_review( int $post_id, int $user_id, string $author_email ): bool {
		$args = array(
			'post_id' => $post_id,
			'type'    => 'review',
			'number'  => 1,
			'status'  => array( 'approve', 'hold' ),
		);

		if ( $user_id > 0 ) {
			$args['user_id'] = $user_id;
		} elseif ( '' !== $author_email ) {
			$args['author_email'] = $author_email;
		}

		return array() !== get_comments( $args );
	}

	/**
	 * The refusal for a second review, for the pipeline here and the AJAX handler.
	 */
	public static function duplicate_message(): string {
		return __( 'Error: You have already reviewed this vehicle. Please edit your existing review instead.', 'mhm-rentiva' );
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

		if ( $post_type !== 'mhmrentiva_vehicle' ) {
			return $commentdata;
		}

		// This filter used to reach into $_POST for the rating. It ran inside
		// WordPress core's comment pipeline, which carries no nonce of its own, so
		// the read could never be justified in the scope that performed it -- only
		// annotated. It does not need the request at all: the submitted rating is
		// part of the comment being filtered, and the one caller that creates
		// vehicle reviews (VehicleRatingForm::ajax_submit_rating(), which verifies
		// its own nonce) hands it over in comment_meta. Validating the argument
		// instead of ambient request state is also what makes this filter testable.

		// 1. Mandatory Rating Check
		$rating = (int) ( $commentdata['comment_meta']['rating'] ?? 0 );

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

		// If user is editing their own existing comment, the comment_ID travels
		// with the comment data itself.
		$existing_comment_id = (int) ( $commentdata['comment_ID'] ?? 0 );

		if ( ! $existing_comment_id ) {
			if ( self::has_review( $post_id, $user_id, $author_email ) ) {
				$msg = esc_html( self::duplicate_message() );
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
