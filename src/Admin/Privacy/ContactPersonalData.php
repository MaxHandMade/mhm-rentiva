<?php
/**
 * Contact form messages in the core privacy tools.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

use MHMRentiva\Admin\ContactMessages\ContactAttachmentStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `mhm-rentiva-contact` exporter and eraser (5a spec T7).
 *
 * A contact message holds the sender's name, address, phone, company, the
 * message itself, the raw IP address and browser string, and maybe a private
 * attachment. Erasing deletes the message -- it is not a business record --
 * attachment first: the message is the only thing that points at the file, so
 * while the file stays the message stays too, marked (spec r5 #3).
 */
final class ContactPersonalData {

	public const GROUP_ID = 'mhm-rentiva-contact';

	private const TYPE = 'mhmrentiva_contact';

	/**
	 * Core exporter callback.
	 *
	 * @param string $email Requested address.
	 * @param int    $page  1-based page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$page = max( 1, $page );
		$ids  = self::ids( $email, false, PostQueue::PER_PAGE, ( $page - 1 ) * PostQueue::PER_PAGE );

		$items = array();
		foreach ( $ids as $id ) {
			$items[] = array(
				'group_id'    => self::GROUP_ID,
				'group_label' => __( 'Contact messages', 'mhm-rentiva' ),
				'item_id'     => 'contact-' . $id,
				'data'        => self::fields( $id ),
			);
		}

		return array(
			'data' => $items,
			'done' => count( $ids ) < PostQueue::PER_PAGE,
		);
	}

	/**
	 * Core eraser callback.
	 *
	 * @param string $email Requested address.
	 * @param int    $page  1-based page.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public static function erase( string $email, int $page = 1 ): array {
		return PostQueue::erase(
			static fn( int $limit ): array => self::ids( $email, true, $limit ),
			static fn(): int => self::count_marked( $email ),
			array( self::class, 'erase_one' ),
			__( 'Contact message', 'mhm-rentiva' ),
			$page
		);
	}

	/**
	 * Delete one message, its private file first.
	 *
	 * @param int $id Contact message ID.
	 * @return string|null Null on success, else what was left.
	 */
	public static function erase_one( int $id ): ?string {
		$record = ContactAttachmentStore::record( $id );
		$path   = null !== $record ? ContactAttachmentStore::path( $record ) : null;
		if ( null !== $path ) {
			ContactAttachmentStore::delete_for_post( $id );
			if ( file_exists( $path ) && 0 === self::other_references( $record['file'], $id ) ) {
				return 'attachment';
			}
		}

		return PostQueue::delete_post( $id );
	}

	/**
	 * Matching contact message IDs.
	 *
	 * @param string      $email  Requested address.
	 * @param bool|string $queue  false: all; true: not recently marked; 'marked': recently marked.
	 * @param int         $limit  Max rows.
	 * @param int         $offset Offset.
	 * @return int[]
	 */
	private static function ids( string $email, $queue, int $limit, int $offset = 0 ): array {
		global $wpdb;

		$email = EmailMatch::normalise( $email );
		if ( '' === $email ) {
			return array();
		}

		$where = PostQueue::sql_type( self::TYPE ) . $wpdb->prepare(
			" AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ce WHERE ce.post_id = p.ID AND ce.meta_key = '_mhmrentiva_contact_email' AND LOWER( TRIM( ce.meta_value ) ) = %s )",
			$email
		);
		if ( true === $queue ) {
			$where .= ' AND ' . ErasureMarker::sql_not_recently_marked( 'p' );
		} elseif ( 'marked' === $queue ) {
			$where .= ' AND ' . ErasureMarker::sql_recently_marked( 'p' );
		}

		$sql = $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p WHERE {$where} ORDER BY p.ID ASC LIMIT %d OFFSET %d", $limit, $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.

		return array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- prepared above.
	}

	/**
	 * Number of matching messages marked failed inside the window.
	 *
	 * @param string $email Requested address.
	 * @return int
	 */
	private static function count_marked( string $email ): int {
		global $wpdb;

		$email = EmailMatch::normalise( $email );
		if ( '' === $email ) {
			return 0;
		}

		$where = PostQueue::sql_type( self::TYPE ) . $wpdb->prepare(
			" AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ce WHERE ce.post_id = p.ID AND ce.meta_key = '_mhmrentiva_contact_email' AND LOWER( TRIM( ce.meta_value ) ) = %s )",
			$email
		) . ' AND ' . ErasureMarker::sql_recently_marked( 'p' );

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery -- $where is prepared above.
	}

	/**
	 * Other contact messages that reference the same stored file.
	 *
	 * @param string $file    Stored file name.
	 * @param int    $exclude This message's ID.
	 * @return int
	 */
	private static function other_references( string $file, int $exclude ): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s AND p.ID <> %d",
				ContactAttachmentStore::FILE_META,
				$file,
				self::TYPE,
				$exclude
			)
		);
	}

	/**
	 * Name/value pairs for one message.
	 *
	 * @param int $id Contact message ID.
	 * @return array<int, array{name: string, value: string}>
	 */
	private static function fields( int $id ): array {
		$meta   = static function ( string $key ) use ( $id ): string {
			$v = get_post_meta( $id, $key, true );
			return is_scalar( $v ) ? trim( (string) $v ) : '';
		};
		$record = ContactAttachmentStore::record( $id );

		$pairs = array(
			__( 'Date', 'mhm-rentiva' )          => $meta( '_mhmrentiva_contact_timestamp' ),
			__( 'Type', 'mhm-rentiva' )          => $meta( '_mhmrentiva_contact_type' ),
			__( 'Name', 'mhm-rentiva' )          => $meta( '_mhmrentiva_contact_name' ),
			__( 'E-mail', 'mhm-rentiva' )        => $meta( '_mhmrentiva_contact_email' ),
			__( 'Phone', 'mhm-rentiva' )         => $meta( '_mhmrentiva_contact_phone' ),
			__( 'Company', 'mhm-rentiva' )       => $meta( '_mhmrentiva_contact_company' ),
			__( 'Message', 'mhm-rentiva' )       => trim( (string) get_post_field( 'post_content', $id ) ),
			__( 'Consent given', 'mhm-rentiva' ) => $meta( '_mhmrentiva_contact_consent' ),
			__( 'IP address', 'mhm-rentiva' )    => $meta( '_mhmrentiva_contact_ip_address' ),
			__( 'Browser', 'mhm-rentiva' )       => $meta( '_mhmrentiva_contact_user_agent' ),
			__( 'Attachment', 'mhm-rentiva' )    => null !== $record ? $record['name'] : '',
		);

		$out = array();
		foreach ( $pairs as $name => $value ) {
			if ( '' !== $value ) {
				$out[] = array(
					'name'  => $name,
					'value' => $value,
				);
			}
		}

		return $out;
	}
}
