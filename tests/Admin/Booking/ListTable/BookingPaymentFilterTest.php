<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\Core\PaymentView;
use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * The payment filter lists exactly the bookings whose row badge shows the
 * chosen payment status.
 *
 * The filter used to offer unpaid / paid / refunded / failed and compare them
 * to the raw stored value. Bookings are stored as pending (never "unpaid"), a
 * deposit booking with a balance is stored as paid but its badge reads
 * "Partially paid", and partially refunded / cancelled could not be picked at
 * all. The badge comes from PaymentView::status(); the filter now resolves the
 * same status in SQL and offers only the statuses bookings actually have.
 */
final class BookingPaymentFilterTest extends WP_UnitTestCase
{
    /** @var array<string, int> */
    private array $ids = array();

    public function setUp(): void
    {
        parent::setUp();

        $this->ids['pending']            = $this->booking(array('_mhmrentiva_payment_status' => 'pending'));
        $this->ids['paid_full']          = $this->booking(array(
            '_mhmrentiva_payment_status'   => 'paid',
            '_mhmrentiva_payment_type'     => 'full',
            '_mhmrentiva_remaining_amount' => '0',
        ));
        $this->ids['deposit_balance']    = $this->booking(array(
            '_mhmrentiva_payment_status'   => 'paid',
            '_mhmrentiva_payment_type'     => 'deposit',
            '_mhmrentiva_remaining_amount' => '150.50',
        ));
        $this->ids['deposit_settled']    = $this->booking(array(
            '_mhmrentiva_payment_status'   => 'paid',
            '_mhmrentiva_payment_type'     => 'deposit',
            '_mhmrentiva_remaining_amount' => '0.00',
        ));
        $this->ids['no_meta']            = $this->booking(array());
        $this->ids['legacy_completed']   = $this->booking(array('_mhmrentiva_booking_payment_status' => 'completed'));
        $this->ids['legacy_on_hold']     = $this->booking(array('_mhmrentiva_payment_status' => 'on-hold'));
        $this->ids['legacy_processing']  = $this->booking(array('_mhmrentiva_payment_status' => 'processing'));
        $this->ids['legacy_deposit']     = $this->booking(array(
            '_mhmrentiva_booking_payment_status' => 'completed',
            '_mhmrentiva_payment_type'           => 'deposit',
            '_mhmrentiva_remaining_amount'       => '75',
        ));
        $this->ids['partially_refunded'] = $this->booking(array('_mhmrentiva_payment_status' => 'partially_refunded'));
        $this->ids['cancelled']          = $this->booking(array('_mhmrentiva_payment_status' => 'cancelled'));

        $trashed = $this->booking(array('_mhmrentiva_payment_status' => 'pending'));
        wp_trash_post($trashed);

        set_current_screen('edit-mhmrentiva_booking');
    }

    public function tearDown(): void
    {
        set_current_screen('front');
        parent::tearDown();
    }

