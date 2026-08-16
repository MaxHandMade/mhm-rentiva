<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersOptimizer;
use WP_UnitTestCase;

/**
 * Independent review finding M-02: the Customers screen listed essentially every
 * account on the site.
 *
 * The query starts FROM wp_users and LEFT JOINs the bookings, so a booking is
 * not required to appear; the only account filters were `u.ID > 1` and
 * `u.user_login != 'admin'`. Administrators, editors, subscribers and accounts
 * belonging to other plugins were all returned as "customers", with their email,
 * phone and address surfaced in a customer UI, and the total and pagination
 * counted them too.
 *
 * The rest of the customer area already had the right definition:
 * CustomerIdentity::is_customer() -- used by the detail view, delete and export
 * routes since the T8 round. The list simply never asked it. Filtering in PHP
 * after the query would have broken LIMIT/OFFSET and the total, so the same
 * three criteria are applied in SQL.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomersOptimizer::get_customers_optimized
 */
final class CustomersListIsCustomersOnlyTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		CustomerIdentity::flush_memo();
	}

	/**
	 * @return list<int>
	 */
	private function listed_user_ids(): array
	{
		$result = CustomersOptimizer::get_customers_optimized(1, 100);

		return array_map(
			static fn( $row ): int => (int) $row['id'],
			$result['customers'] ?? array()
		);
	}

	private function total(): int
	{
		$result = CustomersOptimizer::get_customers_optimized(1, 100);

		return (int) ( $result['total'] ?? 0 );
	}

	/**
	 * RED before the fix: an editor with no relationship to this plugin is
	 * returned as a customer.
	 */
	public function test_unrelated_accounts_are_not_listed_as_customers(): void
	{
		$editor     = (int) self::factory()->user->create(array( 'role' => 'editor' ));
		$admin      = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		$subscriber = (int) self::factory()->user->create(array( 'role' => 'subscriber' ));

		$listed = $this->listed_user_ids();

		$this->assertNotContains($editor, $listed, 'An editor is not a customer of this plugin.');
		$this->assertNotContains($admin, $listed, 'An administrator is not a customer of this plugin.');
		$this->assertNotContains($subscriber, $listed, 'A plain subscriber with no booking is not a customer.');
	}

	/**
	 * Negative control 1: somebody with a booking must still be listed, or the
	 * fix could be "list nobody".
	 */
	public function test_account_with_a_booking_is_listed(): void
	{
		$user = (int) self::factory()->user->create(array(
			'role'       => 'subscriber',
			'user_email' => 'has.booking@example.test',
		));

		$booking = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
		));
		update_post_meta($booking, '_mhmrentiva_customer_email', 'has.booking@example.test');
		update_post_meta($booking, '_mhmrentiva_customer_user_id', $user);

		$this->assertContains($user, $this->listed_user_ids(), 'An account with a booking must be listed.');
	}

	/**
	 * Negative control 2: the plugin's own customer role counts even with no
	 * booking yet -- a customer added by hand has not booked anything.
	 */
	public function test_account_with_the_customer_role_is_listed(): void
	{
		$user = (int) self::factory()->user->create(array( 'role' => 'customer' ));

		$this->assertContains($user, $this->listed_user_ids(), 'An account carrying the customer role must be listed.');
	}

	/**
	 * Negative control 3: user meta this plugin writes is also ownership.
	 */
	public function test_account_with_plugin_user_meta_is_listed(): void
	{
		$user = (int) self::factory()->user->create(array( 'role' => 'subscriber' ));
		update_user_meta($user, 'mhmrentiva_phone', '5551234567');

		$this->assertContains($user, $this->listed_user_ids(), 'An account carrying this plugin\'s user meta must be listed.');
	}

	/**
	 * The count has to agree with the rows, or pagination lies even once the
	 * rows are right.
	 */
	public function test_total_counts_only_customers(): void
	{
		self::factory()->user->create(array( 'role' => 'editor' ));
		self::factory()->user->create(array( 'role' => 'administrator' ));
		$customer = (int) self::factory()->user->create(array( 'role' => 'customer' ));

		$this->assertSame(
			array( $customer ),
			$this->listed_user_ids(),
			'Only the customer may be listed.'
		);
		$this->assertSame(1, $this->total(), 'The total must count customers only, or pagination is wrong.');
	}
}
