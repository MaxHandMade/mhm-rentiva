<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Events;

/**
 * Event emitted immediately within the DB transaction window
 * when an atomic payout request lands successfully in the ledger.
 *
 * @since 4.21.0
 */
final class PayoutApprovedEvent implements DomainEvent
{
    private int $payout_id;
    private string $tx_uuid;

    public function __construct(int $payout_id, string $tx_uuid)
    {
        $this->payout_id = $payout_id;
        $this->tx_uuid   = $tx_uuid;
    }

    public function get_event_name(): string
    {
        return 'payout_approved';
    }

    public function get_payout_id(): int
    {
        return $this->payout_id;
    }

    public function get_tx_uuid(): string
    {
        return $this->tx_uuid;
    }
}
