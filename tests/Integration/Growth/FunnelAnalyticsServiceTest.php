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

	public function test_get_variant_breakdown_aggregates_views_clicks_and_conversion(): void
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
					'variant' => array(
						'A' => array('views' => 7, 'clicks' => 2),
						'B' => array('views' => 5, 'clicks' => 1),
					),
				),
				$day_2 => array(
					'license_page_view_lite' => 8,
					'upgrade_cta_click_license_page' => 2,
					'variant' => array(
						'A' => array('views' => 3, 'clicks' => 1),
						'B' => array('views' => 5, 'clicks' => 1),
					),
				),
			),
			false
		);

		$service = new FunnelAnalyticsService();
		$breakdown = $service->get_variant_breakdown();

		$this->assertSame(10, (int) $breakdown['A']['views']);
		$this->assertSame(3, (int) $breakdown['A']['clicks']);
		$this->assertEqualsWithDelta(0.3, (float) $breakdown['A']['conversion'], 0.0001);

		$this->assertSame(10, (int) $breakdown['B']['views']);
		$this->assertSame(2, (int) $breakdown['B']['clicks']);
		$this->assertEqualsWithDelta(0.2, (float) $breakdown['B']['conversion'], 0.0001);
	}

	public function test_get_variant_breakdown_handles_legacy_daily_data_without_variant_bucket(): void
	{
		$today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
		update_option(
			'mhm_rentiva_upgrade_funnel_stats',
			array(
				$today => array(
					'license_page_view_lite' => 5,
					'upgrade_cta_click_license_page' => 1,
				),
			),
			false
		);

		$service = new FunnelAnalyticsService();
		$breakdown = $service->get_variant_breakdown();

		$this->assertSame(0, (int) $breakdown['A']['views']);
		$this->assertSame(0, (int) $breakdown['A']['clicks']);
		$this->assertEqualsWithDelta(0.0, (float) $breakdown['A']['conversion'], 0.0001);
		$this->assertSame(0, (int) $breakdown['B']['views']);
		$this->assertSame(0, (int) $breakdown['B']['clicks']);
		$this->assertEqualsWithDelta(0.0, (float) $breakdown['B']['conversion'], 0.0001);
	}

	public function test_get_variant_breakdown_returns_stable_zero_shape_when_dataset_empty(): void
	{
		delete_option('mhm_rentiva_upgrade_funnel_stats');

		$service = new FunnelAnalyticsService();
		$breakdown = $service->get_variant_breakdown();

		$this->assertSame(array('A', 'B'), array_keys($breakdown));
		$this->assertSame(0, (int) $breakdown['A']['views']);
		$this->assertSame(0, (int) $breakdown['A']['clicks']);
		$this->assertEqualsWithDelta(0.0, (float) $breakdown['A']['conversion'], 0.0001);
		$this->assertSame(0, (int) $breakdown['B']['views']);
		$this->assertSame(0, (int) $breakdown['B']['clicks']);
		$this->assertEqualsWithDelta(0.0, (float) $breakdown['B']['conversion'], 0.0001);
	}
}
