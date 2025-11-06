<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Base;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Gutenberg Block Class
 * 
 * Base class for all MHM Rentiva Gutenberg blocks
 * 
 * @since 3.0.1
 */
abstract class GutenbergBlockBase
{
    /**
     * Block namespace
     */
    protected string $namespace = 'mhm-rentiva';
    
    /**
     * Block category
     */
    protected string $category = 'mhm-rentiva';
    
    /**
     * Block icon
     */
    protected string $icon = 'car';
    
    /**
     * Block keywords
     */
    protected array $keywords = ['mhm', 'rentiva', 'vehicle', 'rental'];

    /**
     * Registers block
     */
    public function register(): void
    {
        // Register block - with namespace
        register_block_type($this->namespace . '/' . $this->get_block_name(), [
            'attributes' => $this->get_block_attributes(),
            'render_callback' => [$this, 'render_block'],
            'editor_script' => 'mhm-rentiva-gutenberg-blocks',
            'editor_style' => 'mhm-rentiva-gutenberg-blocks-editor',
            'style' => 'mhm-rentiva-gutenberg-blocks',
            'supports' => $this->get_block_supports(),
        ]);
    }

    /**
     * Returns block name
     */
    abstract protected function get_block_name(): string;

    /**
     * Returns block attributes
     */
    abstract protected function get_block_attributes(): array;

    /**
     * Renders block
     * 
     * @param array $attributes Block attributes
     * @param string $content Block content
     * @return string Rendered block
     */
    abstract public function render_block(array $attributes, string $content): string;

    /**
     * Returns block supports
     */
    protected function get_block_supports(): array
    {
        return [
            'align' => ['wide', 'full'],
            'anchor' => true,
            'customClassName' => true,
            'html' => false,
        ];
    }

    /**
     * Get Vehicle selection attribute
     * 
     * @param string $attribute_name Attribute name
     * @param string $label Attribute label
     * @return array Attribute array
     */
    protected function get_vehicle_selection_attribute(
        string $attribute_name = 'vehicleId',
        string $label = 'Vehicle'
    ): array {
        return [
            $attribute_name => [
                'type' => 'number',
                'default' => $this->get_default_vehicle_id(),
                'description' => $label,
            ],
        ];
    }

    /**
     * Get Layout selection attribute
     * 
     * @param string $attribute_name Attribute name
     * @return array Attribute array
     */
    protected function get_layout_selection_attribute(
        string $attribute_name = 'layout'
    ): array {
        return [
            $attribute_name => [
                'type' => 'string',
                'default' => 'default',
                'enum' => ['default', 'compact', 'grid', 'featured'],
            ],
        ];
    }

    /**
     * Get Display options attributes
     * 
     * @param array $options Display options
     * @return array Attributes array
     */
    protected function get_display_options_attributes(array $options = []): array
    {
        $default_options = [
            'showImage' => 'Image',
            'showTitle' => 'Title',
            'showPrice' => 'Price',
            'showFeatures' => 'Features',
            'showRating' => 'Rating',
            'showBookingBtn' => 'Booking Button',
            'showFavoriteBtn' => 'Favorite Button',
        ];

        $options = array_merge($default_options, $options);
        $attributes = [];

        foreach ($options as $key => $label) {
            $attributes[$key] = [
                'type' => 'boolean',
                'default' => true,
                'description' => $label,
            ];
        }

        return $attributes;
    }

    /**
     * Get Vehicle options
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
            $options[] = [
                'value' => $vehicle->ID,
                'label' => $vehicle->post_title,
            ];
        }

        return $options;
    }

    /**
     * Get Default vehicle ID
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
     * Prepare Shortcode attributes
     * 
     * @param array $attributes Block attributes
     * @return array Shortcode attributes
     */
    protected function prepare_shortcode_attributes(array $attributes): array
    {
        $atts = [];

        // Vehicle ID
        if (!empty($attributes['vehicleId'])) {
            $atts['id'] = $attributes['vehicleId'];
        }

        // Layout
        if (!empty($attributes['layout'])) {
            $atts['layout'] = $attributes['layout'];
        }

        // Display options
        $display_options = [
            'showImage' => 'show_image',
            'showTitle' => 'show_title',
            'showPrice' => 'show_price',
            'showFeatures' => 'show_features',
            'showRating' => 'show_rating',
            'showBookingBtn' => 'show_booking_btn',
            'showFavoriteBtn' => 'show_favorite_btn',
        ];

        foreach ($display_options as $block_attr => $shortcode_attr) {
            if (isset($attributes[$block_attr])) {
                $atts[$shortcode_attr] = $attributes[$block_attr] ? '1' : '0';
            }
        }

        // Custom CSS class
        if (!empty($attributes['className'])) {
            $atts['class'] = $attributes['className'];
        }

        return $atts;
    }

    /**
     * Render Shortcode
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

    /**
     * Add Block wrapper
     * 
     * @param string $content Block content
     * @param array $attributes Block attributes
     * @return string Wrapped content
     */
    protected function wrap_block_content(string $content, array $attributes): string
    {
        $wrapper_class = 'wp-block-mhm-rentiva-' . $this->get_block_name();
        
        if (!empty($attributes['className'])) {
            $wrapper_class .= ' ' . $attributes['className'];
        }
        
        if (!empty($attributes['align'])) {
            $wrapper_class .= ' align' . $attributes['align'];
        }

        return '<div class="' . esc_attr($wrapper_class) . '">' . $content . '</div>';
    }
}
