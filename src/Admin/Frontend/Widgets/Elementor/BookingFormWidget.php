<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Widgets\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\ElementorWidgetBase;
use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking Form Elementor Widget
 * 
 * Rezervasyon formunu Elementor'da widget olarak gösterir
 * 
 * @since 3.0.1
 */
class BookingFormWidget extends ElementorWidgetBase
{
    /**
     * Widget'ın adını döndürür
     */
    public function get_name(): string
    {
        return 'rv-booking-form';
    }

    /**
     * Widget'ın başlığını döndürür
     */
    public function get_title(): string
    {
        return __('Rezervasyon Formu', 'mhm-rentiva');
    }

    /**
     * Widget'ın açıklamasını döndürür
     */
    public function get_description(): string
    {
        return __('Gelişmiş rezervasyon formu - araç seçimi, ek hizmetler, depozito sistemi', 'mhm-rentiva');
    }

    /**
     * Widget keywords'lerini döndürür
     */
    public function get_keywords(): array
    {
        return array_merge($this->widget_keywords, [
            'booking', 'reservation', 'form', 'rental'
        ]);
    }

    /**
     * Content tab'ı için kontrolleri kaydeder
     */
    protected function register_content_controls(): void
    {
        // General Settings
        $this->start_controls_section(
            'general_section',
            [
                'label' => __('Genel Ayarlar', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'form_title',
            [
                'label' => __('Form Başlığı', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => __('Rezervasyon Formu', 'mhm-rentiva'),
            ]
        );

        $this->add_vehicle_selection_control(
            'vehicle_id',
            __('Belirli Araç', 'mhm-rentiva'),
            __('Boş bırakırsanız kullanıcı araç seçebilir', 'mhm-rentiva')
        );

        $this->end_controls_section();

        // Form Options
        $this->start_controls_section(
            'form_options_section',
            [
                'label' => __('Form Seçenekleri', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'show_vehicle_selector',
            [
                'label' => __('Araç Seçici Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_vehicle_info',
            [
                'label' => __('Araç Bilgisi Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_addons',
            [
                'label' => __('Ek Hizmetler Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'show_payment_options',
            [
                'label' => __('Ödeme Seçenekleri Göster', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->end_controls_section();

        // Booking Settings
        $this->start_controls_section(
            'booking_settings_section',
            [
                'label' => __('Rezervasyon Ayarları', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'default_days',
            [
                'label' => __('Default Number of Days', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 365,
                'default' => 3,
            ]
        );

        $this->add_control(
            'min_days',
            [
                'label' => __('Minimum Number of Days', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 365,
                'default' => 1,
            ]
        );

        $this->add_control(
            'max_days',
            [
                'label' => __('Maximum Number of Days', 'mhm-rentiva'),
                'type' => Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 365,
                'default' => 30,
            ]
        );

        $this->end_controls_section();

        // Payment Settings
        $this->start_controls_section(
            'payment_section',
            [
                'label' => __('Ödeme Ayarları', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'enable_deposit',
            [
                'label' => __('Depozito Sistemi Aktif', 'mhm-rentiva'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Evet', 'mhm-rentiva'),
                'label_off' => __('Hayır', 'mhm-rentiva'),
                'return_value' => '1',
                'default' => '1',
            ]
        );

        $this->add_control(
            'default_payment',
            [
                'label' => __('Varsayılan Ödeme Türü', 'mhm-rentiva'),
                'type' => Controls_Manager::SELECT,
                'default' => 'deposit',
                'options' => [
                    'deposit' => __('Depozito', 'mhm-rentiva'),
                    'full' => __('Tam Ödeme', 'mhm-rentiva'),
                ],
            ]
        );

        $this->end_controls_section();

        // Advanced Settings
        $this->start_controls_section(
            'advanced_section',
            [
                'label' => __('Gelişmiş Ayarlar', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'redirect_url',
            [
                'label' => __('Başarı Sonrası Yönlendirme', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('https://ornek.com/tesekkurler', 'mhm-rentiva'),
            ]
        );

        $this->add_control(
            'custom_css_class',
            [
                'label' => __('Özel CSS Sınıfı', 'mhm-rentiva'),
                'type' => Controls_Manager::TEXT,
                'default' => '',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Style tab'ı için kontrolleri kaydeder
     */
    protected function register_style_controls(): void
    {
        // Form Styles
        $this->start_controls_section(
            'form_style_section',
            [
                'label' => __('Form Stili', 'mhm-rentiva'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'form_background',
            [
                'label' => __('Arka Plan Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-booking-form' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'form_border_radius',
            [
                'label' => __('Köşe Yuvarlaklığı', 'mhm-rentiva'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .rv-booking-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_box_shadow_control('.rv-booking-form', __('Gölge', 'mhm-rentiva'));

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
            'button_color',
            [
                'label' => __('Buton Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-submit' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_hover_color',
            [
                'label' => __('Buton Hover Rengi', 'mhm-rentiva'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .rv-btn-submit:hover' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_typography_control('.rv-btn-submit', __('Buton Tipografi', 'mhm-rentiva'));

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
        
        // Shortcode'u render et
        $shortcode_output = $this->render_shortcode('rentiva_booking_form', $atts);
        
        // Widget wrapper'ı ekle
        echo '<div class="elementor-widget-rv-booking-form">';
        echo $shortcode_output;
        echo '</div>';
    }

    /**
     * Widget'ın JavaScript kodunu döndürür
     */
    protected function content_template(): void
    {
        // JavaScript template (gerekirse)
    }
}

