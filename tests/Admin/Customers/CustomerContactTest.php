<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerContact;
use WP_UnitTestCase;

/**
 * One answer to "what is this customer's phone / address".
 *
 * The same account carried its phone under up to four keys and every screen
 * read a different one, in a different order: the Customers screen read only
 * `mhmrentiva_phone`, the booking form preferred `billing_phone`, the manual
 * booking box read `mhmrentiva_phone` then `phone`. Found on the dev site: a
 * customer with a billing phone and address read "Phone -  Address -" on
 * Customers, and the edit form demanded a phone it already had.
 *
 * Order: Rentiva's own field (what My Account, the admin edit screen and the
 * manual booking write) → WooCommerce billing (what checkout writes) → whatever
 * `mhmrentiva_customer_contact` adds. Nothing is copied.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomerContact
 */
final class CustomerContactTest extends WP_UnitTestCase
{
	private function user(): int
	{
		return (int) self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	public function test_rentivas_own_phone_wins(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'mhmrentiva_phone', '+90 111' );
		update_user_meta( $user, 'billing_phone', '+90 222' );

		$this->assertSame( '+90 111', CustomerContact::phone( $user ) );
	}

	public function test_the_billing_phone_fills_an_empty_rentiva_phone(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'mhmrentiva_phone', '' );
		update_user_meta( $user, 'billing_phone', '+90 222' );

		$this->assertSame( '+90 222', CustomerContact::phone( $user ) );
	}

	public function test_a_bare_phone_key_is_the_last_resort(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'phone', '+90 333' );

		$this->assertSame( '+90 333', CustomerContact::phone( $user ) );

		update_user_meta( $user, 'billing_phone', '+90 222' );
		$this->assertSame( '+90 222', CustomerContact::phone( $user ) );
	}

	public function test_reading_writes_nothing(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'billing_phone', '+90 222' );

		CustomerContact::phone( $user );
		CustomerContact::address( $user );

		$this->assertSame( '', (string) get_user_meta( $user, 'mhmrentiva_phone', true ) );
		$this->assertSame( '', (string) get_user_meta( $user, 'mhmrentiva_address', true ) );
	}

	public function test_rentivas_own_address_wins(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'mhmrentiva_address', 'Rentiva Sok. 5' );
		update_user_meta( $user, 'billing_address_1', 'Test Sokak 1' );

		$this->assertSame( 'Rentiva Sok. 5', CustomerContact::address( $user ) );
	}

	public function test_the_billing_address_is_assembled_from_its_parts(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'billing_address_1', 'Test Sokak 1' );
		update_user_meta( $user, 'billing_address_2', 'Daire 3' );
		update_user_meta( $user, 'billing_postcode', '06002' );
		update_user_meta( $user, 'billing_city', 'Ulus' );

		$this->assertSame( 'Test Sokak 1, Daire 3, 06002 Ulus', CustomerContact::address( $user ) );
	}

	public function test_a_billing_address_with_only_a_city_is_still_an_address(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'billing_city', 'Ulus' );

		$this->assertSame( 'Ulus', CustomerContact::address( $user ) );
	}

	public function test_nothing_known_is_an_empty_string(): void
	{
		$user = $this->user();

		$this->assertSame( '', CustomerContact::phone( $user ) );
		$this->assertSame( '', CustomerContact::address( $user ) );
		$this->assertSame( '', CustomerContact::phone( 0 ) );
	}

	public function test_the_filter_sees_what_lite_found_and_may_fill_a_gap(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'billing_city', 'Ulus' );

		$seen   = null;
		$filter = static function ( array $contact, int $user_id ) use ( &$seen ): array {
			$seen = array( $contact, $user_id );
			if ( '' === $contact['phone'] ) {
				$contact['phone'] = '+90 555';
			}
			return $contact;
		};
		add_filter( 'mhmrentiva_customer_contact', $filter, 10, 2 );

		try {
			$this->assertSame( '+90 555', CustomerContact::phone( $user ) );
			$this->assertSame(
				array(
					array(
						'phone'   => '',
						'address' => 'Ulus',
					),
					$user,
				),
				$seen
			);
		} finally {
			remove_filter( 'mhmrentiva_customer_contact', $filter, 10 );
		}
	}

	public function test_a_filter_returning_garbage_cannot_break_the_screen(): void
	{
		$user = $this->user();
		update_user_meta( $user, 'billing_phone', '+90 222' );

		$filter = static fn(): string => 'not an array';
		add_filter( 'mhmrentiva_customer_contact', $filter );

		try {
			$this->assertSame( '+90 222', CustomerContact::phone( $user ) );
		} finally {
			remove_filter( 'mhmrentiva_customer_contact', $filter );
		}
	}
}
