<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Admin\Core\LocationContext;
use WP_UnitTestCase;

/**
 * LocationContext caches the rental locations for the PHP process's request;
 * a test run is one process, so the cache is dropped before every test by a
 * listener registered in phpunit.xml.
 */
final class ResetsLocationContextListenerTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		remove_all_filters('mhmrentiva_locations');
		LocationContext::flush();
		parent::tearDown();
	}

	public function test_listener_drops_the_cache_before_a_test(): void
	{
		$rows = array( (object) array( 'id' => '3', 'name' => 'A', 'city' => 'X' ) );
		add_filter('mhmrentiva_locations', static function () use (&$rows) {
			return $rows;
		});
		$this->assertCount(1, LocationContext::rental_locations());

		$rows[] = (object) array( 'id' => '4', 'name' => 'B', 'city' => 'X' );
		$this->assertCount(1, LocationContext::rental_locations(), 'Precondition: cached.');

		( new ResetsLocationContextListener() )->startTest($this);
		$this->assertCount(2, LocationContext::rental_locations());
	}

	public function test_listener_is_registered_in_phpunit_xml(): void
	{
		$config = (string) file_get_contents(dirname(__DIR__, 2) . '/phpunit.xml');

		$this->assertStringContainsString('class="MHMRentiva\Tests\Support\ResetsLocationContextListener"', $config);
		$this->assertStringContainsString('file="tests/Support/ResetsLocationContextListener.php"', $config);
	}
}
