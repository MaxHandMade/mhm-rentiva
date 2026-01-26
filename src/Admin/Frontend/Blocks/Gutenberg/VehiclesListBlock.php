<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Gutenberg;

use MHMRentiva\Admin\Frontend\Blocks\Base\GutenbergBlockBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vehicles List Gutenberg Block
 *
 * Shows vehicle list as Gutenberg block
 *
 * @since 3.0.1
 */
class VehiclesListBlock extends GutenbergBlockBase {

	/**
	 * Returns block name
	 */
	protected function get_block_name(): string {
		return 'vehicles-list';
	}

	/**
	 * Returns block attributes
	 */
	protected function get_block_attributes(): array {
		return array_merge(
			$this->get_query_attributes(),
			$this->get_layout_attributes(),
			$this->get_display_options_attributes(),
			$this->get_interaction_attributes(),
			array(
				'className' => array(
					'type'    => 'string',
					'default' => '',
				),
				'align'     => array(
					'type'    => 'string',
					'default' => '',
				),
			)
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

		// Add ids if present
		if ( ! empty( $attributes['ids'] ) ) {
			$atts['ids'] = $attributes['ids'];
		}

		// Render shortcode
		$shortcode_output = $this->render_shortcode( 'rentiva_vehicles_list', $atts );

		// Add block wrapper
		return $this->wrap_block_content( $shortcode_output, $attributes );
	}

	/**
	 * Returns query attributes
	 */
	protected function get_query_attributes(): array {
		return array(
			'limit'    => array(
				'type'    => 'number',
				'default' => 9,
			),
			'order'    => array(
				'type'    => 'string',
				'default' => 'DESC',
				'enum'    => array( 'ASC', 'DESC' ),
			),
			'orderby'  => array(
				'type'    => 'string',
				'default' => 'date',
				'enum'    => array( 'date', 'title', 'price', 'rating', 'rand' ),
			),
			'exclude'  => array(
				'type'    => 'string',
				'default' => '',
			),
			'ids'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'category' => array(
				'type'    => 'string',
				'default' => '',
			),
			'featured' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	/**
	 * Returns layout attributes
	 */
	protected function get_layout_attributes(): array {
		return array(
			'layout'  => array(
				'type'    => 'string',
				'default' => 'grid',
				'enum'    => array( 'grid', 'list' ),
			),
			'columns' => array(
				'type'    => 'string',
				'default' => '3',
				'enum'    => array( '1', '2', '3', '4' ),
			),
			'gap'     => array(
				'type'    => 'string',
				'default' => '1.5rem',
			),
		);
	}

	/**
	 * Returns display options attributes
	 */
	protected function get_display_options_attributes( array $options = array() ): array {
		return parent::get_display_options_attributes( $options );
	}

	/**
	 * Returns interaction attributes
	 */
	protected function get_interaction_attributes(): array {
		return array(
			'show_booking_btn'     => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'show_favorite_btn'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'enableAjaxFiltering'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'enableInfiniteScroll' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'enableLazyLoad'       => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
	}

	/**
	 * Prepare Shortcode attributes
	 */
	protected function prepare_shortcode_attributes( array $attributes ): array {
		$atts = parent::prepare_shortcode_attributes( $attributes );

		if ( ! empty( $attributes['category'] ) ) {
			$atts['category'] = $attributes['category'];
		}
		if ( isset( $attributes['featured'] ) ) {
			$atts['featured'] = $attributes['featured'] ? '1' : '0';
		}

		if ( isset( $attributes['enableAjaxFiltering'] ) ) {
			$atts['enable_ajax_filtering'] = $attributes['enableAjaxFiltering'] ? '1' : '0';
		}
		if ( isset( $attributes['enableInfiniteScroll'] ) ) {
			$atts['enable_infinite_scroll'] = $attributes['enableInfiniteScroll'] ? '1' : '0';
		}
		if ( isset( $attributes['enableLazyLoad'] ) ) {
			$atts['enable_lazy_load'] = $attributes['enableLazyLoad'] ? '1' : '0';
		}

		return $atts;
	}
}
