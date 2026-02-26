<?php

declare(strict_types=1);

namespace MHMRentiva\Core\Tenancy;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Resolves the active TenantContext from the current WordPress environment.
 *
 * In a WordPress Multisite environment, each site is a distinct tenant.
 *
 * This class acts as the single authoritative source for TenantContext
 * resolution. Always use this class; never construct TenantContext manually.
 *
 * @since 4.23.0
 */
final class TenantResolver
{
    /**
     * Cache for the resolved context within a single request.
     *
     * @var TenantContext|null
     */
    private static ?TenantContext $resolved_context = null;

    /**
     * Resolves the active TenantContext for the current request.
     *
     * Prioritizes manually set context, then WordPress Multisite context.
     * If no valid tenant can be resolved, throws a TenantResolutionException.
     *
     * @since 4.23.0
     * @return TenantContext
     * @throws Exceptions\TenantResolutionException If isolation context cannot be established.
     */
    public static function resolve(): TenantContext
    {
        if (self::$resolved_context !== null) {
            return self::$resolved_context;
        }

        // 1. Identification via WordPress Multisite
        $tenant_id = null;
        if (is_multisite()) {
            $tenant_id = (int) get_current_blog_id();
        }

        /**
         * Filters the resolved tenant ID before context construction.
         * Allows external systems to inject a specific tenant ID.
         *
         * @since 4.23.0
         * @param int|null $tenant_id The resolved site/blog ID.
         */
        $tenant_id = (int) apply_filters('mhm_rentiva_filter_tenant_id', $tenant_id);

        if ($tenant_id <= 0) {
            throw Exceptions\TenantResolutionException::not_found('No valid tenant ID detected in current request scope.');
        }

        // 2. Resolve metadata (can be customized per-tenant)
        $tenant_key         = (string) apply_filters('mhm_rentiva_filter_tenant_key', 'tenant_' . $tenant_id, $tenant_id);
        $compliance_profile = (string) apply_filters('mhm_rentiva_filter_tenant_compliance_profile', 'global', $tenant_id);
        $locale             = (string) apply_filters('mhm_rentiva_filter_tenant_locale', get_locale(), $tenant_id);

        self::$resolved_context = new TenantContext(
            $tenant_id,
            $tenant_key,
            $locale,
            $compliance_profile
        );

        return self::$resolved_context;
    }

    /**
     * Manually set a TenantContext for the current scope.
     * Primarily intended for testing environments and CLI commands.
     *
     * @since 4.23.0
     * @param TenantContext $context The context to set.
     */
    public static function set_context(TenantContext $context): void
    {
        self::$resolved_context = $context;
    }

    /**
     * Resets the resolved context cache.
     * Should be called when switching sites (e.g., switch_to_blog()).
     *
     * @since 4.23.0
     */
    public static function reset(): void
    {
        self::$resolved_context = null;
    }
}
