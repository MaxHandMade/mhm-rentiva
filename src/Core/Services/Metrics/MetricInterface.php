<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Contract for trend-capable dashboard metrics.
 */
interface MetricInterface
{
	/**
	 * Unique metric key (e.g. total_bookings).
	 */
	public function key(): string;

	/**
	 * Resolve total/current/previous values for trend math.
	 *
	 * @param string               $context
	 * @param array<string, mixed> $args
	 * @return array{total:int,current:int,previous:int}
	 */
	public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array;

	/**
	 * Resolve subject key used in cache key generation.
	 *
	 * @param array<string, mixed> $args
	 */
	public function subjectKey(array $args): string;
}

