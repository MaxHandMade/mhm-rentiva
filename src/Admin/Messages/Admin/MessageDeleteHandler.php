<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\PostTypes\Message\Message;

final class MessageDeleteHandler {

	/**
	 * Admin_post_mhm_rentiva_delete_messages callback.
	 *
	 * Verifies nonce + capability, delegates to process(), then redirects.
	 */
	public static function handle(): void {
		check_admin_referer( 'mhm_rentiva_delete_messages', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'mhm-rentiva' ), '', array( 'response' => 403 ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Verified by check_admin_referer above; values cast via absint() below.
		$raw_ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
		$ids     = array_values( array_filter( array_map( 'absint', $raw_ids ) ) );

		$deleted = self::process( $ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'mhm-rentiva-messages',
					'deleted' => $deleted,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Trash message posts by ID. Public for testability.
	 *
	 * Only trashes posts whose post_type is Message::POST_TYPE.
	 *
	 * @param  int[] $ids Positive integer post IDs.
	 * @return int        Number of posts actually trashed.
	 */
	public static function process( array $ids ): int {
		$count = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && Message::POST_TYPE === get_post_type( $id ) ) {
				wp_trash_post( $id );
				++$count;
			}
		}
		return $count;
	}
}
