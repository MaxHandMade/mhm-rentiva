<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserDashboardWidget extends ElementorWidgetBase {

	public function get_name(): string {
		return 'rv-user-dashboard';
	}

	public function get_title(): string {
		return __( 'User Dashboard', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-person';
	}

	protected function register_content_controls(): void {
		$this->start_controls_section(
			'general_section',
			array(
				'label' => __( 'Settings', 'mhm-rentiva' ),
				'tab'   => 'content',
			)
		);

		$this->add_control(
			'info',
			array(
				'type'            => 'raw_html',
				'raw'             => __( 'Displays the vendor or customer dashboard panel. Output is determined automatically by the logged-in user\'s role.', 'mhm-rentiva' ),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void {
		// No style controls needed
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_user_dashboard', $atts );
	}
}
