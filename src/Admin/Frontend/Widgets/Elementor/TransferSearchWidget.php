<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transfer Search Elementor Widget
 */
class TransferSearchWidget extends ElementorWidgetBase {


	public function get_name(): string {
		return 'mhm_rentiva_transfer_search';
	}

	public function get_title(): string {
		return __( 'MHM Transfer Search', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-search';
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content Settings', 'mhm-rentiva' ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => __( 'Horizontal', 'mhm-rentiva' ),
					'vertical'   => __( 'Vertical', 'mhm-rentiva' ),
					'compact'    => __( 'Compact', 'mhm-rentiva' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$atts = $this->get_prepared_atts();
		$data = TransferShortcodes::get_data( $atts );
		if ( ! empty( $data ) ) {
			$template_path = MHM_RENTIVA_PLUGIN_DIR . 'templates/shortcodes/transfer-search.php';
			self::include_template_with_vars( $template_path, $data );
		}
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
