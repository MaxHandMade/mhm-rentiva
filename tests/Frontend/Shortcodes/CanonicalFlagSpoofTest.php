<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * `_canonical` is a trust flag: it skips shortcode_atts() truncation and CAM
 * sanitisation. Only the PHP array path (Templates::render_shortcode_atts(),
 * WidgetAttributeBridge::to_canonical(), BlockRegistry) may set it, and it does so
 * with boolean true. Shortcode text (a post author, a hand-written block comment)
 * can only produce strings, so a textual `_canonical="1"` must change nothing.
 */
final class CanonicalFlagSpoofTest extends WP_UnitTestCase
{
	/** @var array<int,array<string,mixed>> */
	private array $seen = array();

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcodes_rentiva_contact_html', array( $this, 'record' ), 10, 2);
		add_filter('mhmrentiva_shortcodes_rentiva_testimonials_html', array( $this, 'record' ), 10, 2);
	}

	public function tearDown(): void
	{
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @param string              $html Markup.
	 * @param array<string,mixed> $atts Attributes the shortcode used.
	 */
	public function record(string $html, array $atts): string
	{
		$this->seen[] = $atts;
		return $html;
	}

	public function test_text_canonical_flag_on_contact_changes_nothing(): void
	{
		do_shortcode('[rentiva_contact _canonical="1" foo="bar" show_phone="maybe"]');
		do_shortcode('[rentiva_contact foo="bar" show_phone="maybe"]');

		$this->assertCount(2, $this->seen);
		[ $spoofed, $plain ] = $this->seen;

		$this->assertArrayNotHasKey('_canonical', $spoofed);
		$this->assertArrayNotHasKey('foo', $spoofed);
		$this->assertSame('0', $spoofed['show_phone'] ?? null);
		$this->assertSame($plain, $spoofed);
	}

	public function test_text_canonical_flag_on_testimonials_changes_nothing(): void
	{
		do_shortcode('[rentiva_testimonials _canonical="1" category="zz-probe" limit="2"]');
		do_shortcode('[rentiva_testimonials category="zz-probe" limit="2"]');

		$this->assertCount(2, $this->seen);
		[ $spoofed, $plain ] = $this->seen;

		$this->assertArrayNotHasKey('_canonical', $spoofed);
		$this->assertArrayNotHasKey('category', $spoofed);
		$this->assertSame($plain, $spoofed);
	}

	/**
	 * shortcode_parse_atts() lowercases attribute names, so `_CANONICAL` reaches the
	 * callback as `_canonical`; the leading underscore is no protection either.
	 */
	public function test_uppercase_text_flag_on_testimonials_changes_nothing(): void
	{
		$truncations = 0;
		add_filter(
			'shortcode_atts_rentiva_testimonials',
			static function ($out) use (&$truncations) {
				++$truncations;
				return $out;
			}
		);

		do_shortcode('[rentiva_testimonials _CANONICAL="yes" offset="5"]');
		$this->assertSame(1, $truncations, 'The uppercase flag skipped shortcode_atts().');
		do_shortcode('[rentiva_testimonials offset="5"]');
		$this->assertSame(2, $truncations);

		$this->assertCount(2, $this->seen);
		[ $spoofed, $plain ] = $this->seen;
		$this->assertArrayNotHasKey('_canonical', $spoofed);
		$this->assertArrayNotHasKey('offset', $spoofed);
		$this->assertSame($plain, $spoofed);
	}

	public function test_uppercase_text_flag_on_contact_changes_nothing(): void
	{
		$truncations = 0;
		add_filter(
			'shortcode_atts_rentiva_contact',
			static function ($out) use (&$truncations) {
				++$truncations;
				return $out;
			}
		);

		do_shortcode('[rentiva_contact _CANONICAL="yes" foo="bar"]');
		$this->assertSame(1, $truncations, 'The uppercase flag skipped shortcode_atts().');
		do_shortcode('[rentiva_contact foo="bar"]');

		$this->assertCount(2, $this->seen);
		[ $spoofed, $plain ] = $this->seen;
		$this->assertArrayNotHasKey('_canonical', $spoofed);
		$this->assertArrayNotHasKey('foo', $spoofed);
		$this->assertSame($plain, $spoofed);
	}

	public function test_block_hands_the_shortcode_a_real_boolean_flag(): void
	{
		// A "[tag _canonical=\"1\"]" serializer can only deliver the string '1', which
		// the strict check rejects; the block path has to call the array API.
		global $shortcode_tags;
		$original = $shortcode_tags['rentiva_contact'];
		$received = null;

		$shortcode_tags['rentiva_contact'] = static function ($atts, $content = null, $tag = '') use ($original, &$received) {
			$received = $atts;
			return call_user_func($original, $atts, $content, $tag);
		};
		try {
			render_block(
				array(
					'blockName'    => 'mhm-rentiva/contact',
					'attrs'        => array( 'title' => 'Plain' ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		} finally {
			$shortcode_tags['rentiva_contact'] = $original;
		}

		$this->assertIsArray($received);
		$this->assertTrue($received['_canonical'] ?? null);
	}

	public function test_block_attribute_canonical_is_ignored_and_bracket_title_survives(): void
	{
		$truncations = 0;
		$count       = static function ($out) use (&$truncations) {
			++$truncations;
			return $out;
		};
		add_filter('shortcode_atts_rentiva_contact', $count);

		$html = (string) render_block(
			array(
				'blockName'    => 'mhm-rentiva/contact',
				'attrs'        => array(
					'_canonical' => true, // A block comment's JSON can carry a real boolean.
					'foo'        => 'bar',
					'title'      => 'Bize ulaşın [7/24]',
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);

		$this->assertCount(1, $this->seen, 'The contact block did not render the shortcode.');
		$atts = $this->seen[0];
		$this->assertSame('Bize ulaşın [7/24]', $atts['title'] ?? null);
		$this->assertArrayNotHasKey('foo', $atts, 'Block attributes must go through CAM.');
		$this->assertArrayNotHasKey('_canonical', $atts);
		// The canonical branch skips shortcode_atts(); the block path must take it.
		$this->assertSame(0, $truncations, 'The block path did not take the canonical branch.');
		$this->assertStringNotContainsString('_canonical', $html);
		$this->assertStringNotContainsString('[7/24]', $html);
		$this->assertStringContainsString('id="rv-contact-form"', $html);
	}
}
