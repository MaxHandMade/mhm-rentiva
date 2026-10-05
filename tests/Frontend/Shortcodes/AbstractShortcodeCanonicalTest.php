<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Frontend\Widgets\Base\WidgetAttributeBridge;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Spec §2.3: a `_canonical` payload gets the shortcode's own defaults, assets
 * are enqueued before the HTML cache can short-circuit, and the cache switch
 * is a filterable seam.
 */
final class AbstractShortcodeCanonicalTest extends WP_UnitTestCase
{
	private const CONTACT_STYLE = 'mhm-rentiva-contact-form';

	public function setUp(): void
	{
		parent::setUp();
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_new_request_clears_enqueued_query(): void
	{
		// Rendering registers the contact style (it is only registered by its own
		// enqueue_assets()) and enqueues it with its dependency.
		ContactForm::render(array( '_canonical' => true ));
		$this->assertContains(self::CONTACT_STYLE, wp_styles()->queue);
		// Priming the private query cache is what makes a hand-cleared queue lie.
		$this->assertTrue(wp_styles()->query('mhm-rentiva-css-variables', 'enqueued'));

		RequestSimulator::new_request();

		$this->assertFalse(wp_styles()->query('mhm-rentiva-css-variables', 'enqueued'));
	}

	public function test_canonical_missing_key_gets_shortcode_default(): void
	{
		SettingsCore::set('mhmrentiva_default_rental_days', 4);

		$seen    = null;
		$capture = static function ($html, $atts) use (&$seen) {
			$seen = $atts;
			return $html;
		};
		add_filter('mhmrentiva_shortcodes_rentiva_booking_form_html', $capture, 10, 2);

		Templates::render_shortcode_atts('rentiva_booking_form', array( '_canonical' => true ));

		$this->assertIsArray($seen);
		$this->assertSame(4, (int) $seen['default_days']);
	}

	public function test_canonical_flag_never_reaches_filter_or_template(): void
	{
		CanonicalProbeShortcode::$prepare_atts  = null;
		CanonicalProbeShortcode::$template_atts = null;

		$seen    = null;
		$capture = static function ($html, $atts) use (&$seen) {
			$seen = $atts;
			return $html;
		};
		add_filter('mhmrentiva_shortcodes_rentiva_canonical_probe_html', $capture, 10, 2);

		CanonicalProbeShortcode::render(array( '_canonical' => true ));

		$this->assertIsArray($seen);
		$this->assertArrayNotHasKey('_canonical', $seen);
		$this->assertSame('bar', $seen['foo'] ?? null, 'Defaults must be applied under _canonical.');
		$this->assertIsArray(CanonicalProbeShortcode::$template_atts);
		$this->assertArrayNotHasKey('_canonical', CanonicalProbeShortcode::$template_atts);
		$this->assertTrue(CanonicalProbeShortcode::$prepare_atts['_canonical'] ?? null);
	}

	public function test_assets_enqueued_on_cache_hit(): void
	{
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');

		$calls = 0;
		add_filter(
			'mhmrentiva_shortcodes_rentiva_contact_html',
			static function ($html) use (&$calls) {
				++$calls;
				return $html;
			}
		);

		ContactForm::render(array( '_canonical' => true ));
		$this->assertSame(1, $calls);

		RequestSimulator::new_request();
		$this->assertNotContains(self::CONTACT_STYLE, wp_styles()->queue);

		$html = ContactForm::render(array( '_canonical' => true ));
		$this->assertNotSame('', $html);
		// The _html filter runs after the cache branch only on a miss, so an
		// unchanged count proves the second render was served from the cache.
		$this->assertSame(1, $calls, 'Second render must be a cache hit.');
		$this->assertContains(self::CONTACT_STYLE, wp_styles()->queue);

		// all_deps() returns true even for an unregistered handle; to_do proves the walk.
		wp_styles()->all_deps(array( self::CONTACT_STYLE ));
		$this->assertContains(self::CONTACT_STYLE, wp_styles()->to_do);
		$this->assertContains('mhm-rentiva-css-variables', wp_styles()->to_do);
	}

	public function test_cache_seam_overrides_default(): void
	{
		$this->assertFalse(CanonicalProbeShortcode::caching_enabled());

		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_true');
		$this->assertTrue(CanonicalProbeShortcode::caching_enabled());
	}

	public function test_bridge_settings_exclude_style_only_controls(): void
	{
		// VehicleCardWidget re-declares its typography controls under one id (a
		// pre-existing widget defect, outside this task); Elementor reports it once
		// per process, from whichever test builds the card first. Drop that notice
		// so this test does not depend on test order.
		$this->caught_doing_it_wrong = array();

		$widget   = WidgetFactory::make(
			VehicleCardWidget::class,
			array(
				'title_color' => '#ff0000',
				'vehicle_id'  => '12',
			)
		);
		$settings = $widget->get_bridge_settings('rentiva_vehicles_list');

		$this->assertArrayNotHasKey('title_color', $settings);
		$this->assertArrayNotHasKey('price_color', $settings);
		$this->assertArrayHasKey('vehicle_id', $settings);
		$this->caught_doing_it_wrong = array();
	}

	public function test_vehicle_card_mapping(): void
	{
		$vehicle  = ShortcodeFixtures::vehicle();
		$widget   = WidgetFactory::make(
			VehicleCardWidget::class,
			array(
				'vehicle_id'       => (string) $vehicle,
				'custom_css_class' => 'my-card',
			)
		);
		$settings = $widget->get_bridge_settings('rentiva_vehicles_list');

		$method = new \ReflectionMethod($widget, 'prepare_shortcode_attributes');
		$mapped = $method->invoke($widget, $settings);

		$this->assertSame('1', (string) $mapped['limit']);
		$this->assertSame('1', (string) $mapped['columns']);
		$this->assertSame((string) $vehicle, (string) $mapped['ids']);
		$this->assertSame('my-card', $mapped['class']);

		$canonical = WidgetAttributeBridge::to_canonical('rentiva_vehicles_list', $settings, $mapped);
		$this->assertSame('1', (string) $canonical['limit']);
		$this->assertSame('1', (string) $canonical['columns']);
		$this->assertSame((string) $vehicle, (string) $canonical['ids']);
		$this->assertSame('my-card', $canonical['class']);
	}
}

/**
 * Records what the base render() hands to the template layer.
 */
final class CanonicalProbeShortcode extends AbstractShortcode
{
	public static ?array $prepare_atts  = null;
	public static ?array $template_atts = null;

	public static function caching_enabled(): bool
	{
		return static::is_caching_enabled();
	}

	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_canonical_probe';
	}

	protected static function get_default_attributes(): array
	{
		return array( 'foo' => 'bar' );
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/none';
	}

	protected static function prepare_template_data(array $atts): array
	{
		self::$prepare_atts = $atts;
		return array( 'atts' => $atts );
	}

	protected static function render_template(array $template_data): string
	{
		self::$template_atts = $template_data['atts'];
		return '<p>probe</p>';
	}
}
