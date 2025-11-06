<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Gutenberg;

use MHMRentiva\Admin\Frontend\Blocks\Base\GutenbergBlockBase;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Card Gutenberg Block
 * 
 * Tekil araç kartını Gutenberg block olarak gösterir
 * 
 * @since 3.0.1
 */
class VehicleCardBlock extends GutenbergBlockBase
{
    /**
     * Block adını döndürür
     */
    protected function get_block_name(): string
    {
        return 'vehicle-card';
    }

    /**
     * Block attribute'larını döndürür
     */
    protected function get_block_attributes(): array
    {
        return array_merge(
            $this->get_vehicle_selection_attribute(),
            $this->get_layout_selection_attribute(),
            $this->get_display_options_attributes(),
            $this->get_button_options_attributes(),
            $this->get_rating_options_attributes(),
            [
                'className' => [
                    'type' => 'string',
                    'default' => '',
                ],
                'align' => [
                    'type' => 'string',
                    'default' => '',
                ],
            ]
        );
    }

    /**
     * Block'u render eder
     * 
     * @param array $attributes Block attributes
     * @param string $content Block content
     * @return string Rendered block
     */
    public function render_block(array $attributes, string $content): string
    {
        // Shortcode attribute'larını hazırla
        $atts = $this->prepare_shortcode_attributes($attributes);
        
        // Shortcode'u render et
        $shortcode_output = '<div class="rv-notice">Vehicle Card shortcode kaldırıldı.</div>';
        
        // Block wrapper'ı ekle
        return $this->wrap_block_content($shortcode_output, $attributes);
    }

    /**
     * Buton seçenekleri attribute'larını döndürür
     */
    protected function get_button_options_attributes(): array
    {
        return [
            'show_booking_btn' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'button_text' => [
                'type' => 'string',
                'default' => __('Book Now', 'mhm-rentiva'),
            ],
            'button_style' => [
                'type' => 'string',
                'default' => 'primary',
                'enum' => ['primary', 'secondary', 'outline'],
            ],
            'show_favorite' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ];
    }

    /**
     * Değerlendirme seçenekleri attribute'larını döndürür
     */
    protected function get_rating_options_attributes(): array
    {
        return [
            'show_rating' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'rating_position' => [
                'type' => 'string',
                'default' => 'overlay',
                'enum' => ['overlay', 'below_image', 'footer'],
            ],
            'show_rating_count' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'custom_rating' => [
                'type' => 'number',
                'default' => 0,
                'minimum' => 0,
                'maximum' => 5,
            ],
        ];
    }

    /**
     * Gelişmiş display seçenekleri attribute'larını döndürür
     */
    protected function get_display_options_attributes(array $options = []): array
    {
        return array_merge(
            parent::get_display_options_attributes($options),
            [
                'show_category' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
                'max_features' => [
                    'type' => 'number',
                    'default' => 3,
                    'minimum' => 1,
                    'maximum' => 10,
                ],
                'price_format' => [
                    'type' => 'string',
                    'default' => 'daily',
                    'enum' => ['daily', 'hourly', 'weekly', 'monthly'],
                ],
            ]
        );
    }
}
