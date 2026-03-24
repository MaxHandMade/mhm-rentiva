<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transfer Results Elementor Widget
 */
class TransferResultsWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'mhm_rentiva_transfer_results';
	}

	public function get_title(): string {
		return __( 'MHM Transfer Results', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-post-list';
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content Settings', 'mhm-rentiva' ),
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'     => __( 'Show Price', 'mhm-rentiva' ),
				'type'      => 'switcher',
				'default'   => 'yes',
				'label_on'  => __( 'Show', 'mhm-rentiva' ),
				'label_off' => __( 'Hide', 'mhm-rentiva' ),
			)
		);

		$this->end_controls_section();

		$this->register_parity_controls_from_block();
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_transfer_results', $atts );
	}

	private static function include_template_with_vars( string $template_path, array $template_data ): void {

		( static function () use ( $template_path, $template_data ): void {

			foreach ( $template_data as $key => $value ) {
				if ( ! is_string( $key ) || ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
					continue;
				}
				${$key} = $value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
			}
			include $template_path;
		} )();
	}
}
