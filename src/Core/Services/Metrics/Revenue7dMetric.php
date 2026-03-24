<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Services\Metrics;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Core\Financial\Ledger;



/**
 * Executes historical dashboard revenue aggregations referencing immutable Ledger ledgers.
 */
final class Revenue7dMetric implements MetricInterface
{
	public function key(): string
	{
		return 'revenue_7d';
	}

	public function resolve(string $context, array $args, int $currentStart, int $now, int $previousStart): array
	{
		if ($context !== 'vendor' || empty($args['vendor_id'])) {
			return array(
				'total'    => 0.0,
				'current'  => 0.0,
				'previous' => 0.0,
			);
		}

		$vendor_id = (int) $args['vendor_id'];

		$total = Ledger::get_total_earned($vendor_id);

		// Current 7d
		$current = $this->query_period_sum($vendor_id, $currentStart, $now);

		// Previous 7d
		$previous = $this->query_period_sum($vendor_id, $previousStart, $currentStart);

		return array(
			'total'    => $total,
			'current'  => $current,
			'previous' => $previous,
		);
	}

	public function subjectKey(array $args): string
	{
		if (! empty($args['vendor_id'])) {
			return (string) $args['vendor_id'];
		}
		return 'global';
	}

	/**
	 * Private evaluation isolated for distinct date-boundaries calculating fractional sums securely mapped against immutable constraints
	 */
	private function query_period_sum(int $vendor_id, int $start_ts, int $end_ts): float
	{
		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'mhm_rentiva_ledger' );

		$start_date = gmdate('Y-m-d H:i:s', $start_ts);
		$end_date   = gmdate('Y-m-d H:i:s', $end_ts);

		$sum = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM {$table} 
				WHERE vendor_id = %d 
				AND type != %s 
				AND status IN ('cleared', 'pending') 
				AND created_at >= %s 
				AND created_at < %s",
				$vendor_id,
				'payout_debit',
				$start_date,
				$end_date
			)
		);

		return (float) $sum;
	}
}
