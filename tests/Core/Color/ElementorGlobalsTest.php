<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Core\Color;

use Elementor\Plugin;
use MHMRentiva\Core\Color\ElementorGlobals;
use MHMRentiva\Core\Color\Sources\ElementorKitSource;
use WP_UnitTestCase;

/**
 * ElementorGlobals resolves a stored global colour reference through Elementor's
 * own data_manager_v2->run() when a REST server is already up, otherwise through
 * the kit array that mirrors the globals/colors endpoint (plan R1, R2).
 *
 * data_manager_v2 keeps `server`, `is_internal` and `cache` for the whole
 * process and the WP test case does not reset $GLOBALS['wp_rest_server'];
 * set_up/tear_down reset them by reflection (plan I3). kill_server() is not used:
 * it empties the controllers, and the Globals controller is only registered in
 * Editor::__construct(), so run() would return [] for the rest of the process.
 */
final class ElementorGlobalsTest extends WP_UnitTestCase
{
	private int $kit_id = 0;

	public function set_up(): void
	{
		parent::set_up();
		if (! class_exists('\Elementor\Plugin') || ! isset(Plugin::$instance->kits_manager)) {
			$this->markTestSkipped('Elementor is not loaded in this test run.');
		}
		self::reset_elementor_rest_state();
	}

	public function tear_down(): void
	{
		self::reset_elementor_rest_state();
		parent::tear_down();
	}

	private static function reset_elementor_rest_state(): void
	{
		$dm = Plugin::$instance->data_manager_v2;
		$rc = new \ReflectionObject($dm);
		foreach (array(
			'server'      => null,
			'is_internal' => false,
			'cache'       => array(),
		) as $prop => $value) {
			if ($rc->hasProperty($prop)) {
				$p = $rc->getProperty($prop);
				$p->setAccessible(true);
				$p->setValue($dm, $value);
			}
		}
		$GLOBALS['wp_rest_server'] = null;
	}

	/**
	 * A fresh active kit with the given system and custom colours.
	 *
	 * @param array<string,string> $system _id => colour
	 * @param array<string,string> $custom _id => colour
	 */
	private function kit(array $system, array $custom = array()): \Elementor\Core\Kits\Documents\Kit
	{
		if (0 === $this->kit_id) {
			$this->kit_id = (int) Plugin::$instance->kits_manager->create_default();
		}
		update_option('elementor_active_kit', $this->kit_id);

		$rows = static function (array $colours): array {
			$out = array();
			foreach ($colours as $id => $colour) {
				$out[] = array(
					'_id'   => $id,
					'title' => ucfirst($id),
					'color' => $colour,
				);
			}
			return $out;
		};

		$kit = Plugin::$instance->documents->get($this->kit_id, false);
		$this->assertInstanceOf(\Elementor\Core\Kits\Documents\Kit::class, $kit);
		// Elementor's settings save checks edit capability; the reads under test
		// then run as a visitor (user 0), like a front-end request.
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$kit->update_settings(
			array(
				'system_colors' => $rows($system),
				'custom_colors' => $rows($custom),
			)
		);
		wp_set_current_user(0);
		return Plugin::$instance->documents->get($this->kit_id, false);
	}

	private function factory_kit(): void
	{
		$this->kit(
			array(
				'primary'   => '#6EC1E4',
				'secondary' => '#54595F',
				'text'      => '#7A7A7A',
				'accent'    => '#61CE70',
			)
		);
	}

	public function test_kit_array_path_resolves_system_colour(): void
	{
		$this->kit(
			array(
				'primary' => '#6EC1E4',
				'accent'  => '#CC0000',
			)
		);
		$this->assertFalse(ElementorGlobals::server_is_up());
		$this->assertSame(
			array(
				'id'    => 'accent',
				'title' => 'Accent',
				'hex'   => '#cc0000',
			),
			ElementorGlobals::resolve('globals/colors?id=accent')
		);
	}

	public function test_kit_array_path_reads_custom_colour_and_filter_items(): void
	{
		$this->kit(array( 'accent' => '#61CE70' ), array( 'a1b2c3' => '#123456' ));
		add_filter(
			'elementor/globals/colors/items',
			static function (array $items): array {
				$items['e-gv-x'] = array(
					'id'    => 'e-gv-x',
					'title' => 'Variable',
					'value' => '#ABCDEF',
				);
				return $items;
			}
		);
		$this->assertSame('#123456', ElementorGlobals::resolve('globals/colors?id=a1b2c3')['hex'] ?? null);
		$this->assertSame('#abcdef', ElementorGlobals::resolve('globals/colors?id=e-gv-x')['hex'] ?? null);
	}

	public function test_kit_array_path_reads_id_with_parse_str(): void
	{
		$this->kit(array( 'accent' => '#CC0000' ));
		$this->assertSame('accent', ElementorGlobals::resolve('globals/colors?id=accent&foo=1')['id'] ?? null);
	}

	public function test_run_path_used_when_rest_is_up(): void
	{
		$this->kit(array( 'accent' => '#CC0000' ), array( 'c0ffee' => '#00AA00' ));
		add_filter(
			'elementor/globals/colors/items',
			static function (array $items): array {
				$items['e-gv-y'] = array(
					'id'    => 'e-gv-y',
					'title' => 'Variable',
					'value' => '#0D6DD7',
				);
				return $items;
			}
		);
		$routes = array();
		add_filter(
			'rest_pre_dispatch',
			static function ($result, $server, $request) use (&$routes) {
				$routes[] = $request->get_route();
				return $result;
			},
			10,
			3
		);

		rest_get_server();
		$this->assertTrue(ElementorGlobals::server_is_up());
		$this->assertSame('#cc0000', ElementorGlobals::resolve('globals/colors?id=accent')['hex'] ?? null);
		$this->assertSame('#00aa00', ElementorGlobals::resolve('globals/colors?id=c0ffee')['hex'] ?? null);
		$this->assertSame('#0d6dd7', ElementorGlobals::resolve('globals/colors?id=e-gv-y')['hex'] ?? null);
		$this->assertNotEmpty($routes, 'run() dispatched a REST request');
	}

