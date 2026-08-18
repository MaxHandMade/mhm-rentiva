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
	 * No reader anywhere may resolve the link on its own.
	 *
	 * The first version of this gate listed three files and searched each for
	 * one literal string (`get_post_meta( $post_id, '_mhmrentiva_order_id',
	 * true ) ?:`). It therefore protected a sample, not the class: eleven other
	 * call sites spelled the same chain with different variable names and the
	 * gate matched none of them. Two of those -- Refunds\Service and
	 * RefundValidator -- carried a chain that stopped after three keys, so a
	 * booking linked only by `_mhmrentiva_booking_order_id` showed its order in
	 * the list and answered "WooCommerce order not found" on the refund screen.
	 *
	 * The gate now walks the whole tree and asks a shape-independent question:
	 * outside the one canonical resolver, does anything read these keys from
	 * post meta at all?
	 *
	 * WHERE THIS TOOL STARTS -- and therefore what it cannot see:
	 *  - It reads `src/` of THIS edition only. The Pro tree is not scanned
	 *    (measured on 2026-08-18: it has no reader of these keys).
	 *  - It matches `get_post_meta` reads. Writers are out of scope by design,
	 *    and a read assembled from a variable key name would slip past it.
	 *  - It is a source-level check. WooCommerce is absent from this suite, so
	 *    the refund path cannot be exercised end to end here; the behavioural
	 *    half of the contract is the resolver's own tests above.
	 */
	public function test_no_reader_anywhere_resolves_the_link_itself(): void
	{
		$root = dirname( __DIR__, 3 ) . '/src';
		$keys = array(
			'_mhmrentiva_woocommerce_order_id',
			'_mhmrentiva_wc_order_id',
			'_mhmrentiva_order_id',
			'_mhmrentiva_booking_order_id',
		);

		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root ) );
		$scanned  = 0;
		$offences = array();

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}

			$path = str_replace( '\\', '/', (string) $file->getPathname() );

			// The canonical resolver is the one place these keys may be named.
			if ( str_ends_with( $path, 'Admin/Core/Utilities/BookingQueryHelper.php' ) ) {
				continue;
			}

			++$scanned;

			foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $number => $line ) {
				if ( ! str_contains( $line, 'get_post_meta' ) ) {
					continue;
				}

				foreach ( $keys as $key ) {
					if ( str_contains( $line, "'" . $key . "'" ) ) {
						$offences[] = basename( $path ) . ':' . ( $number + 1 );
						break;
					}
				}
			}
		}

		$this->assertGreaterThan(
			200,
			$scanned,
			'The scan reached almost no files; it is no longer pointed at the source tree.'
		);

		$this->assertSame(
			array(),
			$offences,
			"These call sites resolve the booking-to-order link themselves instead of asking "
			. "BookingQueryHelper::resolve_wc_order_id(), so they can disagree with it:\n  "
			. implode( "\n  ", $offences )
		);
	}
}
