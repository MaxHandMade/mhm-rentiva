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
	}

	protected function tearDown(): void {
		delete_option( 'mhmrentiva_multi_tenant_migration_attempts' );
		delete_option( 'mhmrentiva_multi_tenant_migration_blocked' );

		if ( false === $this->original_db_version ) {
			delete_option( 'mhmrentiva_db_version' );
		} else {
			update_option( 'mhmrentiva_db_version', $this->original_db_version );
		}

		parent::tearDown();
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