	public function test_stored_string_passed_unchanged(): void
	{
		$this->kit(array( 'accent' => '#CC0000' ));
		$routes = array();
		$params = array();
		add_filter(
			'rest_pre_dispatch',
			static function ($result, $server, $request) use (&$routes, &$params) {
				$routes[] = $request->get_route();
				$params[] = $request->get_query_params();
				return $result;
			},
			10,
			3
		);
		rest_get_server();

		ElementorGlobals::resolve('globals/colors?id=accent&foo=1');
		$this->assertCount(1, $routes);
		$this->assertStringEndsWith('/globals/colors/accent', $routes[0]);
		// The extra query argument travelled with the request: run() got the stored string itself.
		$this->assertSame('1', $params[0]['foo'] ?? null);
	}

	public function test_page_request_resolution_leaves_rest_untouched(): void
	{
		$this->kit(array( 'accent' => '#CC0000' ));
		self::reset_elementor_rest_state();
		$this->assertFalse(ElementorGlobals::server_is_up());

		$marker = static function (): void {};
		add_action('rest_api_init', $marker);
		$before = has_action('rest_api_init', $marker);

		$this->assertSame('#cc0000', ElementorGlobals::resolve('globals/colors?id=accent')['hex'] ?? null);

		$this->assertNull($GLOBALS['wp_rest_server']);
		$this->assertSame($before, has_action('rest_api_init', $marker));
		$this->assertNotFalse(has_action('rest_api_init', $marker));
	}

	/**
	 * Measured in the full suite (PreviewInertMarkupTest went red): even with
	 * the REST server already up, Elementor's run_server() calls
	 * remove_all_actions( 'rest_api_init' ) the first time it runs, so a server
	 * rebuilt later in the same request carried only Elementor's routes.
	 */
	public function test_run_path_keeps_rest_api_init_callbacks(): void
	{
		$this->kit(array( 'accent' => '#CC0000' ));
		rest_get_server();
		$marker = static function (): void {};
		add_action('rest_api_init', $marker, 99);
		$this->assertTrue(ElementorGlobals::server_is_up());

		$this->assertSame('#cc0000', ElementorGlobals::resolve('globals/colors?id=accent')['hex'] ?? null);

		$this->assertSame(99, has_action('rest_api_init', $marker), 'run() must not drop rest_api_init callbacks');
		$this->assertNotFalse(has_action('rest_api_init', 'create_initial_rest_routes'));

		$GLOBALS['wp_rest_server'] = null;
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey('/wp/v2/block-renderer/(?P<name>[a-z0-9-]+/[a-z0-9-]+)', $routes, 'A rebuilt server keeps the core routes');
	}

	public function test_non_globals_string_and_unknown_id_are_null(): void
	{
		$this->kit(array( 'accent' => '#CC0000' ), array( 'v4var' => 'var(--e-global-color-x)' ));
		$this->assertNull(ElementorGlobals::resolve('#cc0000'));
		$this->assertNull(ElementorGlobals::resolve('globals/typography?id=x'));
		$this->assertNull(ElementorGlobals::resolve('globals/colors?id=nope'));
		$this->assertNull(ElementorGlobals::resolve('globals/colors?id=v4var'));
	}

	public function test_kit_source_accent_then_primary_skipping_factory(): void
	{
		$this->kit(
			array(
				'primary' => '#123456',
				'accent'  => '#61CE70',
			)
		);
		$source = new ElementorKitSource();
		$this->assertSame('elementor', $source->id());
		$this->assertSame('#123456', $source->resolve());
		$this->assertSame('primary', $source->label());

		$this->kit(
			array(
				'primary' => '#123456',
				'accent'  => '#0067FF',
			)
		);
		$accent = new ElementorKitSource();
		$this->assertSame('#0067ff', $accent->resolve());
		$this->assertSame('accent', $accent->label());

		$this->factory_kit();
		$this->assertNull(( new ElementorKitSource() )->resolve());
	}

	public function test_draft_kit_resolves_like_the_endpoint(): void
	{
		$this->kit(array( 'accent' => '#CC0000' ));
		wp_update_post(
			array(
				'ID'          => $this->kit_id,
				'post_status' => 'draft',
			)
		);
		clean_post_cache($this->kit_id);
		$this->assertSame('#cc0000', ElementorGlobals::resolve('globals/colors?id=accent')['hex'] ?? null);

		rest_get_server();
		$this->assertTrue(ElementorGlobals::server_is_up());
		$this->assertSame('#cc0000', ElementorGlobals::resolve('globals/colors?id=accent')['hex'] ?? null, 'run() path agrees');
	}

	public function test_missing_kit_is_null(): void
	{
		update_option('elementor_active_kit', 987654321);
		$warnings = array();
		set_error_handler(
			static function (int $errno, string $errstr) use (&$warnings): bool {
				$warnings[] = $errstr;
				return true;
			},
			E_WARNING | E_NOTICE | E_DEPRECATED
		);
		$result = ElementorGlobals::resolve('globals/colors?id=accent');
		restore_error_handler();
		$this->assertNull($result);
		$this->assertSame(array(), $warnings);
	}

	public function test_server_is_up_requires_a_real_rest_server(): void
	{
		rest_get_server();
		$this->assertTrue(ElementorGlobals::server_is_up());
		$GLOBALS['wp_rest_server'] = false;
		$this->assertFalse(ElementorGlobals::server_is_up());
	}
}
