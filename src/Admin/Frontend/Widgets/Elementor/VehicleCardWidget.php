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
 * Vehicle Card Elementor Widget
 * 
 * Tekil araç kartını Elementor'da widget olarak gösterir
 * 
 * @since 3.0.1
 */
class VehicleCardWidget extends ElementorWidgetBase
{
    /**
     * Widget'ın adını döndürür
     */
    public function get_name(): string
    {
        return 'rv-vehicle-card';
    }

    /**
     * Widget'ın başlığını döndürür
     */
    public function get_title(): string
    {
        return __('Vehicle Card', 'mhm-rentiva');
    }

    /**
     * Widget'ın açıklamasını döndürür
     */
    public function get_description(): string
    {
        return __('Displays a single vehicle card - in list or standalone', 'mhm-rentiva');
    }

    /**
     * Widget keywords'lerini döndürür
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'vehicle', 'card', 'car', 'rental', 'booking'
        ]);
    }

    /**
     * Content tab'ı için kontrolleri kaydeder
     */
    protected function register_content_controls(): void
    {
        // Vehicle Selection
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_vehicle_selection_control();

        $this->add_layout_control();

        $this->end_controls_section();

        // Display Options
        $this->start_controls_section(
            'display_section',
            [
                'label' => __('Display Options', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_display_options_control();

        $this->end_controls_section();

        // Button & Interaction Options
        $this->start_controls_section(
            'button_section',
            [
                'label' => __('Buttons and Interaction', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_button_options_control();

        $this->end_controls_section();

        // Rating Options
        $this->start_controls_section(
            'rating_section',
            [
                'label' => __('Değerlendirme', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_rating_options_control();

        $this->end_controls_section();

        // Advanced Options
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Gelişmiş Seçenekler', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'custom_css_class',
            [
                'label' => __('Özel CSS Sınıfı', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'title' => __('Özel CSS sınıfı ekleyin', 'mhm-rentiva'),
                'description' => __('Bu widget için özel CSS sınıfı ekleyin', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'enable_animation',
            [
                'label' => __('Animasyon Etkinleştir', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

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

        $this->add_border_control('.rv-vehicle-card', __('Kenar Çizgisi', 'mhm-rentiva'));

        $this->add_control(
            'border_radius',
            [
                'label' => __('Köşe Yuvarlaklığı', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 12,
                    'right' => 12,
                    'bottom' => 12,
                    'left' => 12,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->add_box_shadow_control('.rv-vehicle-card', __('Gölge', 'mhm-rentiva'));

        $this->add_control(
            'card_padding',
            [
                'label' => __('İç Boşluk', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 16,
                    'right' => 16,
                    'bottom' => 16,
                    'left' => 16,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->end_controls_section();

        // Title Styles
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Başlık Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Renk', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title' => 'color: {{VALUE}}',
                ],
                'default' => '#1e293b',
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label' => __('Hover Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title-link:hover' => 'color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_typography_control('.rv-vehicle-card__title', __('Tipografi', 'mhm-rentiva'));

        $this->add_control(
            'title_margin',
            [
                'label' => __('Dış Boşluk', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Price Styles
        $this->start_controls_section(
            'price_style_section',
            [
                'label' => __('Fiyat Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'price_color',
            [
                'label' => __('Renk', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-price-amount' => 'color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'price_period_color',
            [
                'label' => __('Dönem Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-price-period' => 'color: {{VALUE}}',
                ],
                'default' => '#64748b',
            ]
        );

        $this->add_typography_control('.rv-price-amount', __('Tipografi', 'mhm-rentiva'));

        $this->end_controls_section();

        // Button Styles
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('Buton Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'primary_button_color',
            [
                'label' => __('Birincil Buton Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn--primary' => 'background-color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'primary_button_hover_color',
            [
                'label' => __('Birincil Buton Hover Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn--primary:hover' => 'background-color: {{VALUE}}',
                ],
                'default' => '#1d4ed8',
            ]
        );

        $this->add_control(
            'secondary_button_color',
            [
                'label' => __('İkincil Buton Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn--secondary' => 'color: {{VALUE}}; border-color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Köşe Yuvarlaklığı', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->add_typography_control('.rv-btn', __('Tipografi', 'mhm-rentiva'));

        $this->end_controls_section();

        // Badge Styles
        $this->start_controls_section(
            'badge_style_section',
            [
                'label' => __('Rozet Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'badge_background',
            [
                'label' => __('Arka Plan Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__badge' => 'background-color: {{VALUE}}',
                ],
                'default' => '#2563eb',
            ]
        );

        $this->add_control(
            'badge_text_color',
            [
                'label' => __('Metin Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__badge' => 'color: {{VALUE}}',
                ],
                'default' => '#ffffff',
            ]
        );

        $this->add_control(
            'badge_border_radius',
            [
                'label' => __('Köşe Yuvarlaklığı', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-vehicle-card__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'default' => [
                    'top' => 4,
                    'right' => 4,
                    'bottom' => 4,
                    'left' => 4,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
            ]
        );

        $this->add_typography_control('.rv-vehicle-card__badge', __('Tipografi', 'mhm-rentiva'));

        $this->end_controls_section();
    }

    /**
     * Widget'ı render eder
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        
        // Shortcode attribute'larını hazırla
        $atts = $this->prepare_shortcode_attributes($settings);
        
        // Özel CSS sınıfı ekle
        if (!empty($settings['custom_css_class'])) {
            $atts['class'] = $settings['custom_css_class'];
        }
        
        // Animasyon kontrolü
        if ($settings['enable_animation'] !== 'yes') {
            $atts['disable_animation'] = '1';
        }
        
        // Shortcode'u render et
        $shortcode_output = '<div class="rv-notice">Vehicle Card shortcode kaldırıldı.</div>';
        
        // Widget wrapper'ı ekle
        echo '<div class="elementor-widget-rv-vehicle-card">';
        echo $shortcode_output;
        echo '</div>';
    }

    /**
     * Buton seçenekleri kontrolünü ekler
     */
    protected function add_button_options_control(): void
    {
        $this->add_control(
            'show_booking_btn',
            [
                'label' => __('Rezervasyon Butonu Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => __('Buton Metni', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Rezervasyon Yap', 'mhm-rentiva'),
                'placeholder' => __('Buton metnini girin', 'mhm-rentiva'),
                'condition' => [
                    'show_booking_btn' => '1',
                ],
            ]
        );

        $this->add_control(
            'button_style',
            [
                'label' => __('Buton Stili', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'primary',
                'options' => [
                    'primary' => __('Birincil', 'mhm-rentiva'),
                    'secondary' => __('İkincil', 'mhm-rentiva'),
                    'outline' => __('Çerçeveli', 'mhm-rentiva'),
                ],
                'condition' => [
                    'show_booking_btn' => '1',
                ],
            ]
        );

        $this->add_control(
            'show_favorite',
            [
                'label' => __('Favori Butonu Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );
    }

    /**
     * Değerlendirme seçenekleri kontrolünü ekler
     */
    protected function add_rating_options_control(): void
    {
        $this->add_control(
            'show_rating',
            [
                'label' => __('Yıldız Değerlendirmesi Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'rating_position',
            [
                'label' => __('Yıldız Konumu', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'overlay',
                'options' => [
                    'overlay' => __('Resim Üzeri', 'mhm-rentiva'),
                    'below_image' => __('Resim Altı', 'mhm-rentiva'),
                    'footer' => __('Alt Kısım', 'mhm-rentiva'),
                ],
                'condition' => [
                    'show_rating' => '1',
                ],
            ]
        );

        $this->add_control(
            'show_rating_count',
            [
                'label' => __('Değerlendirme Sayısını Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
                'condition' => [
                    'show_rating' => '1',
                ],
            ]
        );

        $this->add_control(
            'custom_rating',
            [
                'label' => __('Özel Değerlendirme', 'mhm-rentiva'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [''],
                'range' => [
                    '' => [
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => '',
                    'size' => 0,
                ],
                'description' => __('0 = Otomatik, 0.1-5.0 = Özel değer', 'mhm-rentiva'),
                'condition' => [
                    'show_rating' => '1',
                ],
            ]
        );
    }

    /**
     * Gelişmiş display seçenekleri kontrolünü ekler
     */
    protected function add_display_options_control(array $options = []): void
    {
        $this->add_control(
            'show_image',
            [
                'label' => __('Resim Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Başlık Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_category',
            [
                'label' => __('Kategori Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_features',
            [
                'label' => __('Özellikler Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'max_features',
            [
                'label' => __('Maksimum Özellik Sayısı', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default' => 3,
                'condition' => [
                    'show_features' => '1',
                ],
            ]
        );

        $this->add_control(
            'show_price',
            [
                'label' => __('Fiyat Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'price_format',
            [
                'label' => __('Fiyat Formatı', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'daily',
                'options' => [
                    'daily' => __('Günlük', 'mhm-rentiva'),
                    'hourly' => __('Saatlik', 'mhm-rentiva'),
                    'weekly' => __('Haftalık', 'mhm-rentiva'),
                    'monthly' => __('Aylık', 'mhm-rentiva'),
                ],
                'condition' => [
                    'show_price' => '1',
                ],
            ]
        );
    }

    /**
     * Widget'ın JavaScript kodunu döndürür
     */
    protected function content_template(): void
    {
        // JavaScript template (gerekirse)
    }
}
