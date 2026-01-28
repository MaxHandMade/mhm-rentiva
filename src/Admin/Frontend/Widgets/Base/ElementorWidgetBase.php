<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Extensions\Elementor\Core;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use MHMRentiva\Admin\Core\SecurityHelper;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * MHM Rentiva Elementor Base Class
 * * Provides automated attribute mapping and integrated security.
 * Refactored for Gold Standard v2.0.
 */
abstract class MHMElementorWidgetBase extends Widget_Base
{

	/**
	 * Common Prefix for MHM Widgets
	 */
	protected const PREFIX = 'mhm_rentiva_';

	/**
	 * Get Widget Categories
	 */
	public function get_categories(): array
	{
		return ['mhm-rentiva-category'];
	}

	/**
	 * Automated Attribute Preparation
	 * * Converts Elementor settings directly to Shortcode attributes.
	 * Replaces the old manual 'prepare_shortcode_attributes' method.
	 */
	protected function get_prepared_atts(): array
	{
		$settings = $this->get_settings_for_display();
		$atts = [];

		foreach ($settings as $key => $value) {
			// Convert 'yes'/'no' to '1'/'0' for shortcode compatibility
			if ($value === 'yes') {
				$atts[$key] = '1';
			} elseif ($value === 'no') {
				$atts[$key] = '0';
			} else {
				$atts[$key] = $value;
			}
		}

		// Sanitize everything before usage
		return array_map(function ($val) {
			return is_string($val) ? sanitize_text_field($val) : $val;
		}, $atts);
	}

	/**
	 * Standard Style Controls
	 * * Shared typography and color settings for all MHM widgets.
	 */
	protected function register_standard_style_controls(string $section_id, string $label, string $selector): void
	{
		$this->start_controls_section($section_id, [
			'label' => $label,
			'tab'   => Controls_Manager::TAB_STYLE,
		]);

		$this->add_control($section_id . '_color', [
			'label'     => __('Text Color', 'mhm-rentiva'),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [
				'{{WRAPPER}} ' . $selector => 'color: {{VALUE}};',
			],
		]);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => $section_id . '_typography',
				'selector' => '{{WRAPPER}} ' . $selector,
			]
		);

		$this->end_controls_section();
	}
}
