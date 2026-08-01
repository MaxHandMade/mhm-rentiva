<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * Regression test for the v4.27.2 Settings Testing pollution cleanup.
 *
 * Earlier builds of SettingsTester::test_settings_save() flipped empty strings
 * to '1' inside the REAL sanitizer, then only restored the explicitly tested
 * keys. Collateral writes to other tab fields survived as '1' / '0' junk,
 * producing the Brand Name = "1", Cancellation Deadline = 1 regression on
 * fresh installs that ran "Run All Diagnostics" once.
 *
 * The migration clears the pollution fingerprint from free-text / email / URL
 * / currency fields so SettingsCore::get() can fall through to its hardcoded
 * defaults. Numeric fields are left alone (a user may have deliberately saved
 * 1 there) and custom string values are left alone (only the exact '0' / '1'
 * polluted marker is removed).
 */
class SettingsTestPollutionMigrationTest extends WP_UnitTestCase
{
    private const FLAG_OPTION   = 'mhmrentiva_v4272_test_pollution_cleaned';
    private const OPTION_NAME   = 'mhmrentiva_settings';

    protected function tearDown(): void
    {
        delete_option(self::FLAG_OPTION);
        delete_option(self::OPTION_NAME);
        parent::tearDown();
    }

    /**
     * Polluted entries in free-text / email / URL / currency fields should be
     * removed so the getter falls back to defaults.
     */
    public function test_migration_removes_flip_pollution_from_text_fields(): void
    {
        update_option(self::OPTION_NAME, array(
            'mhmrentiva_brand_name'          => '1',
            'mhmrentiva_email_from_name'     => '1',
            'mhmrentiva_email_from_address'  => '1',
            'mhmrentiva_booking_url'         => '1',
            'mhmrentiva_currency'            => '1',
        ));

        SettingsCore::migrate_clean_test_pollution();

        $stored = get_option(self::OPTION_NAME, array());
        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('mhmrentiva_brand_name', $stored);
        $this->assertArrayNotHasKey('mhmrentiva_email_from_name', $stored);
        $this->assertArrayNotHasKey('mhmrentiva_email_from_address', $stored);
        $this->assertArrayNotHasKey('mhmrentiva_booking_url', $stored);
        $this->assertArrayNotHasKey('mhmrentiva_currency', $stored);
    }

    /**
     * Legitimate user-entered values must survive the migration untouched.
     */
    public function test_migration_preserves_genuine_user_values(): void
    {
        update_option(self::OPTION_NAME, array(
            'mhmrentiva_brand_name'          => 'Otokira Rent a Car',
            'mhmrentiva_email_from_name'     => 'Support Team',
            'mhmrentiva_email_from_address'  => 'support@example.com',
            'mhmrentiva_booking_url'         => '/reservations',
            'mhmrentiva_currency'            => 'EUR',
        ));

        SettingsCore::migrate_clean_test_pollution();

        $stored = get_option(self::OPTION_NAME, array());
        $this->assertSame('Otokira Rent a Car', $stored['mhmrentiva_brand_name']);
        $this->assertSame('Support Team', $stored['mhmrentiva_email_from_name']);
        $this->assertSame('support@example.com', $stored['mhmrentiva_email_from_address']);
        $this->assertSame('/reservations', $stored['mhmrentiva_booking_url']);
        $this->assertSame('EUR', $stored['mhmrentiva_currency']);
    }

    /**
     * Numeric fields (booking deadlines etc.) are not in the polluted-key list
     * because a user might legitimately save 1 there. They must be left alone.
     */
    public function test_migration_leaves_numeric_fields_alone(): void
    {
        update_option(self::OPTION_NAME, array(
            'mhmrentiva_booking_cancellation_deadline_hours' => 1,
            'mhmrentiva_booking_payment_deadline_minutes'    => 1,
            'mhmrentiva_brand_name'                          => '1',
        ));

        SettingsCore::migrate_clean_test_pollution();

        $stored = get_option(self::OPTION_NAME, array());
        $this->assertSame(1, $stored['mhmrentiva_booking_cancellation_deadline_hours']);
        $this->assertSame(1, $stored['mhmrentiva_booking_payment_deadline_minutes']);
        $this->assertArrayNotHasKey('mhmrentiva_brand_name', $stored, 'Text field pollution still removed.');
    }

    /**
     * The idempotency flag must be set so subsequent calls are no-ops even
     * when a user legitimately enters "1" as a brand name later on.
     */
    public function test_migration_is_idempotent(): void
    {
        update_option(self::OPTION_NAME, array( 'mhmrentiva_brand_name' => '1' ));

        SettingsCore::migrate_clean_test_pollution();
        $this->assertSame('1', get_option(self::FLAG_OPTION));
        $stored = get_option(self::OPTION_NAME, array());
        $this->assertArrayNotHasKey('mhmrentiva_brand_name', $stored);

        // Simulate a later legitimate brand name of "1" (rare but valid edge
        // case). The migration must not clobber it because the flag is set.
        update_option(self::OPTION_NAME, array( 'mhmrentiva_brand_name' => '1' ));
        SettingsCore::migrate_clean_test_pollution();
        $this->assertSame('1', get_option(self::OPTION_NAME)['mhmrentiva_brand_name']);
    }

    /**
     * Missing option or empty array should still set the flag so the
     * migration does not re-run on every admin request.
     */
    public function test_migration_on_fresh_install_sets_flag(): void
    {
        delete_option(self::OPTION_NAME);

        SettingsCore::migrate_clean_test_pollution();

        $this->assertSame('1', get_option(self::FLAG_OPTION));
        $this->assertFalse(get_option(self::OPTION_NAME));
    }
}
