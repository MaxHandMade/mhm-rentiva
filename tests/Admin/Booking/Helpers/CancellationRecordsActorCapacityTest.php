<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\CancellationHandler;
use WP_UnitTestCase;

/**
 * A cancellation records in what capacity it was made: the canceller's roles at
 * that moment and the surface it came through.
 *
 * `cancelled_by` alone is an id, and an id outlives the role it had: an
 * operator who cancelled on their own vehicle and later became a vendor was
 * counted as a cancelling vendor (Codex Pro #124). A customer who is also a
 * vendor cancelling their own rental from My Account is a customer's
 * cancellation, which only the surface can tell.
 *
 * Plan: docs/superpowers/plans/2026-11-09-guvenilirlik-puani-ve-ceza-ayari.md (r3).
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\CancellationHandler::cancel_booking
 */
final class CancellationRecordsActorCapacityTest extends WP_UnitTestCase
{
	/** @var callable */
	private $no_transactions;

	public function setUp(): void
	{
		parent::setUp();

		// cancel_booking() opens its own transaction; its START TRANSACTION
		// would implicitly commit the framework's, and its COMMIT would make
		// the cancellation stick past the test (Codex #182). Removed in
		// tearDown() BEFORE the framework's ROLLBACK, which this filter would
		// otherwise swallow too.
		$this->no_transactions = static function ($query) {
			return preg_match('/^\s*(START TRANSACTION|COMMIT|ROLLBACK)\b/i', (string) $query) ? 'SELECT 1' : $query;
		};
		add_filter('query', $this->no_transactions);
	}

	public function tearDown(): void
	{
		remove_filter('query', $this->no_transactions);
		wp_set_current_user(0);
		parent::tearDown();
	}

	private function booking(int $customer): int
	{
		return (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'meta_input'  => array(
				'_mhmrentiva_status'           => 'confirmed',
				'_mhmrentiva_payment_status'   => 'pending',
				'_mhmrentiva_customer_user_id' => $customer,
				'_mhmrentiva_pickup_date'      => gmdate('Y-m-d', strtotime('+20 days')),
				'_mhmrentiva_dropoff_date'     => gmdate('Y-m-d', strtotime('+22 days')),
			),
		));
	}

	/**
	 * @return array<string, mixed>
	 */
	private function data(int $booking): array
	{
		return (array) get_post_meta($booking, '_mhmrentiva_cancellation_data', true);
	}

	public function test_a_customer_cancellation_records_their_roles_and_the_surface(): void
	{
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		// A second role, as a customer who is also something else would have.
		( new \WP_User($customer) )->add_role('editor');
		$booking = $this->booking($customer);
		wp_set_current_user($customer);

		$this->assertNotWPError(CancellationHandler::cancel_booking($booking, $customer, 'plan changed', true, false, 'customer_account'));

		$data  = $this->data($booking);
		$roles = $data['cancelled_by_roles'] ?? null;
		$this->assertIsArray($roles);
		sort($roles);
		$this->assertSame(array( 'customer', 'editor' ), $roles);
		$this->assertSame('customer_account', $data['cancelled_via'] ?? null);
		$this->assertSame($customer, $data['cancelled_by'] ?? null);
	}

	public function test_an_operator_cancellation_records_the_administrator_role(): void
	{
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$admin    = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$booking  = $this->booking($customer);
		wp_set_current_user($admin);

		$this->assertNotWPError(CancellationHandler::cancel_booking($booking, $admin, 'no show', true, false, 'admin_deposit'));

		$data = $this->data($booking);
		$this->assertSame(array( 'administrator' ), $data['cancelled_by_roles'] ?? null);
		$this->assertSame('admin_deposit', $data['cancelled_via'] ?? null);
	}

	public function test_a_system_cancellation_records_no_roles(): void
	{
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		$booking  = $this->booking($customer);
		wp_set_current_user(0);

		$this->assertNotWPError(CancellationHandler::cancel_booking($booking, 0, 'payment expired', true, true));

		$data = $this->data($booking);
		$this->assertSame(array(), $data['cancelled_by_roles'] ?? null);
		$this->assertSame('', $data['cancelled_via'] ?? null);
		$this->assertSame('admin', $data['cancelled_by'] ?? null, 'unchanged: a system call is recorded as "admin"');
	}
}
