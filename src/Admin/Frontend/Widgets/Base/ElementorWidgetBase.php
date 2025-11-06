<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Base;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Background;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Elementor Widget Class
 * 
 * Tüm MHM Rentiva Elementor widget'ları için temel sınıf
 * 
 * @since 3.0.1
 */
abstract class ElementorWidgetBase extends Widget_Base
{
    /**
     * Widget kategori
     */
    protected string $widget_category = 'mhm-rentiva';
    
    /**
     * Widget icon
     */
    protected string $widget_icon = 'eicon-car';
    
    /**
     * Widget keywords
     */
    protected array $widget_keywords = ['mhm', 'rentiva', 'vehicle', 'rental'];

    /**
     * Widget'ın kategorisini döndürür
     */
    public function get_categories(): array
    {
        return [$this->widget_category];
    }

    /**
     * Widget icon'unu döndürür
     */
    public function get_icon(): string
    {
        return $this->widget_icon;
    }

    /**
     * Widget keywords'lerini döndürür
     */
    public function get_keywords(): array
    {
        return $this->widget_keywords;
    }

    /**
     * Widget'ın script dependencies'lerini döndürür
     */
    public function get_script_depends(): array
    {
        return ['mhm-rentiva-elementor'];
    }

    /**
     * Widget'ın style dependencies'lerini döndürür
     */
    public function get_style_depends(): array
    {
        return ['mhm-rentiva-elementor'];
    }

    /**
     * Content tab'ı için kontrolleri ekler
     */
    abstract protected function register_content_controls(): void;

    /**
     * Style tab'ı için kontrolleri ekler
     */
    abstract protected function register_style_controls(): void;

    /**
     * Widget kontrollerini kaydeder
     */
    protected function register_controls(): void
    {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Vehicle selection control'ü ekler
     * 
     * @param string $control_id Control ID
     * @param string $label Control label
     * @param string $description Control description
     */
    protected function add_vehicle_selection_control(
        string $control_id = 'vehicle_id',
        string $label = 'Araç Seçin',
        string $description = 'Gösterilecek aracı seçin'
    ): void {
        $this->add_control(
            $control_id,
            [
                'label' => $label,
                'type' => Controls_Manager::SELECT2,
                'label_block' => true,
                'multiple' => false,
                'options' => $this->get_vehicle_options(),
                'description' => $description,
                'default' => $this->get_default_vehicle_id(),
            ]
        );
    }

    /**
     * Layout selection control'ü ekler
     * 
     * @param string $control_id Control ID
     * @param string $label Control label
     */
    protected function add_layout_control(
        string $control_id = 'layout',
        string $label = 'Düzen'
    ): void {
        $this->add_control(
            $control_id,
            [
                'label' => $label,
                'type' => Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => 'Varsayılan',
                    'compact' => 'Kompakt',
                    'grid' => 'Izgara',
                    'featured' => 'Öne Çıkan',
                ],
            ]
        );
    }

    /**
     * Display options control'ü ekler
     * 
     * @param array $options Gösterilecek seçenekler
     */
    protected function add_display_options_control(array $options = []): void
    {
        $default_options = [
            'show_image' => 'Görsel Göster',
            'show_title' => 'Başlık Göster',
            'show_price' => 'Fiyat Göster',
            'show_features' => 'Özellikler Göster',
            'show_rating' => 'Değerlendirme Göster',
            'show_booking_btn' => 'Rezervasyon Butonu Göster',
            'show_favorite_btn' => 'Favori Butonu Göster',
        ];

        $options = array_merge($default_options, $options);

        $this->add_control(
            'display_options',
            [
                'label' => 'Gösterim Seçenekleri',
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        foreach ($options as $key => $label) {
            $this->add_control(
                $key,
                [
                    'label' => $label,
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Evet',
                    'label_off' => 'Hayır',
                    'return_value' => 'yes',
                    'default' => 'yes',
                ]
            );
        }
    }

    /**
     * Typography control'ü ekler
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_typography_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => $selector . '_typography',
                'label' => $label,
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Border control'ü ekler
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_border_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => $selector . '_border',
                'label' => $label,
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Box shadow control'ü ekler
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_box_shadow_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => $selector . '_shadow',
                'label' => $label,
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Background control'ü ekler
     * 
     * @param string $selector CSS selector
     * @param string $label Control label
     */
    protected function add_background_control(
        string $selector,
        string $label
    ): void {
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name' => $selector . '_background',
                'label' => $label,
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );
    }

    /**
     * Araç seçeneklerini döndürür
     * 
     * @return array Vehicle options
     */
    protected function get_vehicle_options(): array
    {
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $options = [];
        foreach ($vehicles as $vehicle) {
            $options[$vehicle->ID] = $vehicle->post_title;
        }

        return $options;
    }

    /**
     * Varsayılan araç ID'sini döndürür
     * 
     * @return int Default vehicle ID
     */
    protected function get_default_vehicle_id(): int
    {
        $vehicles = get_posts([
            'post_type' => 'vehicle',
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return $vehicles ? $vehicles[0]->ID : 0;
    }

    /**
     * Shortcode attribute'larını hazırlar
     * 
     * @param array $settings Elementor settings
     * @return array Shortcode attributes
     */
    protected function prepare_shortcode_attributes(array $settings): array
    {
        $atts = [];

        // Vehicle ID
        if (!empty($settings['vehicle_id'])) {
            $atts['id'] = $settings['vehicle_id'];
        }

        // Layout
        if (!empty($settings['layout'])) {
            $atts['layout'] = $settings['layout'];
        }

        // Display options
        $display_options = [
            'show_image', 'show_title', 'show_price', 'show_features',
            'show_rating', 'show_booking_btn', 'show_favorite_btn'
        ];

        foreach ($display_options as $option) {
            if (isset($settings[$option])) {
                $atts[$option] = $settings[$option] === 'yes' ? '1' : '0';
            }
        }

        return $atts;
    }

    /**
     * Shortcode'u render eder
     * 
     * @param string $shortcode_tag Shortcode tag
     * @param array $atts Shortcode attributes
     * @return string Rendered shortcode
     */
    protected function render_shortcode(string $shortcode_tag, array $atts): string
    {
        $shortcode = '[' . $shortcode_tag;
        
        foreach ($atts as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        
        $shortcode .= ']';
        
        return do_shortcode($shortcode);
    }
}
