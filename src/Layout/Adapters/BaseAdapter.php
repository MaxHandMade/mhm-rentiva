<?php

declare(strict_types=1);

namespace MHMRentiva\Layout\Adapters;

use MHMRentiva\Core\Attribute\CanonicalAttributeMapper;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Base Adapter
 *
 * Abstract foundation for all component adapters.
 *
 * @package MHMRentiva\Layout\Adapters
 * @since 4.14.0
 */
abstract class BaseAdapter
{

    /**
     * Renders the component to WordPress markup (Shortcode or Block).
     *
     * @param array  $attributes Raw attributes from manifest.
     * @param string $instance_id Unique ID for this instance.
     * @return string Rendered markup.
     */
    abstract public function render(array $attributes, string $instance_id): string;

    /**
     * Normalizes attributes using core CAM service.
     *
     * @param string $tag        Shortcode tag.
     * @param array  $attributes Raw attributes.
     * @return array Normalized attributes.
     */
    protected function normalize(string $tag, array $attributes): array
    {
        // Phase 3 LOCKED: Always use CanonicalAttributeMapper
        return CanonicalAttributeMapper::map($tag, $attributes, true);
    }

    /**
     * Formats attributes into a shortcode string.
     *
     * @param string $tag
     * @param array  $atts
     * @return string
     */
    protected function to_shortcode(string $tag, array $atts): string
    {
        $string = '[' . $tag;
        foreach ($atts as $key => $val) {
            $string .= sprintf(' %s="%s"', esc_attr($key), esc_attr((string) $val));
        }
        $string .= ']';
        return $string;
    }
}
