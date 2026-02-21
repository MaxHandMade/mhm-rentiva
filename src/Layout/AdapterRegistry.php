<?php

declare(strict_types=1);

namespace MHMRentiva\Layout;

use MHMRentiva\Layout\Adapters\BaseAdapter;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adapter Registry
 *
 * Manages the mapping between blueprint component types and their renderers.
 *
 * @package MHMRentiva\Layout
 * @since 4.14.0
 */
final class AdapterRegistry
{

    /**
     * @var array Registry of type => adapter_class
     */
    private static array $registry = [];

    /**
     * Registers an adapter for a component type.
     *
     * @param string $type
     * @param string $class_name
     */
    public static function register(string $type, string $class_name): void
    {
        if (is_subclass_of($class_name, BaseAdapter::class)) {
            self::$registry[$type] = $class_name;
        }
    }

    /**
     * Gets an adapter instance for a component type.
     *
     * @param string $type
     * @return BaseAdapter|null
     */
    public static function get_adapter(string $type): ?BaseAdapter
    {
        if (! isset(self::$registry[$type])) {
            return null;
        }

        $class = self::$registry[$type];
        return new $class();
    }

    /**
     * Boots default adapters for Phase 1.
     */
    public static function boot_defaults(): void
    {
        self::register('search_hero', \MHMRentiva\Layout\Adapters\SearchHeroAdapter::class);
        self::register('vehicle_listing', \MHMRentiva\Layout\Adapters\VehicleListingAdapter::class);
        self::register('vehicle_slider', \MHMRentiva\Layout\Adapters\VehicleListingAdapter::class);
        self::register('reviews_grid', \MHMRentiva\Layout\Adapters\ReviewsAdapter::class);
        self::register('testimonials', \MHMRentiva\Layout\Adapters\ReviewsAdapter::class);
    }
}
