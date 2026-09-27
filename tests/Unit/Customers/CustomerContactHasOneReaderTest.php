<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Customers;

use PHPUnit\Framework\TestCase;

/**
 * Nothing but CustomerContact reads a customer's phone or address from user meta.
 *
 * Before it existed, eight places read the phone, from four keys, in four
 * orders -- and five of them fell back to a bare `phone` key nothing here
 * writes. A ninth reader added later would reopen the split this closes, so
 * the rule is pinned over the whole source tree rather than per call site.
 * Writers (update_user_meta) are not the subject; they are Rentiva's own field
 * and WooCommerce's.
 *
 * Scans src/ and templates/ for get_user_meta() on one of the contact keys,
 * the call spanning at most one line. A reader built differently (a variable
 * key, get_metadata()) is not seen -- this is a guard, not a proof.
 */
final class CustomerContactHasOneReaderTest extends TestCase
{
	private const KEYS = array( 'mhmrentiva_phone', 'mhmrentiva_address', 'billing_phone', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'phone' );

	private const THE_READER = 'src/Admin/Customers/CustomerContact.php';

	/**
	 * Structured billing fields copied into a WooCommerce order's own
	 * structured fields -- WooCommerce's shape, not a displayed address.
	 */
	private const STRUCTURED = array(
		'src/Admin/Payment/WooCommerce/RemainingPaymentHandler.php' => array( 'billing_phone', 'billing_address_1', 'billing_city', 'billing_postcode' ),
	);

	public function test_only_customer_contact_reads_the_contact_keys(): void
	{
		$root     = dirname( __DIR__, 3 );
		$pattern  = '/get_user_meta\s*\([^;\n]*[\'"](' . implode( '|', array_map( 'preg_quote', self::KEYS ) ) . ')[\'"]/';
		$offences = array();

		foreach ( array( 'src', 'templates' ) as $dir ) {
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( 'php' !== $file->getExtension() ) {
					continue;
				}
				$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
				if ( self::THE_READER === $relative ) {
					continue;
				}
				foreach ( file( $file->getPathname() ) as $n => $line ) {
					if ( preg_match( $pattern, $line, $m ) && ! in_array( $m[1], self::STRUCTURED[ $relative ] ?? array(), true ) ) {
						$offences[] = $relative . ':' . ( $n + 1 );
					}
				}
			}
		}

		$this->assertSame( array(), $offences, 'Read contact details through CustomerContact::phone()/address().' );
	}

	public function test_the_scan_sees_the_shape_it_looks_for(): void
	{
		$pattern = '/get_user_meta\s*\([^;\n]*[\'"](' . implode( '|', array_map( 'preg_quote', self::KEYS ) ) . ')[\'"]/';

		$this->assertSame( 1, preg_match( $pattern, "\$p = get_user_meta(\$id, 'billing_phone', true);" ) );
		$this->assertSame( 1, preg_match( $pattern, "get_user_meta( (int) \$user_id, 'phone', true )" ) );
		$this->assertSame( 0, preg_match( $pattern, "update_user_meta(\$id, 'mhmrentiva_phone', \$v);" ) );
		$this->assertSame( 0, preg_match( $pattern, "get_user_meta(\$id, '_mhmrentiva_vendor_phone', true);" ) );
	}
}
