<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\SearchResults;
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

		// The first request's order. Its options are the values the shortcode's sort
		// switch applies; the former Order By / Order selects never reached it
		// (spec 3A §2.5 E).
		$this->add_control(
			'default_sort',
			array(
				'label'   => __( 'Default Sort Order', 'mhm-rentiva' ),
				'type'    => 'select',
				'default' => 'price_asc',
				'options' => SearchResults::get_sorting_options(),
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
		$atts = array();

		// The Elementor limit control feeds the shortcode's page size; the shortcode has no separate 'limit'.
		if ( array_key_exists( 'limit', $settings ) ) {
			$atts['results_per_page'] = (string) max( 1, (int) $settings['limit'] );
		}

		// Map only keys Elementor delivered; an absent key must fall through to the shortcode default.
		foreach ( array( 'layout', 'default_sort' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = sanitize_text_field( (string) $settings[ $key ] );
			}
		}

		foreach ( array( 'show_filters', 'show_pagination', 'show_sorting', 'show_favorite_button', 'show_compare_button', 'show_booking_button', 'show_price' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$atts[ $key ] = $this->convert_switcher_to_boolean( $settings[ $key ] );
			}
		}

		return $atts;
	}

	protected function render(): void {
		echo '<div class="elementor-widget-rv-search-results">';
		$this->render_canonical( 'rentiva_search_results' );
		echo '</div>';
	}
}
