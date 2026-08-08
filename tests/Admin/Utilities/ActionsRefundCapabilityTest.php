<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Utilities;

use MHMRentiva\Admin\Utilities\Actions\Actions;
use WP_UnitTestCase;

/**
 * Refunds are financial mutations of a booking and must use the booking CPT's
 * object capability. A legacy ownership meta value is not authorization.
 */
final class ActionsRefundCapabilityTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $_GET     = array();
        $_POST    = array();
        $_REQUEST = array();

        add_filter(
            'wp_redirect',
            static function (string $location): string {
                throw new \RuntimeException('Refund path reached redirect: ' . $location);
            }
        );
    }

    public function test_legacy_booking_owner_cannot_refund_without_edit_post_permission(): void
    {
        $booking_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        $customer_id = self::factory()->user->create(array( 'role' => 'subscriber' ));
        update_post_meta($booking_id, '_mhmrentiva_user_id', $customer_id);
        wp_set_current_user($customer_id);

        $this->assertFalse(current_user_can('edit_post', $booking_id), 'Precondition: the customer cannot edit the booking.');

        $_POST['_wpnonce']     = wp_create_nonce('mhmrentiva_refund_booking');
        $_POST['booking_id']   = (string) $booking_id;
        $_POST['amount_kurus'] = '100';
        $_REQUEST              = $_POST;

        try {
            Actions::refund_booking();
            $this->fail('Expected the refund handler to deny a caller without edit_post.');
        } catch (\WPDieException $e) {
            $this->assertStringContainsString('permission', strtolower($e->getMessage()));
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function test_refund_rejects_non_booking_post_before_service_dispatch(): void
    {
        $admin_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        $post_id  = self::factory()->post->create(array( 'post_type' => 'post' ));
        wp_set_current_user($admin_id);

        $_POST['_wpnonce']     = wp_create_nonce('mhmrentiva_refund_booking');
        $_POST['booking_id']   = (string) $post_id;
        $_POST['amount_kurus'] = '100';
        $_REQUEST              = $_POST;

        try {
            Actions::refund_booking();
            $this->fail('Expected the refund handler to reject a non-booking target.');
        } catch (\WPDieException $e) {
            $this->assertStringContainsString('booking', strtolower($e->getMessage()));
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage());
        }
    }

    public function test_administrator_can_reach_refund_service_for_booking(): void
    {
        $admin_id   = self::factory()->user->create(array( 'role' => 'administrator' ));
        $booking_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        wp_set_current_user($admin_id);

        $_POST['_wpnonce']     = wp_create_nonce('mhmrentiva_refund_booking');
        $_POST['booking_id']   = (string) $booking_id;
        $_POST['amount_kurus'] = '100';
        $_REQUEST              = $_POST;

        try {
            Actions::refund_booking();
            $this->fail('Expected the test redirect interceptor to stop the handler.');
        } catch (\WPDieException $e) {
            $this->fail('An administrator editing a booking was unexpectedly denied: ' . $e->getMessage());
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('mhmrentiva_refund=0', $e->getMessage());
        }
    }
}
