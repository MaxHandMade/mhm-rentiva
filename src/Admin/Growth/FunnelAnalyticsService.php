<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Growth;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Read-only analytics adapter for S22 upgrade funnel telemetry.
 */
final class FunnelAnalyticsService
{
	private const OPTION = 'mhm_rentiva_upgrade_funnel_stats';
	private const EVENT_VIEW = 'license_page_view_lite';
	private const EVENT_CLICK = 'upgrade_cta_click_license_page';

	/**
	 * @return array<int, array<string, float|int|string>>
	 */
	public function get_last_30_days(): array
	{
		return $this->get_daily_stats(30);
	}

	/**
	 * @return array<int, array<string, float|int|string>>
	 */
	public function get_daily_stats(int $days = 30): array
	{
		$days = max(1, $days);
		$raw_stats = get_option(self::OPTION, array());
		if (! is_array($raw_stats)) {
			return array();
		}

		$timezone = new \DateTimeZone('UTC');
		$today = new \DateTimeImmutable('now', $timezone);
		$from = $today->sub(new \DateInterval('P' . ($days - 1) . 'D'));

		$rows = array();
		foreach ($raw_stats as $date => $events) {
			if (! is_string($date) || ! is_array($events)) {
				continue;
			}

			$date_obj = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
			if (! $date_obj instanceof \DateTimeImmutable) {
				continue;
			}

			if ($date_obj < $from || $date_obj > $today) {
				continue;
			}

			$views = (int) ($events[self::EVENT_VIEW] ?? 0);
			$clicks = (int) ($events[self::EVENT_CLICK] ?? 0);

			if ($views <= 0 && $clicks <= 0) {
				continue;
			}

			$rows[] = array(
				'date' => $date,
				'views' => $views,
				'clicks' => $clicks,
				'conversion' => $this->calculate_conversion($views, $clicks),
			);
		}

		usort(
			$rows,
			static function (array $left, array $right): int {
				return strcmp((string) $right['date'], (string) $left['date']);
			}
		);

		return $rows;
	}

	/**
	 * @return array<string, float|int>
	 */
	public function get_totals(int $days = 30): array
	{
		$rows = $this->get_daily_stats($days);

		$total_views = 0;
		$total_clicks = 0;
		foreach ($rows as $row) {
			$total_views += (int) ($row['views'] ?? 0);
			$total_clicks += (int) ($row['clicks'] ?? 0);
		}

		return array(
			'views' => $total_views,
			'clicks' => $total_clicks,
			'conversion_rate' => $this->calculate_conversion($total_views, $total_clicks),
		);
	}

	public function get_conversion_rate(int $days = 30): float
	{
		$totals = $this->get_totals($days);

		return (float) ($totals['conversion_rate'] ?? 0.0);
	}

	private function calculate_conversion(int $views, int $clicks): float
	{
		if ($views <= 0) {
			return 0.0;
		}

		return $clicks / $views;
	}
}

