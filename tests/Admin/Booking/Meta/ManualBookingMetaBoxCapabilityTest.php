<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_Ajax_UnitTestCase;

/**
 * WP.org T4 #5 gap — ajax_create_booking() also creates a real WP user via
 * wp_create_user() whenever customer_id === 'new_customer'. Creating that
 * account is mandatory to the booking on this path (there is no fallback:
 * $customer stays null and the later wp_insert_post() reads $customer->ID),
 * so the whole operation must be denied for a caller lacking create_users.
 *
 * Both AJAX actions are reachable only from the booking creation screen. That
 * screen, its menu entry, and the booking CPT's create_posts capability all
 * require manage_options, so each AJAX boundary must enforce the same action
 * capability instead of trusting the screen to protect an edit_posts handler.
 */
final class ManualBookingMetaBoxCapabilityTest extends WP_Ajax_UnitTestCase
{
    use UserManagementCapabilities;

    protected $_last_response;

    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        // create_users is the capability this suite is about, and it is the one
        // capability with TWO multisite paths: super admin, or the network
        // option add_new_users. Turning the option on is the faithful choice
        // here -- it keeps the actor a plain administrator, so the DENIED
        // tests (roles without create_users) stay denied for exactly the
        // reason they always did, and it models the deployment where a site
        // owner really can add customers. No-op on a single site.
        $this->allow_site_admins_to_create_users();

        $this->vehicle_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle' ));
        update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
        update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

        remove_role('mhmrentiva_test_editposts_only');
        remove_role('mhmrentiva_test_booking_manager');
        add_role(
            'mhmrentiva_test_editposts_only',
            'MHM Test EditPosts Only',
            array(
                'read'       => true,
                'edit_posts' => true,
            )
        );
        add_role(
            'mhmrentiva_test_booking_manager',
            'MHM Test Booking Manager',
            array(
                'read'           => true,
                'edit_posts'     => true,
                'manage_options' => true,
            )
        );

