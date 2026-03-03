<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Billing\Usage;

if (! defined('ABSPATH')) {
    exit;
}

final class UsageBillingExecutionOutcome
{
    private bool $executed;

    private string $reason;

    private string $idempotency_key;

    private ?string $ledger_transaction_uuid;

    private int $ledger_writes;

    private function __construct(
        bool $executed,
        string $reason,
        string $idempotency_key,
        ?string $ledger_transaction_uuid,
        int $ledger_writes
    ) {
        $this->executed = $executed;
        $this->reason = $reason;
        $this->idempotency_key = $idempotency_key;
        $this->ledger_transaction_uuid = $ledger_transaction_uuid;
        $this->ledger_writes = $ledger_writes;
    }

    public static function committed(string $idempotency_key, string $ledger_transaction_uuid): self
    {
        return new self(true, 'committed', $idempotency_key, $ledger_transaction_uuid, 1);
    }

    public static function duplicate_noop(string $idempotency_key): self
    {
        return new self(false, 'duplicate_noop', $idempotency_key, null, 0);
    }

    public static function legacy_path(string $idempotency_key): self
    {
        return new self(false, 'legacy_path', $idempotency_key, null, 0);
    }

    public static function drift_mismatch(string $idempotency_key): self
    {
        return new self(false, 'drift_mismatch', $idempotency_key, null, 0);
    }

    public function executed(): bool
    {
        return $this->executed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function idempotency_key(): string
    {
        return $this->idempotency_key;
    }

    public function ledger_transaction_uuid(): ?string
    {
        return $this->ledger_transaction_uuid;
    }

    public function ledger_writes(): int
    {
        return $this->ledger_writes;
    }
}
