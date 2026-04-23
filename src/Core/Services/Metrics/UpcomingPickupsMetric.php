<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Core\Services\TrendService;



/**
 * Customer upcoming pickups metric.
 */
final class UpcomingPickupsMetric implements MetricInterface {

	public function key(): string
	{
		return 'upcoming_pickups';
	}

	public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
	{
		unset($context, $currentStart, $previousStart);

		$userId = TrendService::resolve_customer_user_id_from_args($args);
		if ($userId <= 0) {
			return array(
				'total'    => 0,
				'current'  => 0,
				'previous' => 0,
			);
		}

		$today      = gmdate('Y-m-d', $now);
		$plusSeven  = gmdate('Y-m-d', (int) strtotime('+7 days', $now));
		$minusSeven = gmdate('Y-m-d', (int) strtotime('-7 days', $now));

		return array(
			'total'    => TrendService::count_user_pickups_between($userId, $today, '2099-12-31'),
			'current'  => TrendService::count_user_pickups_between($userId, $today, $plusSeven),
			'previous' => TrendService::count_user_pickups_between($userId, $minusSeven, $today),
		);
	}

	public function subjectKey(array $args): string
	{
		$userId = TrendService::resolve_customer_user_id_from_args($args);
		return $userId > 0 ? (string) $userId : 'guest';
	}
}