        // WP_UnitTestCase restores $wp_filter to its pre-suite snapshot after
        // every test, and this action is only registered (in production) when
        // is_admin() was already true at plugin bootstrap — so it must be
        // re-registered here every test.
        ManualBookingMetaBox::register();
    }

    public function tearDown(): void
    {
        // Defensive: allow_site_admins_to_create_users() works through a hook, and
        // WP_UnitTestCase restores hooks after each test, so this is belt and
        // braces rather than load-bearing. It is kept because a future edit that
        // reaches for update_site_option() again would reintroduce a leak this
        // suite has already been bitten by once.
        $this->forbid_site_admins_from_creating_users();
        remove_role('mhmrentiva_test_editposts_only');
        remove_role('mhmrentiva_test_booking_manager');
        parent::tearDown();
    }

    private function dispatch_ajax(string $action = 'mhmrentiva_create_manual_booking'): void
    {
        try {
            $this->_handleAjax($action);
        } catch (\WPAjaxDieContinueException $e) {
            // Expected path for WP_Ajax_UnitTestCase.
        }
    }

    private function decode_response(): array
    {
        $decoded = json_decode((string) $this->_last_response, true);
        return is_array($decoded) ? $decoded : array();
    }

    public function test_ajax_create_booking_denied_new_customer_without_create_users_capability(): void
    {
        $capped_id = self::factory()->user->create(array( 'role' => 'mhmrentiva_test_booking_manager' ));
        wp_set_current_user($capped_id);

        $_POST['nonce']                   = wp_create_nonce('mhmrentiva_manual_booking_nonce');
        $_POST['vehicle_id']              = (string) $this->vehicle_id;
        $_POST['customer_id']             = 'new_customer';
        $_POST['pickup_date']             = '2099-01-01';
        $_POST['dropoff_date']            = '2099-01-03';
        $_POST['new_customer_first_name'] = 'Jane';
        $_POST['new_customer_last_name']  = 'Denied';
        $_POST['new_customer_email']      = 'denied-manual-customer@example.com';
        $_POST['new_customer_phone']      = '5551234567';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $this->assertFalse($response['success'] ?? true);
        $this->assertFalse(get_user_by('email', 'denied-manual-customer@example.com'));
    }

    public function test_ajax_create_booking_allowed_new_customer_with_create_users_capability(): void
    {
        $manager_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($manager_id);

        $_POST['nonce']                   = wp_create_nonce('mhmrentiva_manual_booking_nonce');
        $_POST['vehicle_id']              = (string) $this->vehicle_id;
        $_POST['customer_id']             = 'new_customer';
        $_POST['pickup_date']             = '2099-01-01';
        $_POST['pickup_time']             = '10:00';
        $_POST['dropoff_date']            = '2099-01-03';
        $_POST['dropoff_time']            = '10:00';
        $_POST['payment_type']            = 'full';
        $_POST['new_customer_first_name'] = 'Jane';
        $_POST['new_customer_last_name']  = 'Allowed';
        $_POST['new_customer_email']      = 'allowed-manual-customer@example.com';
        $_POST['new_customer_phone']      = '5559876543';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $created = get_user_by('email', 'allowed-manual-customer@example.com');
        $this->assertNotFalse($created, 'A user with create_users must be able to create a new customer account while creating a manual booking. Response: ' . (string) $this->_last_response);
        $this->assertTrue($response['success'] ?? false, 'Booking creation with a valid vehicle/dates/full payment should succeed end to end. Response: ' . (string) $this->_last_response);
    }

    public function test_ajax_create_booking_denies_edit_posts_only_caller(): void
    {
        $capped_id = self::factory()->user->create(array( 'role' => 'mhmrentiva_test_editposts_only' ));
        wp_set_current_user($capped_id);

        $existing_customer_id = self::factory()->user->create(array( 'role' => 'customer' ));

        $_POST['nonce']        = wp_create_nonce('mhmrentiva_manual_booking_nonce');
        $_POST['vehicle_id']   = (string) $this->vehicle_id;
        $_POST['customer_id']  = (string) $existing_customer_id;
        $_POST['pickup_date']  = '2099-02-01';
        $_POST['pickup_time']  = '10:00';
        $_POST['dropoff_date'] = '2099-02-03';
        $_POST['dropoff_time'] = '10:00';
        $_POST['payment_type'] = 'full';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $this->assertFalse($response['success'] ?? true, 'An edit_posts-only caller must not create bookings through the admin AJAX boundary. Response: ' . (string) $this->_last_response);
        $this->assertSame('Permission denied.', $response['data']['message'] ?? '');
    }

    public function test_ajax_create_booking_existing_customer_path_unaffected_by_create_users_gate(): void
    {
        $capped_id = self::factory()->user->create(array( 'role' => 'mhmrentiva_test_booking_manager' ));
        wp_set_current_user($capped_id);

        $existing_customer_id = self::factory()->user->create(array( 'role' => 'customer' ));

        $_POST['nonce']        = wp_create_nonce('mhmrentiva_manual_booking_nonce');
        $_POST['vehicle_id']   = (string) $this->vehicle_id;
        $_POST['customer_id']  = (string) $existing_customer_id;
        $_POST['pickup_date']  = '2099-03-01';
        $_POST['pickup_time']  = '10:00';
        $_POST['dropoff_date'] = '2099-03-03';
        $_POST['dropoff_time'] = '10:00';
        $_POST['payment_type'] = 'full';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $this->assertTrue($response['success'] ?? false, 'A manage_options caller must still create a booking for an existing customer without create_users. Response: ' . (string) $this->_last_response);
    }

    public function test_ajax_calculate_price_denies_edit_posts_only_caller(): void
    {
        $capped_id = self::factory()->user->create(array( 'role' => 'mhmrentiva_test_editposts_only' ));
        wp_set_current_user($capped_id);

        $_POST['nonce']        = wp_create_nonce('mhmrentiva_manual_booking_nonce');
        $_POST['vehicle_id']   = (string) $this->vehicle_id;
        $_POST['pickup_date']  = '2099-04-01';
        $_POST['pickup_time']  = '10:00';
        $_POST['dropoff_date'] = '2099-04-03';
        $_POST['dropoff_time'] = '10:00';
        $_POST['payment_type'] = 'full';

        $this->dispatch_ajax('mhmrentiva_calculate_manual_booking');
        $response = $this->decode_response();

        $this->assertFalse($response['success'] ?? true, 'An edit_posts-only caller must not calculate private booking prices through the admin AJAX boundary. Response: ' . (string) $this->_last_response);
        $this->assertSame('Permission denied.', $response['data']['message'] ?? '');
    }

    public function test_ajax_calculate_price_allows_administrator(): void
    {
        $manager_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($manager_id);

        $_POST['nonce']        = wp_create_nonce('mhmrentiva_manual_booking_nonce');
        $_POST['vehicle_id']   = (string) $this->vehicle_id;
        $_POST['pickup_date']  = '2099-05-01';
        $_POST['pickup_time']  = '10:00';
        $_POST['dropoff_date'] = '2099-05-03';
        $_POST['dropoff_time'] = '10:00';
        $_POST['payment_type'] = 'full';

        $this->dispatch_ajax('mhmrentiva_calculate_manual_booking');
        $response = $this->decode_response();

        $this->assertTrue($response['success'] ?? false, 'An administrator must retain the manual price calculation flow. Response: ' . (string) $this->_last_response);
    }
}
