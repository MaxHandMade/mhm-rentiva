<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Licensing;

use MHMRentiva\Admin\Licensing\UpgradeFunnelTelemetry;
use WP_UnitTestCase;

final class UpgradeFunnelTelemetryTest extends WP_UnitTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		delete_option('mhm_rentiva_upgrade_funnel_stats');
		UpgradeFunnelTelemetry::reset_request_guard_for_tests();
	}

	protected function tearDown(): void
	{
		UpgradeFunnelTelemetry::reset_request_guard_for_tests();
		delete_option('mhm_rentiva_upgrade_funnel_stats');
		parent::tearDown();
	}

	public function test_lite_license_page_tracks_view_once_per_request(): void
	{
		do_action('mhm_rentiva_track_upgrade_funnel_event', 'license_page_view_lite');
		do_action('mhm_rentiva_track_upgrade_funnel_event', 'license_page_view_lite');

		$stats = get_option('mhm_rentiva_upgrade_funnel_stats', array());
		$today = gmdate('Y-m-d');

		$this->assertSame(1, (int) ($stats[$today]['license_page_view_lite'] ?? 0));
	}

	public function test_upgrade_cta_click_increments_counter(): void
	{
		do_action('mhm_rentiva_track_upgrade_funnel_event', 'upgrade_cta_click_license_page');

		$stats = get_option('mhm_rentiva_upgrade_funnel_stats', array());
		$today = gmdate('Y-m-d');

		$this->assertSame(1, (int) ($stats[$today]['upgrade_cta_click_license_page'] ?? 0));
	}
}
