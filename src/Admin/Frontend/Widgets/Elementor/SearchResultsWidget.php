<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Search Results Elementor Widget
 *
 * @since 3.0.1
 */
class SearchResultsWidget extends ElementorWidgetBase
{


	public function get_name(): string
	{
		return 'rv-search-results';
	}

	public function get_title(): string
	{
		return __('Search Results', 'mhm-rentiva');
	}

	public function get_description(): string
	{
		return __('Displays search results with advanced filters', 'mhm-rentiva');
	}

	public function get_icon(): string
	{
		return 'eicon-filter';
	}

	public function get_keywords(): array
	{
		return array_merge(
			$this->widget_keywords,
			array(
				'search',
				'results',
				'filter',
			)
		);
	}

	protected function register_content_controls(): void
	{
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __('Settings', 'mhm-rentiva'),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'show_filters',
			array(
				'label'        => __('Show Filters', 'mhm-rentiva'),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void
	{
		// Style controls can be added here if needed
	}

	protected function prepare_shortcode_attributes(array $settings): array
	{
		return array(
			'show_filters' => ($settings['show_filters'] === 'yes') ? '1' : '0',
		);
	}

	protected function render(): void
	{
		$atts = $this->prepare_shortcode_attributes($this->get_settings_for_display());
		echo '<div class="elementor-widget-rv-search-results">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode('rentiva_search_results', $atts);
		echo '</div>';
	}
}
