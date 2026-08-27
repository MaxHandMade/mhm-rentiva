<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Config;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Contract Governance Rules
 *
 * Internal source of truth for allowed UI contracts and tokens.
 *
 * @package MHMRentiva\Layout\Config
 * @since 4.14.1
 */
class ContractRules {

    /**
     * Allowed wrapper component types.
     */
    public const ALLOWED_WRAPPERS = [
        'layout_hero',
        'layout_section',
        'layout_grid',
        'layout_container',
    ];

    /**
     * Allowed functional component types (Adapters required).
     */
    public const ALLOWED_COMPONENTS = [
        'search_hero',
        'vehicle_listing',
        'featured_vehicles',
        'reviews_grid',
        'testimonials_slider',
    ];

    /**
     * Required design tokens for "home-like" pages.
     */
    public const REQUIRED_HOME_TOKENS = [
        'brand_primary',
        'surface_background',
        'text_primary',
    ];

    /**
     * Allowed slot patterns (references to UI_CONTRACTS).
     */
    public const ALLOWED_SLOTS = [
        'hero_content',
        'main_content',
        'sidebar_content',
        'footer_content',
    ];

    /**
     * Forbidden patterns (Tailwind / Framework-leakage).
     */
    public const FORBIDDEN_PATTERNS = [
        'tw-',
        'tailwind',
        'cdn.tailwindcss.com',
        'antialiased',
        'flex-1',
    ];

    /**
     * Design Token Mapping (Source -> Target CSS Variable).
     * Based on D2 - Token Mapping Specification.
     */
    public const TOKEN_MAPPING = [
        // Namespaced `--mhm-bp-*` so a blueprint cannot write the names the rest
        // of the product reads. These land as an inline style on
        // .mhm-layout-root, and an inline custom property beats an inherited one
        // on every descendant -- so before this rename a published blueprint
        // silently redefined the palette for its whole subtree. It looked
        // harmless only because every component stylesheet happened to
        // re-declare the same names on itself; deleting those copies would have
        // handed the blueprint the palette.
        'colors.primary'    => '--mhm-bp-primary',
        'colors.secondary'  => '--mhm-bp-secondary',
        'colors.text'       => '--mhm-bp-text-primary',
        'colors.background' => '--mhm-bp-bg-main',
        'colors.surface'    => '--mhm-bp-bg-soft',
        'colors.accent'     => '--mhm-bp-accent',
        'spacing.unit'      => '--mhm-bp-spacing-base',
        'radius.main'       => '--mhm-bp-border-radius',
        'fonts.body'        => '--mhm-bp-font-family',
    ];

    /**
     * Returns the full permit configuration.
     *
     * @return array
     */
    public static function get_config(): array
    {
        return [
            'wrappers'   => self::ALLOWED_WRAPPERS,
            'components' => self::ALLOWED_COMPONENTS,
            'slots'      => self::ALLOWED_SLOTS,
            'forbidden'  => self::FORBIDDEN_PATTERNS,
        ];
    }

    /**
     * Validates if a component type is allowed.
     *
     * @param string $type Component type.
     * @return bool
     */
    public static function is_component_allowed(string $type): bool
    {
        $all_allowed = array_merge(self::ALLOWED_COMPONENTS, self::ALLOWED_WRAPPERS);
        return in_array($type, $all_allowed, true);
    }
}
