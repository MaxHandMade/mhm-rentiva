<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * Regression test for the v4.64.1 second-pass pollution cleanup.
 *
 * On mhmrentiva.com the v4.27.2 migration flag (mhm_rentiva_v4272_test_pollution_cleaned)
 * was already stamped "done" before the collateral '1' pollution actually
 * happened, so SettingsCore::migrate_clean_test_pollution() never ran again to
 * catch it. SettingsCore::migrate_reclean_test_pollution() reuses the same
 * fingerprint check under its own flag so residual pollution gets cleared once
 * on upgrade, independent of whatever state the old flag is in.
 */
class SettingsTestReCleanPollutionMigrationTest extends WP_UnitTestCase
{
    private const OLD_FLAG_OPTION = 'mhm_rentiva_v4272_test_pollution_cleaned';
    private const NEW_FLAG_OPTION = 'mhm_rentiva_v4641_test_pollution_recleaned';
    private const OPTION_NAME     = 'mhm_rentiva_settings';

    protected function tearDown(): void
    {
        delete_option(self::OLD_FLAG_OPTION);
        delete_option(self::NEW_FLAG_OPTION);
        delete_option(self::OPTION_NAME);
        parent::tearDown();
    }

    /**
     * The exact mhmrentiva.com scenario: the old flag is already set (the
     * original migration ran and considered itself done), but the settings
     * array still carries the '1' pollution fingerprint. The new migration
     * must clean it regardless of the old flag's state.
     */
    public function test_recleans_pollution_left_behind_by_an_already_flagged_old_migration(): void
    {
        update_option(self::OLD_FLAG_OPTION, '1');
        update_option(self::OPTION_NAME, array(
            'mhm_rentiva_brand_name'    => '1',
            'mhm_rentiva_contact_phone' => '1',
        ));

        SettingsCore::migrate_reclean_test_pollution();

        $stored = get_option(self::OPTION_NAME, array());
        $this->assertArrayNotHasKey('mhm_rentiva_brand_name', $stored);
        $this->assertArrayNotHasKey('mhm_rentiva_contact_phone', $stored);
    }

    /**
     * Legitimate user-entered values must survive untouched.
     */
    public function test_preserves_genuine_user_values(): void
    {
        update_option(self::OPTION_NAME, array(
            'mhm_rentiva_brand_name'    => 'Otokira Rent a Car',
            'mhm_rentiva_contact_phone' => '+90 555 123 45 67',
        ));

        SettingsCore::migrate_reclean_test_pollution();

        $stored = get_option(self::OPTION_NAME, array());
        $this->assertSame('Otokira Rent a Car', $stored['mhm_rentiva_brand_name']);
        $this->assertSame('+90 555 123 45 67', $stored['mhm_rentiva_contact_phone']);
    }

    /**
     * The new flag must be set so repeat calls are no-ops even when a user
     * legitimately enters "1" as a brand name later on.
     */
    public function test_is_idempotent_via_its_own_flag(): void
    {
        update_option(self::OPTION_NAME, array( 'mhm_rentiva_brand_name' => '1' ));

        SettingsCore::migrate_reclean_test_pollution();
        $this->assertSame('1', get_option(self::NEW_FLAG_OPTION));
        $stored = get_option(self::OPTION_NAME, array());
        $this->assertArrayNotHasKey('mhm_rentiva_brand_name', $stored);

        update_option(self::OPTION_NAME, array( 'mhm_rentiva_brand_name' => '1' ));
        SettingsCore::migrate_reclean_test_pollution();
        $this->assertSame('1', get_option(self::OPTION_NAME)['mhm_rentiva_brand_name']);
    }
}
