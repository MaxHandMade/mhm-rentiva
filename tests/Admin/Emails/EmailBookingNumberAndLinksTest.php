<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Mailer;
use MHMRentiva\Admin\Emails\Core\Templates;
use WP_UnitTestCase;

/**
 * Plan 0.12 (measured on the dev site): a booking's emails disagreed with
 * themselves and with the customer's account.
 *
 * - The status-change email's subject carried the order number while its
 *   body printed the raw post ID, so one email showed two numbers.
 * - The cancellation email's "View My Account" button pointed at
 *   /my-account/ through a setting nothing registers -- a 404 on a site whose
 *   account page lives elsewhere.
 * - The booking context offered /panel/, a retired URL.
 *
 * @covers \MHMRentiva\Admin\Emails\Core\Mailer::getBookingContext
 */
final class EmailBookingNumberAndLinksTest extends WP_UnitTestCase
{
	private int $booking_id;

	private int $order_number = 987654;

	public function setUp(): void
	{
		parent::setUp();

		$customer         = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->booking_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->booking_id, '_mhmrentiva_customer_user_id', $customer );
		update_post_meta( $this->booking_id, '_mhmrentiva_status', 'pending' );
		update_post_meta( $this->booking_id, '_mhmrentiva_wc_order_id', $this->order_number );
	}

	public function test_the_booking_context_carries_the_number_the_customer_sees(): void
	{
		$context = Mailer::getBookingContext( $this->booking_id );

		$this->assertSame( mhmrentiva_get_display_id( $this->booking_id ), $context['booking']['display_id'] ?? null );
		$this->assertSame( $this->order_number, mhmrentiva_get_display_id( $this->booking_id ), 'Fixture guard: the order number is the display number.' );
	}

	public function test_the_status_change_email_body_uses_the_same_number_as_its_subject(): void
	{
		$context = Mailer::getBookingContext( $this->booking_id );
		$context = array_merge( $context, array( 'old_status' => 'pending', 'new_status' => 'confirmed' ) );

		$body = Templates::render_body( 'booking_status_changed_customer', $context );

		$this->assertStringContainsString( '#' . $this->order_number, $body );
		$this->assertStringNotContainsString( '#' . $this->booking_id . '<', $body );
	}

	public function test_the_context_does_not_offer_the_retired_panel_url(): void
	{
		$context = Mailer::getBookingContext( $this->booking_id );

		$this->assertStringNotContainsString( '/panel/', (string) ( $context['panel']['url'] ?? '' ) );
	}

	public function test_the_cancellation_email_links_to_the_booking_not_a_dead_my_account_url(): void
	{
		$template = (string) file_get_contents( MHMRENTIVA_PLUGIN_DIR . 'templates/emails/booking-cancelled.html.php' );

		$this->assertStringNotContainsString( 'mhmrentiva_my_account_url', $template, 'No registered setting backs this key; it always fell back to /my-account/.' );
		$this->assertStringContainsString( 'AccountController::get_booking_view_url', $template );
	}
}
