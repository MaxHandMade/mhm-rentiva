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
    }
}
