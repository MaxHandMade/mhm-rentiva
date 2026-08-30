<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Customers\Export\CustomerExporter;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_UnitTestCase;

final class CustomerExporterTest extends WP_UnitTestCase
{
    use UserManagementCapabilities;

    private int $admin_id = 0;

    public function setUp(): void
    {
        parent::setUp();
        $this->admin_id = (int) $this->factory->user->create( array( 'role' => 'administrator' ) );
        // The Customers surface is gated on edit_users, which an administrator
        // does not hold on a network -- core rewrites it to do_not_allow for
        // anyone who is not a super admin. Ask for what the mode requires so
        // the assertions below measure this plugin's guard rather than core's
        // capability rewrite. No-op on a single site.
        $this->grant_user_management_privilege( $this->admin_id );
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

    /**
     * The currency sweep routed `total_spent` through `CurrencyHelper::format_price()`
     * (symbol, WooCommerce grouping/decimal separators, a U+00A0 under the
     * `*_space` positions), which broke the one promise this column has to
     * keep: a spreadsheet must be able to SUM() it. This locks the recovered
     * shape in place -- plain digits, a `.` decimal, no thousands grouping --
     * for an amount that spans into four figures, the exact range the old bug
     * introduced a thousands separator into.
     */
    public function test_total_spent_is_a_bare_number_with_no_thousands_separator(): void
    {
        wp_set_current_user( $this->admin_id );

        $uid   = (int) $this->factory->user->create( array( 'role' => 'customer' ) );
        $user  = get_userdata( $uid );
        $email = $user->user_email;

        $booking_id = self::factory()->post->create(
            array(
                'post_type'   => 'mhmrentiva_booking',
                'post_status' => 'publish',
            )
        );
        update_post_meta( $booking_id, '_mhmrentiva_customer_email', $email );
        // Four figures: the range `CurrencyHelper::format_price()` starts
        // inserting a thousands separator into.
        update_post_meta( $booking_id, '_mhmrentiva_total_price', '1500.00' );

        $rows = CustomerExporter::get_csv_rows( '', array( $uid ) );
        $this->assertCount( 2, $rows, 'Header plus exactly one data row for the single requested ID.' );

        $header = $rows[0];
        $data   = $rows[1];

        $spent_index    = array_search( 'Total Spent', $header, true );
        $currency_index = array_search( 'Currency', $header, true );
        $this->assertNotFalse( $spent_index, 'Header must still carry a Total Spent column.' );
        $this->assertNotFalse( $currency_index, 'Header must carry the new Currency column.' );

        $spent = $data[ $spent_index ];

        $this->assertTrue( is_numeric( $spent ), sprintf( 'Exported "Total Spent" ("%s") must be a parseable number.', $spent ) );
        $this->assertSame( '1500.00', $spent, 'A four-figure amount must not acquire a thousands separator.' );
        $this->assertStringNotContainsString( "\xC2\xA0", $spent, 'No non-breaking space may survive into the exported value.' );
        $this->assertNotSame( '', (string) $data[ $currency_index ], 'The Currency column must carry the site currency code.' );
    }
}
