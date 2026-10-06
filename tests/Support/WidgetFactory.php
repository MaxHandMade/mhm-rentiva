<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Builds real Elementor widget instances for tests.
 *
 * Elementor 4.3.2 throws when `$data` is non-empty and the second `$args`
 * argument is null (includes/base/widget-base.php:132-138), so every test
 * constructs widgets through here instead of `new $class( $data )`.
 */
final class WidgetFactory
{
	/**
	 * @param class-string<\Elementor\Widget_Base> $class    Widget class.
	 * @param array<string,mixed>                  $settings Saved widget settings.
	 */
	public static function make(string $class, array $settings = array()): \Elementor\Widget_Base
	{
		return new $class(
			array(
				'id'       => 't' . wp_generate_password(7, false),
				'elType'   => 'widget',
				'settings' => $settings,
			),
			array()
		);
	}
}
