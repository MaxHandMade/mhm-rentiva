<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Vehicle Comparison Shortcode
 *
 * Comparison table for multiple vehicles
 *
 * Usage: [rentiva_vehicle_comparison vehicle_ids="123,456,789" max_vehicles="3"]
 *
 * The rows are the admin's comparison_fields selection (Field Definitions), not
 * per-instance toggles. What the template prints comes from ComparisonView.
 */
final class VehicleComparison extends AbstractShortcode {




	public const SHORTCODE = 'rentiva_vehicle_comparison';

	/**
	 * Rows a site compares until the admin saves a selection of their own (also what the Display
	 * tab's reset writes). Every key is Active on a default install.
	 */
	public const DEFAULT_FIELDS = array(
		'details' => array( 'price_per_day', 'brand', 'model', 'fuel_type', 'transmission', 'seats', 'year', 'daily_km_limit', 'min_driver_age', 'min_license_years', 'vehicle_max_big_luggage', 'deposit' ),
	);

	/**
	 * The comparison selection in effect: the saved one, or DEFAULT_FIELDS when the site has
	 * never saved one. A saved empty selection stays empty (the admin chose to compare nothing).
	 * The frontend table and the admin screen both read this, so they cannot disagree.
	 *
	 * @return array<string,mixed> category => key list
	 */
	public static function selected_fields_map(): array
	{
		$settings = (array) get_option('mhmrentiva_settings', array());
		if (! array_key_exists('comparison_fields', $settings)) {
			return self::DEFAULT_FIELDS;
		}

		return is_array($settings['comparison_fields']) ? $settings['comparison_fields'] : array();
	}

	public static function register(): void
	{
		parent::register();

		// DEPRECATED: Standardizing on CompareService::ajax_toggle_compare to avoid split-brain.
	}

	protected static function get_shortcode_tag(): string
	{
		return 'rentiva_vehicle_comparison';
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/vehicle-comparison';
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'vehicle_ids'          => '', // Vehicle IDs (comma-separated)
			'max_vehicles'         => '3', // Maximum number of vehicles
			'show_remove_buttons'  => '1', // Show remove buttons
			'show_prices'          => '1', // Show prices
			'show_images'          => '1', // Show vehicle images
			'show_booking_buttons' => '1', // Show booking buttons
			'layout'               => 'table', // table, cards
			'title'                => '', // Custom title
			'class'                => '', // Custom CSS class
		);
	}

	protected static function get_css_filename(): string
	{
		return 'vehicle-comparison.css';
	}

	protected static function get_js_filename(): string
	{
		return 'vehicle-comparison.js';
	}

	/**
	 * Enqueue assets
	 */
	protected static function enqueue_assets(array $atts = array()): void
	{
		// Call parent method (enqueue_styles and enqueue_scripts from AbstractShortcode)
		parent::enqueue_assets();

		// Localize script — this is the effective runtime config the front-end script reads.
		self::localize_script(self::get_asset_handle());
	}

	protected static function get_asset_handle(): string
	{
		return 'mhm-rentiva-vehicle-comparison';
	}

	protected static function get_script_object_name(): string
	{
		return 'mhmRentivaVehicleComparison';
	}

	protected static function get_localized_data(): array
	{
		return array(
			'ajax_url'                   => admin_url('admin-ajax.php'),
			'toggle_nonce'               => \MHMRentiva\Frontend\RenderContext::script_nonce('mhmrentiva_toggle_compare'),
			'loading'                    => __('Loading...', 'mhm-rentiva'),
			'error'                      => __('An error occurred', 'mhm-rentiva'),
			'vehicleAdded'               => __('Vehicle added to comparison', 'mhm-rentiva'),
			'vehicleRemoved'             => __('Vehicle removed from comparison', 'mhm-rentiva'),
			'maxVehiclesReached'         => __('Maximum vehicle count reached', 'mhm-rentiva'),
			'noVehiclesToCompare'        => __('No vehicles to compare', 'mhm-rentiva'),
			'addVehicle'                 => __('Add Vehicle', 'mhm-rentiva'),
			'removeVehicle'              => __('Remove', 'mhm-rentiva'),
			'bookNow'                    => __('Book Now', 'mhm-rentiva'),
			'one_vehicle_compared'       => __('1 vehicle being compared', 'mhm-rentiva'),
			/* translators: %d: number of vehicles */
			'multiple_vehicles_compared' => __('%d vehicles being compared', 'mhm-rentiva'),
		);
	}


	protected static function prepare_template_data(array $atts): array
	{
		$vehicle_ids = self::parse_vehicle_ids($atts['vehicle_ids'] ?? '');
		$fixed       = array() !== $vehicle_ids;

		// An editor preview without explicit vehicle_ids shows sample vehicles,
		// never the editing user's own comparison list (spec 3B-2a §2.1). Explicit
		// ids are the editor's choice: those vehicles, no badge.
		$sample = ! $fixed && \MHMRentiva\Frontend\RenderContext::current()->allows_sample();
		if ($sample) {
			$vehicle_ids = \MHMRentiva\Frontend\PreviewSamples::vehicle_ids(3);
		} elseif (! $fixed) {
			// Canonical Source of Truth: the visitor's own list.
			$vehicle_ids = \MHMRentiva\Admin\Services\CompareService::get_list();
		}

		$view = ComparisonView::build($atts, $vehicle_ids, $fixed || $sample);

		$data = array(
			'atts' => $atts,
			'view' => $view,
		);

		// Too few sample vehicles: the template's own empty state, without a badge.
		if ($sample && $view['has_vehicles']) {
			$data[ self::PREVIEW_BADGE_KEY ] = __('Preview: sample data. Logged-in customers see their own records.', 'mhm-rentiva');
		}

		return $data;
	}

	/**
	 * Parse vehicle IDs string
	 */
	private static function parse_vehicle_ids(string $ids_string): array
	{
		if (empty($ids_string)) {
			return array();
		}

		$ids = explode(',', $ids_string);
		$ids = array_map('intval', $ids);
		$ids = array_filter(
			$ids,
			function ($id) {
				return $id > 0;
			}
		);

		return array_values(array_unique($ids));
	}
}
