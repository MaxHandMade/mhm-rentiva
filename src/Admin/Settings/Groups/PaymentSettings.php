<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Settings
 * 
 * Handles payment configuration and WooCommerce integration status.
 * 
 * @since 4.0.0
 */
final class PaymentSettings
{
    public const SECTION_GENERAL = 'mhm_rentiva_general_payment_section';

    /**
     * Get default settings for payment
     *
     * @return array
     */
    public static function get_default_settings(): array
    {
        return [
            // Notification Defaults (Payment related)
            'mhm_rentiva_email_payment_confirmation'    => '1',

            // Rate Limiting Defaults (Payment related)
            'mhm_rentiva_rate_limit_payment_per_minute' => 3,
            'mhm_rentiva_rate_limit_payment_minute'     => 3, // Legacy support
        ];
    }

    /**
     * Render the payment settings section
     */
    public static function render_settings_section(): void
    {
        // General Payment Information / WooCommerce Status
        \MHMRentiva\Admin\Settings\SettingsView::render_section_clean(self::SECTION_GENERAL);
    }

    /**
     * Register settings
     */
    public static function register(): void
    {
        self::register_settings();
    }

    /**
     * Register all payment settings
     */
    public static function register_settings(): void
    {
        add_settings_section(
            self::SECTION_GENERAL,
            __('Payment Configuration', 'mhm-rentiva'),
            [self::class, 'render_payment_section_description'],
            'mhm_rentiva_settings'
        );
    }

    /**
     * Render payment section description
     */
    public static function render_payment_section_description(): void
    {
        if (class_exists('WooCommerce')) {
            echo '<div class="notice notice-info inline" style="margin: 10px 0; padding: 15px;">';
            echo '<p style="font-size: 1.1em; margin-bottom: 10px;"><strong>' . esc_html__('WooCommerce Integration Active', 'mhm-rentiva') . '</strong></p>';
            echo '<p>' . esc_html__('All payment processing is currently handled securely by WooCommerce.', 'mhm-rentiva') . '</p>';

            $woo_settings_url = admin_url('admin.php?page=wc-settings&tab=checkout');
            echo '<p><a href="' . esc_url($woo_settings_url) . '" class="button button-primary">' . esc_html__('Manage Payment Gateways in WooCommerce', 'mhm-rentiva') . '</a></p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning inline" style="margin: 10px 0; padding: 15px;">';
            echo '<p><strong>' . esc_html__('WooCommerce Required', 'mhm-rentiva') . '</strong></p>';
            echo '<p>' . esc_html__('Please install and activate WooCommerce to accept payments.', 'mhm-rentiva') . '</p>';
            echo '</div>';
        }
    }
}
