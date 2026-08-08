<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core\Database;

use MHMRentiva\Core\Database\Migrations\MultiTenantMigration;

/**
 * @covers \MHMRentiva\Core\Database\Migrations\MultiTenantMigration
 */
final class MultiTenantMigrationFailureTest extends \WP_UnitTestCase {

	private \wpdb $original_wpdb;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$this->original_wpdb = $wpdb;
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = $this->original_wpdb;

		parent::tearDown();
	}

	/**
	 * @dataProvider failedAlterProvider
	 */
	public function test_each_required_alter_failure_is_reported(
		string $failed_fragment,
		bool $tenant_index_exists,
		bool $composite_index_exists,
		bool $old_unique_exists,
		bool $new_unique_exists
	): void {
		global $wpdb;

		$wpdb         = $this->database_double();
		$wpdb->prefix = 'wp_';

		$wpdb->method( 'get_var' )->willReturnCallback(
			static function ( string $sql ) use ( $tenant_index_exists, $composite_index_exists, $old_unique_exists, $new_unique_exists ) {
				if ( str_contains( $sql, 'INFORMATION_SCHEMA.COLUMNS' ) ) {
					return 1;
				}

				if ( str_contains( $sql, 'SEQ_IN_INDEX = 1' ) ) {
					return $tenant_index_exists ? 1 : 0;
				}

				if ( str_contains( $sql, 'tenant_created_idx' ) ) {
					return $composite_index_exists ? 1 : 0;
				}

				if ( str_contains( $sql, 'tenant_active_key_unique' ) ) {
					return $new_unique_exists ? 1 : 0;
				}

				if ( str_contains( $sql, 'active_key_unique' ) ) {
					return $old_unique_exists ? 1 : 0;
				}

				return 0;
			}
		);

		$wpdb->method( 'query' )->willReturnCallback(
			static fn( string $sql ) => str_contains( $sql, $failed_fragment ) ? false : 1
		);

		$this->assertFalse(
			MultiTenantMigration::run(),
			'A failed required ALTER must keep the outer migration gate open.'
		);
	}

	/**
	 * @return array<string, array{0:string,1:bool,2:bool,3:bool,4:bool}>
	 */
	public function failedAlterProvider(): array {
		return array(
			'tenant index'    => array( 'ADD INDEX `tenant_id_idx`', false, true, false, true ),
			'composite index' => array( 'ADD INDEX `tenant_created_idx`', true, false, false, true ),
			'legacy unique'   => array( 'DROP INDEX `active_key_unique`', true, true, true, true ),
			'tenant unique'   => array( 'ADD UNIQUE INDEX `tenant_active_key_unique`', true, true, false, false ),
		);
	}

	/**
	 * @return \wpdb&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function database_double(): \wpdb {
		$double = $this->getMockBuilder( \wpdb::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'get_var', 'prepare', 'query' ) )
			->getMock();

		$double->method( 'prepare' )->willReturnCallback(
			static function ( string $query, ...$args ): string {
				return $query . ' ' . implode( ' ', array_map( 'strval', $args ) );
			}
		);

		return $double;
	}
}
