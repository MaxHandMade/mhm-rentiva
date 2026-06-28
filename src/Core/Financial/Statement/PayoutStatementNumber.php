<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Statement;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Atomic sequential payout-statement numbers: MKB-{YEAR}-{NNNN}.
 */
final class PayoutStatementNumber {

	private const OPTION = 'mhm_rentiva_statement_counter';

	public static function next(): string
	{
		global $wpdb;

		// Atomic increment guarded at the DB level to avoid duplicate numbers under concurrency.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic counter bump on the options table.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				 VALUES (%s, '1', 'no')
				 ON DUPLICATE KEY UPDATE option_value = option_value + 1",
				self::OPTION
			)
		);

		wp_cache_delete(self::OPTION, 'options');
		$counter = (int) get_option(self::OPTION, 1);

		return sprintf('MKB-%s-%04d', gmdate('Y'), $counter);
	}
}
