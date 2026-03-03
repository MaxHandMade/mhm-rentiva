<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Adapter boundary for writing usage billing financial mutations into ledger.
 *
 * Implementations must be deterministic and return a transaction UUID for each successful write.
 */
interface UsageBillingLedgerAdapterInterface
{
    public function write(int $tenant_id, int $amount_cents): string;
}
