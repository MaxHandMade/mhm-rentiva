<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Core\Services\Metrics\MetricRegistry;
use MHMRentiva\Core\Services\TrendService;



/**
 * Orchestrates dashboard runtime data by context.
 */
final class DashboardDataProvider {

	/**
	 * Build dashboard data for a given context.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(string $context, int $user_id, string $user_email): array
	{
		$config   = DashboardConfig::get_kpis($context);
		$kpi_data = array();

		foreach ($config as $key => $kpi) {
			$metric           = sanitize_key( (string) ( $kpi['metric'] ?? '' ));
			$with_trend       = ! empty($kpi['trend']);
			$kpi_data[ $key ] = self::resolve_metric($metric, $context, $user_id, $user_email, $with_trend);
		}

		// Vendor-only: build analytics data (ledger-only, deferred — not called for customers).
		// AnalyticsDashboardDataProvider is an extension point filled by the add-on
		// (it reads the ledger), so the guard keeps this core provider loadable
		// when the add-on is absent; consumers
		// already treat an empty 'analytics' array as "no analytics".
		$analytics = array();
		if ($context === 'vendor' && $user_id > 0
			&& class_exists('\MHMRentiva\Core\Dashboard\AnalyticsDashboardDataProvider')) {
			$analytics = AnalyticsDashboardDataProvider::build($user_id);
		}

		return array(
			'kpis'                    => $config,
			'kpi_data'                => $kpi_data,
			'analytics'               => $analytics,
			'recent_bookings'         => self::get_recent_bookings($context, $user_id),
			'bookings_tab_shortcode'  => '[rentiva_my_bookings hide_nav="1" limit="10"]',
			'favorites_tab_shortcode' => '[rentiva_my_favorites limit="12"]',
			'messages_tab_shortcode'  => '[rentiva_messages hide_nav="1"]',
		);
	}

	/**
	 * Resolve one KPI metric payload.
	 *
	 * @return array<string, int|string>
	 */
	private static function resolve_metric(string $metric, string $context, int $user_id, string $user_email, bool $with_trend): array
	{
		$resolvers = self::metric_resolvers();
		if (isset($resolvers[ $metric ])) {
			return $resolvers[ $metric ]($context, $user_id, $user_email);
		}

		if ($with_trend) {
			$args = self::trend_args_for_metric($metric, $context, $user_id, $user_email);
			return self::normalize_trend_payload(TrendService::get_trend($metric, $context, $args));
		}

		return array(
			'total' => 0,
		);
	}

