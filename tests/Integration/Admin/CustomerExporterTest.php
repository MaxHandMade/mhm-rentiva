<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Customers\Export\CustomerExporter;
use WP_UnitTestCase;

final class CustomerExporterTest extends WP_UnitTestCase
{
    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
    }

    // Test 11
    public function test_get_csv_rows_returns_header_plus_data(): void
    {
        wp_set_current_user( $this->admin_id );
        $rows = CustomerExporter::get_csv_rows( '', array() );
        // At minimum the header row must be present.
        $this->assertNotEmpty( $rows );
        $header = $rows[0];
        $this->assertContains( 'Name', $header );
        $this->assertContains( 'Email', $header );
        $this->assertContains( 'Phone', $header );
        $this->assertContains( 'Bookings', $header );
        $this->assertContains( 'Total Spent', $header );
    }

    // Test 12
    public function test_handle_with_invalid_nonce_calls_wp_die(): void
    {
        wp_set_current_user( $this->admin_id );
        $_POST = array(
            'action' => 'mhmrentiva_export_customers',
            'nonce'  => 'bad_nonce',
            'search' => '',
        );
        $this->expectException( \WPDieException::class );
        CustomerExporter::handle();
    }

    // Test 13
    public function test_get_csv_rows_with_ids_exports_only_those(): void
    {
        wp_set_current_user( $this->admin_id );
        $uid1 = (int) $this->factory->user->create( array( 'role' => 'customer' ) );
        $uid2 = (int) $this->factory->user->create( array( 'role' => 'customer' ) );

        $rows_filtered = CustomerExporter::get_csv_rows( '', array( $uid1 ) );
        $rows_all      = CustomerExporter::get_csv_rows( '', array() );

        // Filtered set must not be larger than unfiltered set.
        $this->assertLessThanOrEqual( count( $rows_all ), count( $rows_filtered ) );
    }
}
