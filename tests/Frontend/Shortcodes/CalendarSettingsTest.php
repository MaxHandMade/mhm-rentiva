<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\AvailabilityCalendarWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Availability calendar settings, spec 2026-10-10 Elementor bridge slice 3A Task 6.
 *
 * APPLY: calendarHeight (the wrapper read `height`, the block sends
 * `calendarHeight`; read `calendarHeight ?? height`, exception 4 -- a calendar saved
 * before slice 1 with "height":"500px" keeps its height), show_legend,
 * show_month_nav, show_vehicle_selector, start_week_on.
 * REMOVE: integrate_pricing, show_booking_links (its editor toggle now writes the
 * live showBookingButton, Rule 2), show_discounts (ruling T6-1: no Lite/Pro code
 * supplies discount data), show_seasonal_prices, show_today_btn,
 * show_week_numbers, start_date. The Elementor `height` parity control is excluded
 * (the widget has no BlockRegistry wrapper; Elementor has its own sizing).
 *
 * G2 (Rule 1): the default renders (shortcode text, block, widget) stay today's,
 * recorded before the change (tests/fixtures/golden/calendar/,
 * MHMRENTIVA_RECORD_GOLDEN=1 re-records). The month grid depends on the day the
 * suite runs, so the golden masks dates and the day cells (calendar_golden()).
 */
