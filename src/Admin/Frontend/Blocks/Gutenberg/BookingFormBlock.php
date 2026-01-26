<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Gutenberg;

use MHMRentiva\Admin\Frontend\Blocks\Base\GutenbergBlockBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Booking Form Gutenberg Block
 *
 * Displays booking form as Gutenberg block
 *
 * @since 3.0.1
 */
class BookingFormBlock extends GutenbergBlockBase {

	/**
	 * Returns block name
	 */
	protected function get_block_name(): string {
		return 'booking-form';
	}

	/**
	 * Returns block attributes
	 */
	protected function get_block_attributes(): array {
		return array(
			// General
			'form_title'            => array(
				'type'    => 'string',
				'default' => __( 'Booking Form', 'mhm-rentiva' ),
			),
			'vehicle_id'            => array(
				'type'    => 'string',
				'default' => '',
			),

			// Form Options
			'show_vehicle_selector' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'show_vehicle_info'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'show_addons'           => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'show_payment_options'  => array(
				'type'    => 'boolean',
				'default' => true,
			),

			// Booking Settings
			'default_days'          => array(
				'type'    => 'number',
				'default' => 3,
			),
			'min_days'              => array(
				'type'    => 'number',
				'default' => 1,
			),
			'max_days'              => array(
				'type'    => 'number',
				'default' => 30,
			),

			// Payment Settings
			'enable_deposit'        => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'default_payment'       => array(
				'type'    => 'string',
				'default' => 'deposit',
				'enum'    => array( 'deposit', 'full' ),
			),

			// Advanced
			'redirect_url'          => array(
				'type'    => 'string',
				'default' => '',
			),
			'className'             => array(
				'type'    => 'string',
				'default' => '',
			),
			'align'                 => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * Renders block
	 *
	 * @param array  $attributes Block attributes
	 * @param string $content Block content
	 * @return string Rendered block
	 */
	public function render_block( array $attributes, string $content ): string {
		// Prepare shortcode attributes
		$atts = $this->prepare_shortcode_attributes( $attributes );

		// Render shortcode
		$shortcode_output = $this->render_shortcode( 'rentiva_booking_form', $atts );

		// Add block wrapper
		return $this->wrap_block_content( $shortcode_output, $attributes );
	}
}
