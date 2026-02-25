<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registry for metric handlers.
 */
final class MetricRegistry
{
	/** @var array<string, class-string<MetricInterface>>|null */
	private static ?array $map = null;

	/**
	 * Get metric handler by key.
	 */
	public static function get(string $metric): ?MetricInterface
	{
		$metric = sanitize_key($metric);
		$map = self::get_map();
		$class = $map[$metric] ?? null;

		if (! is_string($class) || ! class_exists($class)) {
			return null;
		}

		return new $class();
	}

	/**
	 * @return array<string, class-string<MetricInterface>>
	 */
	public static function all(): array
	{
		return self::get_map();
	}

	/**
	 * Register a new metric handler dynamically.
	 *
	 * @param string $metric Custom metric key.
	 * @param string $class  FQCN of metric implementing MetricInterface.
	 * @throws \InvalidArgumentException If the metric key is already registered.
	 */
	public static function register(string $metric, string $class): void
	{
		$metric = sanitize_key($metric);

		if (self::$map === null) {
			self::get_map();
		}

		if (isset(self::$map[$metric])) {
			throw new \InvalidArgumentException(sprintf('Metric handler "%s" is already registered.', $metric));
		}

		self::$map[$metric] = $class;
	}

	/**
	 * Boot the metrics array and allow third-party WordPress filters to inject custom dashboard metrics lazily.
	 *
	 * @return array<string, class-string<MetricInterface>>
	 */
	private static function get_map(): array
	{
		if (self::$map !== null) {
			return self::$map;
		}

		$core_metrics = array(
			'total_bookings'          => TotalBookingsMetric::class,
			'upcoming_pickups'        => UpcomingPickupsMetric::class,
			'unread_messages'         => UnreadMessagesMetric::class,
			'revenue_7d'              => Revenue7dMetric::class,
			'available_balance'       => AvailableBalanceMetric::class,
			'pending_balance'         => PendingBalanceMetric::class,
			'total_paid_out'          => TotalPaidOutMetric::class,
			// Sprint 3 — Vendor Analytics
			'vendor_revenue_30d'       => VendorRevenue30dMetric::class,
			'vendor_growth_7d'         => VendorGrowth7dMetric::class,
			'vendor_avg_booking_value' => VendorAvgBookingValueMetric::class,
		);

		/**
		 * Filters the registered dashboard metrics.
		 *
		 * @param array<string, class-string<MetricInterface>> $metrics Array of metric keys and their handler classes.
		 */
		$filtered = apply_filters('mhm_rentiva_registered_metrics', $core_metrics);

		self::$map = array();

		if (is_array($filtered)) {
			foreach ($filtered as $key => $class) {
				if (is_string($key) && is_string($class)) {
					self::register($key, $class);
				}
			}
		}

		return self::$map;
	}
}
