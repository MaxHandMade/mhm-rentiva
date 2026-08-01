<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\ListTable;

use MHMRentiva\Admin\Addons\AddonListTable;
use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use MHMRentiva\Admin\Vehicle\ListTable\VehicleColumns;
use WP_UnitTestCase;

/**
 * WP.org T7 — the admin list screens used to read their sort/filter parameters
 * straight out of $_GET, which is the shape the reviewer flagged
 * (AbstractListTable.php:450 was one of the five examples shown in the T7
 * letter). Nonce-gating those reads is not the answer: they are bookmarkable,
 * state-changing-nothing display parameters, and a nonce would break shareable
 * admin URLs.
 *
 * The answer already existed in this plugin: SearchResults registers its public
 * filter params on WordPress's own `query_vars` whitelist and reads them with
 * get_query_var() (the fix WP.org accepted for T4 #11). These tests pin the same
 * contract for the three admin list tables — the params must survive the
 * WP::parse_request() round trip, which only happens while they are registered.
 *
 * They are written to fail if the `query_vars` registration is removed: without
 * it get_query_var() answers the default, not the URL value.
 */
final class AdminFilterQueryVarsTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        BookingColumns::register();
        VehicleColumns::register();
        AddonListTable::register_query_var_filter();
    }

    /**
     * @param array<string, string> $params
     */
    private function requestAdminUrl( array $params ): void
    {
        $this->go_to( admin_url( 'edit.php?' . http_build_query( $params ) ) );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function filterParamProvider(): array
    {
        return array(
            'booking status'   => array( 'mhm_booking_status', 'confirmed' ),
            'payment status'   => array( 'mhm_payment_status', 'paid' ),
            'payment gateway'  => array( 'mhm_payment_gateway', 'woocommerce' ),
            'booking id'       => array( 'mhm_booking_id', '4242' ),
            'license plate'    => array( 'mhm_license_plate', '34ABC34' ),
            'availability'     => array( 'mhm_available', 'available' ),
            'location'         => array( 'mhm_location_filter', '7' ),
            'lifecycle'        => array( 'mhm_lifecycle_filter', 'archive' ),
            'owner'            => array( 'mhm_owner_filter', 'vendor' ),
            'addon status'     => array( 'addon_status', 'active' ),
            'addon category'   => array( 'addon_category', 'insurance' ),
            'addon price min'  => array( 'price_min', '10' ),
            'addon price max'  => array( 'price_max', '99' ),
            // Calendar navigation on both list screens. Prefixed on purpose: an
            // unprefixed `month` on a global whitelist would collide with any
            // other plugin registering it, and `year` is already core's own.
            'calendar month'   => array( 'mhm_month', '3' ),
            'calendar year'    => array( 'mhm_year', '2027' ),
        );
    }

    /**
     * @dataProvider filterParamProvider
     */
    public function test_admin_list_filter_param_is_readable_through_query_vars( string $key, string $value ): void
    {
        $this->requestAdminUrl( array( $key => $value ) );

        $this->assertSame(
            $value,
            (string) get_query_var( $key ),
            sprintf( '"%s" must be registered on the query_vars whitelist, or the list screen has to fall back to a raw $_GET read.', $key )
        );
    }

    public function test_unregistered_param_does_not_survive_the_round_trip(): void
    {
        // Negative control: proves the assertions above measure the registration
        // and not merely that go_to() copies the query string somewhere readable.
        $this->requestAdminUrl( array( 'mhm_not_registered_param' => 'value' ) );

        $this->assertSame( '', (string) get_query_var( 'mhm_not_registered_param' ) );
    }
}