	/**
	 * Non-trend metric resolver map.
	 *
	 * @return array<string, callable>
	 */
	private static function metric_resolvers(): array
	{
		return array(
			'saved_favorites'   => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_email);
				return array(
					'total' => count(FavoritesService::get_user_favorites($user_id)),
				);
			},
			'active_listings'   => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_email);
				$count = count(get_posts(array(
					'post_type'      => 'vehicle',
					'author'         => $user_id,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)));
				return array( 'total' => $count );
			},
			'pending_requests'  => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_email);
				// Booking CPT: 'vehicle_booking'. Vehicle link meta: '_mhm_vehicle_id'.
				// Status meta: '_mhm_status'. We count bookings on this vendor's vehicles with status 'pending'.
				global $wpdb;
				$vehicle_ids = array_map('intval', get_posts(array(
					'post_type'      => 'vehicle',
					'author'         => $user_id,
					'post_status'    => array( 'publish', 'pending' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)));
				if (empty($vehicle_ids)) {
					return array( 'total' => 0 );
				}
				$placeholders = implode(', ', array_fill(0, count($vehicle_ids), '%d'));
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN placeholder list is generated from the vehicle count; values are passed via $wpdb->prepare().
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregated dashboard metrics are intentionally queried live for the current request.
				$count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT p.ID)
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} vm ON vm.post_id = p.ID AND vm.meta_key = '_mhm_vehicle_id'
						INNER JOIN {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = '_mhm_status' AND sm.meta_value = 'pending'
						WHERE p.post_type = 'vehicle_booking'
						AND p.post_status NOT IN ('trash','auto-draft')
						AND CAST(vm.meta_value AS UNSIGNED) IN ($placeholders)",
						$vehicle_ids
					)
				);
				// phpcs:enable
				return array( 'total' => $count );
			},
			'upcoming_rentals'  => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_email);
				global $wpdb;
				$vehicle_ids = array_map('intval', get_posts(array(
					'post_type'      => 'vehicle',
					'author'         => $user_id,
					'post_status'    => array( 'publish', 'pending' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)));
				if (empty($vehicle_ids)) {
					return array( 'total' => 0 );
				}
				$placeholders = implode(', ', array_fill(0, count($vehicle_ids), '%d'));
				$today        = gmdate('Y-m-d');
				$active       = array( 'confirmed', 'pending_payment', 'in_progress', 'pending' );
				$status_ph    = implode(', ', array_fill(0, count($active), '%s'));
				$args         = array_merge($active, $vehicle_ids, array( $today ));
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN placeholder lists are generated from bounded status/vehicle arrays; values are passed via $wpdb->prepare().
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregated dashboard metrics are intentionally queried live for the current request.
				$count = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT p.ID)
						FROM {$wpdb->posts} p
						INNER JOIN {$wpdb->postmeta} vm ON vm.post_id = p.ID AND vm.meta_key = '_mhm_vehicle_id'
						INNER JOIN {$wpdb->postmeta} sm ON sm.post_id = p.ID AND sm.meta_key = '_mhm_status' AND sm.meta_value IN ($status_ph)
						INNER JOIN {$wpdb->postmeta} dm ON dm.post_id = p.ID AND dm.meta_key IN ('_mhm_pickup_date','_booking_pickup_date')
						WHERE p.post_type = 'vehicle_booking'
						AND p.post_status NOT IN ('trash','auto-draft')
						AND CAST(vm.meta_value AS UNSIGNED) IN ($placeholders)
						AND dm.meta_value >= %s",
						$args
					)
				);
				// phpcs:enable
				return array( 'total' => $count );
			},
			'occupancy_rate'    => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_email);
				return self::resolve_vendor_operational_metric('occupancy_rate', $user_id);
			},
			'cancellation_rate' => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_email);
				return self::resolve_vendor_operational_metric('cancellation_rate', $user_id);
			},
			'available_balance' => static function (string $context, int $user_id, string $user_email): array {
				unset($user_email);
				return self::resolve_vendor_ledger_metric('available_balance', $context, $user_id);
			},
			'pending_balance'   => static function (string $context, int $user_id, string $user_email): array {
				unset($user_email);
				return self::resolve_vendor_ledger_metric('pending_balance', $context, $user_id);
			},
			'total_paid_out'    => static function (string $context, int $user_id, string $user_email): array {
				unset($user_email);
				return self::resolve_vendor_ledger_metric('total_paid_out', $context, $user_id);
			},
		);
	}

	/**
	 * Resolve a vendor-scoped operational metric (occupancy_rate, cancellation_rate).
	 *
	 * Both KPIs are only wired under the 'vendor' context (DashboardConfig::get_kpis),
	 * which a Lite install cannot reach anyway — the empty default is defence-in-depth
	 * for the Pro→Lite downgrade case, where vendor-role users already exist in the
	 * DB. A subscriber (Pro) supplies the ledger-backed metrics map; Lite's own
	 * default is an empty array, which the `?? 0` fallback below turns into '0%',
	 * identically to the pre-inversion "no metrics" case.
	 *
	 * @return array<string, string>
	 */
	private static function resolve_vendor_operational_metric(string $metric_key, int $user_id): array
	{
		$now_ts  = time();
		$from_ts = $now_ts - ( 30 * DAY_IN_SECONDS );
		$metrics = apply_filters('mhm_rentiva_dashboard_vendor_metrics', array(), $user_id, $from_ts, $now_ts);

		return array( 'total' => (string) ( $metrics[ $metric_key ] ?? 0 ) . '%' );
	}

	/**
	 * Resolve a vendor-scoped ledger metric (available_balance, pending_balance,
	 * total_paid_out) directly via its MetricRegistry handler.
	 *
	 * These metrics are lifetime/point-in-time ledger totals, not period trends,
	 * so they bypass TrendService::get_trend() entirely — that path passes a
	 * 'user_id' arg key (see trend_args_for_metric()) which these handlers do not
	 * recognize; they require 'vendor_id' specifically (see e.g.
	 * AvailableBalanceMetric::resolve()).
	 *
	 * @return array<string, float>
	 */
	private static function resolve_vendor_ledger_metric(string $metric_key, string $context, int $vendor_id): array
	{
		$handler = MetricRegistry::get($metric_key);
		if (! $handler) {
			return array( 'total' => 0.0 );
		}

		$now      = time();
		$resolved = $handler->resolve($context, array( 'vendor_id' => $vendor_id ), $now, $now, $now);

		return array( 'total' => (float) ( $resolved['total'] ?? 0.0 ) );
	}

	/**
	 * Build trend args based on metric contract.
	 *
	 * @return array<string, int|string>
	 */
	private static function trend_args_for_metric(string $metric, string $context, int $user_id, string $user_email): array
	{
		$metric = sanitize_key($metric);

		if ($metric === 'unread_messages') {
			return array( 'email' => $user_email );
		}

		// Vendor-context metric handlers (e.g. Revenue7dMetric) key their subject on
		// 'vendor_id', not 'user_id' — see AvailableBalanceMetric et al. for the same
		// contract on the metrics resolved directly in metric_resolvers() below.
		if ($context === 'vendor') {
			return array( 'vendor_id' => $user_id );
		}

		return array( 'user_id' => $user_id );
	}

	/**
	 * Normalize trend payload to renderer contract.
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, int|string>
	 */
	private static function normalize_trend_payload(array $payload): array
	{
		$direction = sanitize_key( (string) ( $payload['direction'] ?? 'neutral' ));
		if (! in_array($direction, array( 'up', 'down', 'neutral' ), true)) {
			$direction = 'neutral';
		}

		return array(
			'total'     => (int) ( $payload['total'] ?? 0 ),
			'trend'     => (int) ( $payload['trend'] ?? 0 ),
			'direction' => $direction,
		);
	}

	/**
	 * @return array<int, \WP_Post>
	 */
	private static function get_recent_bookings(string $context, int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}

		if ($context === 'vendor') {
			return self::get_vendor_recent_bookings($user_id);
		}

		$bookings_data = AccountRenderer::get_bookings_data(
			array(
				'limit'    => 5,
				'status'   => '',
				'orderby'  => 'date',
				'order'    => 'DESC',
				'hide_nav' => true,
			)
		);

		$recent_bookings = $bookings_data['data']['bookings'] ?? array();
		$recent_bookings = is_array($recent_bookings) ? $recent_bookings : array();

		return array_slice($recent_bookings, 0, 5);
	}

	/**
	 * Recent bookings for a vendor: bookings of vehicles the vendor owns.
	 *
	 * @return array<int, \WP_Post>
	 */
	private static function get_vendor_recent_bookings(int $user_id): array
	{
		$vehicle_ids = get_posts(array(
			'post_type'      => 'vehicle',
			'author'         => $user_id,
			'post_status'    => array( 'publish', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		));

		if (empty($vehicle_ids)) {
			return array();
		}

		$bookings = get_posts(array(
			'post_type'      => 'vehicle_booking',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- meta_query accepted; custom-table migration is out of scope for this release.
			'meta_query'     => array(
				array(
					'key'     => '_mhm_vehicle_id',
					'value'   => array_map('strval', $vehicle_ids),
					'compare' => 'IN',
				),
			),
		));

		return is_array($bookings) ? $bookings : array();
	}
}
