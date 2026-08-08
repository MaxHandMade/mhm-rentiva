<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * ajax_create_customer_account() performs two privileged operations: it creates
 * a real WordPress user and links that user to a specific booking. The request
 * therefore needs both create_users and permission to edit the named booking.
 */
final class BookingPortalMetaBoxCapabilityTest extends WP_Ajax_UnitTestCase
{
    protected $_last_response;

    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));

        remove_role('mhmrentiva_test_editposts_only');
        remove_role('mhmrentiva_test_user_creator');
        add_role(
            'mhmrentiva_test_editposts_only',
            'MHM Test EditPosts Only',
            array(
                'read'       => true,
                'edit_posts' => true,
            )
        );
        add_role(
            'mhmrentiva_test_user_creator',
            'MHM Test User Creator',
            array(
                'read'         => true,
                'create_users' => true,
            )
        );

        // WP_UnitTestCase restores $wp_filter to its pre-suite snapshot after
        // every test, and this filter is only registered (in production) when
        // is_admin() was already true at plugin bootstrap — so it must be
        // re-registered here every test.
        BookingPortalMetaBox::register();
    }

    public function tearDown(): void
    {
        remove_role('mhmrentiva_test_editposts_only');
        remove_role('mhmrentiva_test_user_creator');
        parent::tearDown();
    }

    private function dispatch_ajax(): void
    {
        try {
            $this->_handleAjax('mhmrentiva_create_customer_account_manual');
        } catch (\WPAjaxDieContinueException $e) {
            // Expected path for WP_Ajax_UnitTestCase.
        }
    }

    private function decode_response(): array
    {
        $decoded = json_decode((string) $this->_last_response, true);
        return is_array($decoded) ? $decoded : array();
    }

    public function test_ajax_create_customer_account_denied_without_create_users_capability(): void
    {
        $capped_id = self::factory()->user->create(array( 'role' => 'mhmrentiva_test_editposts_only' ));
        wp_set_current_user($capped_id);

        $_POST['nonce']      = wp_create_nonce('mhmrentiva_create_customer_account');
        $_POST['booking_id'] = (string) $this->booking_id;
        $_POST['email']      = 'denied-portal-customer@example.com';
        $_POST['name']       = 'Denied Portal Customer';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $this->assertFalse($response['success'] ?? true);
        $this->assertFalse(get_user_by('email', 'denied-portal-customer@example.com'));
    }

    public function test_ajax_create_customer_account_allowed_with_create_users_capability(): void
    {
        $manager_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($manager_id);

        $_POST['nonce']      = wp_create_nonce('mhmrentiva_create_customer_account');
        $_POST['booking_id'] = (string) $this->booking_id;
        $_POST['email']      = 'allowed-portal-customer@example.com';
        $_POST['name']       = 'Allowed Portal Customer';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $this->assertTrue($response['success'] ?? false);

        $created = get_user_by('email', 'allowed-portal-customer@example.com');
        $this->assertNotFalse($created, 'An administrator with create_users and booking edit permission must be able to create a customer account.');
    }

    public function test_ajax_create_customer_account_denies_user_creator_without_booking_edit_permission(): void
    {
        $user_creator_id = self::factory()->user->create(array( 'role' => 'mhmrentiva_test_user_creator' ));
        wp_set_current_user($user_creator_id);

        $this->assertTrue(current_user_can('create_users'), 'Precondition: the caller can create users.');
        $this->assertFalse(current_user_can('edit_post', $this->booking_id), 'Precondition: the caller cannot edit this booking.');

        $_POST['nonce']      = wp_create_nonce('mhmrentiva_create_customer_account');
        $_POST['booking_id'] = (string) $this->booking_id;
        $_POST['email']      = 'booking-denied-portal-customer@example.com';
        $_POST['name']       = 'Booking Denied Portal Customer';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $this->assertFalse($response['success'] ?? true, 'create_users alone must not authorize a write to a booking the caller cannot edit.');
        $this->assertFalse(get_user_by('email', 'booking-denied-portal-customer@example.com'));
        $this->assertSame('', get_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', true));
    }

    public function test_ajax_create_customer_account_rejects_non_booking_target(): void
    {
        $manager_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        $post_id    = self::factory()->post->create(array( 'post_type' => 'post' ));
        wp_set_current_user($manager_id);

        $_POST['nonce']      = wp_create_nonce('mhmrentiva_create_customer_account');
        $_POST['booking_id'] = (string) $post_id;
        $_POST['email']      = 'non-booking-portal-customer@example.com';
        $_POST['name']       = 'Non Booking Portal Customer';

        $this->dispatch_ajax();
        $response = $this->decode_response();

        $this->assertFalse($response['success'] ?? true);
        $this->assertSame('Booking not found.', $response['data']['message'] ?? '');
        $this->assertFalse(get_user_by('email', 'non-booking-portal-customer@example.com'));
    }
}
