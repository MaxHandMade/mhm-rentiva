<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Growth;

use MHMRentiva\Admin\Growth\FunnelAnalyticsService;
use WP_UnitTestCase;

final class FunnelAnalyticsServiceTest extends WP_UnitTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		delete_option('mhm_rentiva_upgrade_funnel_stats');
	}

	protected function tearDown(): void
	{
		delete_option('mhm_rentiva_upgrade_funnel_stats');
		parent::tearDown();
	}

	public function test_get_last_30_days_returns_daily_aggregated_rows(): void
	{
		$today = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
		$day_1 = $today->format('Y-m-d');
		$day_2 = $today->sub(new \DateInterval('P1D'))->format('Y-m-d');

		update_option(
			'mhm_rentiva_upgrade_funnel_stats',
			array(
				$day_1 => array(
					'license_page_view_lite' => 12,
					'upgrade_cta_click_license_page' => 3,
				),
				$day_2 => array(
					'license_page_view_lite' => 8,
					'upgrade_cta_click_license_page' => 2,
				),
			),
			false
		);

		$service = new FunnelAnalyticsService();
		$rows = $service->get_last_30_days();

		$this->assertCount(2, $rows);
		$this->assertSame($day_1, (string) $rows[0]['date']);
		$this->assertSame(12, (int) $rows[0]['views']);
		$this->assertSame(3, (int) $rows[0]['clicks']);
		$this->assertEqualsWithDelta(0.25, (float) $rows[0]['conversion'], 0.0001);
	}

	public function test_get_conversion_rate_returns_ratio_from_last_30_days(): void
	{
		$today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

		update_option(
			'mhm_rentiva_upgrade_funnel_stats',
			array(
				$today => array(
					'license_page_view_lite' => 10,
					'upgrade_cta_click_license_page' => 2,
				),
			),
			false
		);

		$service = new FunnelAnalyticsService();
		$this->assertEqualsWithDelta(0.2, $service->get_conversion_rate(), 0.0001);
	}

	public function test_empty_dataset_returns_zero_totals_and_empty_rows(): void
	{
		delete_option('mhm_rentiva_upgrade_funnel_stats');

		$service = new FunnelAnalyticsService();
		$rows = $service->get_last_30_days();
		$totals = $service->get_totals();

		$this->assertSame(array(), $rows);
		$this->assertSame(0, (int) $totals['views']);
		$this->assertSame(0, (int) $totals['clicks']);
		$this->assertEqualsWithDelta(0.0, (float) $totals['conversion_rate'], 0.0001);
	}
}

