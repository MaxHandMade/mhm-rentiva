<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Tenancy\Exceptions;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Thrown when a tenant context cannot be resolved.
 *
 * This prevent silent fallbacks to a default tenant, which is a critical
 * security and isolation requirement for the v1.8 Financial Infrastructure.
 *
 * @since 4.23.0
 */
class TenantResolutionException extends \RuntimeException
{
    public static function not_found(string $reason = ''): self
    {
        $message = 'Failed to resolve a valid TenantContext.';
        if ($reason) {
            $message .= ' Reason: ' . $reason;
        }

        return new self($message);
    }
}
