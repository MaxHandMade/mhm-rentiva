<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Attribute;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Canonical Attribute Mapper (CAM) Service
 *
 * Orchestrates the normalization and transformation of block attributes
 * ensuring deterministic parity with shortcodes.
 *
 * @package MHMRentiva\Core\Attribute
 * @since 4.11.0
 */
final class CanonicalAttributeMapper {




    /**
     * Maps raw attributes to canonical shortcode attributes.
     *
     * @param string $tag        Shortcode tag.
     * @param array  $attributes Raw input attributes (from block or shortcode).
     * @param bool   $strict     Whether to strip unknown attributes.
     * @return array Mapped and sanitized attributes.
     */
    public static function map(string $tag, array $attributes, bool $strict = true): array
    {
        $schema = AllowlistRegistry::get_schema($tag);
        $mapped = [];

        // 1. Process existing attributes
        foreach ($attributes as $raw_key => $value) {
            $canonical_key = KeyNormalizer::normalize($raw_key, $schema);

            // Strict mode: Drop unknown
            if ($strict && ! isset($schema[ $canonical_key ])) {
                self::log_unknown($tag, $raw_key);
                continue;
            }

            // If verified canonical key exists in schema, transform it
            if (isset($schema[ $canonical_key ])) {
                $config                   = $schema[ $canonical_key ];
                $mapped[ $canonical_key ] = Transformers::transform($value, $config['type'] ?? 'string', $config);
            } else {
                // Passthrough for non-strict or fallback (though generally strict should be on)
                $mapped[ $canonical_key ] = $value;
            }
        }

        // 2. Apply defaults for missing keys
        foreach ($schema as $canonical_key => $config) {
            if (! isset($mapped[ $canonical_key ]) && isset($config['default'])) {
                $mapped[ $canonical_key ] = $config['default'];
            }
        }

        return $mapped;
    }

    /**
     * Logs unknown attributes when in DEBUG mode.
     */
    private static function log_unknown(string $tag, string $key): void
    {
        // Routed to the plugin's own logger instead of the site's PHP error log.
        // Still debug-gated: an unknown attribute is an author typo, not a fault
        // worth recording on every page view of a production site.
        if (defined('WP_DEBUG') && WP_DEBUG && class_exists(\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::class)) {
            \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info(
                'Dropped unknown shortcode attribute',
                array(
                    'tag'       => $tag,
                    'attribute' => $key,
                ),
                \MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::CATEGORY_SYSTEM
            );
        }
    }
}
