<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * WP.org T4 #8 sibling, revisited at T8 — modify_booking_title() /
 * get_booking_title_raw() build the admin "Title" column from DB-read customer
 * data (post meta / user profile / WooCommerce order fields) and hand it back
 * through the `the_title` filter. That output must be escaped exactly once so a
 * customer name/phone containing markup cannot inject live HTML into the booking
 * list table.
 *
 * T8 moved the escaping from the builder to the filter callback's own return
 * line. The behaviour did not change; where it is provable did. These tests
 * therefore assert through get_the_title(), the path WordPress actually prints,
 * so they keep measuring the same thing across that move.
 */
final class BookingColumnsTitleEscapingTest extends WP_UnitTestCase
{
    private int $booking_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->booking_id = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));

        // In production this is wired via Plugin::initialize_admin_services(),
        // which only runs when is_admin() is already true at bootstrap time —
        // before this test can flip admin context with set_current_screen().
        // WP_UnitTestCase snapshots $wp_filter once (on the very first test of
        // the whole run) and restores that snapshot after every test, so a
        // filter added here must be re-added every setUp() rather than once.
        BookingColumns::register();
    }

    /**
     * The builder's contract after T8: it returns raw text and the filter
     * callback escapes on its own return line. Asserting the rawness here is
     * what stops someone "fixing" the builder by re-adding esc_html() to it,
     * which would double-encode every booking title.
     */
    public function test_the_raw_builder_returns_unescaped_text(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_customer_first_name', '<script>alert(1)</script>');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_last_name', 'Doe');

        $raw = BookingColumns::get_booking_title_raw($this->booking_id);

        $this->assertStringContainsString('<script>', $raw);
        $this->assertStringNotContainsString('&lt;script&gt;', $raw);
    }

    /**
     * The anchor, unchanged in value from before the T8 restructuring: whatever
     * the internals do, a customer name carrying markup must reach the screen
     * escaped. This goes through get_the_title(), which is the path WordPress
     * actually prints, rather than through the helper.
     */
    public function test_the_title_filter_output_is_escaped_for_booking_list_screen(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_customer_first_name', '<script>alert(1)</script>');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_last_name', 'Doe');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_phone', '<b>555</b>');

        set_current_screen('edit-mhmrentiva_booking');

        // get_the_title() applies the `the_title` filter internally.
        $filtered = get_the_title($this->booking_id);

        $this->assertStringNotContainsString('<script>', $filtered);
        $this->assertStringNotContainsString('<b>', $filtered);
        $this->assertStringContainsString('&lt;script&gt;', $filtered);
        $this->assertStringContainsString('&lt;b&gt;555&lt;/b&gt;', $filtered);

        set_current_screen('front');
    }

    public function test_the_title_filter_escapes_exactly_once(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_customer_first_name', 'Marks & Spencer');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_last_name', '');

        set_current_screen('edit-mhmrentiva_booking');

        $filtered = get_the_title($this->booking_id);

        // Escaped exactly once: a single "&amp;", never "&amp;amp;".
        $this->assertStringContainsString('&amp;', $filtered);
        $this->assertStringNotContainsString('&amp;amp;', $filtered);

        set_current_screen('front');
    }

    /**
     * The passthrough branch. `the_title` fires for every title on the screen,
     * so a title this plugin does not touch has to come back byte-identical --
     * escaping it would double-encode content core already processed.
     */
    public function test_a_title_outside_the_booking_screen_is_passed_through_untouched(): void
    {
        $page_id = self::factory()->post->create(
            array(
                'post_type'  => 'page',
                'post_title' => 'Marks & Spencer',
            )
        );

        set_current_screen('edit-mhmrentiva_booking');

        // Not a byte comparison against the stored title: core hooks its own
        // callbacks onto `the_title` (wptexturize turns the ampersand into
        // &#038;), so the value legitimately differs from what was saved. The
        // claim is narrower and is the one that matters -- our callback must
        // make no difference at all to a title that is not ours.
        $callback = array(BookingColumns::class, 'modify_booking_title');

        remove_filter('the_title', $callback, 10);
        $without_us = get_the_title($page_id);
        add_filter('the_title', $callback, 10, 2);
        $with_us = get_the_title($page_id);

        $this->assertSame($without_us, $with_us);

        set_current_screen('front');
    }
}
