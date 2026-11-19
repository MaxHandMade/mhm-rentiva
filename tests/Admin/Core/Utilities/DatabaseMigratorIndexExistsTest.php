<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * On a first install the key registry table does not exist yet when
 * create_key_registry_table() asks whether its legacy `key_uuid` index is
 * still there, so `SHOW INDEX` failed and wrote a "Table ... doesn't exist"
 * database error to the log on every fresh activation. Asking about an index
 * on a missing table is a plain "no", not a query error.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator
 */
final class DatabaseMigratorIndexExistsTest extends WP_UnitTestCase
{
    private function indexExists( string $table, string $index ): bool
    {
        $method = new \ReflectionMethod( DatabaseMigrator::class, 'index_exists' );
        $method->setAccessible( true );
        return (bool) $method->invoke( null, $table, $index );
    }

    public function test_a_missing_table_has_no_index_and_raises_no_database_error(): void
    {
        global $wpdb;
        $wpdb->last_error = '';
        $suppress         = $wpdb->suppress_errors( true );

        $exists = $this->indexExists( $wpdb->prefix . 'mhmrentiva_table_that_is_not_there', 'key_uuid' );

        $wpdb->suppress_errors( $suppress );
        $this->assertFalse( $exists );
        $this->assertSame( '', $wpdb->last_error, 'no query may run against a table that does not exist' );
    }

    public function test_an_existing_index_is_still_found(): void
    {
        global $wpdb;
        $this->assertTrue( $this->indexExists( $wpdb->posts, 'PRIMARY' ) );
        $this->assertFalse( $this->indexExists( $wpdb->posts, 'no_such_index' ) );
    }
}
