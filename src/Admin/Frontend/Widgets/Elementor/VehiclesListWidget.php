<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use Elementor\Controls_Manager;
use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;

use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Vehicles List Elementor Widget
 * * Automatically maps Elementor controls to MHM Shortcode attributes.
 */
class VehiclesListWidget extends ElementorWidgetBase
{

	public function get_name(): string
	{
		return 'mhm_rentiva_vehicles_list';
	}

	public function get_title(): string
	{
		return __('MHM Vehicles List', 'mhm-rentiva');
	}

	public function get_icon(): string
	{
		return 'eicon-post-list';
	}

	protected function register_controls(): void
	{
		// --- CONTENT SECTION ---
		$this->start_controls_section('section_content', [
			'label' => __('Content Settings', 'mhm-rentiva'),
		]);

		$this->add_control('columns', [
			'label'   => __('Columns', 'mhm-rentiva'),
			'type'    => 'select',
			'default' => '3',
			'options' => [
				'1' => '1',
				'2' => '2',
				'3' => '3',
				'4' => '4',
			],
		]);

		$this->add_control('show_images', [
			'label'        => __('Show Images', 'mhm-rentiva'),
			'type'         => 'switcher',
			'label_on'     => __('Show', 'mhm-rentiva'),
			'label_off'    => __('Hide', 'mhm-rentiva'),
			'return_value' => 'yes',
			'default'      => 'yes',
		]);

		$this->add_control('show_price', [
			'label'     => __('Show Price', 'mhm-rentiva'),
			'type'      => Controls_Manager::SWITCHER,
			'default'   => 'yes',
		]);

		$this->add_control('booking_btn_text', [
			'label'       => __('Button Text', 'mhm-rentiva'),
			'type'        => 'text',
			'placeholder' => __('Book Now', 'mhm-rentiva'),
			'default'     => __('Book Now', 'mhm-rentiva'),
		]);

		$this->end_controls_section();

		// --- STYLE SECTION (The Magic Part) ---
		// Başlık stili için otomatik selector bağlantısı
		$this->register_standard_style_controls(
			'title_style',
			__('Vehicle Title', 'mhm-rentiva'),
			'.rv-vehicle-card__title a'
		);

		// Fiyat stili için otomatik selector bağlantısı
		$this->register_standard_style_controls(
			'price_style',
			__('Price Tag', 'mhm-rentiva'),
			'.rv-price-amount'
		);
	}

	protected function render(): void
	{
		$atts = $this->get_prepared_atts();

		// Kısa kod sınıfından verileri alıyoruz
		$data = VehiclesList::get_data($atts);

		if (!empty($data)) {
			extract($data);
			// Şablonu dahil ediyoruz
			include MHM_RENTIVA_PLUGIN_DIR . 'templates/shortcodes/vehicles-list.php';
		}
	}
}
