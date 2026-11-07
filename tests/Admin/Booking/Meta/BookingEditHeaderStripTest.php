<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use WP_UnitTestCase;

/**
 * The line under the core H1 on an existing booking (canvas
 * Yon-Rezervasyon-Duzenle v135): back to the list, and what the screen holds.
 * Links and text only -- it runs inside form#post (spec PR 3b "Başlık şeridi").
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox
 */
final class BookingEditHeaderStripTest extends WP_UnitTestCase
{
	private function strip(\WP_Post $post): string
	{
		ob_start();
		BookingEditMetaBox::render_header_strip($post);
		return (string) ob_get_clean();
	}

	public function test_strip_renders_back_link_and_subline_on_an_existing_booking(): void
	{
		$post = self::factory()->post->create_and_get(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		$html = $this->strip($post);

		$this->assertStringContainsString('edit.php?post_type=mhmrentiva_booking', $html);
		$this->assertStringContainsString('Bookings', $html);
		$this->assertStringContainsString('Customer, vehicle and payment details', $html);
		$this->assertDoesNotMatchRegularExpression('/<input|<select|<textarea|name=/', $html);
	}

	public function test_strip_is_silent_on_a_new_booking(): void
	{
		// The new-booking screen has its own strip (ManualBookingMetaBox).
		$post = self::factory()->post->create_and_get(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'auto-draft',
		));

		$this->assertSame('', $this->strip($post));
	}

	public function test_strip_is_silent_on_another_post_type(): void
	{
		$post = self::factory()->post->create_and_get(array( 'post_type' => 'post' ));

		$this->assertSame('', $this->strip($post));
	}
}
