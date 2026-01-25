<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Core\SettingsHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Transfer Settings Group
 * 
 * Manages transfer routes, locations, and pricing configurations.
 * Integrated into main Settings Framework.
 * 
 * @package MHMRentiva\Admin\Settings\Groups
 */
final class TransferSettings
{
    public const SECTION_TRANSFER = 'mhm_rentiva_transfer_section';

    /**
     * Get default settings for transfer
     */
    public static function get_default_settings(): array
    {
        return [
            'mhm_transfer_deposit_type' => 'full_payment',
            'mhm_transfer_deposit_rate' => 20,
            'mhm_transfer_custom_types' => '',
        ];
    }

    /**
     * Render the transfer settings section
     */
    public static function render_settings_section(): void
    {
        if (class_exists('\MHMRentiva\Admin\Settings\View\SettingsViewHelper')) {
            \MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly(self::SECTION_TRANSFER);
        }
    }

    /**
     * Register transfer settings
     */
    public static function register(): void
    {
        $page_slug = SettingsCore::PAGE;

        add_settings_section(
            self::SECTION_TRANSFER,
            __('Transfer Configuration', 'mhm-rentiva'),
            fn() => print('<p class="description">' . esc_html__('Configure payment types and custom location categories for the transfer system.', 'mhm-rentiva') . '</p>'),
            $page_slug
        );

        SettingsHelper::select_field(
            $page_slug,
            'mhm_transfer_deposit_type',
            __('Payment Type', 'mhm-rentiva'),
            [
                'full_payment' => __('Full Payment Required', 'mhm-rentiva'),
                'percentage'   => __('Deposit (Percentage)', 'mhm-rentiva'),
            ],
            __('Select how customers should pay for transfers.', 'mhm-rentiva'),
            self::SECTION_TRANSFER
        );

        SettingsHelper::number_field(
            $page_slug,
            'mhm_transfer_deposit_rate',
            __('Deposit Rate (%)', 'mhm-rentiva'),
            1,
            100,
            __('Percentage of total price to be paid as deposit. Default: 20%', 'mhm-rentiva'),
            self::SECTION_TRANSFER
        );

        SettingsHelper::textarea_field(
            $page_slug,
            'mhm_transfer_custom_types',
            __('Custom Location Types', 'mhm-rentiva'),
            5,
            __('Enter custom location types, one per line.', 'mhm-rentiva'),
            self::SECTION_TRANSFER,
            "Stadium\nExhibition Center\nAirport"
        );
    }

    /**
     * Static accessors
     */
    public static function get_deposit_type(): string
    {
        return (string) SettingsCore::get('mhm_transfer_deposit_type', 'full_payment');
    }

    public static function get_deposit_rate(): int
    {
        return (int) SettingsCore::get('mhm_transfer_deposit_rate', 20);
    }
}
