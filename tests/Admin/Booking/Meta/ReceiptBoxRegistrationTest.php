<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use WP_UnitTestCase;

/**
 * The Payment Receipt box appears only on a booking whose customer uploaded a
 * receipt (PR 3b-1, canvas v135). Without one it only said "no receipt".
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingMeta::add_meta_boxes
 */
final class ReceiptBoxRegistrationTest extends WP_UnitTestCase
{
	private int $booking;

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
	}

	public function tearDown(): void
	{
		global $wp_meta_boxes, $post, $pagenow;
		$wp_meta_boxes = array();
		$post          = null;
		$pagenow       = 'index.php';
		parent::tearDown();
	}

	private function side_boxes(): array
	{
		global $wp_meta_boxes, $post, $pagenow;
		$wp_meta_boxes = array();
		$post          = get_post($this->booking);
		$pagenow       = 'post.php';
		BookingMeta::add_meta_boxes();
		return $wp_meta_boxes['mhmrentiva_booking']['side']['default'] ?? array();
	}

	public function test_receipt_box_absent_without_attachment(): void
	{
		$this->assertArrayNotHasKey('mhmrentiva_receipt_box', $this->side_boxes());
	}

	public function test_receipt_box_present_with_attachment(): void
	{
		$attachment = (int) self::factory()->attachment->create(array( 'post_mime_type' => 'image/png' ));
		update_post_meta($this->booking, '_mhmrentiva_receipt_attachment_id', $attachment);

		$this->assertArrayHasKey('mhmrentiva_receipt_box', $this->side_boxes());
	}
}
