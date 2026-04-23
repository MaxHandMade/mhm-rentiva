<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * Regression test for the v4.27.4 migration-lane architectural fix.
 *
 * Through v4.27.3 the `plugins_loaded` migration trigger bailed out of ALL
 * migrations whenever `get_option('mhm_rentiva_plugin_version') ===
 * MHM_RENTIVA_VERSION`. That's exactly the state a site lands in after a
 * ZIP-replace upgrade, because the activation hook stamps the version
 * before the next `plugins_loaded` fires. The result: data cleanup
 * migrations added in a patch release were NEVER executed on upgraded
 * sites — only on fresh installs. This is what caused mhmrentiva.com to
 * still show Brand Name = '1', Currency = '1' after upgrading from
 * v4.27.0 → v4.27.2 via the WordPress admin uploader.
 *
 * The fix moves the flag-guarded data cleanup migrations OUT of the
 * version-drift block so they run on every admin request. Their own flag
 * options keep them idempotent. This test proves both migrations are safe
 * to invoke when the version stamp is already current.
 */
class MigrationLaneIndependenceTest extends WP_UnitTestCase
{
    private const LABELS_FLAG    = 'mhm_rentiva_v4271_labels_migrated';
    private const POLLUTION_FLAG = 'mhm_rentiva_v4272_test_pollution_cleaned';
    private const SETTINGS_KEY   = 'mhm_rentiva_settings';
    private const VERSION_KEY    = 'mhm_rentiva_plugin_version';

    protected function tearDown(): void
    {
        delete_option(self::LABELS_FLAG);
        delete_option(self::POLLUTION_FLAG);
        delete_option(self::SETTINGS_KEY);
        delete_option(self::VERSION_KEY);
        delete_option('mhm_vehicle_details');
        delete_option('mhm_vehicle_features');
        delete_option('mhm_vehicle_equipment');
        parent::tearDown();
    }

    /**
     * The pollution cleanup migration must run even when the plugin version
     * is already stamped to the current constant — which is the exact state
     * of a ZIP-replace upgrade after the activation hook fires.
     */
    public function test_pollution_migration_runs_after_version_already_stamped(): void
    {
        // Mimic the post-activation state: version stamp matches the
        // constant, but a pre-upgrade run of "Run All Diagnostics" left
        // pollution in mhm_rentiva_settings.
        update_option(self::VERSION_KEY, defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'current');
        update_option(self::SETTINGS_KEY, array(
            'mhm_rentiva_brand_name' => '1',
            'mhm_rentiva_currency'   => '1',
        ));
        delete_option(self::POLLUTION_FLAG);

        SettingsCore::migrate_clean_test_pollution();

        $stored = get_option(self::SETTINGS_KEY, array());
        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('mhm_rentiva_brand_name', $stored, 'Polluted brand name must be removed regardless of version stamp.');
        $this->assertArrayNotHasKey('mhm_rentiva_currency', $stored, 'Polluted currency must be removed regardless of version stamp.');
        $this->assertSame('1', get_option(self::POLLUTION_FLAG), 'Flag must be set so subsequent calls are no-ops.');
    }

    /**
     * Same guarantee for the v4.27.1 locale-leak label migration: post
     * version-stamp execution must still clean leaked option arrays.
     */
    public function test_labels_migration_runs_after_version_already_stamped(): void
    {
        update_option(self::VERSION_KEY, defined('MHM_RENTIVA_VERSION') ? MHM_RENTIVA_VERSION : 'current');
        update_option('mhm_vehicle_details', array( 'price_per_day' => 'Günlük Fiyat' ));
        update_option('mhm_vehicle_features', array( 'air_conditioning' => 'Air Conditioning' ));
        delete_option(self::LABELS_FLAG);

        VehicleMeta::migrate_remove_auto_populated_labels();

        $this->assertFalse(get_option('mhm_vehicle_details'));
        $this->assertFalse(get_option('mhm_vehicle_features'));
        $this->assertSame('1', get_option(self::LABELS_FLAG));
    }

    /**
     * Once the flag is set, repeated invocations on subsequent admin
     * requests must be cheap no-ops that do not clobber legitimate user
     * edits made after the migration already ran.
     */
    public function test_migrations_are_no_ops_after_flag_is_set(): void
    {
        update_option(self::POLLUTION_FLAG, '1');
        update_option(self::SETTINGS_KEY, array( 'mhm_rentiva_brand_name' => '1' ));

        SettingsCore::migrate_clean_test_pollution();

        $this->assertSame(
            array( 'mhm_rentiva_brand_name' => '1' ),
            get_option(self::SETTINGS_KEY),
            'After the flag is set, a legitimate user value of "1" is not treated as pollution.'
        );
    }
}
