<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * M-1 class, twelfth member: save_meta() runs on EVERY post type.
 *
 * BookingMeta::save_meta() is hooked to the untyped `save_post` (BookingMeta.php:96),
 * not to `save_post_mhmrentiva_booking`. Its guards are autosave/revision, a nonce, and
 * current_user_can( 'edit_post' ) — none of which say anything about what KIND of post
 * is being saved.
 *
 * The nonce does not close it either. verified_save_request() accepts the plugin's own
 * nonce OR core's `_wpnonce` for 'update-post_{id}', and that second one is present on
 * every classic-editor save of every post type. So an editor saving their own page, with
 * mhmrentiva_edit_status in the request, reached Status::update_status() on a post that
 * is not a booking: booking status and history written onto the wrong object, and the
 * side effects of a status transition fired with it.
 *
 * Not a privilege escalation — the caller owns the post and holds edit_post for it. It
 * is a data-integrity defect, and it is the busiest member of its class precisely
 * because it is the one hooked to every save.
 */
final class BookingMetaPostTypeTest extends WP_UnitTestCase
{
    private int $editor_id;

    public function setUp(): void
    {
        parent::setUp();

        $this->editor_id = self::factory()->user->create(array( 'role' => 'administrator' ));
        wp_set_current_user($this->editor_id);
    }

    public function tearDown(): void
    {
        $_POST = array();
        wp_set_current_user(0);
        parent::tearDown();
    }

    /**
     * Fills $_POST the way a classic-editor save of $post_id would, carrying booking
     * fields alongside core's own nonce.
     */
    private function stage_classic_editor_save(int $post_id): void
    {
        $_POST = array(
            '_wpnonce'                     => wp_create_nonce('update-post_' . $post_id),
            'mhmrentiva_edit_status'       => 'confirmed',
            'mhmrentiva_edit_pickup_date'  => '2026-01-01',
            'mhmrentiva_edit_guests'       => '3',
        );
    }

    /**
     * @dataProvider foreign_post_types
     */
    public function test_save_meta_writes_nothing_to_a_post_that_is_not_a_booking(string $post_type): void
    {
        $post_id = self::factory()->post->create(array( 'post_type' => $post_type ));
        $this->stage_classic_editor_save($post_id);

        BookingMeta::save_meta($post_id, get_post($post_id));

        $this->assertSame(
            '',
            (string) get_post_meta($post_id, '_mhmrentiva_status', true),
            "A {$post_type} must not receive booking status meta."
        );
        $this->assertSame(
            '',
            (string) get_post_meta($post_id, '_mhmrentiva_pickup_date', true),
            "A {$post_type} must not receive booking date meta."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function foreign_post_types(): array
    {
        return array(
            'page'    => array( 'page' ),
            'post'    => array( 'post' ),
            'vehicle' => array( 'mhmrentiva_vehicle' ),
        );
    }

    /**
     * Negative control for the fix. A destructive sweep needs one: a post-type check
     * that rejects everything would pass every assertion above while breaking the
     * feature this handler exists for.
     */
    public function test_save_meta_still_writes_for_a_real_booking(): void
    {
        $booking_id = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
        $this->stage_classic_editor_save($booking_id);

        BookingMeta::save_meta($booking_id, get_post($booking_id));

        $this->assertSame(
            '2026-01-01',
            (string) get_post_meta($booking_id, '_mhmrentiva_pickup_date', true),
            'A real booking must still be saved by this handler.'
        );
    }
}
