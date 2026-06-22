<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\MetaKeys;
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
final class PenaltyRecorder {

	/**
	 * Register the hook listener.
	 */
	public static function register(): void
	{
		add_action('mhm_rentiva_vehicle_withdrawn', array( self::class, 'record_penalty' ), 20, 3);
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

		/**
		 * Filter — gate the ledger debit. Mirrors the score-deduction gate in
		 * {@see VehicleLifecycleManager::withdraw()} so the score and the ledger
		 * stay in sync. Used by the Vendor Report system (v4.35.0) to suspend
		 * penalties while a withdrawal-reason appeal is open.
		 *
		 * @since 4.35.0
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- prefix `mhm_rentiva_` matches Text Domain.
		$apply_penalty = (bool) apply_filters('mhm_rentiva_before_apply_penalty', true, $vehicle_id, $vendor_id, 'withdrawal', $penalty_amount);
		if (! $apply_penalty) {
			// Penalty is deferred while an appeal is open. Persist the amount computed NOW
			// (before this vehicle's withdrawn_at counts in its own tier) so that, if the
			// appeal is later rejected, the correct tier is applied rather than a recomputed
			// (self-counting, one-tier-too-high) value.
			update_post_meta($vehicle_id, MetaKeys::VEHICLE_DEFERRED_PENALTY, $penalty_amount);
			return;
		}

		$currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY';

		$uuid = self::penalty_uuid($vehicle_id, $vendor_id);

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
			// Ledger::add_entry returns 0 (not an exception) for a duplicate UUID, so a
			// RuntimeException here is a genuine write failure — never swallow it silently,
			// or a vendor escapes their penalty with no trace. Log and abort (no "recorded"
			// event fires).
			if (class_exists('\\MHMRentiva\\Admin\\PostTypes\\Logs\\AdvancedLogger')) {
				\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error(
					sprintf('Failed to record withdrawal penalty for vendor #%d (vehicle #%d): %s', $vendor_id, $vehicle_id, $e->getMessage()),
					array(
						'vendor'  => $vendor_id,
						'vehicle' => $vehicle_id,
						'amount'  => $penalty_amount,
					),
					'payout'
				);
			}
			return;
		}

		// Link the applied penalty to its vehicle so the vendor's appeal UI can reference the
		// exact ledger entry (transaction_uuid) when contesting this penalty, and the admin
		// reversal can find it.
		update_post_meta($vehicle_id, MetaKeys::VEHICLE_PENALTY_UUID, $uuid);

		do_action('mhm_rentiva_withdrawal_penalty_recorded', $vehicle_id, $vendor_id, $penalty_amount);
	}

	/**
	 * Build the ledger transaction UUID for a withdrawal penalty.
	 *
	 * Must fit the ledger `transaction_uuid CHAR(36)` column. The previous
	 * "withdrawal_penalty_<vehicle>_<vendor>_<time>" format overflowed 36 chars for realistic
	 * (multi-digit) post/user IDs, so the penalty debit failed to write — and the failure was
	 * swallowed silently. This hashed form is always 36 chars and stays per-withdrawal unique.
	 *
	 * @param int $vehicle_id Vehicle post ID.
	 * @param int $vendor_id  Vendor user ID.
	 */
	public static function penalty_uuid(int $vehicle_id, int $vendor_id): string
	{
		return substr('wpen_' . md5($vehicle_id . '_' . $vendor_id . '_' . microtime(true)), 0, 36);
	}
}
