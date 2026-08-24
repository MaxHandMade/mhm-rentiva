<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use MHMRentiva\Admin\Payment\WooCommerce\RemainingPaymentHandler;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Finding H-02 (independent review of the 6.0.2 package):
 * get_or_create_remaining_order() read _mhmrentiva_remaining_order_id, and if it
 * found nothing called wc_create_order() and wrote the new ID back -- with no
 * lock spanning the read and the write. Two concurrent requests (a double click,
 * two tabs, a retried payment link) could both read "none", both create a
 * WooCommerce order, and both write. The booking keeps one ID; the other order
 * stays in the database as a live pending order for money already accounted for.
 *
 * The plugin already shipped Locker::withBookingLock() for exactly this shape.
 * Nothing called it -- it had no callers anywhere in src/.
 *
 * TEST COVERAGE IS PARTIAL AND THIS IS DELIBERATE
 *
 * The behavioural half of this finding -- "two callers end up with one order" --
 * needs wc_create_order(), and WooCommerce is not loaded in this suite
 * (RemainingPaymentTaxTest skips for the same reason). That half is marked
 * skipped rather than quietly dropped, so it runs the day WooCommerce is
 * available to the harness.
 *
 * What DOES run here is the wiring: that the critical section is executed inside
 * a booking-scoped transaction at all. It reaches that assertion through the
 * early no-remaining-amount return, which needs no WooCommerce. Remove
 * withBookingLock() and this test goes red.
 */
final class RemainingOrderIdempotencyTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $booking_id;

	/** @var list<string> */
	private array $query_log = array();

	public function setUp(): void
	{
		parent::setUp();

		$this->booking_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));

		$this->query_log = array();
	}

	private function start_recording(): void
	{
		add_filter('query', function ($query) {
			$this->query_log[] = (string) $query;

			// Neutralised so the surrounding WP_UnitTestCase transaction is not
			// implicitly committed by a nested START TRANSACTION.
			if (preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query)) {
				return 'SELECT 1';
			}

			return $query;
		});
	}

	private function logged(string $pattern): bool
	{
		foreach ($this->query_log as $sql) {
			if (preg_match($pattern, $sql)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * RED before the fix: no transaction is ever opened, so the check-then-create
	 * sequence has no critical section around it.
	 */
	public function test_remaining_order_resolution_runs_inside_a_booking_lock(): void
	{
		update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0);

		$this->start_recording();

		RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

		$this->assertTrue(
			$this->logged('/^\s*START TRANSACTION\b/i'),
			'Resolving a booking\'s remaining-payment order must run inside a transaction, or two concurrent callers each create their own WooCommerce order.'
		);
		$this->assertTrue(
			$this->logged('/FOR UPDATE/i'),
			'The booking row must be locked while the existing-order check and the create decision are made.'
		);
		$this->assertTrue(
			$this->logged('/^\s*COMMIT\b/i'),
			'The transaction must be committed.'
		);
	}

	/**
	 * The lock must be taken on the booking, not on some other post: a
	 * vehicle-scoped or global lock would serialise unrelated bookings and still
	 * leave two callers on the SAME booking racing if the id were wrong.
	 */
	public function test_lock_is_scoped_to_the_booking_row(): void
	{
		update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0);

		$this->start_recording();

		RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

		$locking = array_values(array_filter(
			$this->query_log,
			static fn( string $sql ): bool => (bool) preg_match('/FOR UPDATE/i', $sql)
		));

		$this->assertNotEmpty($locking, 'A locking read must be issued.');
		$this->assertStringContainsString(
			'post_id = ' . $this->booking_id,
			$locking[0],
			'The lock must be taken on the booking being resolved.'
		);
	}

	/**
	 * Fable audit finding B-1: the lock alone does not close the race.
	 *
	 * ajax_create_remaining_order() reads _mhmrentiva_customer_user_id for its
	 * ownership check BEFORE entering the lock. That read pulls the booking's
	 * entire meta set into the request-level object cache via update_meta_cache().
	 * Request B therefore arrives at the lock with a cached snapshot taken before
	 * request A committed, and the lookup inside the critical section is served
	 * from that stale snapshot -- it never reaches the database. B sees "no
	 * remaining order", creates a second one, and overwrites A's meta.
	 *
	 * Serialising the two requests is not enough; the critical section has to read
	 * fresh. This test simulates the other request by writing the meta straight to
	 * the database (bypassing the cache the way a separate PHP process would) and
	 * then asserting the handler observes it.
	 */
	public function test_critical_section_does_not_read_a_stale_meta_cache(): void
	{
		global $wpdb;

		update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 0);

		// Warm the cache exactly as the ownership check does.
		get_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', true);
		$this->assertNotFalse(
			wp_cache_get($this->booking_id, 'post_meta'),
			'Sanity check: the ownership read must populate the post_meta cache.'
		);

		// Another request commits a remaining-order id. Written directly so the
		// local object cache stays stale, which is what a separate process means.
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $this->booking_id,
				'meta_key'   => '_mhmrentiva_remaining_order_id',
				'meta_value' => '987654',
			)
		);

		RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

		$this->assertSame(
			'987654',
			(string) get_post_meta($this->booking_id, '_mhmrentiva_remaining_order_id', true),
			'The critical section must invalidate the booking meta cache, or it decides on data that predates the lock it just waited for.'
		);
	}

	/**
	 * The behavioural half. Skipped without WooCommerce rather than deleted, so
	 * it starts running as soon as the harness can load WC.
	 */
	public function test_two_calls_return_the_same_order(): void
	{
		$this->require_woocommerce();
		$this->ensure_booking_product();

		update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', 500);

		$first  = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);
		$second = RemainingPaymentHandler::get_or_create_remaining_order($this->booking_id);

		$this->assertNotWPError($first);
		$this->assertNotWPError($second);
		$this->assertSame(
			$first->get_id(),
			$second->get_id(),
			'A second resolution must reuse the pending remaining-payment order, not create another one.'
		);
	}
}
