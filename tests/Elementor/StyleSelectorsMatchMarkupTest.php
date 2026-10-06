<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use Elementor\Core\Frontend\Performance;
use Elementor\Plugin;
use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Tests\Support\SelectorMatcher;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\StyleControlInventory;
use WP_UnitTestCase;

/**
 * Spec §3 T2/T3/T3b: a style control is only real when its selector reaches the
 * markup the widget renders. Before Slice 2, 116 of 129 selectors matched nothing
 * (`.rv-vehicle-card__title`, `.rv-btn-submit` …): the user changed a colour and
 * nothing happened. Also: the card's dead badge/secondary-button controls are gone,
 * and no selector-carrying control ships a default, so an unstyled widget keeps the
 * site's Broadsheet skin instead of the old blue/12px look.
 */
final class StyleSelectorsMatchMarkupTest extends WP_UnitTestCase
{
	private const NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	/**
	 * Widgets that render a single vehicle and need its id.
	 */
	private const NEEDS_VEHICLE = array( 'rv-vehicle-card', 'rv-booking-form' );

	private const EXCEPTIONS_FILE = __DIR__ . '/style-selector-exceptions.php';

	private int $vehicle = 0;

	public function set_up(): void
	{
		parent::set_up();
		$this->vehicle = ShortcodeFixtures::vehicle();
		// FeaturedVehicles::get_vehicles() filters on the featured meta when no ids are given.
		update_post_meta($this->vehicle, MetaKeys::VEHICLE_FEATURED, '1');
	}

	/**
	 * @return array<string,mixed>
	 */
	private function base_settings(string $name): array
	{
		return in_array($name, self::NEEDS_VEHICLE, true) ? array( 'vehicle_id' => (string) $this->vehicle ) : array();
	}

	/**
	 * @param array<string,mixed> $settings
	 */
	private function element(string $name, array $settings): \Elementor\Element_Base
	{
		$element = Plugin::$instance->elements_manager->create_element_instance(
			array(
				'id'         => substr(md5($name . wp_json_encode($settings)), 0, 7),
				'elType'     => 'widget',
				'widgetType' => $name,
				'settings'   => $settings,
			)
		);
		$this->assertNotNull($element, 'Elementor could not build widget ' . $name);

		return $element;
	}

	/**
	 * @return array<string,string> "widget::control" => reason
	 */
	private function exceptions(): array
	{
		if (! is_file(self::EXCEPTIONS_FILE)) {
			return array();
		}
		$out = array();
		foreach ((array) require self::EXCEPTIONS_FILE as $row) {
			$out[ $row['widget'] . '::' . $row['control'] ] = (string) $row['reason'];
		}

		return $out;
	}

	public function test_every_style_selector_matches_rendered_markup(): void
	{
		$exceptions = $this->exceptions();
		$seen       = array();
		$failing    = array();
		$checked    = 0;

		foreach (StyleControlInventory::WIDGETS as $class => $name) {
			$controls = StyleControlInventory::style_controls($class);
			$element  = $this->element($name, $this->base_settings($name));

			ob_start();
			$element->print_element();
			$html = (string) ob_get_clean();
			$this->assertStringContainsString('elementor-element-' . $element->get_id(), $html, $name . ' rendered no element wrapper.');

			$doc      = new \DOMDocument();
			$previous = libxml_use_internal_errors(true);
			$doc->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>');
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
			$xp = new \DOMXPath($doc);

			foreach ($controls as $id => $control) {
				$key = $name . '::' . $id;
				if (isset($exceptions[ $key ])) {
					$seen[ $key ] = true;
					continue;
				}
				foreach (array_keys((array) ( $control['selectors'] ?? array() )) as $selector_list) {
					foreach (explode(',', (string) $selector_list) as $selector) {
						$selector = str_replace('{{WRAPPER}}', '.elementor-element-' . $element->get_id(), trim($selector));
						++$checked;
						if (SelectorMatcher::count($xp, $selector) < 1) {
							$failing[] = $key . ' → ' . $selector;
						}
					}
				}
			}
		}

		$stale = array_diff(array_keys($exceptions), array_keys($seen));
		$this->assertSame(array(), array_values($stale), 'Stale rows in style-selector-exceptions.php (control no longer exists).');
		$this->assertGreaterThan(50, $checked, 'Too few selectors checked; the inventory is not reading the stacks.');
		$this->assertSame(array(), $failing, "Style selectors that match nothing in the rendered markup:\n" . implode("\n", $failing));
	}

