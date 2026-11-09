<?php
/**
 * A windowed "could not erase" marker shared by the privacy erasers.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Why a marker and not a lock (5a spec r4, T4).
 *
 * WordPress calls an eraser with only ($email, $page). An item the eraser
 * cannot erase keeps matching the e-mail, so without a marker every later page
 * would return it again and the queue would never move. A post that failed
 * within the last WINDOW seconds is left out of the queue; after the window a
 * new run retries it on its own. No run identity, no reset, no lock: erasing is
 * idempotent, so two runs on the same address reach the same end state.
 */
final class ErasureMarker {

	public const KEY = '_mhmrentiva_privacy_erase_failed';

	public const WINDOW = 900;

	/**
	 * Mark a post as failed now. True only when the mark reads back.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function mark( int $post_id ): bool {
		$now = time();
		update_post_meta( $post_id, self::KEY, $now );
		wp_cache_delete( $post_id, 'post_meta' );

		return (int) get_post_meta( $post_id, self::KEY, true ) === $now;
	}

	/**
	 * Remove the mark after a successful erase.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function clear( int $post_id ): void {
		delete_post_meta( $post_id, self::KEY );
	}

	/**
	 * SQL predicate: the post aliased $alias has no mark inside the window.
	 *
	 * @param string $alias Alias of the posts table in the outer query.
	 * @return string
	 */
	public static function sql_not_recently_marked( string $alias ): string {
		return 'NOT ' . self::sql_recently_marked( $alias );
	}

	/**
	 * SQL predicate: the post aliased $alias was marked inside the window.
	 *
	 * @param string $alias Alias of the posts table in the outer query.
	 * @return string
	 */
	public static function sql_recently_marked( string $alias ): string {
		global $wpdb;

		$a     = (string) preg_replace( '/[^A-Za-z0-9_]/', '', $alias );
		$since = time() - self::WINDOW;

		return "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} {$a}_em
			WHERE {$a}_em.post_id = {$a}.ID AND {$a}_em.meta_key = '" . self::KEY . "'
			AND CAST( {$a}_em.meta_value AS UNSIGNED ) > " . (int) $since . ' )';
	}
}
