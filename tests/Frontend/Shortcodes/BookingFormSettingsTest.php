<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\BookingFormWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Booking form settings, spec 2026-10-10 Elementor bridge slice 3A Task 6.
 *
 * APPLY: default_days (the return date is prefilled N days after the chosen
 * pickup; no prefill unless set), form_title (a heading only when non-empty; the
 * Elementor default 'Booking Form' becomes '' so untouched widgets gain nothing).
 * REMOVE: default_payment, max_days, min_days, show_date_picker, show_insurance,
 * show_payment_options.
 *
 * G2 (Rule 1): the default renders (shortcode text, block, widget) stay today's,
 * recorded before the change (tests/fixtures/golden/booking-form/,
 * MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 */
final class BookingFormSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/booking-form';

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

	public function test_default_days_prefills_return_date(): void
	{
		$this->assertSame('3', $this->default_days($this->shortcode('default_days="3"')));
		$this->assertSame('5', $this->default_days($this->block(array( 'defaultDays' => '5' ))));
		$this->assertSame('7', $this->default_days($this->widget(array( 'default_days' => 7 ))));

		// Untouched: no prefill, as today (the global "default rental days" setting
		// is the search form's; the booking form never prefilled from it).
		$this->assertNull($this->default_days($this->shortcode('')));
		$this->assertNull($this->default_days($this->block(array())));
		$this->assertNull($this->default_days($this->widget(array())));
		$this->assertNull($this->default_days($this->shortcode('default_days="0"')));
		$this->assertNull($this->default_days($this->shortcode('default_days="-4"')));

		$controls = SettingProbe::data_controls('BookingFormWidget');
		$this->assertArrayHasKey('default_days', $controls);
		$this->assertSame('', $controls['default_days']['default'] ?? null, 'Elementor default_days default is empty (Rule 1).');

		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/js/frontend/booking-form.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringContainsString("data('default-days')", $js, 'booking-form.js reads the prefill.');
	}

	public function test_form_title_renders_only_when_set(): void
	{
		$title = 'Zz Rent It Today';
		$this->assertSame($title, $this->title($this->shortcode('form_title="' . $title . '"')));
		$this->assertSame($title, $this->title($this->block(array( 'form_title' => $title ))));
		$this->assertSame($title, $this->title($this->widget(array( 'form_title' => $title ))));

		$this->assertNull($this->title($this->shortcode('')));
		$this->assertNull($this->title($this->block(array())));
		$this->assertNull($this->title($this->widget(array())));

		$controls = SettingProbe::data_controls('BookingFormWidget');
		$this->assertSame('', $controls['form_title']['default'] ?? null, 'Elementor form_title default is empty (Rule 1).');

		$html = $this->shortcode('form_title="&lt;b&gt;x&lt;/b&gt;"');
		$this->assertStringNotContainsString('<b>', $html, 'The title is escaped.');
	}

	/**
	 * Review Focus 4: a saved widget with a removed control's value (for example
	 * show_insurance=yes) renders like today, and the block's removed attributes
	 * the same.
	 */
	public function test_saved_value_of_removed_control_is_inert(): void
	{
		$controls = SettingProbe::data_controls('BookingFormWidget');
		foreach (array( 'show_insurance', 'show_date_picker', 'show_payment_options', 'min_days', 'max_days', 'default_payment' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
		}
		$attributes = SettingProbe::block_json('booking-form', array())['attributes'];
		foreach (array( 'show_insurance', 'show_date_picker', 'show_payment_options', 'minDays', 'maxDays', 'defaultPayment' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}
		$schema = AllowlistRegistry::get_schema('rentiva_booking_form');
		foreach (array( 'show_insurance', 'show_date_picker', 'show_payment_options', 'min_days', 'max_days', 'default_payment' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $schema, "schema $gone");
		}

		$this->assertSame(
			RenderNormalizer::normalize($this->widget(array())),
			RenderNormalizer::normalize(
				$this->widget(
					array(
						'show_insurance'       => 'yes',
						'show_date_picker'     => '',
						'show_payment_options' => '',
						'min_days'             => 5,
						'max_days'             => 6,
						'default_payment'      => 'full',
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->block(array())),
			RenderNormalizer::normalize(
				$this->block(
					array(
						'show_insurance'       => false,
						'show_date_picker'     => false,
						'show_payment_options' => false,
						'minDays'              => '5',
						'maxDays'              => '6',
						'defaultPayment'       => 'full',
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->shortcode('')),
			RenderNormalizer::normalize($this->shortcode('show_insurance="1" min_days="5" max_days="6" default_payment="full" show_payment_options="0" show_date_picker="0"'))
		);
	}

	/**
	 * G2: the default render of each surface is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		switch ($name) {
			case 'booking-form-shortcode':
				$html = $this->shortcode('');
				break;
			case 'booking-form-block':
				$html = $this->block(array());
				break;
			default:
				$html = $this->widget(array());
		}
		$this->assertStringContainsString('rv-checkout-summary', $html, "$name rendered nothing to compare; the golden would pin an empty render.");

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
		$names = array( 'booking-form-shortcode', 'booking-form-block', 'booking-form-widget' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	/**
	 * Normalized, with the run's post and attachment ids replaced by stable names.
	 */
	private function golden_form(string $html): string
	{
		$map = array();
		foreach ($this->catalog as $i => $id) {
			$map[ $id ]                               = 'VEHICLE' . $i;
			$map[ (int) get_post_thumbnail_id($id) ] = 'IMAGE' . $i;
		}
		$html = (string) preg_replace_callback(
			'/(?<=_id=|[?&]p=|data-vehicle-id="|<option value="|name="selected_addons\[\]" value=")(\d+)(?=["&])/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? 'ID',
			$html
		);
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	private function default_days(string $html): ?string
	{
		$this->assertStringContainsString('rv-booking-form-wrapper', $html, 'No booking form.');
		return preg_match('/<div class="rv-booking-form-wrapper[^>]*\sdata-default-days="([^"]*)"/', $html, $m) ? $m[1] : null;
	}

	private function title(string $html): ?string
	{
		$this->assertStringContainsString('rv-booking-form-wrapper', $html, 'No booking form.');
		return preg_match('#<h2 class="rv-booking-form-title">([^<]*)</h2>#', $html, $m) ? html_entity_decode($m[1]) : null;
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_booking_form ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(BookingFormWidget::class, $settings);
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
				'blockName'    => 'mhm-rentiva/booking-form',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
