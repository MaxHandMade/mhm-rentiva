<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Blocks\Gutenberg;

use MHMRentiva\Admin\Frontend\Blocks\Base\GutenbergBlockBase;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Booking Form Gutenberg Block
 * 
 * Displays booking form as Gutenberg block
 * 
 * @since 3.0.1
 */
class BookingFormBlock extends GutenbergBlockBase
{
    /**
     * Block adını döndürür
     */
    protected function get_block_name(): string
    {
        return 'booking-form';
    }

    /**
     * Block attribute'larını döndürür
     */
    protected function get_block_attributes(): array
    {
        return [
            // General
            'form_title' => [
                'type' => 'string',
                'default' => __('Booking Form', 'mhm-rentiva'),
            ],
            'vehicle_id' => [
                'type' => 'string',
                'default' => '',
            ],
            
            // Form Options
            'show_vehicle_selector' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_vehicle_info' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_addons' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'show_payment_options' => [
                'type' => 'boolean',
                'default' => true,
            ],
            
            // Booking Settings
            'default_days' => [
                'type' => 'number',
                'default' => 3,
            ],
            'min_days' => [
                'type' => 'number',
                'default' => 1,
            ],
            'max_days' => [
                'type' => 'number',
                'default' => 30,
            ],
            
            // Payment Settings
            'enable_deposit' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'default_payment' => [
                'type' => 'string',
                'default' => 'deposit',
                'enum' => ['deposit', 'full'],
            ],
            
            // Advanced
            'redirect_url' => [
                'type' => 'string',
                'default' => '',
            ],
            'className' => [
                'type' => 'string',
                'default' => '',
            ],
            'align' => [
                'type' => 'string',
                'default' => '',
            ],
        ];
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
        $shortcode_output = $this->render_shortcode('rentiva_booking_form', $atts);
        
        // Block wrapper'ı ekle
        return $this->wrap_block_content($shortcode_output, $attributes);
    }
}

