<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Dashboard;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Core\Services\TrendService;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Orchestrates dashboard runtime data by context.
 */
final class DashboardDataProvider
{
	/**
	 * Build dashboard data for a given context.
	 *
	 * @return array<string, mixed>
	 */
	public static function build(string $context, int $user_id, string $user_email): array
	{
		$config = DashboardConfig::get_kpis($context);
		$kpi_data = array();

		foreach ($config as $key => $kpi) {
			$metric = sanitize_key((string) ($kpi['metric'] ?? ''));
			$with_trend = ! empty($kpi['trend']);
			$kpi_data[$key] = self::resolve_metric($metric, $context, $user_id, $user_email, $with_trend);
		}

		return array(
			'kpis'                    => $config,
			'kpi_data'                => $kpi_data,
			'recent_bookings'         => self::get_recent_bookings($user_id),
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
		if (isset($resolvers[$metric])) {
			return $resolvers[$metric]($context, $user_id, $user_email);
		}

		if ($with_trend) {
			$args = self::trend_args_for_metric($metric, $user_id, $user_email);
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
			'saved_favorites' => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_email);
				return array(
					'total' => count(FavoritesService::get_user_favorites($user_id)),
				);
			},
			'active_listings' => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_id, $user_email);
				return array('total' => 0);
			},
			'pending_requests' => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_id, $user_email);
				return array('total' => 0);
			},
			'upcoming_rentals' => static function (string $context, int $user_id, string $user_email): array {
				unset($context, $user_id, $user_email);
				return array('total' => 0);
			},
		);
	}

	/**
	 * Build trend args based on metric contract.
	 *
	 * @return array<string, int|string>
	 */
	private static function trend_args_for_metric(string $metric, int $user_id, string $user_email): array
	{
		$metric = sanitize_key($metric);

		if ($metric === 'unread_messages') {
			return array('email' => $user_email);
		}

		return array('user_id' => $user_id);
	}

	/**
	 * Normalize trend payload to renderer contract.
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, int|string>
	 */
	private static function normalize_trend_payload(array $payload): array
	{
		$direction = sanitize_key((string) ($payload['direction'] ?? 'neutral'));
		if (! in_array($direction, array('up', 'down', 'neutral'), true)) {
			$direction = 'neutral';
		}

		return array(
			'total'     => (int) ($payload['total'] ?? 0),
			'trend'     => (int) ($payload['trend'] ?? 0),
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
}
