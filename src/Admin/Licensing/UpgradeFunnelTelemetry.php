<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

use MHMRentiva\Admin\Growth\AbVariantResolver;

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
	private const TRACK_NONCE_ACTION = 'mhm_rentiva_track_upgrade_cta';
	private const METRIC_VIEWS = 'views';
	private const METRIC_CLICKS = 'clicks';

	/**
	 * @var array<string, bool>
	 */
	private static array $request_guard = array();

	private static ?AbVariantResolver $variant_resolver = null;

	/**
	 * @var string[]
	 */
	private const ALLOWED_EVENTS = array(
		'license_page_view_lite',
		'upgrade_cta_click_license_page',
		'upgrade_cta_click_pro_notice',
		'upgrade_cta_click_setup_wizard',
	);

	/**
	 * @var string[]
	 */
	private const ALLOWED_VARIANTS = array('A', 'B');

	public static function register(): void
	{
		add_action('mhm_rentiva_track_upgrade_funnel_event', array(self::class, 'track'), 10, 2);
	}

	public static function build_tracked_cta_url(string $event, string $redirect_to): string
	{
		$event = sanitize_key($event);
		$args = array(
			'action' => 'mhm_rentiva_track_upgrade_cta',
			'event' => $event,
			'redirect_to' => esc_url_raw($redirect_to),
		);

		$url = add_query_arg($args, admin_url('admin-post.php'));

		return wp_nonce_url($url, self::TRACK_NONCE_ACTION);
	}

	public static function get_tracking_nonce_action(): string
	{
		return self::TRACK_NONCE_ACTION;
	}

	public static function is_allowed_event(string $event): bool
	{
		return in_array(sanitize_key($event), self::ALLOWED_EVENTS, true);
	}

	public static function resolve_variant_for_current_user(): string
	{
		return self::get_variant_resolver()->resolve(get_current_user_id());
	}

	public static function track(string $event, ?string $variant = null): void
	{
		$event = sanitize_key($event);
		if (! self::is_allowed_event($event)) {
			return;
		}
		$variant = self::normalize_variant($variant);

		$today = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
		$guard_key = $today . ':' . $event;
		if (null !== $variant) {
			$guard_key .= ':' . $variant;
		}
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
		if (null !== $variant) {
			self::increment_variant_metric($stats[$today], $event, $variant);
		}
		$stats = self::trim_to_max_days($stats);

		update_option(self::OPTION, $stats, false);
	}

	/**
	 * Test helper: reset request-scope debounce guard for deterministic test runs.
	 */
	public static function reset_request_guard_for_tests(): void
	{
		self::$request_guard = array();
		self::$variant_resolver = null;

		if (class_exists(AbVariantResolver::class)) {
			AbVariantResolver::reset_request_cache_for_tests();
		}
	}

	/**
	 * @param array<string, array<string, mixed>> $stats
	 * @return array<string, array<string, mixed>>
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

	/**
	 * @param array<string,mixed> $day_stats
	 */
	private static function increment_variant_metric(array &$day_stats, string $event, string $variant): void
	{
		$metric = self::resolve_metric_for_event($event);
		if (null === $metric) {
			return;
		}

		if (! isset($day_stats['variant']) || ! is_array($day_stats['variant'])) {
			$day_stats['variant'] = array();
		}
		if (! isset($day_stats['variant'][$variant]) || ! is_array($day_stats['variant'][$variant])) {
			$day_stats['variant'][$variant] = array();
		}

		$current_value = (int) ($day_stats['variant'][$variant][$metric] ?? 0);
		$day_stats['variant'][$variant][$metric] = $current_value + 1;
	}

	private static function normalize_variant(?string $variant): ?string
	{
		if (null === $variant) {
			return null;
		}

		$variant = strtoupper(trim($variant));
		if (! in_array($variant, self::ALLOWED_VARIANTS, true)) {
			return null;
		}

		return $variant;
	}

	private static function resolve_metric_for_event(string $event): ?string
	{
		if ('license_page_view_lite' === $event) {
			return self::METRIC_VIEWS;
		}

		if (str_starts_with($event, 'upgrade_cta_click_')) {
			return self::METRIC_CLICKS;
		}

		return null;
	}

	private static function get_variant_resolver(): AbVariantResolver
	{
		if (null === self::$variant_resolver) {
			self::$variant_resolver = new AbVariantResolver();
		}

		return self::$variant_resolver;
	}
}
