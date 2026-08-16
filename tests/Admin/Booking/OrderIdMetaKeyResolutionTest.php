<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking;

use MHMRentiva\Admin\Core\Utilities\BookingQueryHelper;
use WP_UnitTestCase;

/**
 * One booking-to-order link, resolved one way everywhere.
 *
 * Four meta keys have carried the WooCommerce order ID over the plugin's life
 * and two are still written today: `create_booking_from_data()` writes
 * `_mhmrentiva_woocommerce_order_id`, `add_order_item_meta()` writes
 * `_mhmrentiva_wc_order_id`. Readers were left to re-implement the fallback
 * chain, and they disagreed: BookingQueryHelper, AutoCancel, RefundValidator
 * and Refunds\Service put the canonical key first, while BookingColumns omitted
 * it entirely and WooCommerceBridge read the two in the opposite order.
 *
 * Disagreeing copies of a lookup are a defect whether or not a caller is
 * currently reachable, so the chain becomes one function and every reader uses
 * it. The order is the one the majority of readers already had.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id
 */
final class OrderIdMetaKeyResolutionTest extends WP_UnitTestCase
{
	private function booking_with( string $meta_key, int $order_id ): int
	{
		$booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $booking_id, $meta_key, $order_id );

		return $booking_id;
	}

	public function test_it_resolves_the_canonical_checkout_key(): void
	{
		$booking_id = $this->booking_with( '_mhmrentiva_woocommerce_order_id', 4101 );

		$this->assertSame( 4101, BookingQueryHelper::resolve_wc_order_id( $booking_id ) );
	}

	public function test_it_resolves_the_cart_item_key(): void
	{
		$booking_id = $this->booking_with( '_mhmrentiva_wc_order_id', 4102 );

		$this->assertSame( 4102, BookingQueryHelper::resolve_wc_order_id( $booking_id ) );
	}

	public function test_it_resolves_the_legacy_order_key(): void
	{
		$booking_id = $this->booking_with( '_mhmrentiva_order_id', 4103 );

		$this->assertSame( 4103, BookingQueryHelper::resolve_wc_order_id( $booking_id ) );
	}

	public function test_it_resolves_the_legacy_booking_order_key(): void
	{
		$booking_id = $this->booking_with( '_mhmrentiva_booking_order_id', 4104 );

		$this->assertSame( 4104, BookingQueryHelper::resolve_wc_order_id( $booking_id ) );
	}

	/**
	 * When a booking carries both writers' keys they point at the same order,
	 * but the canonical one is the one current checkout maintains.
	 */
	public function test_the_canonical_key_wins_over_the_legacy_ones(): void
	{
		$booking_id = $this->booking_with( '_mhmrentiva_woocommerce_order_id', 4201 );
		update_post_meta( $booking_id, '_mhmrentiva_wc_order_id', 4202 );
		update_post_meta( $booking_id, '_mhmrentiva_order_id', 4203 );

		$this->assertSame( 4201, BookingQueryHelper::resolve_wc_order_id( $booking_id ) );
	}

	public function test_a_booking_with_no_order_resolves_to_zero(): void
	{
		$booking_id = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 0, BookingQueryHelper::resolve_wc_order_id( $booking_id ) );
	}

	/**
	 * The readers that had their own copy of the chain must not keep one.
	 */
	public function test_no_reader_reimplements_the_chain(): void
	{
		$root    = dirname( __DIR__, 3 );
		$readers = array(
			'/src/Admin/Booking/ListTable/BookingColumns.php',
			'/src/Admin/Booking/Meta/BookingMeta.php',
			'/src/Admin/Payment/WooCommerce/WooCommerceBridge.php',
		);

		foreach ( $readers as $reader ) {
			$source = (string) file_get_contents( $root . $reader );

			$this->assertStringNotContainsString(
				"get_post_meta( \$post_id, '_mhmrentiva_order_id', true ) ?:",
				$source,
				$reader . ' still carries its own order-id fallback chain.'
			);
		}
	}
}
