<?php
/**
 * The eraser loop shared by every "delete these posts" privacy eraser.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One paging contract for the contact, e-mail log and app log erasers (and
 * Pro's message threads), the same as the booking eraser's (spec T4):
 *
 * - the queue callback returns at most PER_PAGE matching IDs that were not
 *   marked failed in the last ErasureMarker::WINDOW seconds (in SQL);
 * - a deleted post drops out of the queue by itself, so no page offset;
 * - a post the erase callback cannot finish is marked and named; if even the
 *   mark cannot be written the loop stops with done = true instead of getting
 *   the same post back forever;
 * - page one reports what earlier runs left behind inside the window.
 */
final class PostQueue {

	public const PER_PAGE = 50;

	/**
	 * Run one eraser page.
	 *
	 * @param callable(int): int[]     $queue        Returns up to $limit IDs to erase now.
	 * @param callable(): int          $marked_count Number of matching posts marked inside the window.
	 * @param callable(int): ?string   $erase        Erases one post; null on success, else what was left.
	 * @param string                   $noun         Translated noun for messages ("Contact message").
	 * @param int                      $page         1-based page.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase( callable $queue, callable $marked_count, callable $erase, string $noun, int $page ): array {
		$result = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		if ( 1 === $page ) {
			$marked = (int) $marked_count();
			if ( $marked > 0 ) {
				$result['items_retained'] = true;
				$result['messages'][]     = sprintf(
					/* translators: 1: number of items, 2: item noun such as "Contact message". */
					__( '%1$d × %2$s could not be erased in the last 15 minutes; run the erasure again in 15 minutes.', 'mhm-rentiva' ),
					$marked,
					$noun
				);
			}
		}

		$ids = $queue( self::PER_PAGE );
		foreach ( $ids as $id ) {
			$left = $erase( (int) $id );
			if ( null === $left ) {
				$result['items_removed'] = true;
				continue;
			}

			$result['items_retained'] = true;
			$result['messages'][]     = sprintf(
				/* translators: 1: item noun such as "Contact message", 2: item ID, 3: what was left. */
				__( '%1$s #%2$d could not be erased (%3$s); run the erasure again in 15 minutes.', 'mhm-rentiva' ),
				$noun,
				(int) $id,
				$left
			);

			// A post that is gone cannot be marked -- and needs no mark: it
			// cannot come back into the queue.
			if ( get_post( (int) $id ) instanceof \WP_Post && ! ErasureMarker::mark( (int) $id ) ) {
				$result['messages'][] = __( 'The erasure stopped: an item could not be marked as failed.', 'mhm-rentiva' );
				$result['done']       = true;

				return $result;
			}
		}

		$result['done'] = count( $ids ) < self::PER_PAGE;

		return $result;
	}

	/**
	 * Delete a post for good and say what, if anything, stayed behind.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Null when the post and all its meta rows are gone.
	 */
	public static function delete_post( int $post_id ): ?string {
		global $wpdb;

		wp_delete_post( $post_id, true );
		clean_post_cache( $post_id );

		if ( get_post( $post_id ) instanceof \WP_Post ) {
			return 'post';
		}

		$rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an existence check must not read a cache.

		return $rows > 0 ? 'meta' : null;
	}

	/**
	 * Common WHERE for "posts of this type, not trashed, not recently marked".
	 *
	 * @param string $post_type Post type.
	 * @return string Prepared SQL fragment on alias p.
	 */
	public static function sql_type( string $post_type ): string {
		global $wpdb;

		return $wpdb->prepare( "p.post_type = %s AND p.post_status NOT IN ( 'auto-draft' )", $post_type );
	}
}
