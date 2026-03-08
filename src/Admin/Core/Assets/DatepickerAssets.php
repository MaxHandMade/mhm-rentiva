<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Assets;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Datepicker Assets Helper
 *
 * Centralizes datepicker script and style enqueuing across all components.
 * Ensures consistent CSS overrides and JS initialization dependencies.
 *
 * @since 4.20.x
 */
final class DatepickerAssets
{
    /**
     * Enqueue datepicker dependencies, custom styles, and init script.
     */
    public static function enqueue(): void
    {
        // 1. Enqueue WordPress Core jQuery UI Datepicker
        wp_enqueue_script('jquery-ui-datepicker');

        // 2. Enqueue Global Datepicker Style (Core Variables)
        wp_enqueue_style('mhm-css-variables');

        // 3. Enqueue Custom CSS Overrides (Glassmorphism / Premium UI)
        wp_enqueue_style(
            'mhm-rentiva-datepicker-custom',
            MHM_RENTIVA_PLUGIN_URL . 'assets/css/frontend/datepicker-custom.css',
            array('mhm-css-variables'),
            MHM_RENTIVA_VERSION
        );

        // 4. Enqueue Centralized Init Script
        wp_enqueue_script(
            'mhm-datepicker-init',
            MHM_RENTIVA_PLUGIN_URL . 'assets/js/core/datepicker-init.js',
            array('jquery', 'jquery-ui-datepicker'),
            MHM_RENTIVA_VERSION,
            true
        );

        // 5. Pass localized datepicker options to JS
        wp_localize_script('mhm-datepicker-init', 'mhmDatepickerL10n', self::get_localized_options());
    }

    /**
     * Get localized datepicker options for jQuery UI Datepicker.
     *
     * @since 4.21.2
     * @return array<string, mixed>
     */
    private static function get_localized_options(): array
    {
        return array(
            'closeText'    => __('Close', 'mhm-rentiva'),
            'currentText'  => __('Today', 'mhm-rentiva'),
            'monthNames'   => array(
                __('January', 'mhm-rentiva'),
                __('February', 'mhm-rentiva'),
                __('March', 'mhm-rentiva'),
                __('April', 'mhm-rentiva'),
                __('May', 'mhm-rentiva'),
                __('June', 'mhm-rentiva'),
                __('July', 'mhm-rentiva'),
                __('August', 'mhm-rentiva'),
                __('September', 'mhm-rentiva'),
                __('October', 'mhm-rentiva'),
                __('November', 'mhm-rentiva'),
                __('December', 'mhm-rentiva'),
            ),
            'dayNamesMin'  => array(
                __('Su', 'mhm-rentiva'),
                __('Mo', 'mhm-rentiva'),
                __('Tu', 'mhm-rentiva'),
                __('We', 'mhm-rentiva'),
                __('Th', 'mhm-rentiva'),
                __('Fr', 'mhm-rentiva'),
                __('Sa', 'mhm-rentiva'),
            ),
            'firstDay'     => 1,
            'isRTL'        => is_rtl(),
        );
    }
}
