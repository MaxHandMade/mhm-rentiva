<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Migration;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;

/**
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator::run_migrations
 */
final class MultiTenantMigrationCircuitBreakerTest extends \WP_UnitTestCase {

	private $original_db_version;

	protected function setUp(): void {
		parent::setUp();
		$this->original_db_version = get_option( 'mhmrentiva_db_version', false );

		delete_option( 'mhmrentiva_multi_tenant_migration_attempts' );
		delete_option( 'mhmrentiva_multi_tenant_migration_blocked' );
		update_option( 'mhmrentiva_db_version', '4.2.0' );
		self::forget_migration_lock();
	}

	protected function tearDown(): void {
		delete_option( 'mhmrentiva_multi_tenant_migration_attempts' );
		delete_option( 'mhmrentiva_multi_tenant_migration_blocked' );

		if ( false === $this->original_db_version ) {
			delete_option( 'mhmrentiva_db_version' );
		} else {
			update_option( 'mhmrentiva_db_version', $this->original_db_version );
		}
		self::forget_migration_lock();

		parent::tearDown();
	}

	/**
	 * Remove DatabaseMigrator::LOCK_OPTION by hand -- it is written with raw SQL
	 * (acquire_lock()/release_lock()), and the migration's own DDL (the 6.0.0
	 * prefix rename's RENAME TABLE, and RetiredIndexes::drop()'s DROP INDEX on
	 * core tables) is an implicit COMMIT that ends WP_UnitTestCase's per-test
	 * transaction early. A lock acquired before that point survives it while the
	 * DELETE that releases it does not, so a stuck lock silently declines
	 * run_migrations() for every later test in the process. Same fix as the
	 * add-on's ProMigrationLockTest::forget_lock().
	 */
	private static function forget_migration_lock(): void {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", DatabaseMigrator::LOCK_OPTION ) );
		wp_cache_delete( DatabaseMigrator::LOCK_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	public function test_repeated_multi_tenant_failure_never_stamps_and_then_opens_a_circuit(): void {
		$calls  = 0;
		$runner = static function () use ( &$calls ): bool {
			++$calls;
			return false;
		};

		for ( $attempt = 1; $attempt <= 3; ++$attempt ) {
			$this->assertFalse(
				DatabaseMigrator::run_migrations( array(), null, $runner ),
				'A failed tenant migration must report incomplete to the activation caller.'
			);
			$this->assertSame( '4.2.0', get_option( 'mhmrentiva_db_version' ) );
			$this->assertSame(
				$attempt,
				(int) get_option( 'mhmrentiva_multi_tenant_migration_attempts', 0 ),
				'The failure count must persist between requests.'
			);
		}

		$blocked = get_option( 'mhmrentiva_multi_tenant_migration_blocked', array() );
		$this->assertSame( 3, $calls );
		$this->assertIsArray( $blocked );
		$this->assertSame( '4.3.0', $blocked['version'] ?? '' );
		$this->assertGreaterThan( time(), $blocked['retry_after'] ?? 0 );

		$this->assertFalse(
			DatabaseMigrator::run_migrations( array(), null, $runner ),
			'An open migration circuit is incomplete, not a successful migration.'
		);

		$this->assertSame( 3, $calls, 'An open circuit must skip the expensive migration body.' );
		$this->assertSame( '4.2.0', get_option( 'mhmrentiva_db_version' ) );
	}
}
