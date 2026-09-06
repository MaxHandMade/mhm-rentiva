<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use WP_UnitTestCase;

/**
 * The three surfaces are one rule wearing three shapes. If they drift, an
 * account is a customer on one screen and not on another -- the exact defect
 * PR #30 half-closed.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomerIdentity
 */
final class CustomerIdentitySurfacesTest extends WP_UnitTestCase
{
	public function test_the_correlated_surface_is_prepared_and_mentions_both_links(): void
	{
		$sql = CustomerIdentity::sql_user_owns_booking();

		$this->assertStringContainsString( '_mhmrentiva_customer_user_id', $sql );
		$this->assertStringContainsString( '_mhmrentiva_customer_email', $sql );
		$this->assertStringContainsString( 'u.ID', $sql );
		$this->assertStringContainsString( "u.user_email <> ''", $sql );
		$this->assertStringContainsString( 'bmeta.post_id = p.ID', $sql );
	}

	public function test_the_bound_surface_binds_its_values(): void
	{
		$sql = CustomerIdentity::sql_booking_owned_by( 42, "o'brien@example.test" );

		$this->assertStringContainsString( '42', $sql );
		$this->assertStringNotContainsString( "o'brien@example.test", $sql, 'The raw quote must not survive into SQL.' );
		$this->assertStringContainsString( 'bmeta.post_id = p.ID', $sql );
	}

	public function test_the_bound_surface_drops_the_email_branch_when_there_is_no_email(): void
	{
		$sql = CustomerIdentity::sql_booking_owned_by( 42, '' );

		// The guard is bound, so an empty email can never match every row whose
		// email meta is also empty.
		$this->assertStringContainsString( "'' <> ''", $sql );
	}

	public function test_the_meta_query_surface_is_an_or_over_both_links(): void
	{
		$mq = CustomerIdentity::meta_query_owned_by( 42, 'a@example.test' );

		$this->assertSame( 'OR', $mq['relation'] );
		$keys = array_column( array_filter( $mq, 'is_array' ), 'key' );
		$this->assertContains( '_mhmrentiva_customer_user_id', $keys );
		$this->assertContains( '_mhmrentiva_customer_email', $keys );
	}

	public function test_the_meta_query_surface_omits_the_email_clause_when_there_is_no_email(): void
	{
		$mq = CustomerIdentity::meta_query_owned_by( 42, '' );

		$keys = array_column( array_filter( $mq, 'is_array' ), 'key' );
		$this->assertSame( array( '_mhmrentiva_customer_user_id' ), $keys );
	}
}
