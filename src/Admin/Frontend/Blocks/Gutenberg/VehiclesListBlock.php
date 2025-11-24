<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Gutenberg;

use MHMRentiva\Admin\Frontend\Blocks\Base\GutenbergBlockBase;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicles List Gutenberg Block
 * 
 * Araç listesini Gutenberg block olarak gösterir
 * 
 * @since 3.0.1
 */
class VehiclesListBlock extends GutenbergBlockBase
{
    /**
     * Block adını döndürür
     */
    protected function get_block_name(): string
    {
        return 'vehicles-list';
    }

    /**
     * Block attribute'larını döndürür
     */
    protected function get_block_attributes(): array
    {
        return array_merge(
            $this->get_query_attributes(),
            $this->get_layout_attributes(),
            $this->get_display_options_attributes(),
            $this->get_interaction_attributes(),
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
        
        // Add ids if present
        if (!empty($attributes['ids'])) {
            $atts['ids'] = $attributes['ids'];
        }
        
        // Shortcode'u render et
        $shortcode_output = $this->render_shortcode('rentiva_vehicles_list', $atts);
        
        // Block wrapper'ı ekle
        return $this->wrap_block_content($shortcode_output, $attributes);
    }

    /**
     * Sorgu attribute'larını döndürür
     */
    protected function get_query_attributes(): array
    {
        return [
            'limit' => [
                'type' => 'number',
                'default' => 9,
            ],
            'order' => [
                'type' => 'string',
                'default' => 'DESC',
                'enum' => ['ASC', 'DESC'],
            ],
            'orderby' => [
                'type' => 'string',
                'default' => 'date',
                'enum' => ['date', 'title', 'price', 'rating', 'rand'],
            ],
            'exclude' => [
                'type' => 'string',
                'default' => '',
            ],
            'ids' => [
                'type' => 'string',
                'default' => '',
            ],
        ];
    }

    /**
     * Layout attribute'larını döndürür
     */
    protected function get_layout_attributes(): array
    {
        return [
            'layout' => [
                'type' => 'string',
                'default' => 'grid',
                'enum' => ['grid', 'list'],
            ],
            'columns' => [
                'type' => 'string',
                'default' => '3',
                'enum' => ['1', '2', '3', '4'],
            ],
            'gap' => [
                'type' => 'string',
                'default' => '1.5rem',
            ],
        ];
    }

    /**
     * Display options attribute'larını döndürür
     */
    protected function get_display_options_attributes(array $options = []): array
    {
        return [
            'show_image' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_title' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_price' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_features' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_rating' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ];
    }

    /**
     * Etkileşim attribute'larını döndürür
     */
    protected function get_interaction_attributes(): array
    {
        return [
            'show_booking_btn' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_favorite_btn' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'enable_lazy_load' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ];
    }
}

