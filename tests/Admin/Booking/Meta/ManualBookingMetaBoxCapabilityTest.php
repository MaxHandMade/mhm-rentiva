<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * WP.org T4 #5 gap — ajax_create_booking() also creates a real WP user via
 * wp_create_user() whenever customer_id === 'new_customer'. Creating that
 * account is mandatory to the booking on this path (there is no fallback:
 * $customer stays null and the later wp_insert_post() reads $customer->ID),
 * so the whole operation must be denied for a caller lacking create_users —
 * while the booking-creation path itself stays gated on edit_posts as before.
 */
final class ManualBookingMetaBoxCapabilityTest extends WP_Ajax_UnitTestCase
{
    protected $_last_response;

    private int $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->vehicle_id = self::factory()->post->create(array( 'post_type' => 'vehicle' ));
        update_post_meta($this->vehicle_id, '_mhmrentiva_vehicle_status', 'active');
        update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '100');

        remove_role('mhmrentiva_test_editposts_only');
        add_role(
            'mhmrentiva_test_editposts_only',
            'MHM Test EditPosts Only',
            array(
                'read'       => true,
                'edit_posts' => true,
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
        remove_role('mhmrentiva_test_editposts_only');
        parent::tearDown();
    }

    private function dispatch_ajax(): void
    {
        try {
            $this->_handleAjax('mhmrentiva_create_manual_booking');
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
        $capped_id = self::factory()->user->create(array( 'role' => 'mhmrentiva_test_editposts_only' ));
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

    public function test_ajax_create_booking_existing_customer_path_unaffected_by_create_users_gate(): void
    {
        // The targeted guard only applies to the new_customer branch; an
        // existing-customer booking must not be denied by the create_users gate.
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

        if (isset($response['data']['message'])) {
            $this->assertStringNotContainsString('create new customer accounts', (string) $response['data']['message']);
        }
        $this->assertTrue($response['success'] ?? false, 'Existing-customer booking should succeed without requiring create_users. Response: ' . (string) $this->_last_response);
    }
}
