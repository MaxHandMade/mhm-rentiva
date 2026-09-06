<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersOptimizer;
use WP_UnitTestCase;

/**
 * The stat cards and the list underneath them answer the same question.
 *
 * They did not. The list asks CustomerIdentity -- a booking points at the
 * account, this plugin wrote user meta on it, or it wears the customer role --
 * while get_customer_stats_optimized() INNER JOINed bookings through
 * `_mhmrentiva_customer_email` and nothing else. Three consequences, all
 * visible on one screen:
 *
 *   - a customer added by hand, with the role and no booking yet, was counted
 *     by the list and not by the card, so the screen read "Total Customers 0"
 *     above a populated table;
 *   - a booking linked by `_mhmrentiva_customer_user_id` rather than by email
 *     was invisible to the card even though the list found it;
 *   - the card additionally dropped `u.ID = 1` and the login `admin` by hand,
 *     exclusions the list does not apply.
 *
 * These tests pin the equality itself rather than any one of those symptoms,
 * because the equality is the property that cannot drift silently: a future
 * change to either side has to keep the two agreeing or turn this red.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomersOptimizer::get_customer_stats_optimized
 */
final class CustomerStatsMatchTheListTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		CustomerIdentity::flush_memo();
		CustomersOptimizer::clear_cache();
	}

	public function tearDown(): void
	{
		CustomersOptimizer::clear_cache();
		parent::tearDown();
	}

	private function list_total(): int
	{
		return (int) ( CustomersOptimizer::get_customers_optimized( 1, 100 )['total'] ?? 0 );
	}

	private function card_total(): int
	{
		return (int) ( CustomersOptimizer::get_customer_stats_optimized()['total'] ?? 0 );
	}

	/**
	 * @return list<int>
	 */
	private function listed_user_ids(): array
	{
		$result = CustomersOptimizer::get_customers_optimized( 1, 100 );

		return array_map( static fn( $row ): int => (int) $row['id'], $result['customers'] ?? array() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function listed_row( int $user_id ): array
	{
		$rows = CustomersOptimizer::get_customers_optimized( 1, 100 )['customers'] ?? array();

		foreach ( $rows as $row ) {
			if ( (int) $row['id'] === $user_id ) {
				return $row;
			}
		}

		$this->fail( "User {$user_id} is not in the list at all." );
	}

	private function makeBooking( string $email = '', int $user_id = 0, ?float $price = null ): int
	{
		$booking = (int) self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_booking',
				'post_status' => 'publish',
			)
		);

		if ( '' !== $email ) {
			update_post_meta( $booking, '_mhmrentiva_customer_email', $email );
		}

		if ( 0 !== $user_id ) {
			update_post_meta( $booking, '_mhmrentiva_customer_user_id', $user_id );
		}

		if ( null !== $price ) {
			update_post_meta( $booking, '_mhmrentiva_total_price', (string) $price );
		}

		return $booking;
	}

	// --- The lock ---------------------------------------------------------

	/**
	 * The one that fails today: a hand-added customer with no booking.
	 */
	public function test_the_card_counts_what_the_list_shows(): void
	{
		$customer = (int) self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->assertSame(
			array( $customer ),
			$this->listed_user_ids(),
			'Precondition: the list shows exactly this one customer.'
		);

		$this->assertSame(
			$this->list_total(),
			$this->card_total(),
			'The card sits directly above the list; a different number there is the defect this test exists for.'
		);
	}

	/**
	 * The same equality with every shape of customer present at once, so the
	 * two sides cannot agree by accident on a single-row fixture.
	 */
	public function test_the_card_and_the_list_agree_across_every_kind_of_customer(): void
	{
		$by_role = (int) self::factory()->user->create( array( 'role' => 'customer' ) );

		$by_meta = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $by_meta, 'mhmrentiva_phone', '5551234567' );

		$by_email = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'booked.by.email@example.test',
			)
		);
		$this->makeBooking( 'booked.by.email@example.test' );

		$by_user_id = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->makeBooking( '', $by_user_id );

		// Present but not customers, in both directions.
		self::factory()->user->create( array( 'role' => 'editor' ) );
		self::factory()->user->create( array( 'role' => 'administrator' ) );

		$listed = $this->listed_user_ids();
		sort( $listed );
		$expected = array( $by_role, $by_meta, $by_email, $by_user_id );
		sort( $expected );

		$this->assertSame( $expected, $listed, 'Precondition: all four kinds of customer are listed and nobody else is.' );
		$this->assertSame( 4, $this->list_total() );
		$this->assertSame( 4, $this->card_total(), 'The card must reach every kind of customer the list reaches.' );
	}

	// --- The individual gaps, so a failure says which one broke -----------

	/**
	 * A booking linked by user id and no email meta at all.
	 */
	public function test_the_card_counts_a_booking_linked_by_user_id(): void
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->makeBooking( '', $user );

		$this->assertContains( $user, $this->listed_user_ids(), 'Precondition: the list finds this account.' );
		$this->assertSame( 1, $this->card_total(), 'A booking linked by user id counts as much as one linked by email.' );
	}

	/**
	 * The two hand-written exclusions are SHARED, and this pins that.
	 *
	 * Measured, not assumed: both queries carry `u.ID > 1` and
	 * `u.user_login != 'admin'`. An earlier draft of this work was going to
	 * drop them from the card on the belief that only the card had them --
	 * which would have opened the divergence in the other direction. They stay,
	 * and this test fails if either side changes its mind alone.
	 */
	public function test_both_surfaces_exclude_user_one_the_same_way(): void
	{
		$first = get_user_by( 'id', 1 );
		$this->assertNotFalse( $first, 'Precondition: the suite has a user 1 to make a customer of.' );

		$first->add_role( 'customer' );
		CustomerIdentity::flush_memo();
		CustomersOptimizer::clear_cache();

		$this->assertNotContains( 1, $this->listed_user_ids(), 'The list excludes user 1 even when they are a customer.' );
		$this->assertSame(
			$this->list_total(),
			$this->card_total(),
			'Whatever the exclusions are, both surfaces must apply the same ones.'
		);
	}

	// --- Negative controls: the fix must not be "count everybody" ---------

	/**
	 * Without this the whole file could pass by counting every account.
	 */
	public function test_the_card_still_excludes_accounts_that_are_not_customers(): void
	{
		self::factory()->user->create( array( 'role' => 'editor' ) );
		self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( 0, $this->list_total(), 'Precondition: none of these are customers.' );
		$this->assertSame( 0, $this->card_total(), 'The card must not count unrelated accounts just to match a bigger list.' );
	}

	/**
	 * The booking-derived numbers keep their own, narrower meaning: a customer
	 * with no booking joins `total` without becoming "active".
	 */
	public function test_a_customer_without_a_booking_is_counted_but_not_active(): void
	{
		self::factory()->user->create( array( 'role' => 'customer' ) );

		$stats = CustomersOptimizer::get_customer_stats_optimized();

		$this->assertSame( 1, (int) $stats['total'], 'They are a customer.' );
		$this->assertSame( 0, (int) $stats['active_90d'], 'But they have no booking activity, so they are not active.' );
		$this->assertSame( 0.0, (float) $stats['avg_spend'], 'And they have spent nothing.' );
	}

	// --- The user-id link: the defect this task closes --------------------

	/**
	 * The defect PR #30 named and deliberately left: a booking that points at
	 * the account by ID contributes nothing to the account's own figures.
	 */
	public function test_a_booking_linked_only_by_user_id_counts_toward_the_totals(): void
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->makeBooking( '', $user, 100.0 );

		$row = $this->listed_row( $user );

		$this->assertSame( 1, (int) $row['booking_count'] );
		$this->assertSame(
			\MHMRentiva\Admin\Core\CurrencyHelper::format_price( 100.0, 2 ),
			$row['total_spent'],
			'Spend is a formatted string; build the expectation with the same helper the screen uses.'
		);
		$this->assertNotEmpty( $row['last_booking'] );
	}

	/**
	 * A manual booking carries BOTH links (ManualBookingMetaBox writes them
	 * side by side). Joining through postmeta with an OR would match two rows
	 * for one booking and double SUM(price); EXISTS matches once.
	 */
	public function test_a_booking_carrying_both_links_is_counted_once(): void
	{
		$user  = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$email = get_userdata( $user )->user_email;
		$this->makeBooking( $email, $user, 100.0 );

		$row = $this->listed_row( $user );

		$this->assertSame( 1, (int) $row['booking_count'] );
		$this->assertSame(
			\MHMRentiva\Admin\Core\CurrencyHelper::format_price( 100.0, 2 ),
			$row['total_spent'],
			'Double-linked booking must not be counted twice.'
		);
	}

	/**
	 * The card's money and the list's money are the same money.
	 *
	 * Revenue is not in the card payload (CustomerStatsShipNothingUnreadTest
	 * pins the key set exactly), so it is derived: avg_spend * total. The
	 * expectation is NOT a hard-coded number -- that would only hold while the
	 * customer population is exactly the two users this test creates, and one
	 * extra customer anywhere in the fixture makes avg_spend round and fail
	 * the assertion for a reason unrelated to the defect. Instead both sides
	 * are built from the same run: sum the list's own (formatted) totals and
	 * compare that sum, run through the same formatter, against the card's.
	 */
	public function test_the_card_money_equals_the_list_money(): void
	{
		$a = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$b = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->makeBooking( '', $a, 100.0 );
		$this->makeBooking( get_userdata( $b )->user_email, 0, 100.0 );

		$list_rows = CustomersOptimizer::get_customers_optimized( 1, 100 )['customers'] ?? array();
		$list_sum  = 0.0;
		foreach ( $list_rows as $row ) {
			// total_spent is the formatted string the screen renders; parse it back
			// rather than assuming a hard amount so the comparison stays tied to
			// whatever the list actually returned for the whole population.
			$list_sum += (float) preg_replace( '/[^0-9.\-]/', '', (string) $row['total_spent'] );
		}

		$stats    = CustomersOptimizer::get_customer_stats_optimized();
		$card_sum = (float) $stats['avg_spend'] * (int) $stats['total'];

		$this->assertSame(
			\MHMRentiva\Admin\Core\CurrencyHelper::format_price( $list_sum, 2 ),
			\MHMRentiva\Admin\Core\CurrencyHelper::format_price( $card_sum, 2 ),
			'The card sits directly above the list; its money must equal the money the list rows sum to.'
		);
	}
}
