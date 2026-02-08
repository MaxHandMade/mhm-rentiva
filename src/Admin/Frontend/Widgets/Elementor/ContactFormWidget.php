<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (! defined('ABSPATH')) {
	exit;
}

class ContactFormWidget extends ElementorWidgetBase
{


	public function get_name(): string
	{
		return 'rv-contact-form';
	}

	public function get_title(): string
	{
		return __('Contact Form', 'mhm-rentiva');
	}

	public function get_icon(): string
	{
		return 'eicon-mail';
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
			'info',
			array(
				'type'            => 'raw_html',
				'raw'             => __('Displays contact form.', 'mhm-rentiva'),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void
	{
		// No style controls needed
	}

	protected function render(): void
	{
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_shortcode('[rentiva_contact]');
	}
}
