<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use Elementor\Plugin;

/**
 * Reads the style controls of the six Lite widgets that register any.
 *
 * Elementor caches a widget's control stack per type for the whole process, so
 * every read drops the stack first: a test that switched the locale must see
 * register_controls() run again under that locale. Outside the editor Elementor
 * files selector-carrying controls under `style_controls`, so both buckets are read.
 */
final class StyleControlInventory
{
	private const NS = 'MHMRentiva\\Admin\\Frontend\\Widgets\\Elementor\\';

	/**
	 * Widget class => Elementor widget name.
	 */
	public const WIDGETS = array(
		self::NS . 'VehicleCardWidget'      => 'rv-vehicle-card',
		self::NS . 'UnifiedSearchWidget'    => 'rv-vehicle-search',
		self::NS . 'VehiclesListWidget'     => 'mhmrentiva_vehicles_list',
		self::NS . 'VehiclesGridWidget'     => 'mhmrentiva_vehicles_grid',
		self::NS . 'FeaturedVehiclesWidget' => 'mhmrentiva_featured_vehicles',
		self::NS . 'BookingFormWidget'      => 'rv-booking-form',
	);

	/**
	 * Every non-section control of the widget's own stack that carries a selector,
	 * plus every member of a group control (a group's popover toggle such as
	 * `<name>_box_shadow_type` carries no selector but is still stored under the group's id).
	 *
	 * @param class-string<\Elementor\Widget_Base> $class Widget class.
	 * @return array<string,array<string,mixed>>
	 */
	public static function style_controls(string $class): array
	{
		$out = array();
		foreach (self::all_controls($class, false) as $id => $control) {
			if ('section' === ( $control['type'] ?? '' )) {
				continue;
			}
			if (! isset($control['selectors']) && ! isset($control['selector']) && ! isset($control['groupPrefix'])) {
				continue;
			}
			$out[ (string) $id ] = $control;
		}

		return $out;
	}

	/**
	 * Every group prefix registered on the widget, common (Advanced tab) groups included.
	 *
	 * @param class-string<\Elementor\Widget_Base> $class Widget class.
	 * @return list<string>
	 */
	public static function group_prefixes(string $class): array
	{
		$prefixes = array();
		foreach (self::all_controls($class, true) as $control) {
			if (isset($control['groupPrefix'])) {
				$prefixes[ rtrim((string) $control['groupPrefix'], '_') ] = true;
			}
		}

		return array_keys($prefixes);
	}

	/**
	 * @param class-string<\Elementor\Widget_Base> $class Widget class.
	 * @return array<string,array<string,mixed>>
	 */
	private static function all_controls(string $class, bool $with_common): array
	{
		$widget = WidgetFactory::make($class);
		Plugin::$instance->controls_manager->delete_stack($widget);
		$stack = $widget->get_stack($with_common);

		return (array) ( $stack['controls'] ?? array() ) + (array) ( $stack['style_controls'] ?? array() );
	}
}
