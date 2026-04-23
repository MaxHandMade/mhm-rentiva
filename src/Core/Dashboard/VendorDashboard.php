<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Vendor dashboard renderer.
 */
final class VendorDashboard {

	/**
	 * Render vendor dashboard template.
	 *
	 * @param array<string, mixed> $data Dashboard template data.
	 */
	public static function render(array $dashboard_data): string
	{
		ob_start();

		include MHM_RENTIVA_PLUGIN_PATH . 'templates/account/user-dashboard.php';

		return (string) ob_get_clean();
	}
}
