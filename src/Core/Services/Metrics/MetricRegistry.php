<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registry for metric handlers.
 */
final class MetricRegistry {

	/** @var array<string, class-string<MetricInterface>>|null */
	private static ?array $map = null;

	/**
	 * Get metric handler by key.
	 */
	public static function get(string $metric): ?MetricInterface
	{
		$metric = sanitize_key($metric);
		$map    = self::get_map();
		$class  = $map[ $metric ] ?? null;

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

		if (isset(self::$map[ $metric ])) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Domain exception; escaped at render layer.
			throw new \InvalidArgumentException(sprintf('Metric handler "%s" is already registered.', $metric));
		}

		self::$map[ $metric ] = $class;
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

		/*
		 * CORE metrics only — these resolve from booking post data and have no
		 * vendor, ledger or messaging dependency.
		 *
		 * The vendor metrics that used to be hardcoded here (unread_messages,
		 * revenue_7d, available_balance, pending_balance, total_paid_out,
		 * vendor_revenue_30d, vendor_growth_7d, vendor_avg_booking_value) are add-on
		 * features, not core: each one resolves only under the 'vendor' KPI
		 * context and reads the messaging tables or the ledger. They are
		 * registered by the add-on through the 'mhm_rentiva_registered_metrics' filter
		 * below rather than being named here, so that this registry carries no
		 * compile-time reference to a class the Lite package does not ship.
		 *
		 * Consumers already tolerate an unregistered metric: MetricRegistry::get()
		 * returns null for an unknown key, and
		 * DashboardDataProvider::resolve_vendor_ledger_metric() maps that null to
		 * a 0.0 total.
		 */
		$core_metrics = array(
			'total_bookings'   => TotalBookingsMetric::class,
			'upcoming_pickups' => UpcomingPickupsMetric::class,
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
