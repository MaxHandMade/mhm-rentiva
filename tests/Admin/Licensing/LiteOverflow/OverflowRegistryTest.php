<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Licensing\LiteOverflow;

use MHMRentiva\Admin\Licensing\LiteOverflow\OverflowRegistry;
use WP_UnitTestCase;

final class OverflowRegistryTest extends WP_UnitTestCase {

	protected function tearDown(): void {
		delete_option( 'mhm_rentiva_lite_overflow_hidden' );
		parent::tearDown();
	}

	public function test_empty_by_default(): void {
		$this->assertSame( array(), OverflowRegistry::get( 'vehicle' ) );
		$this->assertFalse( OverflowRegistry::isHidden( 'vehicle', 5 ) );
	}

	public function test_set_and_get_roundtrip_with_int_coercion(): void {
		OverflowRegistry::set( 'vehicle', array( '6', 7, '8' ) );
		$this->assertSame( array( 6, 7, 8 ), OverflowRegistry::get( 'vehicle' ) );
		$this->assertTrue( OverflowRegistry::isHidden( 'vehicle', 7 ) );
		$this->assertFalse( OverflowRegistry::isHidden( 'vehicle_addon', 7 ) );
	}

	public function test_unknown_type_is_ignored(): void {
		OverflowRegistry::set( 'bogus', array( 1, 2 ) );
		$this->assertSame( array(), OverflowRegistry::get( 'bogus' ) );
	}

	public function test_clear_all_empties_every_type(): void {
		OverflowRegistry::set( 'vehicle', array( 1 ) );
		OverflowRegistry::set( 'route', array( 2 ) );
		OverflowRegistry::clearAll();
		$this->assertSame( array(), OverflowRegistry::get( 'vehicle' ) );
		$this->assertSame( array(), OverflowRegistry::get( 'route' ) );
	}
}
