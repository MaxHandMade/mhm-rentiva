<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Statement;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Core\Financial\Ledger;

/**
 * Computes a payout statement (period activity + totals) from the ledger.
 * Pure read: builds the data structure, does not persist, number, or render.
 */
final class PayoutStatement {

	public static function build(int $payout_id): array
	{
		$vendor_id = (int) get_post_field('post_author', $payout_id);
		$paid      = (float) get_post_meta($payout_id, '_mhm_payout_amount', true);

		$after_id = PayoutStatementRepository::previous_last_entry_id($vendor_id, $payout_id);
		$up_to_id = Ledger::get_max_entry_id($vendor_id);
		$rows     = Ledger::get_statement_lines($vendor_id, $after_id, $up_to_id);

		$lines            = array();
		$gross            = 0.0;
		$penalties        = 0.0;
		$commission_total = 0.0;
		$currency     = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'TRY';
		$period_start = '';
		$period_end   = '';

		foreach ($rows as $row) {
			$amount = (float) $row->amount;
			if ($amount >= 0) {
				$gross += $amount;
			} else {
				$penalties += abs($amount);
			}
			if ($period_start === '') {
				$period_start = (string) $row->created_at;
			}
			$period_end = (string) $row->created_at;

			$line_commission = $amount >= 0 ? (float) ( $row->commission_amount ?? 0 ) : 0.0;
			if ( (string) $row->type === 'commission_credit' ) {
				$commission_total += $line_commission;
			}

			$lines[] = array(
				'date'            => (string) $row->created_at,
				'type'            => (string) $row->type,
				'ref'             => (int) ( $row->booking_id ?? 0 ),
				'description'     => self::describe($row),
				'amount'          => $amount,
				'gross'           => $amount >= 0 ? (float) ( $row->gross_amount ?? 0 ) : 0.0,
				'commission'      => $line_commission,
				'commission_rate' => $amount >= 0 ? (float) ( $row->commission_rate ?? 0 ) : 0.0,
			);
		}

		$snap = array(
			'name'           => self::vendor_name($vendor_id),
			'tax_office'     => (string) get_user_meta($vendor_id, '_rentiva_vendor_tax_office', true),
			'tax_number'     => (string) get_user_meta($vendor_id, '_rentiva_vendor_tax_number', true),
			'account_holder' => (string) get_user_meta($vendor_id, '_rentiva_vendor_account_holder', true),
			'iban'           => (string) get_user_meta($vendor_id, '_rentiva_vendor_iban', true),
		);

		return array(
			'payout_id'       => $payout_id,
			'vendor_id'       => $vendor_id,
			'number'          => '',
			'generated_at'    => gmdate('Y-m-d H:i:s'),
			'currency'        => $currency,
			'period_start'    => $period_start,
			'period_end'      => $period_end,
			'last_entry_id'   => (int) ( $up_to_id > $after_id ? $up_to_id : $after_id ),
			'lines'           => $lines,
			'gross'            => $gross,
			'penalties'        => $penalties,
			'commission_total' => $commission_total,
			'net_activity'    => $gross - $penalties,
			'paid'            => $paid,
			'carried_balance' => Ledger::get_balance($vendor_id),
			'vendor_snapshot' => $snap,
		);
	}

	private static function vendor_name(int $vendor_id): string
	{
		$user = get_userdata($vendor_id);
		return $user instanceof \WP_User ? $user->display_name : ( '#' . $vendor_id );
	}

	private static function describe(\stdClass $row): string
	{
		$booking = (int) ( $row->booking_id ?? 0 );
		switch ( (string) $row->type) {
			case 'commission_credit':
				return $booking > 0
					/* translators: %d: booking ID */
					? sprintf(__('Earnings — booking #%d', 'mhm-rentiva'), $booking)
					: __('Earnings', 'mhm-rentiva');
			case 'commission_refund':
			case 'refund':
				return $booking > 0
					/* translators: %d: booking ID */
					? sprintf(__('Refund — booking #%d', 'mhm-rentiva'), $booking)
					: __('Refund', 'mhm-rentiva');
			case 'withdrawal_penalty':
				return __('Withdrawal penalty', 'mhm-rentiva');
			default:
				return (string) $row->type;
		}
	}
}
