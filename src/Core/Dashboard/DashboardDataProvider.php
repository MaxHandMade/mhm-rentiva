<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Core\Services\TrendService;

/**
 * Builds the Lite customer dashboard payload.
 */
final class DashboardDataProvider {

	/**
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

		$data = array(
			'kpis'                    => $config,
			'kpi_data'                => $kpi_data,
			'recent_bookings'         => self::get_recent_bookings($user_id),
			'bookings_tab_shortcode'  => '[rentiva_my_bookings hide_nav="1" limit="10"]',
			'favorites_tab_shortcode' => '[rentiva_my_favorites limit="12"]',
			'panels'                  => apply_filters('mhmrentiva_dashboard_panels', array(), $context, $user_id),
		);

		/**
		 * Filters the customer dashboard payload.
		 *
		 * @param array<string, mixed> $data    Dashboard data.
		 * @param string               $context Dashboard context.
		 * @param int                  $user_id Current user ID.
		 */
		return apply_filters('mhmrentiva_dashboard_data', $data, $context, $user_id);
	}

	/**
	 * @return array<string, int|string>
	 */
	private static function resolve_metric(string $metric, string $context, int $user_id, string $user_email, bool $with_trend): array
	{
		/**
		 * Lets an extension resolve a metric that Lite does not own.
		 *
		 * Return null to continue through Lite's metric pipeline, or an array with
		 * at least a `total` key to provide the complete metric payload.
		 *
		 * @param array<string, int|string>|null $metric_data Metric payload.
		 * @param string                        $metric      Sanitized metric key.
		 * @param string                        $context     Dashboard context.
		 * @param int                           $user_id     Current user ID.
		 * @param string                        $user_email  Current user email.
		 * @param bool                          $with_trend  Whether the KPI requests trend data.
		 */
		$metric_data = apply_filters(
			'mhmrentiva_dashboard_metric_data',
			null,
			$metric,
			$context,
			$user_id,
			$user_email,
			$with_trend
		);

		if (is_array($metric_data) && array_key_exists('total', $metric_data)) {
			return $metric_data;
		}

		if ('saved_favorites' === $metric) {
			return array( 'total' => count(FavoritesService::get_user_favorites($user_id)) );
		}

		if ($with_trend) {
			return self::normalize_trend_payload(
				TrendService::get_trend($metric, $context, array( 'user_id' => $user_id ))
			);
		}

		return array( 'total' => 0 );
	}

	/**
	 * @param array<string, mixed> $payload Metric payload.
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
	private static function get_recent_bookings(int $user_id): array
	{
		if ($user_id <= 0) {
			return array();
		}

		$bookings_data   = AccountRenderer::get_bookings_data(
			array(
				'limit'    => 5,
				'status'   => '',
				'orderby'  => 'date',
				'order'    => 'DESC',
				'hide_nav' => true,
			)
		);
		$recent_bookings = $bookings_data['data']['bookings'] ?? array();

		return is_array($recent_bookings) ? array_slice($recent_bookings, 0, 5) : array();
	}
}
