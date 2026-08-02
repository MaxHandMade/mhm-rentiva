<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\REST;

use MHMRentiva\Admin\REST\BlockedDates;
use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * WP.org T7 — the blocked-dates read used to live on `admin-ajax.php` behind a
 * `wp_ajax_nopriv_` registration, which meant the only thing that could describe
 * its "anyone may call this" intent was a `phpcs:ignore` comment on the `$_GET`
 * read. That is a suppression, not a design statement.
 *
 * The route below says the same thing in code a reviewer can read: an explicit
 * `__return_true` permission_callback with the reason next to it. These tests
 * pin that contract — the route exists, it is deliberately public, and the
 * removed AJAX actions do not come back.
 */
final class BlockedDatesTest extends WP_UnitTestCase
{
    private const ROUTE = '/mhm-rentiva/v1/vehicles/(?P<id>\d+)/blocked-dates';

    private static WP_REST_Server $server;

    public function setUp(): void
    {
        parent::setUp();

        add_action( 'rest_api_init', array( BlockedDates::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );
    }

    public function tearDown(): void
    {
        remove_action( 'rest_api_init', array( BlockedDates::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = null;
        parent::tearDown();
    }

    public function test_blocked_dates_route_is_intentionally_public(): void
    {
        $routes = self::$server->get_routes();

        $this->assertArrayHasKey( self::ROUTE, $routes );
        $this->assertSame(
            '__return_true',
            $routes[ self::ROUTE ][0]['permission_callback'],
            'The public availability calendar depends on this route being reachable logged-out; the intent must be declared, not implied.'
        );
    }

    public function test_route_returns_the_vehicles_blocked_dates_to_a_logged_out_visitor(): void
    {
        $vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
        update_post_meta( $vehicle_id, '_mhm_blocked_dates', wp_json_encode( array( '2026-09-01', '2026-09-02' ) ) );
        wp_set_current_user( 0 );

        $response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vehicles/' . $vehicle_id . '/blocked-dates' ) );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame(
            array(
                'success' => true,
                'data'    => array( '2026-09-01', '2026-09-02' ),
            ),
            $response->get_data()
        );
    }

    public function test_route_rejects_an_id_that_is_not_a_vehicle(): void
    {
        $page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

        $response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vehicles/' . $page_id . '/blocked-dates' ) );

        $this->assertSame( 404, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public function nonPublicVehicleProvider(): array
    {
        return array(
            'draft'              => array( array( 'post_status' => 'draft' ) ),
            'pending'            => array( array( 'post_status' => 'pending' ) ),
            'private'            => array( array( 'post_status' => 'private' ) ),
            'trashed'            => array( array( 'post_status' => 'trash' ) ),
            // A scheduled post keeps post_status 'future' only when its date is
            // actually in the future; without that wp_insert_post() normalises it
            // to 'publish' and the case would pass while testing nothing.
            'future'             => array(
                array(
                    'post_status'   => 'future',
                    'post_date'     => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
                    'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
                ),
            ),
            'password protected' => array( array( 'post_status' => 'publish', 'post_password' => 'hunter2' ) ),
        );
    }

    /**
     * A vehicle with no page a logged-out visitor could read must not have its
     * schedule read either — otherwise the route's own "returns only what the
     * public vehicle page already shows" justification is false, and 200-vs-404
     * lets an anonymous caller enumerate unpublished vehicles.
     *
     * @dataProvider nonPublicVehicleProvider
     * @param array<string, string> $overrides
     */
    public function test_route_refuses_a_vehicle_with_no_public_page( array $overrides ): void
    {
        $vehicle_id = self::factory()->post->create( array_merge( array( 'post_type' => 'mhmrentiva_vehicle' ), $overrides ) );
        update_post_meta( $vehicle_id, '_mhm_blocked_dates', wp_json_encode( array( '2026-09-01' ) ) );
        wp_set_current_user( 0 );

        // Guard against a fixture that WordPress silently normalised into a
        // published post -- that would make the assertions below vacuous.
        $this->assertSame( $overrides['post_status'], get_post_status( $vehicle_id ) );

        $response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vehicles/' . $vehicle_id . '/blocked-dates' ) );

        $this->assertSame( 404, $response->get_status() );
        $this->assertFalse( $response->get_data()['success'] );
        $this->assertStringNotContainsString( '2026-09-01', (string) wp_json_encode( $response->get_data() ) );
    }

    public function test_a_non_public_vehicle_is_refused_even_for_an_administrator(): void
    {
        // The gate is about what the route publishes, not about who is asking:
        // it has no permission_callback to fall back on, so it must not start
        // answering differently once a privileged session happens to exist.
        $vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'draft' ) );
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

        $response = self::$server->dispatch( new WP_REST_Request( 'GET', '/mhm-rentiva/v1/vehicles/' . $vehicle_id . '/blocked-dates' ) );

        $this->assertSame( 404, $response->get_status() );
    }

    public function test_blocked_dates_are_no_longer_served_over_admin_ajax(): void
    {
        BlockedDatesMetaBox::register();

        $this->assertFalse(
            has_action( 'wp_ajax_mhmrentiva_get_blocked_dates' ),
            'The authenticated AJAX read was replaced by the REST route.'
        );
        $this->assertFalse(
            has_action( 'wp_ajax_nopriv_mhmrentiva_get_blocked_dates' ),
            'The nopriv AJAX read was replaced by the REST route; a nopriv action is the shape T7 objected to.'
        );
        $this->assertFalse(
            method_exists( BlockedDatesMetaBox::class, 'ajax_get_blocked_dates' ),
            'The AJAX callback must be gone, not merely unregistered.'
        );
    }
}
