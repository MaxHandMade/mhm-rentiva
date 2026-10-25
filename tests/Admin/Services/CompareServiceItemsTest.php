<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Services;

use MHMRentiva\Admin\Services\CompareService;
use WP_UnitTestCase;

/**
 * The results page's comparison band names the compared vehicles. A guest's
 * list is a cookie anyone can write, so only published vehicles are named --
 * never a draft's, a private post's or another post type's title.
 *
 * @covers \MHMRentiva\Admin\Services\CompareService::items
 */
final class CompareServiceItemsTest extends WP_UnitTestCase
{
	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		$this->reset();
	}

	public function tearDown(): void
	{
		unset($_COOKIE['mhmrentiva_compare']);
		$this->reset();
		parent::tearDown();
	}

	private function reset(): void
	{
		$prop = new \ReflectionProperty(CompareService::class, 'cached_list');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
	}

	public function test_items_name_published_vehicles_only(): void
	{
		$vehicle = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => 'Mercedes & Benz' ));
		$draft   = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'draft', 'post_title' => 'Secret Draft Car' ));
		$page    = self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'private', 'post_title' => 'Private Page' ));

		$_COOKIE['mhmrentiva_compare'] = wp_json_encode(array( $vehicle, $draft, $page, 999999 ));
		$items                         = CompareService::items();

		$this->assertSame(array( $vehicle ), wp_list_pluck($items, 'id'));
		$this->assertSame('Mercedes & Benz', $items[0]['title'], 'Plain text for the band (the script writes it with .text()).');

		$quoted                        = self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'publish', 'post_title' => "Driver's Edition" ));
		$_COOKIE['mhmrentiva_compare'] = wp_json_encode(array( $quoted ));
		$this->reset();
		$this->assertStringNotContainsString('&#', CompareService::items()[0]['title'], 'Texturized entities are decoded.');
	}

	public function test_max_items_is_the_service_limit(): void
	{
		$this->assertSame(3, CompareService::max_items());
	}
}
