<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * Task 3 of the Faz 1a list transform: the status dropdown becomes a chip
 * strip. The URL contract is unchanged — chips carry the same registered
 * `mhmrentiva_booking_status` public query var the dropdown used, so
 * bookmarks keep working and apply_status_filter() needs no change. The
 * dropdown itself must be gone (one filter UI, not two); the payment and
 * gateway dropdowns stay.
 */
final class BookingStatusChipsTest extends WP_UnitTestCase
{
    private function create_booking(string $status): void
    {
        $id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($id, '_mhmrentiva_status', $status);
        // The gateway dropdown only renders for gateways in actual use.
        update_post_meta($id, '_mhmrentiva_payment_gateway', 'woocommerce');
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->create_booking('pending');
        $this->create_booking('pending');
        $this->create_booking('completed');
        wp_cache_delete('mhmrentiva_booking_stats');
        wp_cache_delete('mhmrentiva_booking_gateways_in_use');

        global $pagenow, $post_type;
        $pagenow   = 'edit.php';
        $post_type = 'mhmrentiva_booking';
    }

    public function tearDown(): void
    {
        global $pagenow, $post_type;
        $pagenow   = 'index.php';
        $post_type = null;
        parent::tearDown();
    }

    public function test_status_dropdown_is_gone_but_payment_dropdowns_stay(): void
    {
        ob_start();
        BookingColumns::status_filter('mhmrentiva_booking');
        $html = ob_get_clean();

        $this->assertStringNotContainsString('name="mhmrentiva_booking_status"', $html);
        $this->assertStringContainsString('name="mhmrentiva_payment_status"', $html);
        $this->assertStringContainsString('name="mhmrentiva_payment_gateway"', $html);
    }

    public function test_chips_render_links_with_registered_query_var_and_canonical_counts(): void
    {
        ob_start();
        BookingColumns::status_chips();
        $html = ob_get_clean();

        $this->assertStringContainsString('mhmrentiva_booking_status=pending', $html);
        $this->assertStringContainsString('mhmrentiva_booking_status=completed', $html);
        // Non-empty fixture counts (2 pending / 1 completed / 3 total).
        $this->assertStringContainsString('rv-bkl-chip__count">2<', $html);
        $this->assertStringContainsString('rv-bkl-chip__count">3<', $html);
        // "All" chip is active when no status is selected.
        $this->assertMatchesRegularExpression('/rv-bkl-chip[^"]*is-active/', $html);
        // Raw superglobals are never read: links are plain hrefs, output escaped.
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_pending_filter_matches_the_pending_count_semantics(): void
    {
        // The canonical count folds status-less bookings into pending
        // (COALESCE). The chip's filter must agree, or "Beklemede 2" clicks
        // through to an empty list (measured live: IDs with no status meta).
        $statusless = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));

        set_current_screen('edit-mhmrentiva_booking');

        $q = new \WP_Query();
        $q->parse_query(array('post_type' => 'mhmrentiva_booking'));
        $q->set('mhmrentiva_booking_status', 'pending');
        $GLOBALS['wp_the_query'] = $q;
        $GLOBALS['wp_query']     = $q;

        BookingColumns::apply_status_filter($q);

        $found = get_posts(array(
            'post_type'      => 'mhmrentiva_booking',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $q->get('meta_query'),
        ));

        $this->assertContains($statusless, $found, 'Status-less booking must match the pending filter');
        $this->assertCount(3, $found, '2 explicit pending + 1 status-less');

        set_current_screen('front');
    }

    public function test_gateway_dropdown_offers_only_gateways_bookings_actually_use(): void
    {
        // WC registers many gateways (bacs/cheque/cod/sandbox...); offering
        // them all is noise AND the old apply logic silently ignored every
        // value except 'woocommerce'. The dropdown must enumerate the
        // DISTINCT gateway values present on bookings.
        $paid = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($paid, '_mhmrentiva_payment_gateway', 'woocommerce');
        wp_cache_delete('mhmrentiva_booking_gateways_in_use');

        ob_start();
        BookingColumns::status_filter('mhmrentiva_booking');
        $html = ob_get_clean();

        $this->assertStringContainsString('value="woocommerce"', $html);
        $this->assertStringNotContainsString('value="bacs"', $html);
        $this->assertStringNotContainsString('value="cheque"', $html);
        $this->assertStringNotContainsString('value="cod"', $html);
    }

    public function test_chips_render_nothing_off_the_booking_list_screen(): void
    {
        global $pagenow;
        $pagenow = 'index.php';

        ob_start();
        BookingColumns::status_chips();
        $html = ob_get_clean();

        $this->assertSame('', $html);
    }
}
