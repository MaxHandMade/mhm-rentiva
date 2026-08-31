<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\REST;

use MHMRentiva\Admin\Customers\REST\CustomersRestController;
use MHMRentiva\Admin\REST\Availability;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * WP.org T4 #7 / T6 — the ROUTE-level `permission_callback` must declare the WP
 * capability matching what the route actually does, never a manage_options
 * catch-all; deny must resolve to a real `false`/WP_Error.
 *
 * The customer routes return private PII (name, email, phone, address) plus
 * booking and total-spend data. Two review rounds pushed the capability in
 * opposite directions: T4 rejected the blanket `manage_options`, so the routes
 * moved to `list_users`; T6 then rejected `list_users` as too weak for the data
 * class. `edit_users` satisfies both — it is a capability rather than a role
 * check, and it is scoped to the private data actually returned. These tests
 * pin that contract from both sides: `manage_options` alone is denied, and so
 * is `list_users` alone.
 *
 * This is the route-gate layer, independent of the B-G1d handler-body guards
 * (already covered by CustomerUserCapabilityTest.php, which this task does
 * NOT modify). Each test below proves the route gate itself — not the
 * handler body — is what accepts or rejects the caller.
 */
final class CustomersPermissionTest extends WP_UnitTestCase
{
    use UserManagementCapabilities;

    private static WP_REST_Server $server;

