<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Minimal content the shortcodes need before they render their real markup.
 */
final class ShortcodeFixtures
{
	/**
	 * A published vehicle the comparison shortcode counts as comparable:
	 * VehicleComparison::get_vehicle_data() skips anything that is not a
	 * published mhmrentiva_vehicle with status 'active'.
	 */
	public static function vehicle(): int
	{
		$id = wp_insert_post(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Fixture Vehicle ' . wp_generate_password(4, false),
			)
		);

		update_post_meta($id, MetaKeys::VEHICLE_STATUS, 'active');
		update_post_meta($id, '_mhmrentiva_price_per_day', '100');

		return (int) $id;
	}
}
