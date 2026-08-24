<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Actions;

use MHMRentiva\Admin\Booking\Actions\DepositManagementAjax;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Payment\Core\Money;
use MHMRentiva\Admin\PostTypes\Logs\PostType;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_Ajax_UnitTestCase;

/**
 * Spec §5.3, the second surface: DepositManagementAjax::cancel_booking() called
 * Status::update_status() directly, so the operator's own cancel button skipped
 * the cancellation metadata, the availability release, the e-mail and -- once
 * this slice exists -- the refund. Two surfaces, one entry point (decision 4).
 *
 * The screen keeps its own status precondition and its own log line; what it
 * loses is its private copy of "cancel a booking".
 */
final class DepositScreenCancellationTest extends WP_Ajax_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $admin_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->admin_id = (int) self::factory()->user->create(array( 'role' => 'administrator' ));

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');

        DepositManagementAjax::register();
    }

    public function tearDown(): void
    {
        $_POST = array();
        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function call_cancel(): array
    {
        wp_set_current_user($this->admin_id);

        $_POST = array(
            'nonce'      => wp_create_nonce('mhmrentiva_deposit_management_action'),
            'booking_id' => $this->booking_id,
        );

        try {
            $this->_handleAjax('mhmrentiva_deposit_cancel_booking');
        } catch (\WPAjaxDieContinueException | \WPAjaxDieStopException $e) {
            // wp_send_json_* terminates.
        }

        $decoded = json_decode($this->_last_response, true);

        return is_array($decoded) ? $decoded : array();
    }

    private function give_it_dates(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', (int) self::factory()->post->create(array(
            'post_type' => 'mhmrentiva_vehicle',
        )));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('+10 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('+12 days')));
    }

    public function test_the_operator_button_now_refunds(): void
    {
        $this->give_it_dates();
        $order = $this->create_paid_order_for_booking($this->booking_id, '120');

        $response = $this->call_cancel();

        $this->assertTrue((bool) ($response['success'] ?? false), 'Raw: ' . $this->_last_response);
        $this->assertSame(
            Money::toMinor('120'),
            Money::toMinor(wc_get_order($order->get_id())->get_total_refunded()),
            'The deposit screen cancel used to move nothing but a status meta.'
        );
    }

    /**
     * The customer's cancellation deadline is a customer policy. The operator
     * cancels with $force, so a pickup date in the past must not refuse.
     */
    public function test_the_customer_deadline_does_not_block_the_operator(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', (int) self::factory()->post->create(array(
            'post_type' => 'mhmrentiva_vehicle',
        )));
        update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', strtotime('-2 days')));
        update_post_meta($this->booking_id, '_mhmrentiva_dropoff_date', gmdate('Y-m-d', strtotime('-1 day')));

        $response = $this->call_cancel();

        $this->assertTrue((bool) ($response['success'] ?? false), 'Raw: ' . $this->_last_response);
        $this->assertSame(Status::CANCELLED, Status::get($this->booking_id));
    }

    /**
     * Addition C: dev bookings 9471 and 9474 carry no pickup date at all, and
     * before this task free_vehicle_availability()'s WP_Error was thrown,
     * rolled the transaction back and answered "Cancellation failed". The
     * release is bookkeeping; the cancellation is the operator's decision.
     */
    public function test_a_booking_without_dates_still_cancels_and_the_skip_is_logged(): void
    {
        $response = $this->call_cancel();

        $this->assertTrue((bool) ($response['success'] ?? false), 'Raw: ' . $this->_last_response);
        $this->assertSame(Status::CANCELLED, Status::get($this->booking_id));

        $logs = get_posts(array(
            'post_type'      => PostType::TYPE,
            'posts_per_page' => 5,
            'orderby'        => 'ID',
            'order'          => 'DESC',
            'post_status'    => 'publish',
        ));

        $found = false;
        foreach ($logs as $log) {
            if (false !== strpos($log->post_content, 'availability')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'Best-effort must not mean invisible: a skipped availability release has to leave a record.'
        );
    }

    /**
     * The booking stays in a status the screen's OWN precondition accepts
     * ('confirmed', set in setUp()) -- setting it to something the screen
     * itself rejects (e.g. 'completed') exercises the screen's private guard,
     * not the CancellationHandler::cancel_booking() call this task added, and
     * would pass identically if that call -- or the is_wp_error() branch
     * around it -- were deleted.
     *
     * Instead the handler itself is made to fail: this filter blocks the
     * '_mhmrentiva_status' meta write, so Status::update_status() returns
     * false, cancel_booking() throws inside its try block and returns its
     * own 'cancellation_failed' WP_Error. The message assertion is the part
     * that actually distinguishes the two guards: the screen's own rejection
     * reads "This booking cannot be cancelled.", the handler's reads
     * "Cancellation failed: ...".
     */
    public function test_a_handler_error_is_reported_as_a_json_error(): void
    {
        $block_status_write = static function ($check, $object_id, $meta_key) {
            return '_mhmrentiva_status' === $meta_key ? false : $check;
        };
        add_filter('update_post_metadata', $block_status_write, 10, 3);

        try {
            $response = $this->call_cancel();
        } finally {
            // Removed unconditionally so a failed assertion above cannot leak
            // this filter into a neighbouring test.
            remove_filter('update_post_metadata', $block_status_write, 10);
        }

        $this->assertFalse((bool) ($response['success'] ?? false), 'Raw: ' . $this->_last_response);
        $this->assertStringContainsString(
            'Cancellation failed',
            (string) ($response['data']['message'] ?? ''),
            'This must be CancellationHandler\'s own error, not the screen\'s "This booking cannot be cancelled." precondition -- otherwise the delegation this task added is never reached.'
        );
    }
}
