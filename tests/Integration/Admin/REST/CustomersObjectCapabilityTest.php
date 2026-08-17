<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\REST;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\REST\CustomersRestController;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * WordPress.org T8 #2 — object-level capability on the customer routes.
 *
 * The sibling suites already prove the caller needs delete_users / edit_users.
 * This one proves the other half, which is what the review was actually about:
 * holding the capability does not make every account on the site a valid target.
 * A caller with delete_users could hand /customers/bulk the ID of an editor or
 * of a second administrator, and the route would delete it.
 *
 * Both routes now ask two separate questions -- WordPress's per-target meta cap
 * (delete_user / edit_user) and whether the account is a Rentiva customer at all
 * (CustomerIdentity). These tests pin the second one; without it the guard
 * regresses to the blanket check silently, because the happy paths keep passing.
 */
final class CustomersObjectCapabilityTest extends WP_UnitTestCase
{
    private static WP_REST_Server $server;

    public function setUp(): void
    {
        parent::setUp();

        CustomerIdentity::flush_memo();

        add_action('rest_api_init', array(CustomersRestController::class, 'register_routes'));
        global $wp_rest_server;
        $wp_rest_server = new WP_REST_Server();
        self::$server   = $wp_rest_server;
        do_action('rest_api_init', self::$server);
    }

    public function tearDown(): void
    {
        remove_action('rest_api_init', array(CustomersRestController::class, 'register_routes'));
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_set_current_user(0);
        parent::tearDown();
    }

    /**
     * An account with no Rentiva footprint at all: not the customer role, no
     * bookings pointing at it, none of the plugin's user meta. Exactly the shape
     * the Customers screen wrongly lists and the review objected to.
     */
    private function createBystander(string $role): int
    {
        return (int) self::factory()->user->create(array('role' => $role));
    }

    private function createRealCustomer(): int
    {
        return (int) self::factory()->user->create(array('role' => 'customer'));
    }

    private function dispatchBulkDelete(array $ids): \WP_REST_Response
    {
        $request = new WP_REST_Request('DELETE', '/mhm-rentiva/v1/customers/bulk');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(array('ids' => $ids)));

        return self::$server->dispatch($request);
    }

    public function test_bulk_delete_refuses_an_editor_who_is_not_a_customer(): void
    {
        wp_set_current_user($this->createBystander('administrator'));
        $target_id = $this->createBystander('editor');

        $response = $this->dispatchBulkDelete(array($target_id));

        $this->assertSame(200, $response->get_status());
        $this->assertSame(0, $response->get_data()['deleted'], 'A non-customer must not be deleted.');
        $this->assertSame(1, $response->get_data()['skipped']);
        $this->assertNotFalse(get_user_by('id', $target_id), 'The bystander account must survive.');
    }

    public function test_bulk_delete_refuses_a_second_administrator(): void
    {
        wp_set_current_user($this->createBystander('administrator'));
        $target_id = $this->createBystander('administrator');

        $this->dispatchBulkDelete(array($target_id));

        $this->assertNotFalse(get_user_by('id', $target_id), 'A second administrator must survive a customer bulk delete.');
    }

    public function test_bulk_delete_deletes_customers_and_skips_bystanders_in_one_batch(): void
    {
        wp_set_current_user($this->createBystander('administrator'));
        $customer_id  = $this->createRealCustomer();
        $bystander_id = $this->createBystander('editor');

        $response = $this->dispatchBulkDelete(array($customer_id, $bystander_id));

        // The mixed batch is the case a per-batch check cannot express at all.
        $this->assertSame(1, $response->get_data()['deleted']);
        $this->assertSame(1, $response->get_data()['skipped']);
        $this->assertFalse(get_user_by('id', $customer_id));
        $this->assertNotFalse(get_user_by('id', $bystander_id));
    }

    /**
     * The other guard, on its own.
     *
     * Removing the CustomerIdentity clause turns four of these tests red, but
     * removing the current_user_can( 'delete_user', $id ) clause changed nothing
     * — on single site core maps that meta cap straight to delete_users, which
     * the caller has, so the two guards were not independently measured and the
     * suite would have reported the pair green with one of them deleted.
     *
     * Denying the meta cap for one specific target through map_meta_cap gives it
     * something to say on single site: the target is a genuine customer, so
     * CustomerIdentity allows it, and only the per-target capability can stop
     * the delete.
     */
    public function test_bulk_delete_honours_a_denied_per_target_meta_capability(): void
    {
        wp_set_current_user($this->createBystander('administrator'));
        $protected_id = $this->createRealCustomer();

        $deny = static function (array $caps, string $cap, int $user_id, array $args) use ($protected_id): array {
            if ('delete_user' === $cap && isset($args[0]) && (int) $args[0] === $protected_id) {
                return array('do_not_allow');
            }

            return $caps;
        };
        add_filter('map_meta_cap', $deny, 10, 4);

        try {
            $response = $this->dispatchBulkDelete(array($protected_id));
        } finally {
            remove_filter('map_meta_cap', $deny, 10);
        }

        $this->assertSame(0, $response->get_data()['deleted']);
        $this->assertNotFalse(
            get_user_by('id', $protected_id),
            'A customer the caller may not delete per-target must survive, even though CustomerIdentity accepts them.'
        );
    }

    public function test_detail_route_refuses_an_account_that_is_not_a_customer(): void
    {
        wp_set_current_user($this->createBystander('administrator'));
        $target_id = $this->createBystander('editor');

        $request  = new WP_REST_Request('GET', '/mhm-rentiva/v1/customers/' . $target_id);
        $response = self::$server->dispatch($request);

        // 404, not 403: a distinct status would make the route a probe for which
        // user IDs exist.
        $this->assertSame(404, $response->get_status());
    }

    public function test_detail_route_still_returns_a_real_customer(): void
    {
        wp_set_current_user($this->createBystander('administrator'));
        $customer_id = $this->createRealCustomer();

        $request  = new WP_REST_Request('GET', '/mhm-rentiva/v1/customers/' . $customer_id);
        $response = self::$server->dispatch($request);

        $this->assertSame(200, $response->get_status(), 'The guard must not cost a genuine customer their detail view.');
    }
}
