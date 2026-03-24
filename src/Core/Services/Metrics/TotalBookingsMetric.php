<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Core\Services\TrendService;



/**
 * Customer total bookings metric.
 */
final class TotalBookingsMetric implements MetricInterface
{
	public function key(): string
	{
		return 'total_bookings';
	}

	public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
	{
		unset($context);

		$userId = TrendService::resolve_customer_user_id_from_args($args);
		if ($userId <= 0) {
			return array('total' => 0, 'current' => 0, 'previous' => 0);
		}

		return array(
			'total'    => TrendService::count_user_bookings_total($userId),
			'current'  => TrendService::count_user_bookings_between($userId, $currentStart, $now),
			'previous' => TrendService::count_user_bookings_between($userId, $previousStart, $currentStart),
		);
	}

	public function subjectKey(array $args): string
	{
		$userId = TrendService::resolve_customer_user_id_from_args($args);
		return $userId > 0 ? (string) $userId : 'guest';
	}
}
