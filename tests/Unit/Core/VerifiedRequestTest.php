<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use WP_UnitTestCase;

/**
 * WP.org T6 suppression audit: the request readers that used to live as
 * private static helpers on BookingMeta/VehicleMeta/AbstractMetaBox read
 * $_POST directly and carried a blanket
 * `phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in
 * the caller` on every one of them. The suppression was behaviourally
 * accurate but unverifiable by any reviewer (human or WP.org's scanner)
 * reading the helper in isolation, which is exactly the class of "trust me"
 * annotation that got the plugin rejected.
 *
 * VerifiedRequest removes the annotation instead of restating it: the reader
 * never touches a superglobal, so the nonce check and the data read now sit
 * in the SAME function scope at every call site and PHPCS can see it.
 *
 * @covers \MHMRentiva\Admin\Core\Security\VerifiedRequest
 */
final class VerifiedRequestTest extends WP_UnitTestCase
{
	public function test_text_sanitizes_and_unslashes(): void
	{
		$req = VerifiedRequest::from(
			array( 'name' => 'O\\\'Brien <script>alert(1)</script>' )
		);

		$this->assertSame( "O'Brien", $req->text( 'name' ) );
	}

	public function test_text_returns_fallback_for_missing_key(): void
	{
		$req = VerifiedRequest::from( array() );

		$this->assertSame( '', $req->text( 'missing' ) );
		$this->assertSame( 'default', $req->text( 'missing', 'default' ) );
	}

	public function test_textarea_preserves_newlines(): void
	{
		$req = VerifiedRequest::from( array( 'notes' => "line one\nline two" ) );

		$this->assertSame( "line one\nline two", $req->textarea( 'notes' ) );
	}

	public function test_int_casts_through_absint(): void
	{
		$req = VerifiedRequest::from(
			array(
				'a' => '42abc',
				'b' => '-7',
			)
		);

		$this->assertSame( 42, $req->int( 'a' ) );
		$this->assertSame( 7, $req->int( 'b' ) );
		$this->assertSame( 5, $req->int( 'missing', 5 ) );
	}

	public function test_arr_returns_unslashed_array_and_empty_for_scalars(): void
	{
		$req = VerifiedRequest::from(
			array(
				'list'   => array( 'a\\\'b', 'c' ),
				'scalar' => 'not-an-array',
			)
		);

		$this->assertSame( array( "a'b", 'c' ), $req->arr( 'list' ) );
		$this->assertSame( array(), $req->arr( 'scalar' ) );
		$this->assertSame( array(), $req->arr( 'missing' ) );
	}

	public function test_has_reports_key_presence(): void
	{
		$req = VerifiedRequest::from(
			array(
				'present' => '',
				'zero'    => '0',
			)
		);

		$this->assertTrue( $req->has( 'present' ) );
		$this->assertTrue( $req->has( 'zero' ) );
		$this->assertFalse( $req->has( 'absent' ) );
	}

	public function test_raw_unslashes_without_sanitizing(): void
	{
		$req = VerifiedRequest::from( array( 'html' => '<b>bold</b> O\\\'Brien' ) );

		$this->assertSame( "<b>bold</b> O'Brien", $req->raw( 'html' ) );
		$this->assertNull( $req->raw( 'missing' ) );
	}

	public function test_reader_never_falls_back_to_the_superglobal(): void
	{
		$_POST['leak'] = 'from-superglobal';

		$req = VerifiedRequest::from( array() );

		$this->assertSame( '', $req->text( 'leak' ) );
		$this->assertFalse( $req->has( 'leak' ) );

		unset( $_POST['leak'] );
	}
}
