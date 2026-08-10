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
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->create_booking('pending');
        $this->create_booking('pending');
        $this->create_booking('completed');
        wp_cache_delete('mhmrentiva_booking_stats');

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
