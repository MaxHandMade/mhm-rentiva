<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use MHMRentiva\Tests\Support\ForgetsMigrationLock;
use RuntimeException;
use WP_UnitTestCase;

/**
 * Activation must stop before claiming a completed install when required DDL
 * cannot be created.
 */
final class ActivationDatabaseFailureTest extends WP_UnitTestCase {
	use ForgetsMigrationLock;

	private mixed $original_plugin_version = false;

	private mixed $original_db_version = false;

	protected function setUp(): void {
		parent::setUp();

		$this->original_plugin_version = get_option('mhmrentiva_plugin_version', false);
		$this->original_db_version     = get_option('mhmrentiva_db_version', false);
		update_option('mhmrentiva_db_version', '4.3.0');
		delete_option('mhmrentiva_plugin_version');
		self::forget_migration_lock();
	}

	protected function tearDown(): void {
		if (false === $this->original_plugin_version) {
			delete_option('mhmrentiva_plugin_version');
		} else {
			update_option('mhmrentiva_plugin_version', $this->original_plugin_version);
		}

		if (false === $this->original_db_version) {
			delete_option('mhmrentiva_db_version');
		} else {
			update_option('mhmrentiva_db_version', $this->original_db_version);
		}

		parent::tearDown();
		self::forget_migration_lock();
	}

	public function test_failed_required_table_creation_aborts_before_the_plugin_version_stamp(): void {
		$die_handler = static function (): callable {
			return static function ($message): void {
				throw new RuntimeException(wp_strip_all_tags((string) $message));
			};
		};

		add_filter('wp_die_handler', $die_handler);

		try {
			try {
				\mhmrentiva_single_site_activation(
					static fn (): bool => true,
					static fn (string $table): bool => 'sessions' !== $table
				);
				$this->fail('Activation continued after required database table creation failed.');
			} catch (RuntimeException $exception) {
				$this->assertStringContainsString('database', strtolower($exception->getMessage()));
			}

			$this->assertFalse(
				get_option('mhmrentiva_plugin_version', false),
				'An incomplete activation must not stamp the plugin version.'
			);
		} finally {
			remove_filter('wp_die_handler', $die_handler);
		}
	}

	public function test_activation_does_not_die_when_the_migration_runner_reports_a_held_lock(): void {
		// Prime the real static flag the same way another request would: a
		// genuine run_migrations() call that finds LOCK_OPTION held returns
		// false and marks the run lock-busy.
		update_option('mhmrentiva_db_version', '1.0.0');
		add_option(DatabaseMigrator::LOCK_OPTION, (string) time(), '', false);
		$this->assertFalse(DatabaseMigrator::run_migrations());
		$this->assertTrue(DatabaseMigrator::last_run_was_lock_busy());
		self::forget_migration_lock();
		update_option('mhmrentiva_db_version', '4.3.0');

		$die_handler = static function (): callable {
			return static function ($message): void {
				throw new RuntimeException(wp_strip_all_tags((string) $message));
			};
		};

		add_filter('wp_die_handler', $die_handler);

		try {
			// The activation seam itself reports "not ready" (false), exactly
			// as a real run_migrations() call does when it declines because
			// the lock is busy -- last_run_was_lock_busy() being true must
			// still let activation through instead of wp_die()ing.
			\mhmrentiva_single_site_activation(
				static fn (): bool => false,
				static fn (string $table): bool => true
			);
		} finally {
			remove_filter('wp_die_handler', $die_handler);
		}

		$this->assertSame(
			MHMRENTIVA_VERSION,
			get_option('mhmrentiva_plugin_version'),
			'A held lock is not a database failure; activation must complete.'
		);
	}

	public function test_activation_forces_only_the_lite_runtime_tables(): void {
		$created = array();

		\mhmrentiva_single_site_activation(
			static fn (): bool => true,
			static function (string $table) use (&$created): bool {
				$created[] = $table;
				return true;
			}
		);

		$this->assertSame(
			array( 'payment_log', 'sessions', 'ratings', 'queue', 'message_logs' ),
			$created,
			'Activation must not recreate retired notification schema or add-on-owned tables.'
		);
		$this->assertSame(MHMRENTIVA_VERSION, get_option('mhmrentiva_plugin_version'));
	}
}
