<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Licensing;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Thin edition router. Lite ships this class; Pro does NOT (a duplicate would be
 * fatal). Every gate is false when Pro is absent — each Pro reference sits behind
 * an inline class_exists() on a LITERAL FQN string, so both PHPStan and
 * bin/check-guarded-refs.php can see it. The real license/RSA decision lives in
 * Pro's LicenseManager::canUse() (added in Faz 2b); Mode only routes.
 */
final class Mode {

    public static function isPro(): bool {
        return class_exists( '\MHMRentiva\Admin\Licensing\LicenseManager' )
            && LicenseManager::instance()->isActive();
    }

    public static function isLite(): bool {
        return ! self::isPro();
    }

    /** Back-compat alias. */
    public static function is_pro(): bool {
        return self::isPro();
    }

    public static function canUseVendorPayout(): bool {
        return self::canUse( 'vendor_marketplace' );
    }

    public static function canUseVendorMarketplace(): bool {
        return self::canUse( 'vendor_marketplace' );
    }

    public static function canUseMessages(): bool {
        return self::canUse( 'messaging' );
    }

    public static function canUseAdvancedReports(): bool {
        return self::canUse( 'advanced_reports' );
    }

    public static function canUseExport(): bool {
        return self::canUse( 'export' );
    }

    /**
     * GDPR / data-retention tooling. The licence server issues a `gdpr_tools`
     * feature token; without it the export/deletion/consent AJAX, the visitor
     * consent notice, and — critically — the daily retention cron that anonymises
     * or deletes users must all stay off on an unlicensed site.
     */
    public static function canUseGdpr(): bool {
        return self::canUse( 'gdpr_tools' );
    }

    /**
     * Seam decision for the registries that must drop a feature together:
     * ShortcodeServiceProvider::drop_absent_pro_seams() and
     * ElementorIntegration::pro_widget_classes(). BlockRegistry no longer consults
     * this method — the `mhm_rentiva_blocks` seam inversion moved block gating to
     * Pro's own BlockExtensions filter subscriber, which asks
     * \MHMRentiva\Pro\Edition instead.
     *
     * Those registries only ever asked class_exists() — a PRESENCE check. With Pro
     * installed but unlicensed the class exists, so the seam registered and the
     * full UI rendered for free. They must ask the licence too, and they must all
     * ask the SAME question: a shortcode that registers while a sibling seam was
     * dropped prints the raw `[rentiva_transfer_search]` text to visitors.
     *
     * @param string|null $feature Licence feature key, 'pro' for the whole edition,
     *                             or null for a seam with no licence requirement.
     */
    public static function allowsSeam( ?string $feature ): bool {
        if ( null === $feature ) {
            return true;
        }
        if ( 'pro' === $feature ) {
            return self::isPro();
        }

        return self::canUse( $feature );
    }

    /**
     * Route a feature decision to Pro. Lite alone: class_exists false → false.
     * Pro present: LicenseManager::canUse() performs the RSA feature-token check.
     */
    private static function canUse( string $feature ): bool {
        return class_exists( '\MHMRentiva\Admin\Licensing\LicenseManager' )
            && LicenseManager::instance()->canUse( $feature );
    }
}
