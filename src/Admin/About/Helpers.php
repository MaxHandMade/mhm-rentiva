<?php declare(strict_types=1);

namespace MHMRentiva\Admin\About;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper functions class
 */
final class Helpers
{
    /**
     * Render external link
     */
    public static function render_external_link(string $url, string $text, array $attributes = []): string
    {
        $default_attrs = [
            'href' => esc_url($url),
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'class' => 'external-link',
        ];

        $attrs = array_merge($default_attrs, $attributes);

        $attr_string = '';
        foreach ($attrs as $key => $value) {
            $attr_string .= sprintf(' %s="%s"', esc_attr($key), esc_attr($value));
        }

        return sprintf('<a%s>%s</a>', $attr_string, esc_html($text));
    }
}
