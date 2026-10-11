<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\TestimonialsWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Testimonials settings, spec 2026-10-10 Elementor bridge slice 3A Task 8.
 *
 * APPLY: orderby (a rating sort next to date and random; a review without a
 * rating goes last in both orders), show_author_avatar (its own gate on the
 * initial-letter avatar), show_quotes (the decorative quote icon of each card).
 * REMOVE (twins, rule 2 -- the editor control stays and writes the live key):
 * autoplay -> auto_rotate, limit_items -> limit, show_author_name -> show_customer,
 * show_vehicle_name -> show_vehicle. REMOVE: category (testimonials have no
 * category).
 *
 * G2 (Rule 1): the default renders (shortcode text, block, widget) stay today's,
 * recorded before the change (tests/fixtures/golden/testimonials/,
 * MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 */
final class TestimonialsSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/testimonials';

	/** Review texts newest first (the default date order). */
	private const BY_DATE = array( 'Review unrated', 'Review three', 'Review five', 'Review four' );

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		// Four approved reviews on three vehicles; the dates are out of step with
		// the ratings, and the newest one has no rating at all.
		$reviews = array(
			array( 'Review unrated', 'Deniz Kaya', '2026-04-01 10:00:00', 0, 'Alpha' ),
			array( 'Review three', 'Ayse Yilmaz', '2026-03-01 10:00:00', 3, 'Bravo' ),
			array( 'Review five', 'Mehmet Demir', '2026-02-01 10:00:00', 5, 'Charlie' ),
			array( 'Review four', 'Elif Sahin', '2026-01-01 10:00:00', 4, 'Alpha' ),
		);
		$vehicles = array();
		foreach ($reviews as $review) {
			if (! isset($vehicles[ $review[4] ])) {
				$vehicles[ $review[4] ] = (int) wp_insert_post(
					array(
						'post_type'   => 'mhmrentiva_vehicle',
						'post_status' => 'publish',
						'post_title'  => 'Testimonial Vehicle ' . $review[4],
					)
				);
			}
			$comment = (int) wp_insert_comment(
				array(
					'comment_post_ID'  => $vehicles[ $review[4] ],
					'comment_author'   => $review[1],
					'comment_content'  => $review[0],
					'comment_approved' => 1,
					'comment_date'     => $review[2],
				)
			);
			if ($review[3] > 0) {
				update_comment_meta($comment, 'mhmrentiva_rating', $review[3]);
			}
		}

		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * G2: the default render of each surface is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		$surface = substr($name, strlen('testimonials-'));
		$html    = 'shortcode' === $surface ? $this->shortcode('') : ( 'block' === $surface ? $this->block(array()) : $this->widget(array()) );
		$this->assertSame(self::BY_DATE, $this->reviews($html), "$name rendered the four reviews; the golden would pin an empty render.");

		$actual = $this->golden_form($html);
		$file   = self::GOLDEN_DIR . '/' . $name . '.html';

		if ('1' === getenv('MHMRENTIVA_RECORD_GOLDEN')) {
			if (! is_dir(self::GOLDEN_DIR)) {
				mkdir(self::GOLDEN_DIR, 0777, true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.
			}
			file_put_contents($file, $actual); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
			$this->markTestIncomplete("Recorded golden $name.");
		}

		$this->assertFileExists($file, "No golden for $name; record it on the code before the change.");
		$this->assertSame(file_get_contents($file), $actual, "The default render of $name changed (Rule 1, spec §2.2)."); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture.
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function default_surfaces(): array
	{
		$names = array( 'testimonials-shortcode', 'testimonials-block', 'testimonials-widget' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	/**
	 * orderby 'rating': highest first for DESC, lowest first for ASC, and the
	 * review without a rating last in both orders. The editors offer only values
	 * the schema keeps (G3); the Elementor control's old title/modified options
	 * rendered the date order and still do.
	 */
	public function test_orderby_rating(): void
	{
		$desc = array( 'Review five', 'Review four', 'Review three', 'Review unrated' );
		$asc  = array( 'Review three', 'Review four', 'Review five', 'Review unrated' );

		$this->assertSame($desc, $this->reviews($this->shortcode('orderby="rating"')), 'shortcode DESC');
		$this->assertSame($asc, $this->reviews($this->shortcode('orderby="rating" order="ASC"')), 'shortcode ASC');
		$this->assertSame($desc, $this->reviews($this->block(array( 'sortBy' => 'rating' ))), 'block DESC');
		$this->assertSame(
			$asc,
			$this->reviews(
				$this->block(
					array(
						'sortBy'    => 'rating',
						'sortOrder' => 'asc',
					)
				)
			),
			'block ASC'
		);
		$this->assertSame($desc, $this->reviews($this->widget(array( 'orderby' => 'rating' ))), 'widget DESC');
		$this->assertSame(
			$asc,
			$this->reviews(
				$this->widget(
					array(
						'orderby' => 'rating',
						'order'   => 'ASC',
					)
				)
			),
			'widget ASC'
		);

		// The limit cuts the sorted list.
		$this->assertSame(array( 'Review five', 'Review four' ), $this->reviews($this->shortcode('orderby="rating" limit="2"')));

		// Date order in both directions is unchanged.
		$this->assertSame(array_reverse(self::BY_DATE), $this->reviews($this->shortcode('order="ASC"')));

		// Saved legacy Elementor values render the date order, as before.
		foreach (array( 'title', 'modified' ) as $legacy) {
			$this->assertSame(self::BY_DATE, $this->reviews($this->widget(array( 'orderby' => $legacy ))), "widget $legacy");
			$this->assertSame(self::BY_DATE, $this->reviews($this->shortcode('orderby="' . $legacy . '"')), "shortcode $legacy");
		}

		$schema = AllowlistRegistry::get_schema('rentiva_testimonials');
		$this->assertSame(array( 'date', 'rating', 'rand' ), $schema['orderby']['values']);
		$controls = SettingProbe::data_controls('TestimonialsWidget');
		$this->assertSame(array( 'date', 'rating', 'rand' ), array_keys((array) $controls['orderby']['options']));
		$this->assertSame(array( 'date', 'rating' ), SettingProbe::editor_options('testimonials')['sortBy']);
	}

	/**
	 * show_author_avatar gates the initial-letter avatar alone (the name stays);
	 * show_quotes gates the quote icon of each card. Both default on.
	 */
	public function test_author_avatar_and_quotes_follow_flags(): void
	{
		$surfaces = array(
			'shortcode' => array(
				fn(): string => $this->shortcode(''),
				fn(): string => $this->shortcode('show_author_avatar="0" show_quotes="0"'),
				fn(): string => $this->shortcode('show_author_avatar="0"'),
			),
			'block'     => array(
				fn(): string => $this->block(array()),
				fn(): string => $this->block(
					array(
						'showAuthorAvatar' => false,
						'showQuotes'       => false,
					)
				),
				fn(): string => $this->block(array( 'showAuthorAvatar' => false )),
			),
			'widget'    => array(
				fn(): string => $this->widget(array()),
				fn(): string => $this->widget(
					array(
						'show_author_avatar' => '',
						'show_quotes'        => '',
					)
				),
				fn(): string => $this->widget(array( 'show_author_avatar' => '' )),
			),
			'carousel'  => array(
				fn(): string => $this->shortcode('layout="carousel"'),
				fn(): string => $this->shortcode('layout="carousel" show_author_avatar="0" show_quotes="0"'),
				fn(): string => $this->shortcode('layout="carousel" show_author_avatar="0"'),
			),
		);

		foreach ($surfaces as $name => $renders) {
			$on = $renders[0]();
			$this->assertSame(4, substr_count($on, 'class="rv-author-avatar"'), "$name: avatar on by default");
			$this->assertSame(4, substr_count($on, 'class="rv-quote-icon"'), "$name: quotes on by default");

			$off = $renders[1]();
			$this->assertSame(0, substr_count($off, 'class="rv-author-avatar"'), "$name: avatar off");
			$this->assertSame(0, substr_count($off, 'class="rv-quote-icon"'), "$name: quotes off");
			$this->assertSame(4, substr_count($off, 'class="rv-author-name"'), "$name: the names stay");
			$this->assertSame(self::BY_DATE, $this->reviews($off), "$name: the review texts stay");

			$avatar_only = $renders[2]();
			$this->assertSame(0, substr_count($avatar_only, 'class="rv-author-avatar"'), "$name: avatar off alone");
			$this->assertSame(4, substr_count($avatar_only, 'class="rv-quote-icon"'), "$name: quotes stay on");
		}

		// show_customer still hides the whole author block, avatar included.
		$no_customer = $this->shortcode('show_customer="0"');
		$this->assertSame(0, substr_count($no_customer, 'class="rv-author-avatar"'));
		$this->assertSame(0, substr_count($no_customer, 'class="rv-author-name"'));
	}

	/**
	 * Rule 2: the block's Autoplay, Limit Items, Show Author Name and Show Vehicle
	 * Name controls wrote keys nothing read; each now writes its live twin, and one
	 * control writes each key (editor write scanner). Review Focus 4: saved values
	 * of the removed settings change nothing.
	 */
	public function test_autoplay_control_writes_auto_rotate(): void
	{
		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/testimonials/index.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$twins = array(
			'Autoplay'          => array( 'autoplay', 'autoRotate' ),
			'Limit Items'       => array( 'limitItems', 'limit' ),
			'Show Author Name'  => array( 'showAuthorName', 'showCustomer' ),
			'Show Vehicle Name' => array( 'showVehicleName', 'showVehicle' ),
		);
		foreach ($twins as $label => $keys) {
			$this->assertDoesNotMatchRegularExpression('/\b' . $keys[0] . '\b/', $js, "index.js still names {$keys[0]}");
			$this->assertSame(1, substr_count($js, 'setAttributes({ ' . $keys[1] . ': val })'), "One editor control writes {$keys[1]}.");
			$this->assertStringContainsString("__('$label', 'mhm-rentiva')", $js, "The $label control keeps its label.");
		}
		// The block's Autoplay toggle reaches the carousel script.
		$this->assertStringContainsString(
			'data-auto-rotate="1"',
			$this->block(
				array(
					'layout'     => 'carousel',
					'autoRotate' => true,
				)
			)
		);
		$this->assertSame(array( 'Review unrated', 'Review three' ), $this->reviews($this->block(array( 'limit' => '2' ))));

		$removed_attrs    = array( 'autoplay', 'category', 'limitItems', 'showAuthorName', 'showVehicleName' );
		$removed_controls = array( 'autoplay', 'category', 'limit_items', 'show_author_name', 'show_vehicle_name' );

		$attributes = SettingProbe::block_json('testimonials', array())['attributes'];
		foreach ($removed_attrs as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}
		$controls = SettingProbe::data_controls('TestimonialsWidget');
		$schema   = AllowlistRegistry::get_schema('rentiva_testimonials');
		foreach ($removed_controls as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
			$this->assertArrayNotHasKey($gone, $schema, "schema $gone");
		}

		$this->assertSame(
			RenderNormalizer::normalize($this->block(array())),
			RenderNormalizer::normalize(
				$this->block(
					array(
						'autoplay'        => true,
						'category'        => 'suv',
						'limitItems'      => '1',
						'showAuthorName'  => false,
						'showVehicleName' => false,
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->widget(array())),
			RenderNormalizer::normalize(
				$this->widget(
					array(
						'autoplay'          => 'yes',
						'category'          => 'suv',
						'limit_items'       => 1,
						'show_author_name'  => '',
						'show_vehicle_name' => '',
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->shortcode('')),
			RenderNormalizer::normalize($this->shortcode('autoplay="1" category="suv" limit_items="1" show_author_name="0" show_vehicle_name="0"'))
		);
	}

	/**
	 * Review texts in render order.
	 *
	 * @return list<string>
	 */
	private function reviews(string $html): array
	{
		preg_match_all('#<p>&ldquo;(.*?)&rdquo;</p>#', $html, $m);
		return $m[1];
	}

	private function golden_form(string $html): string
	{
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_testimonials ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(TestimonialsWidget::class, $settings);
		ob_start();
		try {
			$widget->render_content();
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	private function block(array $attrs): string
	{
		RequestSimulator::new_request();
		return render_block(
			array(
				'blockName'    => 'mhm-rentiva/testimonials',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
