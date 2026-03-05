<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Search Results Elementor Widget
 *
 * @since 3.0.1
 */
class SearchResultsWidget extends ElementorWidgetBase {



	public function get_name(): string {
		return 'rv-search-results';
	}

	public function get_title(): string {
		return __( 'Search Results', 'mhm-rentiva' );
	}

	public function get_description(): string {
		return __( 'Displays search results with advanced filters', 'mhm-rentiva' );
	}

	public function get_icon(): string {
		return 'eicon-filter';
	}

	public function get_keywords(): array {
		return array_merge(
			$this->widget_keywords,
			array(
				'search',
				'results',
				'filter',
			)
		);
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
			'limit',
			array(
				'label'   => __( 'Results Per Page', 'mhm-rentiva' ),
				'type'    => 'number',
				'min'     => 1,
				'max'     => 100,
				'default' => 12,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'grid',
				'options' => array(
					'grid'    => __( 'Grid', 'mhm-rentiva' ),
					'list'    => __( 'List', 'mhm-rentiva' ),
					'compact' => __( 'Compact', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order By', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'date',
				'options' => array(
					'date'  => __( 'Date', 'mhm-rentiva' ),
					'title' => __( 'Title', 'mhm-rentiva' ),
					'price' => __( 'Price', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'DESC',
				'options' => array(
					'ASC'  => __( 'Ascending', 'mhm-rentiva' ),
					'DESC' => __( 'Descending', 'mhm-rentiva' ),
				),
			)
		);

		$this->add_control(
			'show_filters',
			array(
				'label'        => __( 'Show Filters', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_pagination',
			array(
				'label'        => __( 'Show Pagination', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_sorting',
			array(
				'label'        => __( 'Show Sorting', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_favorite_button',
			array(
				'label'        => __( 'Show Favorite Button', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_compare_button',
			array(
				'label'        => __( 'Show Compare Button', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_booking_button',
			array(
				'label'        => __( 'Show Booking Button', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => __( 'Show Price', 'mhm-rentiva' ),
				'type'         => 'switcher',
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function register_style_controls(): void {
		// Style controls can be added here if needed
	}

	protected function prepare_shortcode_attributes( array $settings ): array {
		$limit = (string) max( 1, (int) ( $settings['limit'] ?? 12 ) );
		$order = strtoupper( sanitize_text_field( (string) ( $settings['order'] ?? 'DESC' ) ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		return array(
			'limit'                => $limit,
			'results_per_page'     => $limit,
			'layout'               => sanitize_text_field( (string) ( $settings['layout'] ?? 'grid' ) ),
			'orderby'              => sanitize_text_field( (string) ( $settings['orderby'] ?? 'date' ) ),
			'order'                => $order,
			'show_filters'         => $this->convert_switcher_to_boolean( $settings['show_filters'] ?? 'yes' ),
			'show_pagination'      => $this->convert_switcher_to_boolean( $settings['show_pagination'] ?? 'yes' ),
			'show_sorting'         => $this->convert_switcher_to_boolean( $settings['show_sorting'] ?? 'yes' ),
			'show_favorite_button' => $this->convert_switcher_to_boolean( $settings['show_favorite_button'] ?? 'yes' ),
			'show_compare_button'  => $this->convert_switcher_to_boolean( $settings['show_compare_button'] ?? 'yes' ),
			'show_booking_button'  => $this->convert_switcher_to_boolean( $settings['show_booking_button'] ?? 'yes' ),
			'show_price'           => $this->convert_switcher_to_boolean( $settings['show_price'] ?? 'yes' ),
		);
	}

	protected function render(): void {
		$atts = $this->prepare_shortcode_attributes( $this->get_settings_for_display() );
		echo '<div class="elementor-widget-rv-search-results">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output contains HTML.
		echo $this->render_shortcode( 'rentiva_search_results', $atts );
		echo '</div>';
	}
}
