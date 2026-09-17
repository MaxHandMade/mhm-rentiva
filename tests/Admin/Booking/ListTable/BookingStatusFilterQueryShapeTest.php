<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\ListTable;

use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Booking\ListTable\BookingColumns;
use WP_UnitTestCase;

/**
 * The status chips must not express the canonical status priority as a
 * meta_query.
 *
 * Measured on the dev site (2026-09-17): clicking "Pending" never returned.
 * The priority (new key wins, legacy key only when the new key is
 * absent/empty, pending when both are) nested OR / NOT EXISTS groups, and
 * WP_Meta_Query turns every `key = value` clause under an OR into its own
 * postmeta self-join WITHOUT a meta_key restriction in the ON clause. The
 * pending branch produced eight joins, five of them unrestricted. With 33
 * meta rows per booking that is 33^5 (about 39 million) intermediate rows
 * per booking -- MySQL was still "Sending data" after 77 seconds. The other
 * chips had three unrestricted joins (33^3), which is why only pending hung.
 *
 * The filter now resolves the matching IDs with the same COALESCE the count
 * uses and hands WP_Query a post__in. This test pins the shape: the list
 * query a chip produces carries no postmeta join at all, whatever the chip.
 * Row agreement with the count stays pinned by BookingStatsConsistencyTest.
 */
final class BookingStatusFilterQueryShapeTest extends WP_UnitTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $pending = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($pending, '_mhmrentiva_status', 'pending');

        $confirmed = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($confirmed, '_mhmrentiva_status', 'confirmed');

        set_current_screen('edit-mhmrentiva_booking');
    }

    public function tearDown(): void
    {
        set_current_screen('front');
        parent::tearDown();
    }

    private function filtered_query_for(string $status): \WP_Query
    {
        $q = new \WP_Query();
        $q->parse_query(array('post_type' => 'mhmrentiva_booking'));
        $q->set('mhmrentiva_booking_status', $status);
        $GLOBALS['wp_the_query'] = $q;
        $GLOBALS['wp_query']     = $q;

        BookingColumns::apply_status_filter($q);

        return $q;
    }

    public function test_no_chip_turns_the_list_query_into_postmeta_self_joins(): void
    {
        global $wpdb;

        foreach (Status::allowed() as $status) {
            $q = $this->filtered_query_for($status);

            $list = new \WP_Query(array(
                'post_type'      => 'mhmrentiva_booking',
                'posts_per_page' => 20,
                'fields'         => 'ids',
                'meta_query'     => $q->get('meta_query') ?: array(),
                'post__in'       => $q->get('post__in') ?: array(),
            ));

            $this->assertSame(
                0,
                substr_count($list->request, 'JOIN ' . $wpdb->postmeta),
                "The '$status' chip must not add postmeta joins to the list query:\n" . $list->request
            );
        }
    }

    public function test_a_chip_with_no_matching_booking_lists_nothing_instead_of_everything(): void
    {
        // An empty post__in means "no restriction" to WP_Query; the filter
        // must send array( 0 ) when nothing matches.
        $q = $this->filtered_query_for('no_show');

        $this->assertSame(array(0), $q->get('post__in'));
    }

    public function test_the_plate_filter_narrows_the_chip_instead_of_replacing_it(): void
    {
        // Both filters write post__in, and apply_custom_filters() runs after
        // apply_status_filter(). Before the status filter used post__in the
        // plate filter could simply set it; now it must intersect, or picking
        // "Pending" plus a plate would list that vehicle's bookings of EVERY
        // status.
        $vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_vehicle'));
        update_post_meta($vehicle, '_mhmrentiva_license_plate', '34PLATE99');

        $pending_on_vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($pending_on_vehicle, '_mhmrentiva_status', 'pending');
        update_post_meta($pending_on_vehicle, '_mhmrentiva_vehicle_id', (string) $vehicle);

        $confirmed_on_vehicle = self::factory()->post->create(array('post_type' => 'mhmrentiva_booking'));
        update_post_meta($confirmed_on_vehicle, '_mhmrentiva_status', 'confirmed');
        update_post_meta($confirmed_on_vehicle, '_mhmrentiva_vehicle_id', (string) $vehicle);

        $q = $this->filtered_query_for('pending');
        $q->set('mhmrentiva_license_plate', '34PLATE');
        BookingColumns::apply_custom_filters($q);

        $this->assertSame(array($pending_on_vehicle), $q->get('post__in'));

        $q = $this->filtered_query_for('cancelled');
        $q->set('mhmrentiva_license_plate', '34PLATE');
        BookingColumns::apply_custom_filters($q);

        $this->assertSame(array(0), $q->get('post__in'), 'No cancelled booking on that plate: list nothing');
    }

    public function test_an_unknown_status_value_leaves_the_query_unfiltered(): void
    {
        $q = $this->filtered_query_for('not-a-status');

        $this->assertEmpty($q->get('post__in'));
        $this->assertEmpty($q->get('meta_query'));
    }
}
