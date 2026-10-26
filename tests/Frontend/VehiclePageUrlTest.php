<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks;
use MHMRentiva\Plugin;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * PR S Task 8 item 1: the "look from that date" link lost its window.
 *
 * Customer links pointed at the vehicle CPT permalink (/arac/<slug>/?window);
 * Plugin::load_vehicle_templates() 301-redirected that to the vehicle-details
 * page (/arac-detay/<slug>/) and dropped the query string, so the vehicle page
 * opened on its default dates. Two fixes, both pinned here:
 * - customer links point straight at the vehicle page
 *   (ShortcodeUrlManager::vehicle_page_url()), no redirect hop;
 * - the redirect itself carries the request's query args across.
 *
 * @covers \MHMRentiva\Admin\Core\ShortcodeUrlManager::vehicle_page_url
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Card\VehicleCardLinks::details_url
 * @covers \MHMRentiva\Plugin::load_vehicle_templates
 * @covers \MHMRentiva\Plugin::vehicle_redirect_url
 */
final class VehiclePageUrlTest extends WP_UnitTestCase
{
	private int $details_page = 0;

	public function set_up(): void
	{
		parent::set_up();
		$this->set_permalink_structure('/%postname%/');
		wp_cache_flush();
		ShortcodeUrlManager::clear_cache();
	}

	public function tear_down(): void
	{
		remove_all_filters('wp_redirect');
		ShortcodeUrlManager::clear_cache();
		parent::tear_down();
	}

	private function details_page(): int
	{
		$this->details_page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => 'arac-detay',
				'post_content' => '[rentiva_vehicle_details]',
			)
		);
		wp_cache_flush();
		ShortcodeUrlManager::clear_cache();

		return $this->details_page;
	}

	private function slug(int $vehicle): string
	{
		return (string) get_post_field('post_name', $vehicle);
	}

	public function test_vehicle_page_url_is_the_details_page_sub_path(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$page    = $this->details_page();

		$this->assertSame(
			trailingslashit(get_permalink($page)) . $this->slug($vehicle) . '/',
			ShortcodeUrlManager::vehicle_page_url($vehicle)
		);
	}

	public function test_without_a_details_page_the_cpt_permalink_is_used(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();

		$this->assertSame(get_permalink($vehicle), ShortcodeUrlManager::vehicle_page_url($vehicle));
	}

	public function test_plain_permalinks_name_the_vehicle_by_query_var(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$page    = $this->details_page();
		$this->set_permalink_structure('');

		$url = ShortcodeUrlManager::vehicle_page_url($vehicle);

		parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
		$this->assertSame((string) $page, $query['page_id'] ?? '');
		$this->assertSame($this->slug($vehicle), $query['mhmrentiva_vehicle_slug'] ?? '');
	}

	public function test_a_non_vehicle_post_has_no_vehicle_page(): void
	{
		$this->details_page();
		$post = self::factory()->post->create();

		$this->assertSame('', ShortcodeUrlManager::vehicle_page_url($post));
		$this->assertSame('', ShortcodeUrlManager::vehicle_page_url(0));
	}

	public function test_details_url_is_the_details_page_with_the_window(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$page    = $this->details_page();

		$url = VehicleCardLinks::details_url($vehicle, array(
			'pickup_date' => '2026-12-10',
			'pickup_time' => '11:00',
			'return_date' => '2026-12-13',
		));

		$this->assertStringStartsWith(trailingslashit(get_permalink($page)) . $this->slug($vehicle) . '/?', $url);
		parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
		$this->assertSame(
			array(
				'mhmrentiva_pickup_date' => '2026-12-10',
				'mhmrentiva_pickup_time' => '11:00',
				'mhmrentiva_return_date' => '2026-12-13',
			),
			$query
		);
	}

	public function test_redirect_url_keeps_the_window_and_drops_routing_vars(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$page    = $this->details_page();

		$url = Plugin::vehicle_redirect_url(get_post($vehicle), array(
			'mhmrentiva_pickup_date' => '2026-12-10',
			'mhmrentiva_pickup_time' => '11:00',
			'mhmrentiva_return_date' => '2026-12-13',
			'utm_source'             => 'mail & news',
			'mhmrentiva_vehicle'     => $this->slug($vehicle),
			'post_type'              => 'mhmrentiva_vehicle',
			'p'                      => (string) $vehicle,
			'nested'                 => array( 'x' ),
		));

		$this->assertStringStartsWith(trailingslashit(get_permalink($page)) . $this->slug($vehicle) . '/?', $url);
		parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);
		$this->assertSame(
			array(
				'mhmrentiva_pickup_date' => '2026-12-10',
				'mhmrentiva_pickup_time' => '11:00',
				'mhmrentiva_return_date' => '2026-12-13',
				'utm_source'             => 'mail & news',
			),
			$query
		);
	}

	public function test_visiting_the_cpt_permalink_redirects_with_the_window(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$page    = $this->details_page();

		$this->go_to(add_query_arg(
			array(
				'mhmrentiva_pickup_date' => '2026-12-10',
				'mhmrentiva_pickup_time' => rawurlencode('11:00'),
				'mhmrentiva_return_date' => '2026-12-13',
			),
			get_permalink($vehicle)
		));
		$this->assertTrue(is_singular('mhmrentiva_vehicle'), 'The CPT permalink resolves to the vehicle.');

		// go_to() does not fill $_GET; the redirect reads the real request.
		$_GET = array(
			'mhmrentiva_pickup_date' => '2026-12-10',
			'mhmrentiva_pickup_time' => '11:00',
			'mhmrentiva_return_date' => '2026-12-13',
		);

		$location = null;
		add_filter(
			'wp_redirect',
			static function ($to) use (&$location) {
				$location = $to;
				throw new \RuntimeException('redirected');
			}
		);

		$plugin = ( new \ReflectionClass(Plugin::class) )->newInstanceWithoutConstructor();
		try {
			$plugin->load_vehicle_templates();
		} catch (\RuntimeException $e) {
			$this->assertSame('redirected', $e->getMessage());
		} finally {
			$_GET = array();
		}

		$this->assertIsString($location);
		$this->assertStringStartsWith(trailingslashit(get_permalink($page)) . $this->slug($vehicle) . '/?', $location);
		parse_str((string) wp_parse_url($location, PHP_URL_QUERY), $query);
		$this->assertSame('2026-12-10', $query['mhmrentiva_pickup_date'] ?? '');
		$this->assertSame('11:00', $query['mhmrentiva_pickup_time'] ?? '');
		$this->assertSame('2026-12-13', $query['mhmrentiva_return_date'] ?? '');
	}
}