final class CalendarSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/calendar';

	/** @var list<int> */
	private array $catalog = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		wp_set_current_user(0);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->catalog = ShortcodeFixtures::catalog();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_calendar_height_applies(): void
	{
		$this->assertSame('600px', $this->wrapper_height($this->block(array( 'calendarHeight' => '600px' ))));
		$this->assertSame('320px', $this->wrapper_height($this->block(array( 'calendarHeight' => '320' ))), 'A unitless number keeps the historical pixels.');
		$this->assertSame('', $this->wrapper_height($this->block(array( 'calendarHeight' => '1px;position:fixed' ))), 'An unsafe value writes nothing.');
	}

	/**
	 * Exception 4 (spec §2.2): a block saved before Gutenberg slice 1 carries
	 * "height":"500px" and no calendarHeight. WP fills calendarHeight with its
	 * block.json default (''), so the read is "calendarHeight when set, else height".
	 */
	public function test_legacy_height_attribute_still_applies(): void
	{
		$this->assertSame('500px', $this->wrapper_height($this->block(array( 'height' => '500px' ))));
		// The live key wins when both are present.
		$this->assertSame('600px', $this->wrapper_height($this->block(array( 'height' => '500px', 'calendarHeight' => '600px' ))));
	}

	public function test_empty_or_auto_height_writes_no_style(): void
	{
		$block = SettingProbe::block_json('availability-calendar', array());
		$this->assertSame('', $block['attributes']['calendarHeight']['default'] ?? null, 'block.json calendarHeight default');

		$this->assertSame('', $this->wrapper_height($this->block(array())));
		$this->assertSame('', $this->wrapper_height($this->block(array( 'calendarHeight' => '' ))));
		$this->assertSame('', $this->wrapper_height($this->block(array( 'calendarHeight' => 'auto' ))));
		$this->assertSame('', $this->wrapper_height($this->block(array( 'calendarHeight' => 'AUTO ' ))));

		// The widget renders no BlockRegistry wrapper; its parity height would be dead.
		$this->assertArrayNotHasKey('height', SettingProbe::data_controls('AvailabilityCalendarWidget'));
	}

	public function test_legend_month_nav_vehicle_selector_follow_flags(): void
	{
		$marks = array(
			'legend'   => 'rv-availability-legend',
			'nav'      => 'rv-calendar-controls',
			'selector' => 'rv-vehicle-switcher-modern',
		);

		foreach (array( 'shortcode', 'block', 'widget' ) as $surface) {
			$default = $this->render_surface($surface, array());
			foreach ($marks as $what => $class) {
				$this->assertStringContainsString($class, $default, "$surface default shows the $what.");
			}
		}

		$off = array(
			'legend'   => array( 'show_legend="0"', array( 'showLegend' => false ), array( 'show_legend' => '' ) ),
			'nav'      => array( 'show_month_nav="0"', array( 'showMonthNavigation' => false ), array( 'show_month_nav' => '' ) ),
			'selector' => array( 'show_vehicle_selector="0"', array( 'showVehicleSelector' => false ), array( 'show_vehicle_selector' => '' ) ),
		);
		foreach ($off as $what => $by_surface) {
			$renders = array(
				'shortcode' => $this->shortcode($by_surface[0]),
				'block'     => $this->block($by_surface[1]),
				'widget'    => $this->widget($by_surface[2]),
			);
			foreach ($renders as $surface => $html) {
				$this->assertStringContainsString('rv-availability-grid', $html, "$surface rendered a calendar.");
				$this->assertStringNotContainsString($marks[ $what ], $html, "$surface hides the $what when it is off.");
				foreach ($marks as $other => $class) {
					if ($other !== $what) {
						$this->assertStringContainsString($class, $html, "$surface: turning the $what off leaves the $other.");
					}
				}
			}
		}

		// The schema default follows today's render (the switcher showed whenever
		// there was more than one vehicle): shortcode text without the attribute.
		$this->assertSame('1', AllowlistRegistry::get_schema('rentiva_availability_calendar')['show_vehicle_selector']['default'] ?? null);
	}

	public function test_start_week_on_sunday_shifts_grid(): void
	{
		foreach (array( 'shortcode', 'block', 'widget' ) as $surface) {
			$default = $this->render_surface($surface, array());
			$this->assertSame('Mon', $this->weekdays($default)[0] ?? null, "$surface default starts on Monday.");
			$this->assertStringNotContainsString('data-start-week-on', $default, "$surface default markup is today's.");
		}

		$sunday = array(
			'shortcode' => $this->shortcode('start_week_on="0"'),
			'block'     => $this->block(array( 'startWeekOn' => '0' )),
			'widget'    => $this->widget(array( 'start_week_on' => '0' )),
		);
		$month  = gmdate('Y-m');
		$sunday_offset = (int) gmdate('w', (int) strtotime($month . '-01'));
		foreach ($sunday as $surface => $html) {
			$this->assertSame(array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ), $this->weekdays($html), "$surface Sunday header.");
			$this->assertStringContainsString('data-start-week-on="0"', $html, "$surface tells the month builder.");
			$this->assertSame($sunday_offset, $this->leading_blanks($html), "$surface: the 1st sits under its weekday.");
		}

		$monday_offset = ( $sunday_offset + 6 ) % 7;
		$this->assertSame($monday_offset, $this->leading_blanks($this->block(array())), 'Monday grid unchanged.');

		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/js/frontend/availability-calendar.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringContainsString("data('start-week-on')", $js, 'The JS month builder reads the start day.');
	}

	/**
	 * Rule 2: the "Show Booking Links" toggle had no markup behind it; the same
	 * promise is the live booking button, so the toggle now writes it.
	 */
	public function test_booking_links_toggle_writes_show_booking_button(): void
	{
		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/availability-calendar/index.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringNotContainsString('showBookingLinks', $js);
		$this->assertSame(1, substr_count($js, 'setAttributes({ showBookingButton: val })'), 'One editor control writes showBookingButton.');

		$this->assertStringContainsString('rv-book-now-btn', $this->block(array()));
		$this->assertStringNotContainsString('rv-book-now-btn', $this->block(array( 'showBookingButton' => false )));
	}

	/**
	 * Review Focus 4: removed controls' saved values change nothing.
	 */
	public function test_saved_value_of_removed_control_is_inert(): void
	{
		$gone_block = array( 'showBookingLinks', 'showTodayButton', 'showWeekNumbers', 'startDate', 'showSeasonalPrices', 'integratePricing', 'showDiscounts' );
		$attributes = SettingProbe::block_json('availability-calendar', array())['attributes'];
		foreach ($gone_block as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}
		$controls = SettingProbe::data_controls('AvailabilityCalendarWidget');
		foreach (array( 'show_booking_links', 'show_today_btn', 'show_week_numbers', 'start_date', 'show_seasonal_prices', 'integrate_pricing', 'show_discounts' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
		}

		$this->assertSame(
			RenderNormalizer::normalize($this->widget(array())),
			RenderNormalizer::normalize(
				$this->widget(
					array(
						'show_booking_links'   => '',
						'show_today_btn'       => '',
						'show_week_numbers'    => 'yes',
						'start_date'           => '2030-01-01',
						'show_seasonal_prices' => '',
						'integrate_pricing'    => '',
						'show_discounts'       => '',
						'height'               => '700px',
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->block(array())),
			RenderNormalizer::normalize(
				$this->block(
					array(
						'showBookingLinks'   => false,
						'showTodayButton'    => false,
						'showWeekNumbers'    => true,
						'startDate'          => '2030-01-01',
						'showSeasonalPrices' => false,
						'integratePricing'   => false,
						'showDiscounts'      => false,
					)
				)
			)
		);
	}

	/**
	 * G2: the default render of each surface is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		$html = $this->render_surface(substr($name, strlen('calendar-')), array());
		$this->assertStringContainsString('rv-availability-grid', $html, "$name rendered nothing to compare; the golden would pin an empty render.");

		$actual = $this->calendar_golden($html);
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
		$names = array( 'calendar-shortcode', 'calendar-block', 'calendar-widget' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	/**
	 * golden(), with the catalog ids named, and what depends on the day the suite
	 * runs masked: dates, the month title and the day cells (their count, weekday
	 * offset and today/past classes). The legend, controls, switcher, weekday header
	 * and booking button -- this task's settings -- stay compared.
	 */
	private function calendar_golden(string $html): string
	{
		$map = array();
		foreach ($this->catalog as $i => $id) {
			$map[ $id ]                               = 'VEHICLE' . $i;
			$map[ (int) get_post_thumbnail_id($id) ] = 'IMAGE' . $i;
		}
		$html = (string) preg_replace_callback(
			'/(?<=_id=|[?&]p=|data-vehicle-id="|&quot;id&quot;:|"id":)(\d+)(?=[",&])/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? $m[1],
			$html
		);
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/\d{4}-\d{2}(-\d{2})?/', 'DATE', $html);
		$html = (string) preg_replace('#(<span class="rv-month-name">)[^<]*(</span>)#', '$1MONTH$2', $html);
		$html = (string) preg_replace('#(<div class="rv-calendar-days">).*?(?=<div class="rv-calendar-unavailable-message")#s', '$1DAYS', $html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	private function wrapper_height(string $html): string
	{
		$this->assertSame(1, preg_match('/^<div\s+([^>]*)>/', trim($html), $m), 'No block wrapper.');
		return preg_match('/height:\s*([^;"]+)/', $m[1], $h) ? trim($h[1]) : '';
	}

	/**
	 * Weekday header labels, in order.
	 *
	 * @return list<string>
	 */
	private function weekdays(string $html): array
	{
		preg_match_all('#<div class="rv-weekday">\s*([^<]*?)\s*</div>#', $html, $m);
		return $m[1];
	}

	private function leading_blanks(string $html): int
	{
		$at = strpos($html, 'rv-calendar-days');
		$this->assertNotFalse($at, 'No day grid.');
		$rest = substr($html, (int) $at);
		preg_match('/^rv-calendar-days">((?:\s*<div class="rv-calendar-day rv-day-empty"><\/div>)*)/', $rest, $m);
		return substr_count($m[1] ?? '', 'rv-day-empty');
	}

	/**
	 * @param array<string,mixed> $settings Block attrs or widget settings; empty for the default.
	 */
	private function render_surface(string $surface, array $settings): string
	{
		switch ($surface) {
			case 'shortcode':
				return $this->shortcode('');
			case 'block':
				return $this->block($settings);
			case 'widget':
				return $this->widget($settings);
		}
		throw new \InvalidArgumentException($surface);
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_availability_calendar ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(AvailabilityCalendarWidget::class, $settings);
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
				'blockName'    => 'mhm-rentiva/availability-calendar',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
