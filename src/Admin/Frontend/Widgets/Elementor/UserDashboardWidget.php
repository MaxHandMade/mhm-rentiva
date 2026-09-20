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
		return __( 'User Dashboard (deprecated)', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-person';
	}

	/**
	 * Hidden from the widget panel and from its search: this surface is retired,
	 * so no one should place a new one. Elementor keeps rendering the widgets
	 * already placed on a page -- registration is unaffected by either method
	 * (Elementor 4.1.4, includes/base/widget-base.php:240,253).
	 */
	public function show_in_panel(): bool {
		return false;
	}

	public function hide_on_search(): bool {
		return true;
	}

	/**
	 * The stub carries no styles, so it depends on none. The kit handle this
	 * used to name is no longer registered by UserDashboard, and Elementor drops
	 * an unregistered handle silently.
	 *
	 * @return string[]
	 */
	public function get_style_depends(): array {
		return array();
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
				'raw'             => __( 'Deprecated: this widget renders a notice pointing customers to the WooCommerce account page. Remove it from this page; it will stop working in 7.0.', 'mhm-rentiva' ),
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
		$this->output_shortcode( 'rentiva_user_dashboard', $atts );
	}
}
