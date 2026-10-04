<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersOptimizer;
use WP_UnitTestCase;

/**
 * The Customers screen shows what CustomerContact answers, and what add-ons
 * badge an account as.
 *
 * It used to JOIN `mhmrentiva_phone` / `mhmrentiva_address` and nothing else, so
 * a customer who had checked out through WooCommerce read "Phone -  Address -"
 * although the billing fields held both.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomersOptimizer
 */
final class CustomersScreenReadsOneContactTest extends WP_UnitTestCase
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

	/**
	 * @return array<string, mixed>
	 */
	private function listedRow( int $user ): array
	{
		foreach ( CustomersOptimizer::get_customers_optimized( 1, 100 )['customers'] as $row ) {
			if ( (int) $row['id'] === $user ) {
				return $row;
			}
		}
		$this->fail( "User {$user} is not listed." );
	}

	private function billingOnlyCustomer(): int
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		update_user_meta( $user, 'billing_phone', '+90555 555 55 55' );
		update_user_meta( $user, 'billing_address_1', 'Test Sokak 1' );
		update_user_meta( $user, 'billing_postcode', '06002' );
		update_user_meta( $user, 'billing_city', 'Ulus' );

		return $user;
	}

	public function test_the_list_shows_the_billing_phone_and_address(): void
	{
		$user = $this->billingOnlyCustomer();

		$row = $this->listedRow( $user );
		$this->assertSame( '+90555 555 55 55', $row['phone'] );
		$this->assertSame( 'Test Sokak 1, 06002 Ulus', $row['address'] );
	}

	public function test_the_detail_shows_the_billing_phone_and_address(): void
	{
		$user = $this->billingOnlyCustomer();

		$detail = CustomersOptimizer::get_customer_details_optimized( $user );
		$this->assertSame( '+90555 555 55 55', $detail['phone'] );
		$this->assertSame( 'Test Sokak 1, 06002 Ulus', $detail['address'] );
	}

	public function test_an_unknown_contact_still_renders_as_a_dash(): void
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->assertSame( '-', $this->listedRow( $user )['phone'] );
		$this->assertSame( '-', CustomersOptimizer::get_customer_details_optimized( $user )['address'] );
	}

	/**
	 * The list used to group by the phone/address meta values,
	 * so an account with two `mhmrentiva_phone` rows was listed twice while
	 * the pager counted it once.
	 */
	public function test_two_phone_rows_do_not_list_a_customer_twice(): void
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		add_user_meta( $user, 'mhmrentiva_phone', '+90 111' );
		add_user_meta( $user, 'mhmrentiva_phone', '+90 999' );

		$result = CustomersOptimizer::get_customers_optimized( 1, 100 );
		$ids    = array_map( static fn( $r ): int => (int) $r['id'], $result['customers'] );

		$this->assertSame( array( $user ), $ids );
		$this->assertSame( 1, $result['total'] );
	}

	public function test_badges_default_to_none(): void
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->assertSame( array(), $this->listedRow( $user )['badges'] );
		$this->assertSame( array(), CustomersOptimizer::get_customer_details_optimized( $user )['badges'] );
	}

	public function test_an_add_on_badge_reaches_the_list_and_the_detail(): void
	{
		$user   = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		$filter = static function ( array $badges, int $user_id ) use ( $user ): array {
			if ( $user_id === $user ) {
				$badges[] = array(
					'key'   => 'vendor',
					'label' => 'Vendor',
					'url'   => 'http://example.org/wp-admin/admin.php?page=x',
				);
			}
			return $badges;
		};
		add_filter( 'mhmrentiva_customer_badges', $filter, 10, 2 );

		try {
			$expected = array(
				array(
					'key'   => 'vendor',
					'label' => 'Vendor',
					'url'   => 'http://example.org/wp-admin/admin.php?page=x',
				),
			);
			$this->assertSame( $expected, $this->listedRow( $user )['badges'] );
			$this->assertSame( $expected, CustomersOptimizer::get_customer_details_optimized( $user )['badges'] );
		} finally {
			remove_filter( 'mhmrentiva_customer_badges', $filter, 10 );
		}
	}

	/**
	 * Only well-formed badges pass; a key becomes a CSS class suffix, so it is
	 * reduced to what sanitize_html_class() allows.
	 */
	public function test_malformed_badges_are_dropped(): void
	{
		$user   = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		$filter = static fn(): array => array(
			'not an array',
			array( 'label' => '' ),
			array(
				'key'   => 'bad key"><script>',
				'label' => 'Shown',
				'url'   => 'javascript:alert(1)',
			),
		);
		add_filter( 'mhmrentiva_customer_badges', $filter );

		try {
			$badges = $this->listedRow( $user )['badges'];
			$this->assertCount( 1, $badges );
			$this->assertSame( 'badkeyscript', $badges[0]['key'] );
			$this->assertSame( 'Shown', $badges[0]['label'] );
			$this->assertSame( '', $badges[0]['url'], 'Only an http(s) URL may become a link.' );
		} finally {
			remove_filter( 'mhmrentiva_customer_badges', $filter );
		}
	}
}
