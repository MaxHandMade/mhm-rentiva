<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
	exit;
}

class VehicleDetailsWidget extends ElementorWidgetBase
{


	public function get_name(): string
	{
		return 'rv-vehicle-details';
	}

	public function get_title(): string
	{
		return __('Vehicle Details', 'mhm-rentiva');
	}

	public function get_icon(): string
	{
		return 'eicon-info-circle';
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
			'vehicle_id',
			array(
				'label'       => __('Vehicle ID', 'mhm-rentiva'),
				'type'        => 'text',
				'description' => __('Specific vehicle ID or leave empty for current vehicle', 'mhm-rentiva'),
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void
	{
		// No style controls needed
	}

	protected function prepare_shortcode_attributes(array $settings): array
	{
		$atts = array();
		if (! empty($settings['vehicle_id'])) {
			$atts['vehicle_id'] = $settings['vehicle_id'];
		}
		return $atts;
	}

	protected function render(): void
	{
		$atts = $this->prepare_shortcode_attributes($this->get_settings_for_display());
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_shortcode('rentiva_vehicle_details', $atts);
	}
}
