<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicles List Elementor Widget
 * 
 * Araç listesini Elementor'da widget olarak gösterir
 * 
 * @since 3.0.1
 */
class VehiclesListWidget extends ElementorWidgetBase
{
    /**
     * Widget'ın adını döndürür
     */
    public function get_name(): string
    {
        return 'rv-vehicles-list';
    }

    /**
     * Widget'ın başlığını döndürür
     */
    public function get_title(): string
    {
        return __('Araç Listesi', 'mhm-rentiva');
    }

    /**
     * Widget'ın açıklamasını döndürür
     */
    public function get_description(): string
    {
        return __('Tüm araçları grid veya liste düzeninde gösterir', 'mhm-rentiva');
    }

    /**
     * Widget keywords'lerini döndürür
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'vehicle', 'list', 'cars', 'rental', 'booking', 'grid'
        ]);
    }

    /**
     * Content tab'ı için kontrolleri kaydeder
     */
    protected function register_content_controls(): void
    {
        // Query Section
        $this->start_controls_section(
            'query_section',
            [
                'label' => __('Sorgu Ayarları', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_query_controls();

        $this->end_controls_section();

        // Layout Section
        $this->start_controls_section(
            'layout_section',
            [
                'label' => __('Düzen Ayarları', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_layout_controls();

        $this->end_controls_section();

        // Display Options
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Gösterim Seçenekleri', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_display_options();

        $this->end_controls_section();

        // Button & Interaction Options
        $this->start_controls_section(
            'interaction_section',
            [
                'label' => __('Buton ve Etkileşim', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_interaction_options();

        $this->end_controls_section();

        // Advanced Options
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Gelişmiş Seçenekler', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_advanced_options();

        $this->end_controls_section();
    }

    /**
     * Style tab'ı için kontrolleri kaydeder
     */
    protected function register_style_controls(): void
    {
        // Card Styles
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => __('Kart Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_card_styles();

        $this->end_controls_section();

        // Grid Styles
        $this->start_controls_section(
            'grid_style_section',
            [
                'label' => __('Grid Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_grid_styles();

        $this->end_controls_section();

        // Typography Styles
        $this->start_controls_section(
            'typography_style_section',
            [
                'label' => __('Tipografi', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_typography_styles();

        $this->end_controls_section();

        // Button Styles
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Buton Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_button_styles();

        $this->end_controls_section();
    }

    /**
     * Sorgu kontrollerini ekler
     */
    protected function add_query_controls(): void
    {
        $this->add_control(
            'limit',
            [
                'label' => __('Araç Sayısı', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 100,
                'step' => 1,
                'default' => 9,
                'description' => __('Gösterilecek araç sayısı', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'order',
            [
                'label' => __('Sıralama', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'DESC',
                'options' => [
                    'ASC' => __('Artan', 'mhm-rentiva'),
                    'DESC' => __('Azalan', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'orderby',
            [
                'label' => __('Sıralama Ölçütü', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'date',
                'options' => [
                    'date' => __('Tarih', 'mhm-rentiva'),
                    'title' => __('Başlık', 'mhm-rentiva'),
                    'price' => __('Fiyat', 'mhm-rentiva'),
                    'rating' => __('Değerlendirme', 'mhm-rentiva'),
                    'rand' => __('Rastgele', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'exclude',
            [
                'label' => __('Hariç Tutulacak Araç ID\'leri', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('Örn: 1, 2, 3', 'mhm-rentiva'),
                'description' => __('Virgülle ayrılmış araç ID\'leri', 'mhm-rentiva'),
            ]
        );
    }

    /**
     * Layout kontrollerini ekler
     */
    protected function add_layout_controls(): void
    {
        $this->add_control(
            'layout',
            [
                'label' => __('Düzen', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __('Grid', 'mhm-rentiva'),
                    'list' => __('Liste', 'mhm-rentiva'),
                ],
            ]
        );

        $this->add_control(
            'columns',
            [
                'label' => __('Kolon Sayısı', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '1' => '1',
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                ],
            ]
        );

        $this->add_control(
            'gap',
            [
                'label' => __('Boşluk', 'mhm-rentiva'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'rem',
                    'size' => 1.5,
                ],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicles-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
    }

    /**
     * Gösterim seçeneklerini ekler
     */
    protected function add_display_options(): void
    {
        $this->add_control(
            'show_image',
            [
                'label' => __('Resim Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Başlık Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Fiyat Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_features',
            [
                'label' => __('Özellikler Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_rating',
            [
                'label' => __('Değerlendirme Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
    }

    /**
     * Etkileşim seçeneklerini ekler
     */
    protected function add_interaction_options(): void
    {
        $this->add_control(
            'show_booking_btn',
            [
                'label' => __('Rezervasyon Butonu Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_favorite_btn',
            [
                'label' => __('Favori Butonu Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_category',
            [
                'label' => __('Kategori Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_badges',
            [
                'label' => __('Rozetler Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Açıklama Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_availability',
            [
                'label' => __('Müsaitlik Durumu Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );
    }

    /**
     * Gelişmiş seçenekleri ekler
     */
    protected function add_advanced_options(): void
    {
        $this->add_control(
            'custom_css_class',
            [
                'label' => __('Özel CSS Sınıfı', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'description' => __('Bu widget için özel CSS sınıfı ekleyin', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'enable_lazy_load',
            [
                'label' => __('Lazy Loading Etkinleştir', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'enable_ajax_filtering',
            [
                'label' => __('AJAX Filtreleme Etkinleştir', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'enable_infinite_scroll',
            [
                'label' => __('Sonsuz Kaydırma Etkinleştir', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'show_compare_btn',
            [
                'label' => __('Karşılaştırma Butonu Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );
    }

    /**
     * Kart stillerini ekler
     */
    protected function add_card_styles(): void
    {
        $this->add_control(
            'card_background',
            [
                'label' => __('Arka Plan Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card' => 'background-color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->add_control(
            'card_border_radius',
            [
                'label' => __('Köşe Yuvarlaklığı', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_box_shadow_control('.rv-vehicle-card', __('Gölge', 'mhm-rentiva'));
    }

    /**
     * Grid stillerini ekler
     */
    protected function add_grid_styles(): void
    {
        $this->add_control(
            'grid_gap',
            [
                'label' => __('Grid Boşluğu', 'mhm-rentiva'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicles-list--grid' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
    }

    /**
     * Tipografi stillerini ekler
     */
    protected function add_typography_styles(): void
    {
        $this->add_control(
            'title_color',
            [
                'label' => __('Başlık Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-vehicle-card__title', __('Başlık Tipografi', 'mhm-rentiva'));

        $this->add_control(
            'price_color',
            [
                'label' => __('Fiyat Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-price-amount' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-price-amount', __('Fiyat Tipografi', 'mhm-rentiva'));
    }

    /**
     * Buton stillerini ekler
     */
    protected function add_button_styles(): void
    {
        $this->add_control(
            'button_color',
            [
                'label' => __('Buton Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-booking' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Buton Hover Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-booking:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-btn-booking', __('Buton Tipografi', 'mhm-rentiva'));
    }

    /**
     * Widget'ı render eder
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        
        // Shortcode attribute'larını hazırla
        $atts = $this->prepare_shortcode_attributes($settings);
        
        
        // Shortcode'u render et
        $shortcode_output = $this->render_shortcode('rentiva_vehicles_list', $atts);
        
        // Widget wrapper'ı ekle
        echo '<div class="elementor-widget-rv-vehicles-list">';
        echo $shortcode_output;
        echo '</div>';
    }


    /**
     * Shortcode attribute'larını hazırlar (VehiclesListWidget için özel)
     * 
     * @param array $settings Elementor settings
     * @return array Shortcode attributes
     */
    protected function prepare_shortcode_attributes(array $settings): array
    {
        $atts = [];

        // Query parameters
        if (isset($settings['limit']) && $settings['limit'] !== '') {
            $atts['limit'] = $settings['limit'];
        }
        if (isset($settings['order']) && $settings['order'] !== '') {
            $atts['order'] = $settings['order'];
        }
        if (isset($settings['orderby']) && $settings['orderby'] !== '') {
            $atts['orderby'] = $settings['orderby'];
        }
        if (isset($settings['exclude']) && $settings['exclude'] !== '') {
            // Exclude array ise string'e çevir
            if (is_array($settings['exclude'])) {
                $atts['exclude'] = implode(',', $settings['exclude']);
            } else {
                $atts['exclude'] = $settings['exclude'];
            }
        }
        if (isset($settings['category']) && $settings['category'] !== '') {
            // Category array ise string'e çevir
            if (is_array($settings['category'])) {
                $atts['category'] = implode(',', $settings['category']);
            } else {
                $atts['category'] = $settings['category'];
            }
        }
        if (isset($settings['featured'])) {
            $atts['featured'] = $settings['featured'] === 'yes' ? '1' : '0';
        }

        // Layout
        if (isset($settings['layout']) && $settings['layout'] !== '') {
            // Layout değerini string'e çevir
            $atts['layout'] = (string) $settings['layout'];
        }
        if (isset($settings['columns']) && $settings['columns'] !== '') {
            $atts['columns'] = $settings['columns'];
        }
        if (isset($settings['gap']) && $settings['gap'] !== '') {
            $atts['gap'] = $settings['gap'];
        }

        // Display options
        $display_options = [
            'show_image', 'show_title', 'show_price', 'show_features',
            'show_rating', 'show_booking_btn', 'show_favorite_btn', 'show_category'
        ];

        foreach ($display_options as $option) {
            if (isset($settings[$option])) {
                $value = $settings[$option];
                $atts[$option] = ($value === 'yes') ? '1' : '0';
            }
        }

        // Additional options
        if (isset($settings['show_badges'])) {
            $value = $settings['show_badges'];
            $atts['show_badges'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['show_description'])) {
            $value = $settings['show_description'];
            $atts['show_description'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['show_availability'])) {
            $value = $settings['show_availability'];
            $atts['show_availability'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['show_compare_btn'])) {
            $value = $settings['show_compare_btn'];
            $atts['show_compare_btn'] = ($value === 'yes') ? '1' : '0';
        }

        // Interaction options
        if (isset($settings['enable_ajax_filtering'])) {
            $value = $settings['enable_ajax_filtering'];
            $atts['enable_ajax_filtering'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['enable_lazy_load'])) {
            $value = $settings['enable_lazy_load'];
            $atts['enable_lazy_load'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['enable_infinite_scroll'])) {
            $value = $settings['enable_infinite_scroll'];
            $atts['enable_infinite_scroll'] = ($value === 'yes') ? '1' : '0';
        }
        if (isset($settings['custom_css_class']) && $settings['custom_css_class'] !== '') {
            $atts['custom_css_class'] = $settings['custom_css_class'];
        }

        return $atts;
    }

    /**
     * Widget'ın JavaScript kodunu döndürür
     */
    protected function content_template(): void
    {
        // JavaScript template (gerekirse)
    }
}