    public function setUp(): void
    {
        parent::setUp();

        add_action( 'rest_api_init', array( CustomersRestController::class, 'register_routes' ) );
        add_action( 'rest_api_init', array( Availability::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );

        // Has manage_options but NOT list_users/delete_users. Proves the old
        // catch-all no longer clears the list/detail/bulk-delete route gates.
        remove_role( 'mhmrentiva_test_perm_options_only' );
        add_role(
            'mhmrentiva_test_perm_options_only',
            'MHM Test Perm Options Only',
            array(
                'read'           => true,
                'manage_options' => true,
            )
        );

        // Has list_users but NOT edit_users. Proves the list/detail routes no
        // longer settle for the browse-the-users-table capability now that they
        // return private PII (WP.org T6).
        remove_role( 'mhmrentiva_test_perm_list_users' );
        add_role(
            'mhmrentiva_test_perm_list_users',
            'MHM Test Perm List Users',
            array(
                'read'       => true,
                'list_users' => true,
            )
        );

        // Has edit_users but NOT manage_options. Proves the list/detail routes
        // accept the operation-specific capability on its own.
        remove_role( 'mhmrentiva_test_perm_edit_users' );
        add_role(
            'mhmrentiva_test_perm_edit_users',
            'MHM Test Perm Edit Users',
            array(
                'read'       => true,
                'edit_users' => true,
            )
        );

        // Has delete_users but NOT manage_options. Proves the bulk-delete
        // route accepts the operation-specific capability on its own.
        remove_role( 'mhmrentiva_test_perm_delete_users' );
        add_role(
            'mhmrentiva_test_perm_delete_users',
            'MHM Test Perm Delete Users',
            array(
                'read'         => true,
                'delete_users' => true,
            )
        );
    }

    public function tearDown(): void
    {
        remove_role( 'mhmrentiva_test_perm_options_only' );
        remove_role( 'mhmrentiva_test_perm_list_users' );
        remove_role( 'mhmrentiva_test_perm_edit_users' );
        remove_role( 'mhmrentiva_test_perm_delete_users' );
        wp_set_current_user( 0 );
        remove_action( 'rest_api_init', array( CustomersRestController::class, 'register_routes' ) );
        remove_action( 'rest_api_init', array( Availability::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = null;
        parent::tearDown();
    }

    // --- GET /customers (list) ----------------------------------------------

    public function test_list_route_denies_manage_options_only_user(): void
    {
        $id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_options_only' ) );
        wp_set_current_user( $id );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $response = self::$server->dispatch( $request );

        $this->assertSame(
            403,
            $response->get_status(),
            'manage_options alone must no longer pass the list route gate (WP.org T4 #7).'
        );
    }

    public function test_list_route_denies_list_users_only_user(): void
    {
        $id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_list_users' ) );
        wp_set_current_user( $id );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $response = self::$server->dispatch( $request );

        $this->assertSame(
            403,
            $response->get_status(),
            'list_users is too weak for a route returning customer PII and spend data (WP.org T6).'
        );
    }

    public function test_list_route_allows_edit_users_capability(): void
    {
        $id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_edit_users' ) );
        wp_set_current_user( $id );
        // On a network core rewrites edit_users unless the caller passes
        // manage_network_users, so grant exactly that -- NOT super admin,
        // which holds every capability and would make this test pass without
        // saying anything about the route gate. The actor still has no
        // manage_options and no list_users, so the claim under test keeps its
        // meaning in both modes. No-op on a single site.
        $this->grant_network_user_editing( $id );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers' );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status(), 'edit_users alone must be sufficient for the list route.' );
    }

    // --- GET /customers/{id} (detail) ----------------------------------------

    public function test_detail_route_denies_manage_options_only_user(): void
    {
        $target_id = self::factory()->user->create( array( 'role' => 'customer' ) );
        $id        = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_options_only' ) );
        wp_set_current_user( $id );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers/' . $target_id );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 403, $response->get_status() );
    }

    public function test_detail_route_denies_list_users_only_user(): void
    {
        $target_id = self::factory()->user->create( array( 'role' => 'customer' ) );
        $id        = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_list_users' ) );
        wp_set_current_user( $id );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers/' . $target_id );
        $response = self::$server->dispatch( $request );

        $this->assertSame(
            403,
            $response->get_status(),
            'list_users is too weak for a route returning customer PII and spend data (WP.org T6).'
        );
    }

    public function test_detail_route_allows_edit_users_capability(): void
    {
        $target_id = self::factory()->user->create( array( 'role' => 'customer' ) );
        $id        = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_edit_users' ) );
        wp_set_current_user( $id );
        $this->grant_network_user_editing( $id );

        $request  = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/customers/' . $target_id );
        $response = self::$server->dispatch( $request );

        // 404 is acceptable when the fixture user has no bookings (same
        // tolerance as CustomersRestEndpointTest.php); either value proves
        // the route gate itself let the request through to the callback.
        $this->assertContains( $response->get_status(), array( 200, 404 ) );
    }

    // --- DELETE /customers/bulk ------------------------------------------------

    public function test_bulk_delete_route_denies_manage_options_only_user_before_reaching_handler(): void
    {
        $target_id = self::factory()->user->create( array( 'role' => 'customer' ) );
        $id        = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_options_only' ) );
        wp_set_current_user( $id );

        $request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/customers/bulk' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array( 'ids' => array( $target_id ) ) ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 403, $response->get_status() );

        // Proves the ROUTE gate denied — not the handler body's delete_users
        // guard (which returns a distinct, handler-specific message): the
        // handler was never reached and the target survives untouched.
        $data = $response->get_data();
        $this->assertNotSame(
            'You do not have permission to delete customers.',
            $data['message'] ?? null,
            'Denial must come from the route permission_callback, not the B-G1d handler-body guard.'
        );
        $this->assertAccountStillOnSite(
            $target_id,
            'bulk_delete() must never execute when the route gate denies the request.'
        );
    }

    public function test_bulk_delete_route_allows_delete_users_capability(): void
    {
        $target_id = self::factory()->user->create( array( 'role' => 'customer' ) );
        $id        = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_perm_delete_users' ) );
        wp_set_current_user( $id );
        // delete_users has no capability path on a network at all: core gates
        // it on is_super_admin() outright, so unlike edit_users above there is
        // nothing narrower to grant. The discrimination this suite exists for
        // is still carried by the denied-side tests -- manage_options alone and
        // list_users alone are both refused -- which run unchanged in both modes.
        $this->grant_user_management_privilege( $id );

        $request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/customers/bulk' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array( 'ids' => array( $target_id ) ) ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 1, $response->get_data()['deleted'] );
        $this->assertAccountRemovedFromSite( $target_id, 'The bulk-delete route must actually delete the target.' );
    }

    // --- GET /availability (deliberately public, doc-commented decision) -----

    public function test_availability_route_serves_anonymous_no_capability_visitor_with_valid_nonce(): void
    {
        wp_set_current_user( 0 );
        $nonce = wp_create_nonce( 'wp_rest' );

        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/availability' );
        $request->set_header( 'X-WP-Nonce', $nonce );
        $request->set_query_params(
            array(
                'vehicle_id'   => 999999,
                'pickup_date'  => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
                'pickup_time'  => '10:00',
                'dropoff_date' => gmdate( 'Y-m-d', strtotime( '+2 day' ) ),
                'dropoff_time' => '10:00',
            )
        );
        $response = self::$server->dispatch( $request );

        // Not gated by any WP capability: an anonymous, zero-capability
        // visitor holding only the standard wp_rest nonce reaches the
        // callback. (The vehicle_id is a fixture that will not exist, so the
        // callback itself reports 400 "not found" — that is the callback's
        // business logic, not the permission gate; 401/403 would mean the
        // permission gate wrongly blocked a public, capability-less caller.)
        $this->assertNotSame( 401, $response->get_status() );
        $this->assertNotSame( 403, $response->get_status() );
    }

    public function test_availability_route_denies_request_without_valid_nonce(): void
    {
        wp_set_current_user( 0 );

        $request = new WP_REST_Request( 'GET', '/mhm-rentiva/v1/availability' );
        $request->set_query_params(
            array(
                'vehicle_id'   => 999999,
                'pickup_date'  => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
                'pickup_time'  => '10:00',
                'dropoff_date' => gmdate( 'Y-m-d', strtotime( '+2 day' ) ),
                'dropoff_time' => '10:00',
            )
        );
        $response = self::$server->dispatch( $request );

        // permission_check() returns bare `false` (not a WP_Error), so
        // WP_REST_Server falls back to rest_authorization_required_code():
        // 401 for a logged-out caller, 403 for a logged-in-but-capability-
        // less one. This caller is logged out, hence 401.
        $this->assertSame(
            401,
            $response->get_status(),
            'The documented nonce requirement (README.md "Authentication") must still be enforced even though the endpoint is public.'
        );
    }
}
