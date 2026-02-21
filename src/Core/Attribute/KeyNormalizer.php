<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Key Normalizer for Attributes
 *
 * Handles conversion between camelCase (Gutenberg) and snake_case (Shortcode).
 * Also handles explicit alias resolution.
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
        // 1. Resolve explicit aliases from schema
        foreach ($schema as $canonical_key => $config) {
            $aliases = $config['aliases'] ?? [];
            if (in_array($key, $aliases, true)) {
                return $canonical_key;
            }
        }

        // 2. Fallback: Convert camelCase to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
    }
}
