<?php

declare(strict_types=1);

namespace MHMRentiva\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Icons Helper Class
 *
 * Centralized registry for all SVG icons used in the frontend.
 * Ensures cross-browser consistency and removes Dashicon dependency.
 *
 * @since 1.2.0
 */
class Icons {

    /**
     * Get SVG iconic markup
     *
     * @param string $icon_name Icon identifier.
     * @param array  $args      Optional arguments for style, width, height, etc.
     * @return string SVG HTML markup.
     */
    public static function get(string $icon_name, array $args = []): string
    {
        $default_args = [
            'class'  => 'rv-icon',
            'width'  => '16px',
            'height' => '16px',
            'style'  => '',
        ];

        $args = array_merge($default_args, $args);

        // Combine classes
        $classes = trim($args['class'] . ' rv-icon-' . $icon_name);

        // Base SVG attributes
        $attrs = sprintf(
            'class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: %s; height: %s; %s"',
            esc_attr($classes),
            esc_attr($args['width']),
            esc_attr($args['height']),
            esc_attr($args['style'])
        );

        switch ($icon_name) {
            case 'car':
                return sprintf('<svg %s><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 13.1V16c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/></svg>', $attrs);

            case 'compare': // Shuffle
                return sprintf('<svg %s><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>', $attrs);

            case 'remove': // X icon
                return sprintf('<svg %s><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>', $attrs);

            case 'chevron-down':
                return sprintf('<svg %s><polyline points="6 9 12 15 18 9"/></svg>', $attrs);

            case 'chevron-left':
                return sprintf('<svg %s><polyline points="15 18 9 12 15 6"/></svg>', $attrs);

            case 'chevron-right':
                return sprintf('<svg %s><polyline points="9 18 15 12 9 6"/></svg>', $attrs);

            case 'heart': // Favorite
                return sprintf('<svg %s><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>', $attrs);

            case 'location':
                return sprintf('<svg %s><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>', $attrs);

            case 'calendar':
                return sprintf('<svg %s><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>', $attrs);

            case 'clock':
                return sprintf('<svg %s><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>', $attrs);

            case 'search':
                return sprintf('<svg %s><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>', $attrs);

            case 'users':
                return sprintf('<svg %s><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', $attrs);

            case 'luggage': // Portfolio / Bag
                return sprintf('<svg %s><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>', $attrs);

            case 'star':
                return sprintf('<svg %s style="fill: currentColor;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>', $attrs);

            case 'table': // Forms/List
                return sprintf('<svg %s><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/></svg>', $attrs);

            case 'quote':
                return sprintf('<svg %s><path d="M3 21c3 0 7-1 7-8V5c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h4c0 3.5-3.5 4.5-3.5 4.5"/><path d="M13 21c3 0 7-1 7-8V5c0-1.1-.9-2-2-2h-4c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h4c0 3.5-3.5 4.5-3.5 4.5"/></svg>', $attrs);

            case 'refresh': // Update/Spinner
                return sprintf('<svg %s><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>', $attrs);

            case 'lock':
                return sprintf('<svg %s><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>', $attrs);

            default:
                return '';
        }
    }

    /**
     * Render and output SVG directly
     */
    public static function render(string $icon_name, array $args = []): void
    {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self::get() returns internal hardcoded SVG markup and escapes dynamic attributes.
        echo self::get($icon_name, $args);
    }
}