	public function test_removed_card_controls_are_gone(): void
	{
		$ids = array_keys(StyleControlInventory::style_controls(self::NS . 'VehicleCardWidget'));

		$this->assertNotContains('secondary_button_color', $ids);
		$this->assertSame(array(), array_values(array_filter($ids, static fn($id) => 0 === strpos($id, 'badge_'))));
	}

	public function test_style_controls_have_no_defaults(): void
	{
		$with_default = array();
		foreach (StyleControlInventory::WIDGETS as $class => $name) {
			$controls = StyleControlInventory::style_controls($class);
			foreach ($controls as $id => $control) {
				if (! array_key_exists('default', $control) || $this->is_empty_default($control['default'])) {
					continue;
				}
				// Elementor's own group defaults (the box shadow's 0 0 10px) sit behind the
				// group's popover toggle, whose default is empty: they emit no CSS until the
				// user switches the group on. test_default_card_generates_no_rentiva_css proves it.
				if (! $this->condition_met_by_defaults($control, $controls)) {
					continue;
				}
				$with_default[] = $name . '::' . $id . ' = ' . wp_json_encode($control['default']);
			}
		}

		$this->assertSame(array(), $with_default, "Style controls with a CSS-producing default:\n" . implode("\n", $with_default));
	}

	/**
	 * Whether the control's `condition` holds when every control sits at its default.
	 *
	 * @param array<string,mixed>               $control
	 * @param array<string,array<string,mixed>> $controls
	 */
	private function condition_met_by_defaults(array $control, array $controls): bool
	{
		foreach ((array) ( $control['condition'] ?? array() ) as $key => $expected) {
			$negate = '!' === substr((string) $key, -1);
			$name   = rtrim((string) $key, '!');
			$actual = $controls[ $name ]['default'] ?? '';
			$equal  = is_array($expected) ? in_array($actual, $expected, true) : $actual === $expected;
			if ($negate === $equal) {
				return false;
			}
		}

		return true;
	}

	/**
	 * An empty string, or a dimensions/slider shape whose only non-empty value is `unit`.
	 *
	 * @param mixed $value
	 */
	private function is_empty_default($value): bool
	{
		if (null === $value || '' === $value || array() === $value) {
			return true;
		}
		if (! is_array($value)) {
			return false;
		}
		foreach ($value as $k => $v) {
			if ('unit' === $k || 'isLinked' === $k || 'sizes' === $k && array() === $v) {
				continue;
			}
			if ('' !== $v && null !== $v) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string,mixed> $extra
	 */
	private function page_css(string $name, array $extra = array()): string
	{
		$element = $this->element($name, $this->base_settings($name) + $extra);
		$css     = new \Elementor\Core\Files\CSS\Post(999999);
		$controls = $css->get_style_controls($element, null, $element->get_settings());
		$css->add_controls_stack_style_rules(
			$element,
			$controls,
			$element->get_settings(),
			array( '{{ID}}', '{{WRAPPER}}' ),
			array( $element->get_id(), '.elementor-element-' . $element->get_id() )
		);

		return (string) $css->get_stylesheet();
	}

	public function test_default_card_generates_no_rentiva_css(): void
	{
		$previous = Performance::is_use_style_controls();
		Performance::set_use_style_controls(true);
		try {
			foreach (StyleControlInventory::WIDGETS as $name) {
				$css = $this->page_css($name);
				$this->assertStringNotContainsString('mhm-', $css, $name . ' produces Rentiva CSS without any style setting.');
				$this->assertStringNotContainsString('rv-', $css, $name . ' produces Rentiva CSS without any style setting.');
			}

			// The probe must be able to see a rule at all, or the assertions above are vacuous.
			$css = $this->page_css('rv-vehicle-card', array( 'title_color' => '#ff0000' ));
			$this->assertStringContainsString('.mhm-card-title', $css);
			$this->assertStringContainsString('#ff0000', $css);
		} finally {
			Performance::set_use_style_controls($previous);
		}
	}
}
