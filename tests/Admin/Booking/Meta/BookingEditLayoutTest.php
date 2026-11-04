<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingEditLayout;
use WP_UnitTestCase;

/**
 * The booking edit screen's boxes in the canvas order (Yon-Rezervasyon-Duzenle
 * v135), as the default of the user's saved order -- someone who dragged the
 * boxes keeps their own. Plus the payment box's title and deposit-type text.
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingEditLayout
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox
 */
final class BookingEditLayoutTest extends WP_UnitTestCase
{
	private int $user;

	public function setUp(): void
	{
		parent::setUp();
		$this->user = self::factory()->user->create(array( 'role' => 'administrator' ));
		wp_set_current_user($this->user);
		BookingEditLayout::register();
	}

	public function test_empty_saved_order_gets_the_canvas_order(): void
	{
		$order = get_user_option('meta-box-order_mhmrentiva_booking', $this->user);

		$this->assertSame(
			'submitdiv,mhmrentiva_booking_customer_account,mhmrentiva_receipt_box,mhmrentiva_booking_refund,mhmrentiva_customer_email_box',
			$order['side'] ?? null
		);
		$this->assertSame('mhmrentiva_booking_edit,mhmrentiva_booking_deposit', $order['normal'] ?? null);
	}

	public function test_a_saved_order_is_left_alone(): void
	{
		$mine = array( 'side' => 'mhmrentiva_booking_refund,submitdiv', 'normal' => 'mhmrentiva_booking_deposit' );
		update_user_option($this->user, 'meta-box-order_mhmrentiva_booking', $mine, true);

		$this->assertSame($mine, get_user_option('meta-box-order_mhmrentiva_booking', $this->user));
	}

	/**
	 * @dataProvider deposit_types
	 */
	public function test_deposit_type_text(string $type, string $deposit, string $total, string $expected): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
		update_post_meta($id, '_mhmrentiva_deposit_type', $type);
		update_post_meta($id, '_mhmrentiva_deposit_amount', $deposit);
		update_post_meta($id, '_mhmrentiva_total_price', $total);

		$this->assertSame($expected, BookingDepositMetaBox::deposit_type_text($id));
	}

	/** @return iterable<string, array{0: string, 1: string, 2: string, 3: string}> */
	public static function deposit_types(): iterable
	{
		yield 'percentage, whole' => array( 'percentage', '585', '5850', 'Percentage · 10%' );
		yield 'percentage, fraction' => array( 'percentage', '125', '1000', 'Percentage · 12.5%' );
		yield 'percentage, no total' => array( 'percentage', '0', '0', 'Percentage' );
		yield 'fixed' => array( 'fixed', '500', '5850', 'Fixed Amount' );
	}

	public function test_payment_box_title_is_payment_management(): void
	{
		global $wp_meta_boxes, $post, $pagenow;
		$post    = get_post((int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' )));
		$pagenow = 'post.php';
		BookingDepositMetaBox::add_meta_boxes();

		$box = $wp_meta_boxes['mhmrentiva_booking']['normal']['high']['mhmrentiva_booking_deposit'] ?? array();
		$this->assertSame('Payment Management', $box['title'] ?? null);
	}
}
