<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\EmailTemplates;
use MHMRentiva\Admin\Emails\Core\Mailer;
use WP_UnitTestCase;

/**
 * PR 3b-3 (Fable I6 / Codex): the Settings preview and test send with a real
 * booking are built from the context the automatic e-mail uses, so what the
 * operator previews is what the customer gets -- formatted dates, the
 * greeting name, a cancellation's reason. The sample-data branch and the
 * extension filter stay.
 */
final class PreviewContextTest extends WP_UnitTestCase
{
	private function booking(): int
	{
		return (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_mhmrentiva_pickup_date'       => '2026-11-20',
				'_mhmrentiva_dropoff_date'      => '2026-11-23',
				'_mhmrentiva_status'            => 'cancelled',
				'_mhmrentiva_cancellation_data' => array( 'cancellation_reason' => 'Uçuş iptal' ),
			),
		));
	}

	public function test_a_real_booking_preview_uses_the_automatic_context(): void
	{
		$id  = $this->booking();
		$ctx = EmailTemplates::build_context('booking_created_customer', $id);
		$real = Mailer::getBookingContext($id);

		$this->assertSame($real['booking']['pickup_date'], $ctx['booking']['pickup_date']);
		$this->assertNotSame('2026-11-20', $ctx['booking']['pickup_date'], 'Formatted, not raw.');
		$this->assertSame($real['customer']['salutation_name'], $ctx['customer']['salutation_name']);
		$this->assertArrayHasKey('payment', $ctx['booking'], 'The refund templates still read booking.payment.');
	}

	public function test_a_cancellation_preview_carries_the_reason(): void
	{
		$ctx = EmailTemplates::build_context('booking_cancelled', $this->booking());

		$this->assertSame('Uçuş iptal', $ctx['cancellation_reason'] ?? null);
	}

	public function test_the_sample_branch_and_the_filter_stay(): void
	{
		$seen = null;
		add_filter('mhmrentiva_email_preview_context', function ($ctx, $key, $booking_id) use (&$seen) {
			$seen = array( $key, $booking_id );
			return $ctx;
		}, 10, 3);

		$ctx = EmailTemplates::build_context('booking_created_customer', 0);

		$this->assertNotEmpty($ctx['booking'] ?? array(), 'Sample data.');
		$this->assertSame(array( 'booking_created_customer', 0 ), $seen);
	}
}