    /**
     * @param array<string, string> $meta
     */
    private function booking(array $meta): int
    {
        $id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        foreach ($meta as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        return $id;
    }

    private function filtered(string $payment_status, string $booking_status = ''): \WP_Query
    {
        $q = new \WP_Query();
        $q->parse_query(array('post_type' => 'mhmrentiva_booking'));
        $q->set('mhmrentiva_payment_status', $payment_status);
        if ('' !== $booking_status) {
            $q->set('mhmrentiva_booking_status', $booking_status);
        }
        $GLOBALS['wp_the_query'] = $q;
        $GLOBALS['wp_query']     = $q;

        BookingColumns::apply_status_filter($q);

        return $q;
    }

    /**
     * @return list<int>
     */
    private function listed(string $payment_status): array
    {
        $ids = array_map('intval', (array) $this->filtered($payment_status)->get('post__in'));
        sort($ids);

        return $ids;
    }

    /**
     * @param list<string> $keys
     * @return list<int>
     */
    private function expected(array $keys): array
    {
        $ids = array();
        foreach ($keys as $key) {
            $ids[] = $this->ids[$key];
        }
        sort($ids);

        return $ids;
    }

    public function test_each_payment_status_lists_the_bookings_whose_badge_shows_it(): void
    {
        $this->assertSame($this->expected(array('pending', 'legacy_on_hold', 'legacy_processing')), $this->listed('pending'));
        $this->assertSame($this->expected(array('paid_full', 'deposit_settled', 'legacy_completed')), $this->listed('paid'));
        $this->assertSame($this->expected(array('deposit_balance', 'legacy_deposit')), $this->listed('partially_paid'));
        $this->assertSame($this->expected(array('no_meta')), $this->listed('unpaid'));
        $this->assertSame($this->expected(array('partially_refunded')), $this->listed('partially_refunded'));
        $this->assertSame($this->expected(array('cancelled')), $this->listed('cancelled'));
    }

    public function test_the_filter_agrees_with_the_row_badge_for_every_booking(): void
    {
        foreach ($this->ids as $name => $id) {
            $status = PaymentView::status($id);
            $this->assertContains($id, $this->listed($status), "$name (badge '$status') must be listed under its own status");
        }
    }

    public function test_a_status_no_booking_has_lists_nothing(): void
    {
        $this->assertSame(array(0), $this->filtered('failed')->get('post__in'));
    }

    public function test_an_unknown_value_leaves_the_query_unfiltered(): void
    {
        $q = $this->filtered('not-a-status');

        $this->assertEmpty($q->get('post__in'));
        $this->assertEmpty($q->get('meta_query'));
    }

    public function test_the_payment_filter_narrows_the_status_chip_instead_of_replacing_it(): void
    {
        update_post_meta($this->ids['pending'], '_mhmrentiva_status', 'confirmed');
        update_post_meta($this->ids['legacy_on_hold'], '_mhmrentiva_status', 'cancelled');

        $this->assertSame(array($this->ids['pending']), $this->filtered('pending', 'confirmed')->get('post__in'));
        $this->assertSame(array(0), $this->filtered('partially_paid', 'cancelled')->get('post__in'));
    }

    public function test_the_trash_view_filters_trashed_bookings(): void
    {
        $q = new \WP_Query();
        $q->parse_query(array('post_type' => 'mhmrentiva_booking', 'post_status' => 'trash'));
        $q->set('mhmrentiva_payment_status', 'pending');
        $GLOBALS['wp_the_query'] = $q;
        $GLOBALS['wp_query']     = $q;

        BookingColumns::apply_status_filter($q);

        $ids = (array) $q->get('post__in');
        $this->assertCount(1, $ids);
        $this->assertSame('trash', get_post_status((int) $ids[0]));
    }

    public function test_a_sub_cent_balance_is_still_partially_paid(): void
    {
        $id = $this->booking(array(
            '_mhmrentiva_payment_status'   => 'paid',
            '_mhmrentiva_payment_type'     => 'deposit',
            '_mhmrentiva_remaining_amount' => '0.004',
        ));

        $this->assertSame('partially_paid', PaymentView::status($id));
        $this->assertContains($id, $this->listed('partially_paid'));
    }

    public function test_the_trash_view_dropdown_offers_the_trashed_statuses(): void
    {
        $refunded = $this->booking(array('_mhmrentiva_payment_status' => 'refunded'));
        wp_trash_post($refunded);

        set_query_var('post_status', 'trash');
        ob_start();
        BookingColumns::status_filter('mhmrentiva_booking');
        $html = (string) ob_get_clean();
        set_query_var('post_status', '');

        $this->assertStringContainsString('value="refunded"', $html);
    }

    public function test_the_list_query_carries_no_postmeta_join(): void
    {
        global $wpdb;

        $q    = $this->filtered('pending');
        $list = new \WP_Query(array(
            'post_type'      => 'mhmrentiva_booking',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'meta_query'     => $q->get('meta_query') ?: array(),
            'post__in'       => $q->get('post__in') ?: array(),
        ));

        $this->assertSame(0, substr_count($list->request, 'JOIN ' . $wpdb->postmeta), $list->request);
    }

    public function test_the_dropdown_offers_only_the_statuses_bookings_have(): void
    {
        ob_start();
        BookingColumns::status_filter('mhmrentiva_booking');
        $html = (string) ob_get_clean();

        foreach (array('unpaid', 'pending', 'paid', 'partially_paid', 'partially_refunded', 'cancelled') as $status) {
            $this->assertStringContainsString('value="' . $status . '"', $html, $status);
        }
        $this->assertStringNotContainsString('value="failed"', $html);
        $this->assertStringNotContainsString('value="refunded"', $html);
        $this->assertStringNotContainsString('value="completed"', $html, 'Legacy values are shown under their current status');
    }
}
