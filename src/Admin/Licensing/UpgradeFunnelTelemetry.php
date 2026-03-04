<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Deterministic Lite->Pro upgrade funnel telemetry collector.
 *
 * Stores per-day event counters in a bounded option map without schema changes.
 */
final class UpgradeFunnelTelemetry
{
	private const OPTION = 'mhm_rentiva_upgrade_funnel_stats';
	private const MAX_DAYS = 90;

	/**
	 * @var array<string, bool>
	 */
	private static array $request_guard = array();

	/**
	 * @var string[]
	 */
	private const ALLOWED_EVENTS = array(
		'license_page_view_lite',
		'upgrade_cta_click_license_page',
		'upgrade_cta_click_pro_notice',
		'upgrade_cta_click_setup_wizard',
	);

	public static function register(): void
	{
		add_action('mhm_rentiva_track_upgrade_funnel_event', array(self::class, 'track'), 10, 1);
	}

	public static function track(string $event): void
	{
		$event = sanitize_key($event);
		if (! in_array($event, self::ALLOWED_EVENTS, true)) {
			return;
		}

		$today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
		$guard_key = $today . ':' . $event;
		if (isset(self::$request_guard[$guard_key])) {
			return;
		}
		self::$request_guard[$guard_key] = true;

		$stats = get_option(self::OPTION, array());
		if (! is_array($stats)) {
			$stats = array();
		}

		if (! isset($stats[$today]) || ! is_array($stats[$today])) {
			$stats[$today] = array();
		}

		$stats[$today][$event] = (int) ($stats[$today][$event] ?? 0) + 1;
		$stats = self::trim_to_max_days($stats);

		update_option(self::OPTION, $stats, false);
	}

	/**
	 * Test helper: reset request-scope debounce guard for deterministic test runs.
	 */
	public static function reset_request_guard_for_tests(): void
	{
		self::$request_guard = array();
	}

	/**
	 * @param array<string, array<string, int>> $stats
	 * @return array<string, array<string, int>>
	 */
	private static function trim_to_max_days(array $stats): array
	{
		if (count($stats) <= self::MAX_DAYS) {
			return $stats;
		}

		uksort(
			$stats,
			static function (string $left, string $right): int {
				return strcmp($left, $right);
			}
		);

		while (count($stats) > self::MAX_DAYS) {
			$oldest_key = array_key_first($stats);
			if (null === $oldest_key) {
				break;
			}
			unset($stats[$oldest_key]);
		}

		return $stats;
	}
}

