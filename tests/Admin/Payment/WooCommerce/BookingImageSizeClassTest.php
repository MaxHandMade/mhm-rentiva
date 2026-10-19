<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use WP_UnitTestCase;

/**
 * Plan 0.10: every bank-transfer order logged "Array to string conversion"
 * in wp-includes/formatting.php. Measured caller (backtrace probe on the dev
 * site): WooCommerceBridge::filter_product_image_for_cart() ran esc_attr() on
 * $size, and WooCommerce's order emails ask for the product image with an
 * array size (width, height), so the warning fired once per email and the
 * vehicle image got the class "attachment-Array".
 *
 * The class now follows wp_get_attachment_image(): an array size becomes
 * "WIDTHxHEIGHT".
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge::image_size_class
 */
final class BookingImageSizeClassTest extends WP_UnitTestCase
{
	private function size_class( $size ): string
	{
		$method = new \ReflectionMethod( WooCommerceBridge::class, 'image_size_class' );
		$method->setAccessible( true );

		return (string) $method->invoke( null, $size );
	}

	public function test_a_named_size_is_kept(): void
	{
		$this->assertSame( 'woocommerce_thumbnail', $this->size_class( 'woocommerce_thumbnail' ) );
	}

	public function test_an_array_size_becomes_width_x_height_without_a_warning(): void
	{
		$this->assertSame( '32x32', $this->size_class( array( 32, 32 ) ) );
	}

	public function test_the_filter_no_longer_escapes_size_directly(): void
	{
		$source = (string) file_get_contents( MHMRENTIVA_PLUGIN_DIR . 'src/Admin/Payment/WooCommerce/WooCommerceBridge.php' );

		$this->assertStringNotContainsString( 'esc_attr($size)', $source );
	}
}
