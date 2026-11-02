<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes\Card;

use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * The suggested vehicles Util::check_availability_with_alternatives() returns
 * for a taken window, as the booking form and the vehicle page show them.
 */
final class AlternativeVehicles {

	/**
	 * Each suggestion gains its vehicle page with the window (`permalink`,
	 * VehicleCardLinks::details_url()) and the rvs card's specs line (`specs`:
	 * seats · transmission · fuel, translated; else the first three features).
	 *
	 * @param array<int, array<string, mixed>> $alternatives
	 * @param array<string, string>            $window Logical key => value (VehicleCardLinks::FORWARD_KEYS).
	 * @return array<int, array<string, mixed>>
	 */
	public static function enrich(array $alternatives, array $window): array
	{
		foreach ($alternatives as $index => $alternative) {
			$alt_id                              = (int) ( $alternative['id'] ?? 0 );
			$alternatives[ $index ]['permalink'] = VehicleCardLinks::details_url($alt_id, $window);
			$alternatives[ $index ]['specs']     = RvsCardView::specs($alt_id, VehiclesList::get_limited_features($alt_id, 3));
		}
		return $alternatives;
	}
}
