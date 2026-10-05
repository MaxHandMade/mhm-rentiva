<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Spec §3 T3: the late wp_kses() in ShortcodeServiceProvider must not eat markup.
 *
 * Every registered Lite tag is rendered twice from the same fixture: once through
 * the raw inner callback (what the shortcode class produced) and once through the
 * registered wrapper (auth gate + wp_kses( Html::allowed_markup() )). The DOM
 * tag/attribute inventories must be equal. The raw render must carry a marker and
 * a minimum number of elements first, so an empty or error render can not pass
 * by having nothing to lose.
 */
final class KsesGateTest extends WP_UnitTestCase
{
	/**
	 * The Lite registry (ShortcodeServiceProvider::get_raw_shortcode_registry()).
	 * test_every_registered_tag_is_gated() keeps this list honest.
	 */
	private const TAGS = array(
		'rentiva_booking_form',
		'rentiva_availability_calendar',
		'rentiva_vehicle_details',
		'rentiva_vehicles_list',
		'rentiva_featured_vehicles',
		'rentiva_vehicles_grid',
		'rentiva_search_results',
		'rentiva_vehicle_comparison',
		'rentiva_unified_search',
		'rentiva_user_dashboard',
		'rentiva_my_bookings',
		'rentiva_my_favorites',
		'rentiva_payment_history',
		'rentiva_contact',
		'rentiva_testimonials',
		'rentiva_vehicle_rating_form',
	);

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		// A cache hit would hand the second render the first one's HTML; both
		// renders must run the shortcode for real.
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false');
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function tags(): array
	{
		$out = array();
		foreach (self::TAGS as $tag) {
			$out[ $tag ] = array( $tag );
		}
		return $out;
	}

	public function test_every_registered_tag_is_gated(): void
	{
		$registered = array_values(
			array_filter(
				array_keys($GLOBALS['shortcode_tags']),
				static fn(string $tag): bool => 0 === strpos($tag, 'rentiva_')
			)
		);
		$expected   = self::TAGS;
		sort($registered);
		sort($expected);

		$this->assertSame($expected, $registered, 'A registered rentiva_* tag has no kses gate row (or a gated tag is gone).');
	}

	/**
	 * @dataProvider tags
	 */
	public function test_kses_keeps_every_tag_and_attribute(string $tag): void
	{
		$fixture = ShortcodeFixtures::for_tag($tag);
		wp_set_current_user((int) $fixture['user']);

		$this->assertArrayHasKey($tag, $GLOBALS['shortcode_tags'], "$tag is not registered.");
		$wrapper = $GLOBALS['shortcode_tags'][ $tag ];
		$this->assertInstanceOf(\Closure::class, $wrapper, "$tag is not registered through the ShortcodeServiceProvider wrapper.");

		$used = ( new \ReflectionFunction($wrapper) )->getClosureUsedVariables();
		$this->assertArrayHasKey('callback', $used, "$tag wrapper does not close over an inner callback.");

		// Same capture as ShortcodeServiceProvider::handle_shortcode_execution():
		// a returned string wins, echoed output is the fallback.
		ob_start();
		$returned = call_user_func($used['callback'], $fixture['atts'], null, $tag);
		$echoed   = (string) ob_get_clean();
		$raw      = (string) ( $returned ?? $echoed );

		RequestSimulator::new_request();
		$wrapped = (string) call_user_func($wrapper, $fixture['atts'], null);

		$raw_inventory = $this->inventory($raw);

		$this->assertStringContainsString($fixture['marker'], $raw, "$tag raw render lacks its marker; the fixture did not reach the real markup.");
		$this->assertGreaterThanOrEqual(
			$fixture['min_elements'],
			$this->element_count($raw_inventory),
			"$tag raw render has fewer elements than its floor."
		);

		$this->assertSame($raw_inventory, $this->inventory($wrapped), "$tag: wp_kses changed the tag/attribute inventory.");
	}

	/**
	 * Negative control: markup the allowlist refuses must show up as a difference,
	 * or the equality above proves nothing.
	 */
	public function test_gate_detects_stripped_markup(): void
	{
		$fixture = ShortcodeFixtures::for_tag('rentiva_contact');
		add_filter(
			'mhmrentiva_shortcodes_rentiva_contact_html',
			static fn(string $html): string => $html . '<script>void 0;</script><div data-x="1" onclick="void 0">x</div>'
		);

		$wrapper = $GLOBALS['shortcode_tags']['rentiva_contact'];
		$used    = ( new \ReflectionFunction($wrapper) )->getClosureUsedVariables();
		$raw     = (string) call_user_func($used['callback'], $fixture['atts'], null, 'rentiva_contact');
		RequestSimulator::new_request();
		$wrapped = (string) call_user_func($wrapper, $fixture['atts'], null);

		$raw_inventory     = $this->inventory($raw);
		$wrapped_inventory = $this->inventory($wrapped);

		$this->assertSame(1, $raw_inventory['script'] ?? 0);
		$this->assertArrayNotHasKey('script', $wrapped_inventory);
		$this->assertArrayNotHasKey('div@onclick', $wrapped_inventory);
		$this->assertNotSame($raw_inventory, $wrapped_inventory);
	}

	/**
	 * Tag and tag@attribute occurrence counts in a fragment.
	 *
	 * An empty style attribute is not counted: it declares nothing, and wp_kses
	 * drops it because safecss_filter_attr() leaves an empty value (the calendar
	 * prints style="" on its visible panels). A style that carried declarations
	 * and lost all of them still counts, and its absence after kses is a failure.
	 *
	 * @return array<string,int>
	 */
	private function inventory(string $html): array
	{
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		$out  = array();
		$body = $doc->getElementsByTagName('body')->item(0);
		if (null === $body) {
			return $out;
		}

		foreach ($body->getElementsByTagName('*') as $element) {
			$name         = $element->nodeName;
			$out[ $name ] = ( $out[ $name ] ?? 0 ) + 1;
			foreach ($element->attributes as $attribute) {
				if ('style' === $attribute->nodeName && '' === trim((string) $attribute->nodeValue)) {
					continue;
				}
				$key         = $name . '@' . $attribute->nodeName;
				$out[ $key ] = ( $out[ $key ] ?? 0 ) + 1;
			}
		}
		ksort($out);

		return $out;
	}

	/**
	 * @param array<string,int> $inventory Result of inventory().
	 */
	private function element_count(array $inventory): int
	{
		$count = 0;
		foreach ($inventory as $key => $n) {
			if (false === strpos($key, '@')) {
				$count += $n;
			}
		}
		return $count;
	}
}
