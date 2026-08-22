<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\Core;

use MHMRentiva\Admin\Payment\Core\MoneyAuthorization;
use MHMRentiva\Admin\Payment\Refunds\Service;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Task 8 (spec §5): "may this actor move money on this booking?" used to live
 * at each call site rather than in one place -- CancellationHandler had its
 * own may_move_money(), and a brand new caller (Actions::refund_booking())
 * simply never asked at all. MoneyAuthorization is the single home for the
 * question; Service::process() and Service::processFullRefund() ask it as
 * their first statement, so every caller inherits the answer whether it
 * remembers to ask or not.
 */
final class MoneyAuthorizationTest extends WP_UnitTestCase
{
    use WooCommerceFixtures;

    private int $booking_id;
    private int $owner_id;
    private int $admin_id;
    private int $subscriber_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->require_woocommerce();

        $this->owner_id      = (int) self::factory()->user->create(array( 'role' => 'customer' ));
        $this->admin_id      = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
        $this->subscriber_id = (int) self::factory()->user->create(array( 'role' => 'subscriber' ));

        $this->booking_id = (int) self::factory()->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_payment_status', 'paid');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', $this->owner_id);

        $this->create_paid_order_for_booking($this->booking_id, '120');
    }

    public function test_it_refuses_an_unattributed_actor(): void
    {
        $this->assertFalse(MoneyAuthorization::mayMoveMoney($this->booking_id, 0));
    }

    public function test_a_filter_cannot_authorise_an_unattributed_actor(): void
    {
        add_filter('mhmrentiva_may_move_money', '__return_true');

        $this->assertFalse(
            MoneyAuthorization::mayMoveMoney($this->booking_id, 0),
            'The hard floor runs before the filter.'
        );
    }

    public function test_the_booking_customer_may_move_their_own_money(): void
    {
        $this->assertTrue(MoneyAuthorization::mayMoveMoney($this->booking_id, $this->owner_id));
    }

    public function test_an_administrator_may(): void
    {
        $this->assertTrue(MoneyAuthorization::mayMoveMoney($this->booking_id, $this->admin_id));
    }

    /**
     * The ambient current user is set to the administrator on purpose: a
     * user_can( $actor_id, ... ) -> current_user_can( ... ) regression inside
     * MoneyAuthorization would see the ambient admin and pass this test for
     * the wrong reason. The predicate must decide from the $actor_id argument
     * (the subscriber, who owns nothing here and holds no capability), never
     * from who is actually logged in.
     */
    public function test_a_subscriber_who_is_not_the_customer_may_not(): void
    {
        wp_set_current_user($this->admin_id);

        $this->assertFalse(MoneyAuthorization::mayMoveMoney($this->booking_id, $this->subscriber_id));
    }

    /**
     * The predicate measured at the money step itself, not at a call site --
     * the defect this task fixes was the predicate stopping at the call site
     * (Fable, slice 5): Actions::refund_booking() never asked at all. This
     * proves the gate is now the money step's own first statement.
     */
    public function test_the_service_refuses_a_refund_the_predicate_rejects(): void
    {
        $subscriber = self::factory()->user->create(array( 'role' => 'subscriber' ));

        $result = Service::processFullRefund($this->booking_id, '', $subscriber);

        $this->assertSame('0', $result['mhmrentiva_refund']);
        $this->assertSame(0, did_action('mhmrentiva_refund_completed'));
    }

    /**
     * The standing positive control for the test above (fix round 1, F7):
     * without it, a fixture that stopped being refundable for some unrelated
     * reason would make the refusal test pass for the wrong reason -- refused
     * because nothing was left to refund, not because the predicate rejected
     * the actor -- and nothing in the suite would notice. Fix round 1's
     * mutation run (Service.php's gate replaced with `if (false)`) is
     * one-off evidence that this fixture is sensitive to the gate; this test
     * is the version of that evidence that stays in the suite.
     */
    public function test_the_service_accepts_a_refund_the_predicate_allows(): void
    {
        $result = Service::processFullRefund($this->booking_id, '', $this->admin_id);

        $this->assertSame('1', $result['mhmrentiva_refund'], $result['mhmrentiva_refund_msg']);
    }
}
