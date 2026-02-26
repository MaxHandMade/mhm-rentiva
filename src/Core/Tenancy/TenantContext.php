<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Tenancy;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Immutable Data Transfer Object representing the current tenant context.
 *
 * All financial operations MUST carry a TenantContext to ensure strict
 * data isolation. Do not construct this object directly; use TenantResolver.
 *
 * @since 4.23.0
 */
final class TenantContext
{
    /**
     * @param int    $tenant_id         The WordPress site/blog ID for this tenant.
     * @param string $tenant_key        Unique string identifier (e.g., slug) for the tenant.
     * @param string $locale            The tenant's locale (e.g., 'tr_TR').
     * @param string $compliance_profile The compliance profile key (e.g., 'global', 'eu', 'tr').
     */
    public function __construct(
        private readonly int    $tenant_id,
        private readonly string $tenant_key,
        private readonly string $locale,
        private readonly string $compliance_profile = 'global',
    ) {
        if ($this->tenant_id <= 0) {
            throw new \InvalidArgumentException('TenantContext: tenant_id must be a positive integer.');
        }

        if (empty($this->tenant_key)) {
            throw new \InvalidArgumentException('TenantContext: tenant_key cannot be empty.');
        }
    }

    /**
     * Returns the primary numeric tenant identifier.
     *
     * @return int
     */
    public function get_id(): int
    {
        return $this->tenant_id;
    }

    /**
     * Returns the unique string key for this tenant.
     *
     * @return string
     */
    public function get_key(): string
    {
        return $this->tenant_key;
    }

    /**
     * Returns the tenant's locale string.
     *
     * @return string
     */
    public function get_locale(): string
    {
        return $this->locale;
    }

    /**
     * Returns the compliance profile identifier for this tenant.
     * Used by risk engine and governance service for jurisdiction-specific rules.
     *
     * @return string
     */
    public function get_compliance_profile(): string
    {
        return $this->compliance_profile;
    }

    /**
     * Serializes the context to an array for logging or caching purposes.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return [
            'tenant_id'          => $this->tenant_id,
            'tenant_key'         => $this->tenant_key,
            'locale'             => $this->locale,
            'compliance_profile' => $this->compliance_profile,
        ];
    }
}
