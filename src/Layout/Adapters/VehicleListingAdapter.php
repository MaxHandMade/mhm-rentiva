<?php
declare(strict_types=1);

namespace MHMRentiva\Layout\Adapters;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Vehicle Listing Adapter
 *
 * Maps vehicle grid/list/slider components to the Featured Vehicles shortcode.
 *
 * @package MHMRentiva\Layout\Adapters
 * @since 4.14.0
 */
final class VehicleListingAdapter extends BaseAdapter
{
    /**
     * @var string Target shortcode tag.
     */
    private const TAG = 'rentiva_featured_vehicles';

    /**
     * Renders the component to WordPress shortcode markup.
     *
     * @param array  $attributes Raw attributes from manifest.
     * @param string $instance_id Unique ID for this instance.
     * @return string Rendered shortcode.
     */
    public function render(array $attributes, string $instance_id): string
    {
        // 1. Normalize attributes through CAM
        $normalized = $this->normalize(self::TAG, $attributes);

        // 2. Add layout tracking
        $normalized['layout_id'] = $instance_id;

        // 3. Convert to shortcode string
        return $this->to_shortcode(self::TAG, $normalized);
    }
}
