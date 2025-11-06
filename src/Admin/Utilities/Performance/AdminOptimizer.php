<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Utilities\Performance;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies lightweight optimizations only on Rentiva admin screens.
 * Previous version's aggressive interventions (Gutenberg/REST blocking etc.)
 * have been removed; thus the default WordPress flow continues without disruption.
 */
final class AdminOptimizer
{
    public static function register(): void
    {
        // Only register if we're in admin area
        if (!is_admin()) {
            return;
        }
        
        add_action('admin_enqueue_scripts', [self::class, 'maybe_optimize_vehicle_editor'], 20);
    }

    /**
     * Remove unnecessary admin pointers on vehicle/booking editing screens.
     */
    public static function maybe_optimize_vehicle_editor(): void
    {
        if (!self::is_vehicle_editor_screen()) {
            return;
        }

        // Light optimization: only disable pointer documents
        wp_dequeue_script('wp-pointer');
        wp_dequeue_style('wp-pointer');
        
        // Additional lightweight optimizations for Rentiva screens
        self::apply_lightweight_optimizations();
    }
    
    /**
     * Apply additional lightweight optimizations for Rentiva admin screens.
     */
    private static function apply_lightweight_optimizations(): void
    {
        // Remove unnecessary admin notices that might clutter the interface
        remove_action('admin_notices', 'wp_print_media_templates');
        
        // Disable some unnecessary admin scripts on Rentiva screens
        wp_dequeue_script('heartbeat');
        wp_dequeue_script('autosave');
    }

    private static function is_vehicle_editor_screen(): bool
    {
        if (!is_admin() || !function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        // Check if it's a Rentiva admin screen
        if (!empty($screen->id) && strpos($screen->id, 'mhm-rentiva') !== false) {
            return true;
        }

        // Check if it's a Rentiva post type editor
        $post_type = $screen->post_type ?? null;
        return in_array($post_type, ['vehicle', 'vehicle_booking', 'vehicle_addon'], true);
    }
}
