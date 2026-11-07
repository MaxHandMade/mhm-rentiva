<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox;
use WP_UnitTestCase;

/**
 * Customer account box on the booking edit screen (PR 3b-1, canvas v135).
 *
 * One way to the customer: the WP profile. The "Customer Account Page" button
 * opened the operator's own My Account in a new tab, not the customer's -- it
 * is gone. Country / state sits under the e-mail when the account has one.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingPortalMetaBox::render
 */
final class BookingPortalMetaBoxRenderTest extends WP_UnitTestCase
{
	private function render_for( int $user_id ): string
	{
		$booking = self::factory()->post->create_and_get( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking->ID, '_mhmrentiva_customer_user_id', $user_id );

		ob_start();
		BookingPortalMetaBox::render( $booking );
		return (string) ob_get_clean();
	}

	public function test_box_has_one_profile_link_and_no_account_page_link(): void
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		$html = $this->render_for( $user );

		$this->assertStringNotContainsString( 'Customer Account Page', $html );
		$this->assertSame( 1, substr_count( $html, 'user-edit.php?user_id=' . $user ) );
		$this->assertStringContainsString( 'View customer profile', $html );
		$this->assertStringNotContainsString( 'target="_blank"', $html );
	}

	public function test_box_shows_location_row_only_when_set(): void
	{
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			$this->markTestSkipped( 'WooCommerce is not loaded.' );
		}

		$bare = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->assertStringNotContainsString( 'Country / state', $this->render_for( $bare ) );

		$located = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		update_user_meta( $located, 'billing_country', 'TR' );
		update_user_meta( $located, 'billing_state', 'TR34' );
		$html = $this->render_for( $located );

		$this->assertStringContainsString( 'Country / state', $html );
		$this->assertStringContainsString( esc_html( WC()->countries->get_states( 'TR' )['TR34'] ), $html );
		$this->assertLessThan(
			strpos( $html, 'Country / state' ),
			strpos( $html, 'mailto:' ),
			'The location row comes after the e-mail.'
		);
	}

	public function test_create_account_button_says_the_customer_gets_an_email(): void
	{
		$booking = self::factory()->post->create_and_get( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking->ID, '_mhmrentiva_customer_email', 'nobody-yet@example.test' );

		ob_start();
		BookingPortalMetaBox::render( $booking );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'mhm-create-customer-account-btn', $html );
		$this->assertStringContainsString( 'The customer receives an e-mail to set their password.', $html );
	}
}
