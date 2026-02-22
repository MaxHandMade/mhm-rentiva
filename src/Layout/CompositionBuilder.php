<?php

declare(strict_types=1);

namespace MHMRentiva\Layout;

use MHMRentiva\Layout\Config\ContractRules;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Composition Builder (Hybrid Strategy)
 *
 * Assembles blueprint composition into WordPress-compatible markup.
 * Implements D4 Hybrid Composition: Manifest Meta + Rendered Content.
 *
 * @package MHMRentiva\Layout
 * @since 4.14.0
 */
final class CompositionBuilder
{
    /**
     * Builds the final post content markup from blueprint composition.
     *
     * @param array $manifest Full blueprint manifest.
     * @param array $page     Specific page entry from manifest.
     * @return string|WP_Error Rendered markup.
     */
    public function build(array $manifest, array $page)
    {
        $markup = '';
        $composition = $page['composition'] ?? [];
        $components_map = $manifest['components'] ?? [];

        foreach ($composition as $instance) {
            $component_id = $instance['component_id'] ?? '';
            $instance_id  = $instance['instance_id'] ?? '';
            $attributes   = $instance['attributes'] ?? [];

            $component_config = $components_map[$component_id] ?? null;
            if (! $component_config) {
                return new WP_Error(
                    'mhm_rentiva_unknown_component',
                    /* translators: %s: unknown component ID. */
                    sprintf(__('Unknown component reference: %s', 'mhm-rentiva'), $component_id)
                );
            }

            $type = $component_config['type'] ?? '';

            // 1. Get adapter from registry
            $adapter = AdapterRegistry::get_adapter($type);
            if (! $adapter) {
                return new WP_Error(
                    'mhm_rentiva_missing_adapter',
                    /* translators: %s: missing adapter type name. */
                    sprintf(__('No adapter found for component type: %s', 'mhm-rentiva'), $type)
                );
            }

            // 2. Render component via adapter
            $component_markup = $adapter->render($attributes, $instance_id);

            // 3. Wrap in layout container if defined in contract
            // For Phase 1, we use a simple div wrapper that follows MHM CSS standards.
            $markup .= sprintf(
                '<div class="mhm-layout-component" data-component-type="%s" data-instance-id="%s">%s</div>' . PHP_EOL,
                esc_attr($type),
                esc_attr($instance_id),
                $component_markup
            );
        }

        // 4. Final Sanity Scan (Tailwind Prohibition Gate)
        $markup_error = $this->scan_for_prohibited_patterns($markup);
        if (is_wp_error($markup_error)) {
            return $markup_error;
        }

        // 5. Apply Design Tokens (Phase 2)
        $tokens = $manifest['tokens'] ?? [];
        $token_mapper = new TokenMapper();
        $token_styles = $token_mapper->map_to_style_string($tokens);

        // Wrap in a root layout container that carries the design tokens
        return sprintf(
            '<div class="mhm-layout-root" style="%s">%s</div>',
            esc_attr($token_styles),
            $markup
        );
    }

    /**
     * Scans rendered markup for prohibited patterns (Tailwind strings and raw framework artifacts).
     *
     * @param string $markup Rendered markup.
     * @return true|WP_Error
     */
    private function scan_for_prohibited_patterns(string $markup)
    {
        // 1. Static Forbidden Patterns (Tailwind, etc.)
        $forbidden = ContractRules::FORBIDDEN_PATTERNS;
        foreach ($forbidden as $pattern) {
            if (stripos($markup, $pattern) !== false) {
                return new WP_Error(
                    'mhm_rentiva_tailwind_leakage',
                    /* translators: %s: forbidden pattern found in rendered markup. */
                    sprintf(__('Tailwind leakage detected in rendered markup: %s', 'mhm-rentiva'), $pattern)
                );
            }
        }

        // 2. Class Attribute Governance (Chief Engineer request for wider coverage)
        // Check for common non-mhm utility abbreviations if they appear in class=""
        // We use negative lookbehind to ensure we don't block classes already prefixed with mhm-
        if (preg_match('/class=["\'][^"\']*(?<!\bmhm-)\b(bg-|p-|m-|flex-|grid-|w-)([a-z0-9-]+)[^"\']*["\']/', $markup, $matches)) {
            return new WP_Error(
                'mhm_rentiva_utility_leakage',
                /* translators: %s: utility class fragment. */
                sprintf(__('Unprefixed utility class detected: %s', 'mhm-rentiva'), $matches[1] . $matches[2])
            );
        }

        return true;
    }
}
