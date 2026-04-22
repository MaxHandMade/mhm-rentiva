<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes\Account;

use WP_UnitTestCase;
use WP_User;

class VendorBookingsTest extends WP_UnitTestCase
{
    public function test_vendor_without_listings_renders_empty_state(): void
    {
        $vendor_id = $this->factory->user->create();
        ( new WP_User( $vendor_id ) )->add_role( 'rentiva_vendor' );

        wp_set_current_user( $vendor_id );

        $output = do_shortcode( '[rentiva_vendor_bookings]' );

        $this->assertStringContainsString( 'You have no vehicle listings yet.', $output );
        $this->assertStringContainsString( 'mhm-rentiva-account-page', $output );
    }

    public function test_vendor_with_booking_renders_bookings_table(): void
    {
        $vendor_id = $this->factory->user->create();
        ( new WP_User( $vendor_id ) )->add_role( 'rentiva_vendor' );

        $vehicle_id = $this->factory->post->create(
            array(
                'post_type'   => 'vehicle',
                'post_status' => 'publish',
                'post_author' => $vendor_id,
                'post_title'  => 'Test Vehicle',
            )
        );

        $booking_id = $this->factory->post->create(
            array(
                'post_type'   => 'vehicle_booking',
                'post_status' => 'publish',
            )
        );

        update_post_meta( $booking_id, '_mhm_vehicle_id', (string) $vehicle_id );
        update_post_meta( $booking_id, '_mhm_status', 'confirmed' );
        update_post_meta( $booking_id, '_mhm_pickup_date', '2026-05-01 10:00:00' );
        update_post_meta( $booking_id, '_mhm_dropoff_date', '2026-05-03 10:00:00' );
        update_post_meta( $booking_id, '_mhm_customer_name', 'Jane Customer' );

        wp_set_current_user( $vendor_id );

        $output = do_shortcode( '[rentiva_vendor_bookings]' );

        $this->assertStringContainsString( 'vendor-bookings-table', $output );
        $this->assertStringContainsString( 'Test Vehicle', $output );
        $this->assertStringContainsString( 'Jane Customer', $output );
    }

    public function tearDown(): void
    {
        wp_set_current_user( 0 );
        parent::tearDown();
    }
}
