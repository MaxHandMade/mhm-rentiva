<?php
declare(strict_types=1);

namespace MHMRentiva\Layout;

if (! defined('ABSPATH')) {
    exit;
}

use MHMUiCore\Layout\LayoutContract;
use MHMUiCore\Layout\LayoutEngine;

/**
 * Layout Engine Factory
 *
 * The blueprint engine lives in mhm/ui-core and knows nothing about this
 * plugin. What it needs from us is a contract: our error-code prefix, our
 * markup prefix, and the component types we can actually render. This class
 * is the one place that contract is written.
 *
 * WHY ONE PLACE AND NOT ONE PER CALLER
 *
 * The adapter map is a correctness-critical fact, not boilerplate. It has
 * FIVE entries, and the two easy-to-miss ones are aliases: `vehicle_slider`
 * renders through VehicleListingAdapter and `testimonials` through
 * ReviewsAdapter. A caller that registers only the three obvious types makes
 * every already-published manifest using either alias fail to import with
 * "no adapter found" -- a failure that appears on a customer's site, not in
 * a gate. Spreading the map across the CLI and half a dozen test set-ups
 * would mean six copies that nothing keeps in agreement; the copy that
 * drifts is the one nobody re-reads.
 *
 * @package MHMRentiva\Layout
 */
final class LayoutEngineFactory {

    /**
     * This plugin's error-code prefix, unchanged from the pre-package engine.
     *
     * @var string
     */
    public const ERROR_PREFIX = 'mhmrentiva';

    /**
     * This plugin's markup prefix. Class names are NOT renamed by the move:
     * shipped pages carry `mhm-` markup and must keep carrying it.
     *
     * @var string
     */
    public const MARKUP_PREFIX = 'mhm';

    /**
     * Build the contract: everything the package needs from this plugin.
     *
     * @return LayoutContract
     */
    public static function contract(): LayoutContract
    {
        return new LayoutContract([
            'error_prefix'  => self::ERROR_PREFIX,
            'markup_prefix' => self::MARKUP_PREFIX,
            'adapters'      => self::adapters(),
        ]);
    }

    /**
     * Build the engine.
     *
     * @return LayoutEngine
     */
    public static function engine(): LayoutEngine
    {
        return new LayoutEngine(self::contract());
    }

    /**
     * The component types this plugin can render, and what renders them.
     *
     * Mirrors what AdapterRegistry::boot_defaults() registered before the
     * engine moved into the package -- all five entries, aliases included.
     *
     * @return array<string, Adapters\BaseAdapter>
     */
    private static function adapters(): array
    {
        return [
            'search_hero'     => new Adapters\SearchHeroAdapter(),
            'vehicle_listing' => new Adapters\VehicleListingAdapter(),
            'vehicle_slider'  => new Adapters\VehicleListingAdapter(),
            'reviews_grid'    => new Adapters\ReviewsAdapter(),
            'testimonials'    => new Adapters\ReviewsAdapter(),
        ];
    }
}
