<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * One-time backfill of `_rentiva_vendor_slug` for existing vendors.
 *
 * Idempotent — guarded by the `mhm_rentiva_vendor_slug_migrated_v4_37_0`
 * site option. The migration is also skipped when the Pro gate is inactive
 * so that data is preserved across temporary deactivations (running it under
 * Lite would silently no-op users for whom slug assignment is a Pro feature).
 *
 * @since 4.37.0
 */
final class VendorSlugMigration
{
    public const VERSION_FLAG = 'mhm_rentiva_vendor_slug_migrated_v4_37_0';

    public static function run(): void
    {
        if (get_option(self::VERSION_FLAG)) {
            return;
        }
        if (!\MHMRentiva\Admin\Licensing\Mode::canUseVendorMarketplace()) {
            return;
        }

        $vendor_users = get_users([
            'role'   => 'rentiva_vendor',
            'fields' => ['ID'],
            'number' => -1,
        ]);

        $assigned = 0;
        foreach ($vendor_users as $u) {
            $user_id  = (int) $u->ID;
            $existing = (string) get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true);
            if ($existing !== '') {
                continue;
            }
            $slug = VendorSlugManager::assign_slug($user_id);
            if ($slug !== '') {
                $assigned++;
            }
        }

        update_option(self::VERSION_FLAG, [
            'completed_at'   => gmdate('Y-m-d H:i:s'),
            'assigned'       => $assigned,
            'plugin_version' => defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'unknown',
        ]);
    }

    public static function reset(): void
    {
        delete_option(self::VERSION_FLAG);
    }
}
