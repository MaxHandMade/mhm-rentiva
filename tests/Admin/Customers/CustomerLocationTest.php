<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerContact;
use WP_UnitTestCase;

/**
 * "Country / state" on the booking edit screen's customer box (PR 3b-1).
 *
 * WooCommerce stores codes (`billing_country` = `TR`, `billing_state` = `TR34`);
 * the operator reads names. The names come from WooCommerce's own tables, so
 * the expected value is read from them rather than written here -- WC renamed
 * "Turkey" to "Türkiye" once already.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomerContact::location
 */
final class CustomerLocationTest extends WP_UnitTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			$this->markTestSkipped( 'WooCommerce is not loaded.' );
		}
	}

	private function user( string $country, string $state ): int
	{
		$user = (int) self::factory()->user->create( array( 'role' => 'customer' ) );
		update_user_meta( $user, 'billing_country', $country );
		update_user_meta( $user, 'billing_state', $state );
		return $user;
	}

	public function test_location_names_country_and_state(): void
	{
		$country = WC()->countries->get_countries()['TR'];
		$state   = WC()->countries->get_states( 'TR' )['TR34'];

		$this->assertSame( $country . ' · ' . $state, CustomerContact::location( $this->user( 'TR', 'TR34' ) ) );
	}

	public function test_location_free_text_state(): void
	{
		$country = WC()->countries->get_countries()['TR'];

		$this->assertSame( $country . ' · Foo', CustomerContact::location( $this->user( 'TR', 'Foo' ) ) );
	}

	public function test_location_country_only(): void
	{
		$this->assertSame( WC()->countries->get_countries()['DE'], CustomerContact::location( $this->user( 'DE', '' ) ) );
	}

	public function test_location_empty(): void
	{
		$this->assertSame( '', CustomerContact::location( $this->user( '', '' ) ) );
		$this->assertSame( '', CustomerContact::location( 0 ) );
	}

	public function test_an_unknown_country_code_is_not_shown_raw(): void
	{
		$this->assertSame( '', CustomerContact::location( $this->user( 'ZZ', '' ) ) );
	}
}
