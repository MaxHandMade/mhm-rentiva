<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Core\Services\TrendService;



/**
 * Customer unread messages metric.
 */
final class UnreadMessagesMetric implements MetricInterface {

	public function key(): string
	{
		return 'unread_messages';
	}

	public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
	{
		unset($context);

		$email = TrendService::resolve_customer_email_from_args($args);
		if ($email === '') {
			return array(
				'total'    => 0,
				'current'  => 0,
				'previous' => 0,
			);
		}

		return array(
			'total'    => TrendService::count_unread_messages_total($email),
			'current'  => TrendService::count_unread_messages_between($email, $currentStart, $now),
			'previous' => TrendService::count_unread_messages_between($email, $previousStart, $currentStart),
		);
	}

	public function subjectKey(array $args): string
	{
		$email = TrendService::resolve_customer_email_from_args($args);
		return $email !== '' ? md5(strtolower($email)) : 'guest';
	}
}
