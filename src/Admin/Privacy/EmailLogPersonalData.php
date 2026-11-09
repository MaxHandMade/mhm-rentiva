<?php
/**
 * E-mail log entries in the core privacy tools.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `mhm-rentiva-email-log` exporter and eraser (5a spec T7).
 *
 * A log entry is found when it was sent to the address or when its stored
 * context carries the address -- the copy sent to the site's administrator
 * holds the same customer details. The export gives date and subject only:
 * the recipient of an administrator copy is staff, not the requester.
 */
final class EmailLogPersonalData {

	public const GROUP_ID = 'mhm-rentiva-email-log';

	private const TYPE = 'mhmrentiva_email_log';

	/**
	 * Core exporter callback.
	 *
	 * @param string $email Requested address.
	 * @param int    $page  1-based page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export( string $email, int $page = 1 ): array {
		$page = max( 1, $page );
		$ids  = LogQuery::ids( self::TYPE, $email, false, PostQueue::PER_PAGE, ( $page - 1 ) * PostQueue::PER_PAGE );

		$items = array();
		foreach ( $ids as $id ) {
			$subject = (string) get_post_meta( $id, '_mhmrentiva_email_subject', true );
			$items[] = array(
				'group_id'    => self::GROUP_ID,
				'group_label' => __( 'E-mails sent', 'mhm-rentiva' ),
				'item_id'     => 'email-log-' . $id,
				'data'        => array(
					array(
						'name'  => __( 'Date', 'mhm-rentiva' ),
						'value' => (string) get_post_field( 'post_date', $id ),
					),
					array(
						'name'  => __( 'Subject', 'mhm-rentiva' ),
						'value' => '' !== $subject ? $subject : (string) get_post_field( 'post_title', $id ),
					),
				),
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
			static fn( int $limit ): array => LogQuery::ids( self::TYPE, $email, true, $limit ),
			static fn(): int => count( LogQuery::ids( self::TYPE, $email, 'marked', 1000 ) ),
			array( PostQueue::class, 'delete_post' ),
			__( 'E-mail log entry', 'mhm-rentiva' ),
			$page
		);
	}
}
