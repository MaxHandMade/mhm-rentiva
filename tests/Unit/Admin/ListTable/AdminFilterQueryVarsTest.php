<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Admin\ListTable;

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
            'booking status'   => array( 'mhmrentiva_booking_status', 'confirmed' ),
            'payment status'   => array( 'mhmrentiva_payment_status', 'paid' ),
            'payment gateway'  => array( 'mhmrentiva_payment_gateway', 'woocommerce' ),
            'booking id'       => array( 'mhmrentiva_booking_id', '4242' ),
            'license plate'    => array( 'mhmrentiva_license_plate', '34ABC34' ),
            'availability'     => array( 'mhmrentiva_available', 'available' ),
            'location'         => array( 'mhmrentiva_location_filter', '7' ),
            'lifecycle'        => array( 'mhmrentiva_lifecycle_filter', 'archive' ),
            // Calendar navigation on both list screens. Prefixed on purpose: an
            // unprefixed `month` on a global whitelist would collide with any
            // other plugin registering it, and `year` is already core's own.
            'calendar month'   => array( 'mhmrentiva_month', '3' ),
            'calendar year'    => array( 'mhmrentiva_year', '2027' ),
            // View engine (Faz 2): registered on BOTH screens. This provider
            // only pins the query_vars round trip, not the per-screen
            // whitelist of allowed values — that is BookingViewEngineTest /
            // VehicleViewEngineTest's job (get_current_view()).
            'view'             => array( 'mhmrentiva_view', 'calendar' ),
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

    /**
     * @return array<string, array{0: class-string, 1: string, 2: string}>
     */
    public function arrayValuedParamProvider(): array
    {
        return array(
            'bookings list' => array( BookingColumns::class, 'get_query_text', 'mhmrentiva_booking_status' ),
            'vehicles list' => array( VehicleColumns::class, 'get_query_text', 'mhmrentiva_available' ),
        );
    }

    /**
     * WP::parse_request() keeps arrays intact for registered query vars, so
     * `?addon_status[]=x` reaches a reader typed as string. Each reader must
     * fall back rather than cast, or the screen prints a live PHP
     * "Array to string conversion" warning into the admin page.
     *
     * The readers are private (they are an implementation detail of each screen,
     * and the add-ons screen has no live filter UI to drive them through), so
     * this guard is pinned directly.
     *
     * @dataProvider arrayValuedParamProvider
     * @param class-string $class
     */
    public function test_an_array_valued_filter_param_falls_back_instead_of_casting( string $class, string $method, string $key ): void
    {
        $params = array( $key => array( 'x', 'y' ) );
        $this->requestAdminUrl( $params );
        $this->assertIsArray( get_query_var( $key ), 'Fixture must actually deliver an array, or this proves nothing.' );

        $raised = array();
        set_error_handler(
            static function ( int $errno, string $errstr ) use ( &$raised ): bool {
                $raised[] = $errstr;
                return true;
            },
            E_ALL
        );

        try {
            $reader = new \ReflectionMethod( $class, $method );
            $reader->setAccessible( true );
            $result = $reader->invoke( null, $key, 'FALLBACK' );
        } finally {
            restore_error_handler();
        }

        $this->assertSame( 'FALLBACK', $result );
        $this->assertSame( array(), $raised, 'Reading an array-valued param must not raise a PHP diagnostic.' );
    }

    public function test_unregistered_param_does_not_survive_the_round_trip(): void
    {
        // Negative control: proves the assertions above measure the registration
        // and not merely that go_to() copies the query string somewhere readable.
        $this->requestAdminUrl( array( 'mhmrentiva_not_registered_param' => 'value' ) );

        $this->assertSame( '', (string) get_query_var( 'mhmrentiva_not_registered_param' ) );
    }

    public function test_paid_owner_filter_param_does_not_survive_the_round_trip(): void
    {
        $this->requestAdminUrl( array( 'mhmrentiva_owner_filter' => 'vendor' ) );

        $this->assertSame( '', (string) get_query_var( 'mhmrentiva_owner_filter' ) );
    }
}
