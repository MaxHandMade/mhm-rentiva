<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core\Utilities;

use MHMRentiva\Admin\Core\Utilities\DatabaseMigrator;
use WP_UnitTestCase;

/**
 * On a first install the key registry table does not exist yet when
 * create_key_registry_table() checks for its legacy `key_uuid` index, so
 * `SHOW INDEX` failed and logged "Table ...mhmrentiva_key_registry doesn't
 * exist" on every fresh activation. With no table there is no legacy index
 * to drop, and nothing may be asked about it.
 *
 * The missing table is set up with a table prefix nothing else uses, so the
 * suite's own key registry table is never touched.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\DatabaseMigrator
 */
final class KeyRegistryFirstInstallTest extends WP_UnitTestCase
{
    public function test_a_first_install_asks_no_index_question_of_a_table_that_is_not_there(): void
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $wpdb->prefix = $prefix . 'fresh' . wp_rand( 1000, 9999 ) . '_';

        $this->assertNull(
            $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'mhmrentiva_key_registry' ) ) ),
            'Precondition: the key registry table does not exist under this prefix.'
        );

        $queries   = array();
        $collector = static function ( string $query ) use ( &$queries ): string {
            $queries[] = $query;
            return $query;
        };
        add_filter( 'query', $collector, 9999 );
        $suppress = $wpdb->suppress_errors( true );

        try {
            DatabaseMigrator::create_key_registry_table();
        } finally {
            $wpdb->suppress_errors( $suppress );
            remove_filter( 'query', $collector, 9999 );
            $wpdb->prefix = $prefix;
        }

        $show_index = array_values(
            array_filter( $queries, static fn( string $q ): bool => false !== stripos( $q, 'SHOW INDEX' ) )
        );
        $this->assertSame( array(), $show_index );
    }
}
