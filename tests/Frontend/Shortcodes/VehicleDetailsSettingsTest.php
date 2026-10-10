<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleDetailsWidget;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Vehicle details settings, spec 2026-10-10 Elementor bridge slice 3A Task 4.
 *
 * APPLY: `show_rating` (the sidebar star line), `show_reviews` (the Ratings &
 * Reviews section; named exception 2 of spec §2.2: it was gated by
 * `show_booking_form`), `show_similar_vehicles` (the similar section) and
 * `similar_vehicles_limit` (empty keeps today's wp_is_mobile() 6 / 3 branch, a
 * set value overrides both).
 *
 * G2 (Rule 1): the default renders (shortcode text desktop and mobile, block,
 * widget) stay today's, recorded before the change
 * (tests/fixtures/golden/vehicle-details/, MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 */
final class VehicleDetailsSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/vehicle-details';

	private const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';

	/** @var list<int> */
	private array $catalog = array();

	private ?string $user_agent = null;

	/** @var list<array<string,string>> */
	private array $featured_calls = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		wp_set_current_user(0);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->catalog    = ShortcodeFixtures::catalog();
		$this->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- test state.
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		remove_filter('pre_do_shortcode_tag', array( $this, 'record_featured_call' ), 10);
		if (null === $this->user_agent) {
			unset($_SERVER['HTTP_USER_AGENT']);
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->user_agent;
		}
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * Exception 2 (spec §2.2): the reviews section follows show_reviews; switching
	 * the booking form off no longer takes the reviews with it.
	 */
	public function test_reviews_follow_show_reviews_not_booking_form(): void
	{
		$reviews = 'rv-vd2-ratings-card';
		$id      = (string) $this->catalog[0];

		$this->assertStringContainsString($reviews, $this->shortcode(''));
		$this->assertStringNotContainsString($reviews, $this->shortcode('show_reviews="0"'));
		$this->assertStringContainsString($reviews, $this->shortcode('show_booking="0"'), 'Hiding the booking section must not hide the reviews (exception 2).');

		$this->assertStringContainsString($reviews, $this->block(array( 'vehicleId' => $id )));
		$this->assertStringNotContainsString($reviews, $this->block(array( 'vehicleId' => $id, 'showReviews' => false )));
		$this->assertStringContainsString($reviews, $this->block(array( 'vehicleId' => $id, 'showBooking' => false )));

		$this->assertStringContainsString($reviews, $this->widget(array( 'vehicle_id' => $id )));
		$this->assertStringNotContainsString($reviews, $this->widget(array( 'vehicle_id' => $id, 'show_reviews' => '' )));
	}

	/**
	 * Ruling T4-1 (rule 2, spec §2.3): the block's "Show Booking Form" toggle writes the
	 * live show_booking (the booking section); show_booking_form is removed. One control
	 * per key: Elementor keeps its own show_booking switcher only.
	 */
	public function test_booking_form_toggle_drives_booking_section(): void
	{
		$cta     = 'rv-cta-container';
		$reviews = 'rv-vd2-ratings-card';
		$id      = (string) $this->catalog[0];

		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/vehicle-details/index.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
		$this->assertMatchesRegularExpression("/label: __\('Show Booking Form', 'mhm-rentiva'\),\s*checked: showBooking,\s*onChange: function \(val\) \{ setAttributes\(\{ showBooking: val \}\); \}/", $js);
		$this->assertStringNotContainsString('showBookingForm', $js);
		$this->assertSame(array(), $this->block_json_attribute('showBookingForm'));
		$this->assertTrue($this->block_json_attribute('showBooking')['default'] ?? null);

		$on  = $this->block(array( 'vehicleId' => $id ));
		$off = $this->block(array( 'vehicleId' => $id, 'showBooking' => false ));
		$this->assertStringContainsString($cta, $on);
		$this->assertStringNotContainsString($cta, $off);
		$this->assertStringContainsString($reviews, $off, 'The booking toggle leaves the reviews alone.');
		$this->assertStringContainsString('rv-vd2-booking-card', $off, 'Title and price stay; only the booking call to action goes.');

		// A saved showBookingForm value is inert (spec §2.4).
		$this->assertSame(
			RenderNormalizer::normalize($on),
			RenderNormalizer::normalize($this->block(array( 'vehicleId' => $id, 'showBookingForm' => false )))
		);

		$controls = WidgetFactory::make(VehicleDetailsWidget::class)->get_controls();
		$this->assertArrayHasKey('show_booking', $controls);
		$this->assertArrayNotHasKey('show_booking_form', $controls);
		$this->assertStringNotContainsString($cta, $this->widget(array( 'vehicle_id' => $id, 'show_booking' => '' )));
		$this->assertStringNotContainsString($cta, $this->shortcode('show_booking="0"'));
	}

	public function test_rating_line_follows_show_rating(): void
	{
		$line = 'rv-vd2-rating-line';
		$id   = (string) $this->catalog[0];

		$on = $this->shortcode('');
		$this->assertStringContainsString($line, $on);
		$this->assertStringNotContainsString($line, $this->shortcode('show_rating="0"'));
		$this->assertStringNotContainsString($line, $this->block(array( 'vehicleId' => $id, 'showRating' => false )));
		$this->assertStringNotContainsString($line, $this->widget(array( 'vehicle_id' => $id, 'show_rating' => '' )));

		// Only the line goes: the title and the price stay.
		$off = $this->shortcode('show_rating="0"');
		$this->assertStringContainsString('rv-vehicle-title', $off);
		$this->assertStringContainsString('rv-vd2-price-block', $off);
	}

	public function test_similar_section_follows_flag(): void
	{
		$section = 'rv-vd2-related';
		$id      = (string) $this->catalog[0];

		$this->assertStringContainsString($section, $this->shortcode(''));
		$this->assertStringNotContainsString($section, $this->shortcode('show_similar_vehicles="0"'));
		$this->assertStringNotContainsString($section, $this->block(array( 'vehicleId' => $id, 'showSimilarVehicles' => false )));
		$this->assertStringNotContainsString($section, $this->widget(array( 'vehicle_id' => $id, 'show_similar_vehicles' => '' )));

		// Off, the inner featured shortcode does not run at all.
		$this->record_featured();
		$this->shortcode('show_similar_vehicles="0"');
		$this->assertSame(array(), $this->featured_calls);
	}

	public function test_similar_limit_empty_keeps_mobile_branch(): void
	{
		$block = $this->block_json_attribute('similarVehiclesLimit');
		$this->assertSame('', $block['default'] ?? null, 'block.json similarVehiclesLimit default must be empty (Rule 1, spec §2.2).');

		$id = (string) $this->catalog[0];

		// Desktop: grid of 3, from every surface left untouched.
		$this->assertSame(array( 'grid', '3', '3' ), $this->featured_shape(fn() => $this->shortcode('')));
		$this->assertSame(array( 'grid', '3', '3' ), $this->featured_shape(fn() => $this->shortcode('similar_vehicles_limit=""')));
		$this->assertSame(array( 'grid', '3', '3' ), $this->featured_shape(fn() => $this->block(array( 'vehicleId' => $id ))));
		$this->assertSame(array( 'grid', '3', '3' ), $this->featured_shape(fn() => $this->widget(array( 'vehicle_id' => $id ))));

		// Mobile: slider of 6.
		$_SERVER['HTTP_USER_AGENT'] = self::MOBILE_UA;
		$this->assertTrue(wp_is_mobile());
		$this->assertSame(array( 'slider', '6', '1' ), $this->featured_shape(fn() => $this->shortcode('')));
		$this->assertSame(array( 'slider', '6', '1' ), $this->featured_shape(fn() => $this->block(array( 'vehicleId' => $id ))));
		$this->assertSame(array( 'slider', '6', '1' ), $this->featured_shape(fn() => $this->widget(array( 'vehicle_id' => $id ))));

		// Not a positive number: today's branch.
		$this->assertSame(array( 'slider', '6', '1' ), $this->featured_shape(fn() => $this->shortcode('similar_vehicles_limit="0"')));
		$this->assertSame(array( 'slider', '6', '1' ), $this->featured_shape(fn() => $this->shortcode('similar_vehicles_limit="abc"')));
	}

	public function test_similar_limit_value_overrides(): void
	{
		$id = (string) $this->catalog[0];

		// A set value replaces the limit on both branches; the layout stays.
		$this->assertSame(array( 'grid', '5', '3' ), $this->featured_shape(fn() => $this->shortcode('similar_vehicles_limit="5"')));
		$this->assertSame(array( 'grid', '2', '3' ), $this->featured_shape(fn() => $this->block(array( 'vehicleId' => $id, 'similarVehiclesLimit' => '2' ))));
		$this->assertSame(array( 'grid', '8', '3' ), $this->featured_shape(fn() => $this->widget(array( 'vehicle_id' => $id, 'similar_vehicles_limit' => '8' ))));

		$_SERVER['HTTP_USER_AGENT'] = self::MOBILE_UA;
		$this->assertSame(array( 'slider', '5', '1' ), $this->featured_shape(fn() => $this->shortcode('similar_vehicles_limit="5"')));
		$this->assertSame(array( 'slider', '2', '1' ), $this->featured_shape(fn() => $this->block(array( 'vehicleId' => $id, 'similarVehiclesLimit' => '2' ))));
	}

	/**
	 * G2: the default render of each surface is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		$html = $this->render_default_surface($name);
		$this->assertStringContainsString('rv-vd2-gallery-card', $html, "$name rendered no vehicle; the golden would pin an empty render.");

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
		$names = array( 'shortcode', 'shortcode-mobile', 'block', 'widget' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	private function render_default_surface(string $name): string
	{
		$id = (string) $this->catalog[0];
		switch ($name) {
			case 'shortcode':
				return $this->shortcode('');
			case 'shortcode-mobile':
				$_SERVER['HTTP_USER_AGENT'] = self::MOBILE_UA;
				return $this->shortcode('');
			case 'block':
				return $this->block(array( 'vehicleId' => $id ));
			case 'widget':
				return $this->widget(array( 'vehicle_id' => $id ));
		}
		throw new \InvalidArgumentException($name);
	}

	/**
	 * Normalized, with the run's post and attachment ids replaced by stable names,
	 * and the availability calendar's month (it moves with the clock) blanked.
	 */
	private function golden_form(string $html): string
	{
		$html = (string) preg_replace('#(<span id="rv-current-month-year">)[^<]*#', '$1MONTH', $html);
		$html = (string) preg_replace('#(<div id="rv-calendar-container" class="rv-cal-body">).*?</div></div></div>(\s*</div>)#s', '$1CALENDAR$2', $html);

		$map = array();
		foreach ($this->catalog as $i => $id) {
			$map[ $id ]                               = 'VEHICLE' . $i;
			$map[ (int) get_post_thumbnail_id($id) ] = 'IMAGE' . $i;
		}
		$html = (string) preg_replace_callback(
			'/(?<=_id=|[?&]p=|vehicle_id=&quot;|vehicle_id=")(\d+)\b/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? $m[1],
			$html
		);
		$html = RenderNormalizer::normalize($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	private function record_featured(): void
	{
		$this->featured_calls = array();
		add_filter('pre_do_shortcode_tag', array( $this, 'record_featured_call' ), 10, 3);
	}

	/**
	 * @param mixed        $short_circuit Filter value, passed through.
	 * @param string       $tag           Shortcode tag.
	 * @param array|string $attr          Parsed attributes.
	 * @return mixed
	 */
	public function record_featured_call($short_circuit, $tag, $attr)
	{
		if ('rentiva_featured_vehicles' === $tag) {
			$this->featured_calls[] = is_array($attr) ? $attr : array();
		}
		return $short_circuit;
	}

	/**
	 * The (layout, limit, columns) the similar section asked the featured shortcode for.
	 *
	 * @return array{0:string,1:string,2:string}
	 */
	private function featured_shape(callable $render): array
	{
		$this->record_featured();
		try {
			$render();
		} finally {
			remove_filter('pre_do_shortcode_tag', array( $this, 'record_featured_call' ), 10);
		}
		$this->assertCount(1, $this->featured_calls, 'The similar section must call the featured shortcode once.');
		$call = $this->featured_calls[0];
		return array( (string) ( $call['layout'] ?? '' ), (string) ( $call['limit'] ?? '' ), (string) ( $call['columns'] ?? '' ) );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function block_json_attribute(string $name): array
	{
		$json = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/vehicle-details/block.json'), true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin asset.
		$this->assertIsArray($json);
		return (array) ( $json['attributes'][ $name ] ?? array() );
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->catalog[0] . '" ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(VehicleDetailsWidget::class, $settings);
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
				'blockName'    => 'mhm-rentiva/vehicle-details',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
