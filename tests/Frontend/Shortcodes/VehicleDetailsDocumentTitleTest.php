<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails;
use WP_UnitTestCase;

/**
 * The vehicle page's document title names the vehicle: the browser tab, a
 * search result and a shared link read "Hyundai Tucson – Site", not the
 * page's own title ("Vehicle details – Site") for every car.
 */
final class VehicleDetailsDocumentTitleTest extends WP_UnitTestCase
{
	private int $page;

	private const PARTS = array(
		'title' => 'Vehicle details',
		'site'  => 'Rentiva Test',
	);

	public function setUp(): void
	{
		parent::setUp();
		$this->page = (int) self::factory()->post->create(array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Vehicle details',
			'post_content' => '[rentiva_vehicle_details]',
		));
		self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Hyundai Tucson & Co',
			'post_name'   => 'hyundai-tucson',
		));
		wp_cache_flush();
	}

	public function tearDown(): void
	{
		set_query_var('mhmrentiva_vehicle_slug', '');
		parent::tearDown();
	}

	private function on(int $page, string $slug): array
	{
		$this->go_to((string) get_permalink($page));
		set_query_var('mhmrentiva_vehicle_slug', $slug);
		return (array) VehicleDetails::document_title_parts(self::PARTS);
	}

	public function test_the_vehicle_page_names_its_vehicle_and_keeps_the_site(): void
	{
		$parts = $this->on($this->page, 'hyundai-tucson');

		$this->assertSame('Hyundai Tucson & Co', $parts['title'], 'Plain text: core\'s document_title filter escapes it.');
		$this->assertSame('Rentiva Test', $parts['site']);

		// End to end: the escaped document title, as both theme kinds print it.
		add_filter('document_title_parts', array( VehicleDetails::class, 'document_title_parts' ));
		$this->assertStringStartsWith('Hyundai Tucson &#038; Co', wp_get_document_title(), 'esc_html() writes & as &#038;.');
	}

	public function test_without_a_vehicle_in_the_url_the_title_is_the_page(): void
	{
		$this->assertSame(self::PARTS, $this->on($this->page, ''));
	}

	public function test_an_unknown_vehicle_leaves_the_title_alone(): void
	{
		$this->assertSame(self::PARTS, $this->on($this->page, 'no-such-car'));
	}

	public function test_the_query_var_on_another_page_does_not_rename_it(): void
	{
		$other = (int) self::factory()->post->create(array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'About' ));

		$this->assertSame(self::PARTS, $this->on($other, 'hyundai-tucson'));
	}

	public function test_the_filter_is_registered(): void
	{
		VehicleDetails::register();

		$this->assertNotFalse(has_filter('document_title_parts', array( VehicleDetails::class, 'document_title_parts' )));
	}
}
