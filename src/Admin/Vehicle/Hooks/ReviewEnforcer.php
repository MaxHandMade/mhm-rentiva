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

		// 1. Mandatory Rating Check
		$rating = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs on core comment submission payload; plugin does not mutate options/state here.
		if ( isset( $_POST['rating'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs on core comment submission payload; plugin does not mutate options/state here.
			$rating = (int) $_POST['rating'];
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs on core comment submission payload; plugin does not mutate options/state here.
		} elseif ( isset( $_POST['mhm_rating'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Runs on core comment submission payload; plugin does not mutate options/state here.
			$rating = (int) $_POST['mhm_rating'];
		} elseif ( isset( $commentdata['comment_meta']['rating'] ) ) {
			$rating = (int) $commentdata['comment_meta']['rating'];
		}

		if ( $rating < 1 || $rating > 5 ) {
			$msg = esc_html__( 'Error: You must provide a valid rating (1-5 stars) for this vehicle.', 'mhm-rentiva' );
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'PHP_SAPI' ) && PHP_SAPI === 'cli' ) ) {
				$cli_message = esc_html( wp_strip_all_tags( $msg ) );
				if ( class_exists( 'WP_CLI' ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI output is plain text, pre-escaped above.
					\WP_CLI::error( $cli_message );
				} else {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is pre-escaped above.
					throw new \Exception( $cli_message );
				}
			} else {
				wp_die( esc_html( $msg ), esc_html__( 'Review Error', 'mhm-rentiva' ), array( 'response' => 400 ) );
			}
		}

		// 2. One Review Per User Check
		$user_id      = (int) ( $commentdata['user_id'] ?? 0 );
		$author_email = sanitize_email( $commentdata['comment_author_email'] ?? '' );

		// If user is editing their own existing comment via AJAX/Admin, the comment_ID might be passed.
		$existing_comment_id = 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only duplicate detection from core/admin comment payload.
		if ( isset( $_POST['comment_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only duplicate detection from core/admin comment payload.
			$existing_comment_id = (int) $_POST['comment_ID'];
		}

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
					$cli_message = esc_html( wp_strip_all_tags( $msg ) );
					if ( class_exists( 'WP_CLI' ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI output is plain text, pre-escaped above.
						\WP_CLI::error( $cli_message );
					} else {
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is pre-escaped above.
						throw new \Exception( $cli_message );
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
