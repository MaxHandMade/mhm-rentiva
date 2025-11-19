<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Gutenberg;

use MHMRentiva\Admin\Frontend\Blocks\Base\GutenbergBlockBase;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Card Gutenberg Block
 * 
 * Outputs a single vehicle card as a Gutenberg block.
 * 
 * @since 3.0.1
 */
class VehicleCardBlock extends GutenbergBlockBase
{
    /**
     * Return block name.
     */
    protected function get_block_name(): string
    {
        return 'vehicle-card';
    }

    /**
     * Return block attributes.
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
     * Render block output.
     *
     * @param array $attributes Block attributes
     * @param string $content Block content
     * @return string Rendered block markup
     */
    public function render_block(array $attributes, string $content): string
    {
        // Prepare shortcode attributes
        $atts = $this->prepare_shortcode_attributes($attributes);
        
        // Render shortcode (placeholder text because block is deprecated)
        $shortcode_output = '<div class="rv-notice">' . esc_html__('Vehicle Card shortcode has been removed.', 'mhm-rentiva') . '</div>';
        
        // Wrap content with standard block container
        return $this->wrap_block_content($shortcode_output, $attributes);
    }

    /**
     * Return button option attributes.
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
     * Return rating option attributes.
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
     * Return advanced display option attributes.
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
