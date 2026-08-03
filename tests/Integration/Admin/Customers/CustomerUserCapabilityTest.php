<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Customers;

use MHMRentiva\Admin\Customers\AddCustomerPage;
use MHMRentiva\Admin\Customers\CustomersOptimizer;
use MHMRentiva\Admin\Customers\CustomersPage;
use MHMRentiva\Admin\Customers\REST\CustomersRestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * WP.org T4 #5 — Customers feature creates/edits/deletes real WordPress users.
 * These operations must be gated on WordPress user-management capabilities
 * (create_users / edit_users / delete_users), never on manage_options or a role,
 * and must never assign a hardcoded password.
 */
final class CustomerUserCapabilityTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;

    public function setUp(): void
    {
        parent::setUp();

        // Role that has manage_options but NOT the WP user-management caps.
        // Proves the guard checks the specific capability, not manage_options.
        remove_role( 'mhmrentiva_test_options_only' );
        add_role(
            'mhmrentiva_test_options_only',
            'MHM Test Options Only',
            array(
                'read'           => true,
                'manage_options' => true,
            )
        );

        // Role that has the specific user-management caps (plus manage_options,
        // so REST-route-level access — still gated on manage_options until
        // B-G1e — is unaffected by this task). Proves the operation succeeds
        // once the matching capability is present.
        remove_role( 'mhmrentiva_test_user_manager' );
        add_role(
            'mhmrentiva_test_user_manager',
            'MHM Test User Manager',
            array(
                'read'           => true,
                'manage_options' => true,
                'create_users'   => true,
                'edit_users'     => true,
                'delete_users'   => true,
            )
        );
    }

    public function tearDown(): void
    {
        remove_role( 'mhmrentiva_test_options_only' );
        remove_role( 'mhmrentiva_test_user_manager' );
        wp_set_current_user( 0 );
        unset( $_POST['submit'], $_POST['mhmrentiva_add_customer_nonce'], $_POST['mhmrentiva_edit_customer_nonce'], $_POST['nonce'], $_POST['customer_name'], $_POST['customer_email'], $_GET['customer_id'] );
        parent::tearDown();
    }

    /**
     * render_customer_edit() is intentionally private (not part of the public
     * admin-page API); invoke it directly via reflection so the test can
     * exercise the operation-level guard in isolation from the page-level
     * manage_options gate in render(), which is out of this task's scope.
     */
    private function invokeRenderCustomerEdit( CustomersPage $page ): void
    {
        $method = new \ReflectionMethod( CustomersPage::class, 'render_customer_edit' );
        $method->setAccessible( true );
        $method->invoke( $page );
    }

    // --- Create (AddCustomerPage::render) ---------------------------------

    public function test_add_customer_denied_without_create_users_capability(): void
    {
        $capped_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_options_only' ) );
        wp_set_current_user( $capped_id );

        $count_before = count( get_users( array( 'fields' => 'ID' ) ) );

        $_POST['submit']                            = 'submit';
        $_POST['mhmrentiva_add_customer_nonce']     = wp_create_nonce( 'mhmrentiva_add_customer' );
        $_POST['customer_name']                      = 'Denied Customer';
        $_POST['customer_email']                     = 'denied-customer@example.com';

        ob_start();
        AddCustomerPage::render();
        ob_end_clean();

        $count_after = count( get_users( array( 'fields' => 'ID' ) ) );

        $this->assertSame( $count_before, $count_after, 'A user without create_users must not be able to create a customer user.' );
        $this->assertFalse( get_user_by( 'email', 'denied-customer@example.com' ) );
    }

    public function test_add_customer_allowed_with_create_users_capability(): void
    {
        $manager_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_user_manager' ) );
        wp_set_current_user( $manager_id );

        $_POST['submit']                        = 'submit';
        $_POST['mhmrentiva_add_customer_nonce'] = wp_create_nonce( 'mhmrentiva_add_customer' );
        $_POST['customer_name']                  = 'Allowed Customer';
        $_POST['customer_email']                 = 'allowed-customer@example.com';

        ob_start();
        AddCustomerPage::render();
        ob_end_clean();

        $created = get_user_by( 'email', 'allowed-customer@example.com' );
        $this->assertNotFalse( $created, 'A user with create_users must be able to create a customer user.' );

        // Never a hardcoded password: a login attempt with the previously-hardcoded
        // guess (and other common guesses) must fail against the generated hash.
        $this->assertFalse( wp_check_password( 'demo123', $created->user_pass, $created->ID ) );
        $this->assertFalse( wp_check_password( 'password', $created->user_pass, $created->ID ) );
        $this->assertFalse( wp_check_password( '', $created->user_pass, $created->ID ) );
    }

    // test_ajax_add_customer_denied_without_create_users_capability() was
    // removed (WP.org T8 Görev 10b, row A12): it covered
    // AddCustomerPage::ajax_add_customer(), the wp_ajax_mhmrentiva_add_customer
    // wrapper -- zero consumer in either repo, deleted as dead code.
    // render()'s own inline POST handler is the live create-customer path;
    // its capability gate is covered by the two tests above.

    // --- Edit (CustomersPage::render -> render_customer_edit) -------------

    public function test_edit_customer_denied_without_edit_users_capability(): void
    {
        $target_id = self::factory()->user->create(
            array(
                'role'         => 'customer',
                'display_name' => 'Original Name',
            )
        );
        $capped_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_options_only' ) );
        wp_set_current_user( $capped_id );

        $_GET['customer_id']                      = (string) $target_id;
        $_POST['submit']                           = 'submit';
        $_POST['mhmrentiva_edit_customer_nonce']  = wp_create_nonce( 'mhmrentiva_edit_customer' );
        $_POST['customer_name']                    = 'Hacked Name';
        $_POST['customer_email']                   = 'hacked@example.com';

        $page = new CustomersPage();

        ob_start();
        try {
            $this->invokeRenderCustomerEdit( $page );
            $this->fail( 'Expected wp_die() to short-circuit editing for a user without edit_users.' );
        } catch ( \WPDieException $e ) {
            // Expected.
        } finally {
            ob_end_clean();
        }

        $target = get_user_by( 'id', $target_id );
        $this->assertSame( 'Original Name', $target->display_name, 'A user without edit_users must not be able to modify a customer user.' );
    }

    public function test_edit_customer_allowed_with_edit_users_capability(): void
    {
        $target_id = self::factory()->user->create(
            array(
                'role'         => 'customer',
                'display_name' => 'Original Name',
            )
        );
        $manager_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_user_manager' ) );
        wp_set_current_user( $manager_id );

        $_GET['customer_id']                      = (string) $target_id;
        $_POST['submit']                           = 'submit';
        $_POST['mhmrentiva_edit_customer_nonce']  = wp_create_nonce( 'mhmrentiva_edit_customer' );
        $_POST['customer_name']                    = 'Updated Name';
        $_POST['customer_email']                   = 'updated@example.com';

        $page = new CustomersPage();

        ob_start();
        $this->invokeRenderCustomerEdit( $page );
        ob_end_clean();

        $target = get_user_by( 'id', $target_id );
        $this->assertSame( 'Updated Name', $target->display_name, 'A user with edit_users must be able to modify a customer user.' );
    }

    // --- Batch update (CustomersOptimizer::batch_update_customers) --------

    public function test_batch_update_customers_denied_without_edit_users_capability(): void
    {
        $target_id = self::factory()->user->create(
            array(
                'role'         => 'customer',
                'display_name' => 'Original Batch Name',
            )
        );
        $capped_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_options_only' ) );
        wp_set_current_user( $capped_id );

        $result = CustomersOptimizer::batch_update_customers( array( $target_id ), array( 'name' => 'Hacked Batch Name' ) );

        $this->assertFalse( $result );
        $target = get_user_by( 'id', $target_id );
        $this->assertSame( 'Original Batch Name', $target->display_name );
    }

    public function test_batch_update_customers_allowed_with_edit_users_capability(): void
    {
        $target_id = self::factory()->user->create(
            array(
                'role'         => 'customer',
                'display_name' => 'Original Batch Name',
            )
        );
        $manager_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_user_manager' ) );
        wp_set_current_user( $manager_id );

        $result = CustomersOptimizer::batch_update_customers( array( $target_id ), array( 'name' => 'Updated Batch Name' ) );

        $this->assertTrue( $result );
        $target = get_user_by( 'id', $target_id );
        $this->assertSame( 'Updated Batch Name', $target->display_name );
    }

    // --- Delete (CustomersRestController::bulk_delete) ---------------------
    //
    // The route's permission_callback stays manage_options (B-G1e's job); this
    // proves the handler itself denies a delete_users-less caller even though
    // that caller clears the route-level gate.

    public function setUpRestServer(): void
    {
        add_action( 'rest_api_init', array( CustomersRestController::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action( 'rest_api_init', self::$server );
    }

    public function test_bulk_delete_denied_without_delete_users_capability(): void
    {
        $this->setUpRestServer();

        $target_id = self::factory()->user->create( array( 'role' => 'customer' ) );
        $capped_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_options_only' ) );
        wp_set_current_user( $capped_id );

        $request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/customers/bulk' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array( 'ids' => array( $target_id ) ) ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 403, $response->get_status(), 'A caller without delete_users must be denied even if the route-level check passed.' );
        $this->assertNotNull( get_user_by( 'id', $target_id ), 'The target user must not be deleted when the operation is denied.' );

        remove_action( 'rest_api_init', array( CustomersRestController::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = null;
    }

    public function test_bulk_delete_allowed_with_delete_users_capability(): void
    {
        $this->setUpRestServer();

        $target_id  = self::factory()->user->create( array( 'role' => 'customer' ) );
        $manager_id = self::factory()->user->create( array( 'role' => 'mhmrentiva_test_user_manager' ) );
        wp_set_current_user( $manager_id );

        $request = new WP_REST_Request( 'DELETE', '/mhm-rentiva/v1/customers/bulk' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( json_encode( array( 'ids' => array( $target_id ) ) ) );
        $response = self::$server->dispatch( $request );

        $this->assertSame( 200, $response->get_status() );
        $this->assertSame( 1, $response->get_data()['deleted'] );
        $this->assertFalse( get_user_by( 'id', $target_id ) );

        remove_action( 'rest_api_init', array( CustomersRestController::class, 'register_routes' ) );
        global $wp_rest_server;
        $wp_rest_server = null;
    }
}
