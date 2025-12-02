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
        
        // Map 'id' to 'ids' for VehiclesList shortcode
        if (isset($atts['id'])) {
            $atts['ids'] = $atts['id'];
            unset($atts['id']);
        }
        
        // Force single item display
        $atts['limit'] = '1';
        $atts['columns'] = '1';
        
        // Render shortcode
        $shortcode_output = $this->render_shortcode('rentiva_vehicles_list', $atts);
        
        // Wrap content with standard block container
        return $this->wrap_block_content($shortcode_output, $attributes);
    }

    /**
     * Return button option attributes.
     */
    protected function get_button_options_attributes(): array
    {
        return [
            'buttonText' => [
                'type' => 'string',
                'default' => __('Book Now', 'mhm-rentiva'),
            ],
            'buttonStyle' => [
                'type' => 'string',
                'default' => 'primary',
                'enum' => ['primary', 'secondary', 'outline'],
            ],
        ];
    }

    /**
     * Return rating option attributes.
     */
    protected function get_rating_options_attributes(): array
    {
        return [
            'ratingPosition' => [
                'type' => 'string',
                'default' => 'overlay',
                'enum' => ['overlay', 'below_image', 'footer'],
            ],
            'showRatingCount' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'customRating' => [
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
                'maxFeatures' => [
                    'type' => 'number',
                    'default' => 3,
                    'minimum' => 1,
                    'maximum' => 10,
                ],
                'priceFormat' => [
                    'type' => 'string',
                    'default' => 'daily',
                    'enum' => ['daily', 'hourly', 'weekly', 'monthly'],
                ],
            ]
        );
    }

    /**
     * Prepare Shortcode attributes
     */
    protected function prepare_shortcode_attributes(array $attributes): array
    {
        $atts = parent::prepare_shortcode_attributes($attributes);

        if (!empty($attributes['buttonText'])) {
            $atts['booking_btn_text'] = $attributes['buttonText'];
        }
        if (!empty($attributes['maxFeatures'])) {
            $atts['max_features'] = $attributes['maxFeatures'];
        }
        if (!empty($attributes['priceFormat'])) {
            $atts['price_format'] = $attributes['priceFormat'];
        }

        return $atts;
    }
}
