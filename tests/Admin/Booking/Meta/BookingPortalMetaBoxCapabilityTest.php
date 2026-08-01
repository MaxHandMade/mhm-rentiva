<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * WP.org T4 #5 gap — ajax_create_customer_account() creates a real WP user via
 * wp_create_user(). Its sole purpose is that account creation, so it must be
 * gated on create_users (WP user-management capability), not the much more
 * common edit_posts.
 */
final class BookingPortalMetaBoxCapabilityTest extends WP_Ajax_UnitTestCase
{
    protected $_last_response;

    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->booking_id = self::factory()->post->create(array('post_type' => 'vehicle_booking'));

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
        // every test, and this filter is only registered (in production) when
        // is_admin() was already true at plugin bootstrap — so it must be
        // re-registered here every test.
        BookingPortalMetaBox::register();
    }

    public function tearDown(): void
    {
        remove_role('mhmrentiva_test_editposts_only');
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
        $this->assertNotFalse($created, 'A user with create_users must be able to create a customer account.');
    }
}
