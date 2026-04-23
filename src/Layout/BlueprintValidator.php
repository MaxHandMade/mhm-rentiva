<?php
declare(strict_types=1);

namespace MHMRentiva\Layout;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Layout\Config\ContractRules;
use WP_Error;



/**
 * Blueprint Validator
 *
 * Validates Layout Manifests against structural requirements and governance rules.
 *
 * @package MHMRentiva\Layout
 * @since 4.14.0
 */
final class BlueprintValidator {

    /**
     * Validates raw manifest data.
     *
     * @param array $manifest Manifest data.
     * @return true|WP_Error
     */
    public function validate(array $manifest)
    {
        // 1. Root structure check
        $required_keys = [ 'version', 'source', 'pages', 'tokens', 'components', 'constraints' ];
        foreach ($required_keys as $key) {
            if (! isset($manifest[ $key ])) {
                return new WP_Error(
                    'mhm_rentiva_invalid_blueprint',
                    /* translators: %s: missing root key in manifest. */
                    sprintf(__('Manifest root key missing: %s', 'mhm-rentiva'), $key)
                );
            }
        }

        // 1.1 Strict Version Check (Phase 1 supports v1.x)
        if (
            version_compare( (string) $manifest['version'], '1.0.0', '<') ||
            version_compare( (string) $manifest['version'], '2.0.0', '>=')
        ) {
            return new WP_Error(
                'mhm_rentiva_unsupported_version',
                /* translators: %s: blueprint version string. */
                sprintf(__('Unsupported blueprint version: %s', 'mhm-rentiva'), $manifest['version'])
            );
        }

        // 2. Forbidden pattern scan (Tailwind leakage)
        // Strictly scan pages and tokens only to avoid self-triggering in constraints definition.
        $scannable_data = [
            'pages'  => $manifest['pages']  ?? [],
            'tokens' => $manifest['tokens'] ?? [],
        ];
        $json_to_scan   = (string) wp_json_encode($scannable_data);

        foreach (ContractRules::FORBIDDEN_PATTERNS as $pattern) {
            if (stripos($json_to_scan, $pattern) !== false) {
                return new WP_Error(
                    'mhm_rentiva_forbidden_pattern',
                    /* translators: %s: forbidden pattern name. */
                    sprintf(__('Forbidden pattern detected in manifest: %s', 'mhm-rentiva'), $pattern)
                );
            }
        }

        // 3. Pages validation
        if (! is_array($manifest['pages']) || empty($manifest['pages'])) {
            return new WP_Error(
                'mhm_rentiva_no_pages',
                __('Manifest contains no pages.', 'mhm-rentiva')
            );
        }

        foreach ($manifest['pages'] as $index => $page) {
            $error = $this->validate_page($page, $index);
            if (is_wp_error($error)) {
                return $error;
            }
        }

        // 4. Components validation
        if (! is_array($manifest['components'])) {
            return new WP_Error(
                'mhm_rentiva_invalid_components',
                __('Manifest components section must be an object/array.', 'mhm-rentiva')
            );
        }

        return true;
    }

    /**
     * Validates a single page entry.
     *
     * @param array $page Page data.
     * @param int   $index Index for error reporting.
     * @return true|WP_Error
     */
    private function validate_page(array $page, int $index): ?WP_Error
    {
        $required_keys = [ 'slug', 'layout', 'composition' ];
        foreach ($required_keys as $key) {
            if (! isset($page[ $key ])) {
                return new WP_Error(
                    'mhm_rentiva_invalid_page',
                    /* translators: 1: page index, 2: missing key. */
                    sprintf(__('Page #%1$d is missing key: %2$s', 'mhm-rentiva'), $index, $key)
                );
            }
        }

        // Validate composition components against allowlist
        if (is_array($page['composition'])) {
            foreach ($page['composition'] as $comp_idx => $instance) {
                $component_id = $instance['component_id'] ?? '';
                if (! $component_id) {
                    continue;
                }

                // Note: Actual component type check happens during import phase against Registry.
                // Here we just ensure basic instance metadata exists.
                if (! isset($instance['instance_id'])) {
                    return new WP_Error(
                        'mhm_rentiva_invalid_instance',
                        /* translators: 1: component index, 2: page index. */
                        sprintf(__('Component instance #%1$d in page #%2$d missing instance_id', 'mhm-rentiva'), $comp_idx, $index)
                    );
                }
            }
        }

        return null;
    }
}
