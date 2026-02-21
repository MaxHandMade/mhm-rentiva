<?php

declare(strict_types=1);

namespace MHMRentiva\Layout;

use MHMRentiva\Layout\Config\ContractRules;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Token Mapper
 *
 * Translates blueprint tokens into MHM-standard CSS variables.
 * Implements D2 Governance: No global explosion, deterministic fallbacks.
 *
 * @package MHMRentiva\Layout
 * @since 4.15.0
 */
final class TokenMapper
{
    /**
     * Maps manifest tokens to a CSS inline style string.
     *
     * @param array $tokens Raw tokens from blueprint manifest.
     * @return string Sanity-checked CSS variable string (e.g., "--mhm-primary:#000;").
     */
    public function map_to_style_string(array $tokens): string
    {
        $style_rules = [];
        $mapping     = ContractRules::TOKEN_MAPPING;

        foreach ($mapping as $source_key => $target_var) {
            $value = $this->resolve_token_value($tokens, $source_key);

            if ($value) {
                // Sanitize value for CSS (allow hex, rem, px, simple strings)
                $sanitized_value = $this->sanitize_css_value($value);
                if ($sanitized_value) {
                    $style_rules[] = sprintf('%s: %s;', $target_var, $sanitized_value);
                }
            }
        }

        return implode(' ', $style_rules);
    }

    /**
     * Resolves a token value using dot notation for nested arrays.
     *
     * @param array  $tokens Manifest tokens.
     * @param string $path   Dot-notated path.
     * @return mixed|null
     */
    private function resolve_token_value(array $tokens, string $path)
    {
        $keys = explode('.', $path);
        $current = $tokens;

        foreach ($keys as $key) {
            if (! isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Basic sanitization for CSS values.
     * Blocks potentially harmful injections or framework leakage.
     *
     * @param mixed $value Raw value.
     * @return string|null
     */
    private function sanitize_css_value($value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = (string) $value;

        // Block internal framework references
        if (stripos($value, 'tailwind') !== false || stripos($value, 'tw-') !== false) {
            return null;
        }

        // Allow basic CSS patterns (colors, units, inherit)
        // Regex: Simple hex, rgb, rgba, px, rem, em, %, or standard font family names.
        if (preg_match('/^#[a-fA-F0-9]{3,8}$|^rgba?\(.*\)$|^[0-9.]+(px|rem|em|%|vh|vw|ch)?$|^[a-zA-Z0-9\s,\-\'"]+$/', $value)) {
            return esc_attr($value);
        }

        return null;
    }
}
