<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Plan 0.10: every bank-transfer order logged "Array to string conversion"
 * in wp-includes/formatting.php. Measured caller (backtrace probe on the dev
 * site): WooCommerceBridge::filter_product_image_for_cart() ran esc_attr() on
 * $size, and WooCommerce's order emails ask for the product image with an
 * array size (width, height) while the checkout request is still running, so
 * the warning fired once per email and the image got the class
 * "attachment-Array".
 *
 * The class now follows wp_get_attachment_image(): an array size becomes
 * "WIDTHxHEIGHT".
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::filter_product_image_for_cart
 */
final class BookingImageSizeClassTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();

		$vehicle_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_vehicle' ) );
		$image_id   = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $vehicle_id );
		set_post_thumbnail( $vehicle_id, $image_id );

		$booking_id = self::factory()->post->create( array( 'post_type' => 'mhmrentiva_booking' ) );
		update_post_meta( $booking_id, '_mhmrentiva_vehicle_id', $vehicle_id );

		$this->product = $this->ensure_booking_product( '100' );

		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->product->get_id(), 1, 0, array(), array( 'mhmrentiva_booking_id' => $booking_id ) );

		// The order emails run inside wc-ajax=checkout, where is_checkout() is true.
		add_filter( 'woocommerce_is_checkout', '__return_true' );
	}

	public function tearDown(): void
	{
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		// The test config shares the dev site's ABSPATH, so uploads land in its
		// real uploads directory; remove only what this test added.
		$this->remove_added_uploads();
		parent::tearDown();
	}

	private function image( $size ): string
	{
		return (string) WooCommerceBridge::filter_product_image_for_cart( '<img>', $this->product, $size, array(), true );
	}

	public function test_an_email_sized_request_is_answered_without_a_warning(): void
	{
		// Before the fix esc_attr() turned the array into "Array" (with the
		// "Array to string conversion" warning) and the class read
		// "attachment-Array"; the assertions below tell the two apart.
		$html = $this->image( array( 32, 32 ) );

		$this->assertStringContainsString( 'attachment-32x32', $html );
		$this->assertStringNotContainsString( 'Array', $html );
	}

	public function test_a_named_size_keeps_its_name(): void
	{
		$this->assertStringContainsString( 'attachment-woocommerce_thumbnail', $this->image( 'woocommerce_thumbnail' ) );
	}
}
