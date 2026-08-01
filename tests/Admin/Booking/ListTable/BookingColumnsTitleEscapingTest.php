<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * WP.org T4 #8 sibling — modify_booking_title()/get_booking_title_display()
 * build the admin "Title" column from DB-read customer data (post meta / user
 * profile / WooCommerce order fields) and hand it back through the `the_title`
 * filter. That output must be escaped exactly once so a customer name/phone
 * containing markup cannot inject live HTML into the booking list table.
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

    public function test_get_booking_title_display_escapes_malicious_customer_name(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_customer_first_name', '<script>alert(1)</script>');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_last_name', 'Doe');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_phone', '<b>555</b>');

        $title = BookingColumns::get_booking_title_display($this->booking_id);

        $this->assertStringNotContainsString('<script>', $title);
        $this->assertStringNotContainsString('<b>', $title);
        $this->assertStringContainsString('&lt;script&gt;', $title);
        $this->assertStringContainsString('&lt;b&gt;555&lt;/b&gt;', $title);
    }

    public function test_get_booking_title_display_does_not_double_escape(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_customer_first_name', 'Marks & Spencer');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_last_name', '');

        $title = BookingColumns::get_booking_title_display($this->booking_id);

        // Escaped exactly once: a single "&amp;", never "&amp;amp;".
        $this->assertStringContainsString('&amp;', $title);
        $this->assertStringNotContainsString('&amp;amp;', $title);
    }

    public function test_the_title_filter_output_is_escaped_for_booking_list_screen(): void
    {
        update_post_meta($this->booking_id, '_mhmrentiva_customer_first_name', '<script>alert(1)</script>');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_last_name', 'Doe');

        set_current_screen('edit-mhmrentiva_booking');

        // get_the_title() applies the `the_title` filter internally.
        $filtered = get_the_title($this->booking_id);

        $this->assertStringNotContainsString('<script>', $filtered);
        $this->assertStringContainsString('&lt;script&gt;', $filtered);

        set_current_screen('front');
    }
}
