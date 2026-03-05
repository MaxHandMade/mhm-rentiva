<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Key Normalizer for Attributes (CAM contract)
 *
 * Canonical Attribute Mapper (CAM) relies on this class as the single source
 * of truth for key normalization.
 *
 * Contract:
 * 1. Explicit alias mapping wins over all fallback logic.
 * 2. If there is no alias match, camelCase inputs are normalized to snake_case.
 * 3. Output is always the canonical key expected by shortcode/registry schemas.
 *
 * This guarantees that block/editor attributes and shortcode attributes converge
 * to one canonical key space without duplicating mapping logic.
 *
 * @package MHMRentiva\Core\Attribute
 * @since 4.11.0
 */
final class KeyNormalizer
{

    /**
     * Normalizes a key to its canonical snake_case format.
     *
     * @param string $key    The raw key.
     * @param array  $schema The attribute schema for the tag.
     * @return string Normalized key.
     */
    public static function normalize(string $key, array $schema = []): string
    {
        // 1) Explicit alias resolution (highest priority in CAM contract)
        foreach ($schema as $canonical_key => $config) {
            $aliases = $config['aliases'] ?? [];
            if (in_array($key, $aliases, true)) {
                return $canonical_key;
            }
        }

        // 2) Fallback: camelCase -> snake_case canonicalization
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
    }
}
