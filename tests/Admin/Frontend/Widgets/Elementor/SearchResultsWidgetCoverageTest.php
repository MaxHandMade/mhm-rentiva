<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Frontend\Widgets\Elementor;

use WP_UnitTestCase;

final class SearchResultsWidgetCoverageTest extends WP_UnitTestCase
{
	public function test_widget_defines_at_least_ten_core_controls_in_source(): void
	{
		$widgetSource = $this->read_source_file('src/Admin/Frontend/Widgets/Elementor/SearchResultsWidget.php');

		preg_match_all('/\\$this->add_control\\(\\s*\'([^\']+)\'/m', $widgetSource, $matches);
		$controls = array_unique($matches[1] ?? array());

		$this->assertGreaterThanOrEqual(10, count($controls));

		$expected = array(
			'limit',
			'orderby',
			'order',
			'show_pagination',
			'show_sorting',
			'show_favorite_button',
			'show_compare_button',
			'show_booking_button',
			'layout',
			'show_price',
		);

		foreach ($expected as $controlKey) {
			$this->assertContains($controlKey, $controls, sprintf('Expected control "%s" is missing.', $controlKey));
		}
	}

	public function test_widget_uses_canonical_switcher_conversion_for_shortcode_flags(): void
	{
		$widgetSource = $this->read_source_file('src/Admin/Frontend/Widgets/Elementor/SearchResultsWidget.php');

		$this->assertStringContainsString("'results_per_page'     => \$limit", $widgetSource);
		$this->assertStringContainsString("'show_pagination'      => \$this->convert_switcher_to_boolean", $widgetSource);
		$this->assertStringContainsString("'show_sorting'         => \$this->convert_switcher_to_boolean", $widgetSource);
		$this->assertStringContainsString("'show_favorite_button' => \$this->convert_switcher_to_boolean", $widgetSource);
		$this->assertStringContainsString("'show_compare_button'  => \$this->convert_switcher_to_boolean", $widgetSource);
		$this->assertStringContainsString("'show_booking_button'  => \$this->convert_switcher_to_boolean", $widgetSource);
		$this->assertStringContainsString("'show_price'           => \$this->convert_switcher_to_boolean", $widgetSource);
	}

	public function test_base_widget_declares_shared_switcher_to_boolean_helper(): void
	{
		$baseSource = $this->read_source_file('src/Admin/Frontend/Widgets/Base/ElementorWidgetBase.php');

		$this->assertStringContainsString('function convert_switcher_to_boolean', $baseSource);
		$this->assertStringContainsString("'yes'", $baseSource);
		$this->assertStringContainsString("'on'", $baseSource);
		$this->assertStringContainsString("return '1';", $baseSource);
		$this->assertStringContainsString("return '0';", $baseSource);
	}

	private function read_source_file(string $relativePath): string
	{
		$path = trailingslashit(MHM_RENTIVA_PLUGIN_PATH) . $relativePath;
		$this->assertFileExists($path, sprintf('Expected source file not found: %s', $relativePath));

		$source = file_get_contents($path);
		$this->assertNotFalse($source, sprintf('Could not read source file: %s', $relativePath));

		return (string) $source;
	}
}
