<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Core\Financial\Ledger;
use MHMRentiva\Core\Financial\LedgerEntry;

/**
 * Records withdrawal penalties in the financial ledger.
 *
 * Listens to the `mhm_rentiva_vehicle_withdrawn` hook and writes a negative
 * ledger entry (debit) when the penalty amount is > 0.
 *
 * @since 4.24.0
 */
final class PenaltyRecorder
{
	/**
	 * Register the hook listener.
	 */
	public static function register(): void
	{
		add_action('mhm_rentiva_vehicle_withdrawn', array(self::class, 'record_penalty'), 20, 3);
	}

	/**
	 * Record withdrawal penalty in the ledger if applicable.
	 *
	 * @param int   $vehicle_id     Vehicle post ID.
	 * @param int   $vendor_id      Vendor user ID.
	 * @param float $penalty_amount Pre-calculated penalty (0.0 if first withdrawal).
	 */
	public static function record_penalty(int $vehicle_id, int $vendor_id, float $penalty_amount): void
	{
		if ($penalty_amount <= 0.0) {
			return;
		}

		$currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY';

		$uuid = 'withdrawal_penalty_' . $vehicle_id . '_' . $vendor_id . '_' . time();

		$entry = new LedgerEntry(
			$uuid,
			$vendor_id,
			null,           // booking_id
			null,           // order_id
			'withdrawal_penalty',
			-1 * abs($penalty_amount), // Negative = debit from vendor balance.
			null,           // gross_amount
			null,           // commission_amount
			null,           // commission_rate
			$currency,
			'platform',     // Context: platform-level penalty, not booking-related.
			'cleared',      // Immediately applied.
			null,           // created_at (auto)
			null,           // policy_id
			null            // policy_version_hash
		);

		try {
			Ledger::add_entry($entry);
		} catch (\RuntimeException $e) {
			// Idempotent: duplicate UUID is silently ignored.
		}

		do_action('mhm_rentiva_withdrawal_penalty_recorded', $vehicle_id, $vendor_id, $penalty_amount);
	}
}
