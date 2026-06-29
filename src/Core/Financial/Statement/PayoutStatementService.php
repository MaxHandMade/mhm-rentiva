<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Statement;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Orchestrates payout-statement generation: build → number → save → notify.
 */
final class PayoutStatementService {

	public static function generate_for_payout(int $payout_id): ?array
	{
		if (PayoutStatementRepository::exists($payout_id)) {
			return null; // Idempotent: already generated.
		}

		$statement           = PayoutStatement::build($payout_id);
		$statement['number'] = PayoutStatementNumber::next();

		PayoutStatementRepository::save($payout_id, $statement);

		do_action('mhm_rentiva_payout_statement_generated', $payout_id);

		return $statement;
	}
}
