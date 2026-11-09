<?php
/**
 * Application log entries in the core privacy eraser.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `mhm-rentiva-app-log` eraser (5a spec T7). No exporter: the application log
 * is an internal diagnostic record that its own retention setting purges; the
 * eraser still removes every entry that names the person -- by the two account
 * meta keys, by the address anywhere in the entry, or by the account ID in the
 * entry's JSON context.
 */
final class AppLogPersonalData {

	private const TYPE = 'mhmrentiva_app_log';

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
			static fn(): int => LogQuery::count_marked( self::TYPE, $email ),
			array( PostQueue::class, 'delete_post' ),
			__( 'Application log entry', 'mhm-rentiva' ),
			$page
		);
	}
}
