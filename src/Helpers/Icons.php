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
class Icons
{

    /**
     * Get SVG iconic markup
     *
     * @param string $icon_name Icon identifier.
     * @param array  $args      Optional arguments for style, width, height, etc.
     * @return string SVG HTML markup.
     */
    public static function get(string $icon_name, array $args = []): string
    {
        $requested_icon_name = $icon_name;
        $icon_aliases        = [
            'people' => 'users',
        ];

        if (isset($icon_aliases[$icon_name])) {
            $icon_name = $icon_aliases[$icon_name];
        }

        $default_args = [
            'class'  => 'rv-icon',
            'width'  => '20px',
            'height' => '20px',
            'style'  => '',
        ];

        $args = array_merge($default_args, $args);

        // Combine classes
        $classes = trim($args['class'] . ' rv-icon-' . $requested_icon_name);

        // Keep alias and canonical icon classes for backward compatibility.
        if ($requested_icon_name !== $icon_name) {
            $classes .= ' rv-icon-' . $icon_name;
        }

        // Base SVG attributes
        $attrs = sprintf(
            'class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" overflow="visible" style="width: %s; height: %s; %s"',
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
                return sprintf('<svg %s><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1"/><circle cx="17" cy="9" r="2.5"/><path d="M14.5 20v-1a4.5 4.5 0 0 1 4.5-4.5"/></svg>', $attrs);

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

            case 'email':
                return sprintf('<svg %s><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>', $attrs);

            case 'phone':
                return sprintf('<svg %s><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>', $attrs);

            case 'success': // Check circle
                return sprintf('<svg %s><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>', $attrs);

            case 'warning': // Alert triangle
                return sprintf('<svg %s><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>', $attrs);

            case 'flag': // Priority
                return sprintf('<svg %s><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>', $attrs);

            case 'building': // Company
                return sprintf('<svg %s><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="22" x2="9" y2="18"/><line x1="15" y1="22" x2="15" y2="18"/><line x1="12" y1="2" x2="12" y2="18"/><line x1="4" y1="10" x2="20" y2="10"/><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="14" x2="20" y2="14"/></svg>', $attrs);

            case 'attachment': // Paperclip
                return sprintf('<svg %s><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>', $attrs);

            case 'upload':
                return sprintf('<svg %s><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>', $attrs);

            case 'edit':
                return sprintf('<svg %s><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>', $attrs);

            case 'trash':
                return sprintf('<svg %s><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>', $attrs);

            case 'back-arrow':
                return sprintf('<svg %s><polyline points="15 18 9 12 15 6"/></svg>', $attrs);

            case 'spinner':
                return sprintf('<svg %s><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg>', $attrs);

            case 'fuel':
                return sprintf('<svg %s><line x1="3" y1="22" x2="15" y2="22"/><line x1="4" y1="9" x2="14" y2="9"/><path d="M14 22V4a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v18"/><path d="M18 13h1a2 2 0 0 0 2-2V8"/><path d="M18 22v-5"/><path d="M22 13v9"/></svg>', $attrs);

            case 'bolt':
                return sprintf('<svg %s><path d="M13 2L4 14h6l-1 8 9-12h-6z"/></svg>', $attrs);

            case 'gear':
                return sprintf('<svg %s><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M19.07 4.93l-1.41 1.41"/><path d="M6.34 17.66l-1.41 1.41"/></svg>', $attrs);

            case 'speedometer':
                return sprintf('<svg %s><path d="M12 2v2"/><path d="M12 20v2"/><path d="M20 12h2"/><path d="M2 12h2"/><path d="M19.07 4.93l-1.41 1.41"/><path d="M6.34 17.66l-1.41 1.41"/><path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M12 12l4 4"/></svg>', $attrs);

            case 'info':
                return sprintf('<svg %s><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>', $attrs);

            case 'error':
                return sprintf('<svg %s><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>', $attrs);

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
