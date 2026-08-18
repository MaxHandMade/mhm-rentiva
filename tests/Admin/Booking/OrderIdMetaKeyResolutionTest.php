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
	 * WHERE THIS TOOL STARTS -- rewritten on 2026-08-18 after an independent audit
	 * showed the first version started in the wrong place. It had scanned `src/`
	 * for `get_post_meta` lines, was green, and missed three live resolvers:
	 * `mhmrentiva_get_display_id()` in the ROOT plugin file, a read in
	 * `templates/account/booking-detail.php`, and -- in the very file this round
	 * had just fixed one screen away -- a LEFT JOIN in DashboardService that
	 * resolved the link in SQL, where no `get_post_meta` appears at all.
	 *
	 * So the question is no longer "who calls get_post_meta with these keys" but
	 * "who NAMES these keys": every mention is a decision about the link, whether
	 * it is a read, a JOIN, or a delete list. Files that legitimately name them
	 * are listed below with a reason, and that list is the audit surface.
	 *
	 * What it still cannot see:
	 *  - The Pro tree (measured 2026-08-18 and again by the audit: no mentions).
	 *  - A key assembled at runtime from a variable or concatenation.
	 *  - Behaviour. WooCommerce is not loaded in this suite, so the refund path
	 *    cannot be exercised end to end; the behavioural half of the contract is
	 *    the resolver's own tests above.
	 */
	public function test_nothing_outside_the_resolver_names_these_keys(): void
	{
		$root = dirname( __DIR__, 3 );
		$keys = array(
			'_mhmrentiva_woocommerce_order_id',
			'_mhmrentiva_wc_order_id',
			'_mhmrentiva_order_id',
			'_mhmrentiva_booking_order_id',
		);

		// Path suffix => why this file is allowed to name the keys.
		$allowed = array(
			'src/Admin/Core/Utilities/BookingQueryHelper.php'     => 'the canonical resolver',
			'src/Admin/Core/Utilities/DatabaseCleaner.php'        => 'uninstall must delete every historical key',
			'src/Admin/Payment/WooCommerce/WooCommerceBridge.php' => 'writes the link',
			'src/Admin/Core/MetaKeys.php'                         => 'declares the constants',
			'src/Admin/Utilities/Dashboard/DashboardService.php'  => 'resolves the link in SQL, where the resolver cannot run -- pinned separately by test_the_pending_payments_query_joins_every_order_key()',
		);

		$scanned  = 0;
		$offences = array();

		// tests/ is not walked: fixtures name legacy keys on purpose, which is
		// how the legacy paths get exercised at all.
		foreach ( array( '/src', '/templates' ) as $dir ) {
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . $dir ) );

			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
					continue;
				}

				$offences = array_merge( $offences, $this->keys_named_in( (string) $file->getPathname(), $keys, $allowed, $root ) );
				++$scanned;
			}
		}

		// The root plugin file is where mhmrentiva_get_display_id() lived -- a
		// directory walk that starts at src/ never reaches it.
		foreach ( (array) glob( $root . '/*.php' ) as $rootFile ) {
			$offences = array_merge( $offences, $this->keys_named_in( (string) $rootFile, $keys, $allowed, $root ) );
			++$scanned;
		}

		$this->assertGreaterThan(
			200,
			$scanned,
			'The scan reached almost no files; it is no longer pointed at the tree.'
		);

		$this->assertSame(
			array(),
			$offences,
			"These places name the booking-to-order meta keys themselves instead of asking "
			. "BookingQueryHelper::resolve_wc_order_id(), so they can disagree with it:\n  "
			. implode( "\n  ", $offences )
		);
	}

	/**
	 * The pending-payments widget resolves the link in SQL, so it is exempted
	 * from the scan above and pinned here instead.
	 *
	 * Its LEFT JOIN matched only `_mhmrentiva_woocommerce_order_id` and filtered
	 * on IS NOT NULL, so a legacy-linked booking with an outstanding balance
	 * never appeared in the widget at all.
	 *
	 * This is a source-level assertion by necessity: the consumer loop is gated
	 * on `function_exists('wc_get_order')`, and WooCommerce is not loaded in this
	 * suite, so an end-to-end assertion would pass for the wrong reason.
	 */
	public function test_the_pending_payments_query_joins_every_order_key(): void
	{
		$source = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/src/Admin/Utilities/Dashboard/DashboardService.php'
		);

		$start = strpos( $source, 'private static function collect_pending_payments' );
		$this->assertNotFalse( $start, 'collect_pending_payments() has been renamed; this gate no longer measures it.' );

		// Up to the next method, so the assertion covers the query AND the loop
		// that consumes it -- a window of fixed length silently stops measuring
		// when the method grows.
		$next  = strpos( $source, "\tprivate static function", $start + 10 );
		$query = false === $next ? substr( $source, $start ) : substr( $source, $start, $next - $start );

		$this->assertGreaterThan(
			2000,
			strlen( $query ),
			'The extracted method body is implausibly short; the boundary detection has drifted.'
		);

		foreach ( array(
			'_mhmrentiva_woocommerce_order_id',
			'_mhmrentiva_wc_order_id',
			'_mhmrentiva_order_id',
			'_mhmrentiva_booking_order_id',
		) as $key ) {
			$this->assertStringContainsString(
				$key,
				$query,
				"The pending-payments query does not look for {$key}, so bookings linked by that key are invisible to the widget."
			);
		}

		$this->assertStringContainsString(
			'BookingQueryHelper::resolve_wc_order_id',
			$query,
			'The widget must take the resolved order id from the canonical resolver, not from a JOIN of its own.'
		);
	}

	/**
	 * @param list<string>          $keys
	 * @param array<string, string> $allowed
	 * @return list<string>
	 */
	private function keys_named_in( string $path, array $keys, array $allowed, string $root ): array
	{
		$normalised = str_replace( '\\', '/', $path );

		foreach ( array_keys( $allowed ) as $suffix ) {
			if ( str_ends_with( $normalised, $suffix ) ) {
				return array();
			}
		}

		$relative = ltrim( str_replace( str_replace( '\\', '/', $root ), '', $normalised ), '/' );
		$found    = array();

		foreach ( explode( "\n", (string) file_get_contents( $path ) ) as $number => $line ) {
			foreach ( $keys as $key ) {
				if ( str_contains( $line, $key ) ) {
					$found[] = $relative . ':' . ( $number + 1 );
					break;
				}
			}
		}

		return $found;
	}
}
