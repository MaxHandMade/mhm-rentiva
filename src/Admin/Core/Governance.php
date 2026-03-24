<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Governance Enforcement Layer.
 *
 * @package MHMRentiva
 */







/**
 * Class Governance
 *
 * Enforces project-wide governance rules, specifically the "No Tailwind Runtime" policy.
 */
class Governance
{


    /**
     * Forbidden handles that are known to be associated with Tailwind runtime/CDN.
     */
    private const FORBIDDEN_HANDLES = [
        'tailwind',
        'tailwindcss',
        'tailwind-cdn',
        'mhm-tailwind',
    ];

    /**
     * Forbidden domains or URL fragments associated with Tailwind CDN.
     */
    private const FORBIDDEN_URLS = [
        'cdn.tailwindcss.com',
        'unpkg.com/tailwindcss',
    ];

    /**
     * Initialize governance hooks.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enforce_no_tailwind'], 9999);
        add_action('admin_enqueue_scripts', [$this, 'enforce_no_tailwind'], 9999);
    }

    /**
     * Inspects all enqueued styles and scripts and removes any that violate the Tailwind policy.
     *
     * @return void
     */
    public function enforce_no_tailwind(): void
    {
        $this->audit_styles();
        $this->audit_scripts();
    }

    /**
     * Audit enqueued styles.
     *
     * @return void
     */
    private function audit_styles(): void
    {
        global $wp_styles;

        if (!($wp_styles instanceof \WP_Styles)) {
            return;
        }

        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }

            $asset = $wp_styles->registered[$handle];
            if ($this->is_violation($handle, $asset->src)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
                $this->log_violation('style', $handle, $asset->src);
            }
        }
    }

    /**
     * Audit enqueued scripts.
     *
     * @return void
     */
    private function audit_scripts(): void
    {
        global $wp_scripts;

        if (!($wp_scripts instanceof \WP_Scripts)) {
            return;
        }

        foreach ($wp_scripts->queue as $handle) {
            if (!isset($wp_scripts->registered[$handle])) {
                continue;
            }

            $asset = $wp_scripts->registered[$handle];
            if ($this->is_violation($handle, $asset->src)) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
                $this->log_violation('script', $handle, $asset->src);
            }
        }
    }

    /**
     * Checks if a specific handle or source URL violates the Tailwind policy.
     *
     * @param string $handle The asset handle.
     * @param mixed  $src    The asset source URL.
     * @return bool True if it's a violation, false otherwise.
     */
    public function is_violation(string $handle, $src): bool
    {
        // Check handle
        foreach (self::FORBIDDEN_HANDLES as $forbidden) {
            if (stripos($handle, $forbidden) !== false) {
                return true;
            }
        }

        // Check URL
        if (is_string($src)) {
            foreach (self::FORBIDDEN_URLS as $forbidden_url) {
                if (stripos($src, $forbidden_url) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Log a governance violation (Internal Wrapper).
     *
     * @param string $type   The type of asset (style/script).
     * @param string $handle The asset handle.
     * @param mixed  $src    The asset source URL.
     * @return void
     */
    private function log_violation(string $type, string $handle, $src): void
    {
        // Internal Filter Gate: Only log if WP_DEBUG is on and the specific filter is active.
        $logging_enabled = defined('WP_DEBUG') && WP_DEBUG && apply_filters('mhm_rentiva_enable_governance_log', false);

        if ($logging_enabled) {
            error_log(sprintf(
                '[MHM Rentiva Governance] Blocked %s: %s (Source: %s) - No Tailwind Runtime allowed.',
                $type,
                $handle,
                is_string($src) ? $src : 'unknown'
            ));
        }

        /**
         * Action for developers to react to governance violations.
         *
         * @since 1.0.0
         * @param string $type   The type of asset (style/script).
         * @param string $handle The asset handle.
         * @param mixed  $src    The asset source URL.
         */
        do_action('mhm_rentiva_governance_violation', $type, $handle, $src);
    }
}
