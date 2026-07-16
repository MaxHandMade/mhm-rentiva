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
     * Route a feature decision to Pro. Lite alone: class_exists false → false.
     * Pro present: LicenseManager::canUse() performs the RSA feature-token check.
     */
    private static function canUse( string $feature ): bool {
        return class_exists( '\MHMRentiva\Admin\Licensing\LicenseManager' )
            && LicenseManager::instance()->canUse( $feature );
    }
}
