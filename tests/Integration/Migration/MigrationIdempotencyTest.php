<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * Verifies migration idempotency guarantees for payout audit schema.
 */
final class MigrationIdempotencyTest extends WP_UnitTestCase
{
	public function test_payout_audit_migration_rerun_does_not_attempt_duplicate_unique_index(): void
	{
		$queries = array();

		$collector = static function (string $query) use (&$queries): string {
			$queries[] = $query;
			return $query;
		};

		add_filter('query', $collector, 9999);

		DatabaseMigrator::create_payout_audit_table();
		$queries = array();
		DatabaseMigrator::create_payout_audit_table();

		remove_filter('query', $collector, 9999);

		$duplicate_index_attempts = array_values(
			array_filter(
				$queries,
				static function (string $query): bool {
					$query_lower = strtolower($query);

					return str_contains($query_lower, 'alter table')
						&& str_contains($query_lower, 'add unique key')
						&& str_contains($query_lower, 'payout_action_tx');
				}
			)
		);

		$this->assertSame(
			array(),
			$duplicate_index_attempts,
			'Migration re-run must not attempt to re-add payout_action_tx unique index.'
		);
	}
}