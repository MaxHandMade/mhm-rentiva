<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Financial\Audit\Export;

if (! defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Core\Financial\Exceptions\GovernanceException;



/**
 * Deterministic Hash Chain builder.
 *
 * Ensures backward-linked immutability of exported audit trails by hashing a canonical
 * representation of each row combined with the hash of the preceding row.
 *
 * @since 4.22.0
 */
class HashChainBuilder
{
    private string $previous_hash = 'GENESIS';

    /**
     * Ingests a raw database row associative array, parses it into strict canonical form,
     * hashes it, and advances the chain.
     *
     * @param array $row Raw mapped data containing ledger + audit fields.
     * @return array{ canonical: string, current_hash: string, previous_hash: string, csv_row: array }
     * @throws GovernanceException
     */
    public function advance(array $row): array
    {
        $ordered_fields = [
            'id'             => self::parse_int($row['id'] ?? null),
            'tx_uuid'        => self::parse_string($row['tx_uuid'] ?? ''),
            'payout_id'      => self::parse_int($row['payout_id'] ?? null),
            'vendor_id'      => self::parse_int($row['vendor_id'] ?? null),
            'amount'         => self::parse_amount($row['amount'] ?? null),
            'action'         => self::parse_string($row['action'] ?? ''),
            'created_at'     => self::parse_timestamp($row['created_at'] ?? ''),
            'risk_score'     => self::parse_int($row['risk_score'] ?? null), // If not in DB view yet, falls back to integer 0
            'approval_stage' => self::parse_string($row['approval_stage'] ?? ''),
            'actor_id'       => self::parse_int($row['actor_id'] ?? null),
        ];

        // 1. Array Values directly translate to the eventual CSV payload
        $csv_row = array_values($ordered_fields);

        // 2. Implode the EXACT fields using fixed strict delimiter
        $canonical_string = implode('|', $ordered_fields);

        // 3. Chain the Hash computationally: hash(canonical + previous)
        $computation_material = $canonical_string . $this->previous_hash;

        $current_hash = hash('sha256', $computation_material, false);

        $result = [
            'canonical'     => $canonical_string,
            'current_hash'  => $current_hash,
            'previous_hash' => $this->previous_hash,
            'csv_row'       => $csv_row,
        ];

        // 4. Step the chain forward
        $this->previous_hash = $current_hash;

        return $result;
    }

    /**
     * Get the final hash representing the tip of the entire chain.
     */
    public function get_tip_hash(): string
    {
        return $this->previous_hash;
    }

    private static function parse_string(string $value): string
    {
        // Must be strict UTF-8
        $clean = mb_convert_encoding($value, 'UTF-8', 'auto');
        // Prevent accidental delimiter bleeding if someone creatively injects a pipe character
        return str_replace('|', '-', trim($clean));
    }

    private static function parse_int($value): string
    {
        return (string) ((int) $value);
    }

    private static function parse_amount($value): string
    {
        // Float conversion in PHP is non-deterministic. We force a strict 2-decimal string formatting. 
        // Example: 15.5 -> "15.50"
        return sprintf('%.2f', (float) $value);
    }

    private static function parse_timestamp(string $mysql_datetime): string
    {
        if (empty($mysql_datetime)) {
            return '';
        }
        // Force conversion to UTC ISO 8601 without local timezone pollution
        $dt = new \DateTimeImmutable($mysql_datetime, new \DateTimeZone('UTC'));
        return $dt->format('Y-m-d\TH:i:s\Z');
    }
}
