<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Migration;

use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * Regression test for the v4.27.1 i18n leak fix.
 *
 * Historically, VehicleMeta::ensure_default_options() persisted the result of
 * get_default_details()/features()/equipment() into wp_options on first admin
 * load. That array contained __() output, so the site locale active at write
 * time (or the locale of a restored backup) was frozen into the database and
 * outranked subsequent live __() calls. The migration added in v4.27.1 clears
 * those three options a single time so rendering falls back to the canonical
 * code path.
 */
class LocaleLeakLabelsMigrationTest extends WP_UnitTestCase
{
    private const FLAG_OPTION = 'mhm_rentiva_v4271_labels_migrated';

    private const LEGACY_OPTIONS = array(
        'mhm_vehicle_details',
        'mhm_vehicle_features',
        'mhm_vehicle_equipment',
    );

    protected function tearDown(): void
    {
        delete_option(self::FLAG_OPTION);
        foreach (self::LEGACY_OPTIONS as $option) {
            delete_option($option);
        }
        parent::tearDown();
    }

    /**
     * Any legacy install with persisted label arrays should have them removed
     * on the first migration run, regardless of what language the stored
     * values are in.
     */
    public function test_migration_clears_persisted_label_arrays(): void
    {
        $turkish_details = array(
            'price_per_day' => 'Günlük Fiyat',
            'year'          => 'Model Yılı',
            'license_plate' => 'Plaka',
        );
        $english_features = array(
            'air_conditioning' => 'Air Conditioning',
            'abs_brakes'       => 'ABS Brake System',
        );
        $mixed_equipment = array(
            'spare_tire' => 'Yedek Lastik',
            'jack'       => 'Jack',
        );

        update_option('mhm_vehicle_details', $turkish_details);
        update_option('mhm_vehicle_features', $english_features);
        update_option('mhm_vehicle_equipment', $mixed_equipment);

        VehicleMeta::migrate_remove_auto_populated_labels();

        $this->assertFalse(get_option('mhm_vehicle_details'), 'Turkish-leaked details should be removed.');
        $this->assertFalse(get_option('mhm_vehicle_features'), 'English-leaked features should be removed.');
        $this->assertFalse(get_option('mhm_vehicle_equipment'), 'Mixed-locale equipment should be removed.');
    }

    /**
     * The migration must set its idempotency flag so subsequent invocations
     * (e.g. on every admin page load) become no-ops.
     */
    public function test_migration_is_idempotent(): void
    {
        update_option('mhm_vehicle_details', array( 'price_per_day' => 'Günlük Fiyat' ));

        VehicleMeta::migrate_remove_auto_populated_labels();
        $this->assertSame('1', get_option(self::FLAG_OPTION));
        $this->assertFalse(get_option('mhm_vehicle_details'));

        // A user might legitimately rename a default label after the migration
        // runs. Re-running the migration must NOT clobber that new value.
        update_option('mhm_vehicle_details', array( 'price_per_day' => 'Rental Rate' ));

        VehicleMeta::migrate_remove_auto_populated_labels();

        $this->assertSame(
            array( 'price_per_day' => 'Rental Rate' ),
            get_option('mhm_vehicle_details'),
            'Second invocation must be a no-op once the flag is set.'
        );
    }

    /**
     * Fresh installs (no legacy options) should still set the flag so the
     * migration is not reconsidered on every admin request.
     */
    public function test_migration_on_fresh_install_sets_flag(): void
    {
        foreach (self::LEGACY_OPTIONS as $option) {
            delete_option($option);
        }

        VehicleMeta::migrate_remove_auto_populated_labels();

        $this->assertSame('1', get_option(self::FLAG_OPTION));
        foreach (self::LEGACY_OPTIONS as $option) {
            $this->assertFalse(get_option($option), "$option must remain unset on a fresh install.");
        }
    }

    /**
     * Custom-field options (mhm_custom_details etc.) must be left alone — they
     * hold user-added fields that never went through the locale-leak codepath.
     */
    public function test_migration_does_not_touch_custom_field_options(): void
    {
        $custom_details = array( 'extra_insurance' => 'Additional Insurance' );
        $custom_features = array( 'premium_sound' => 'Premium Sound System' );
        $custom_equipment = array( 'ski_rack' => 'Ski Rack' );

        update_option('mhm_custom_details', $custom_details);
        update_option('mhm_custom_features', $custom_features);
        update_option('mhm_custom_equipment', $custom_equipment);

        VehicleMeta::migrate_remove_auto_populated_labels();

        $this->assertSame($custom_details, get_option('mhm_custom_details'));
        $this->assertSame($custom_features, get_option('mhm_custom_features'));
        $this->assertSame($custom_equipment, get_option('mhm_custom_equipment'));

        delete_option('mhm_custom_details');
        delete_option('mhm_custom_features');
        delete_option('mhm_custom_equipment');
    }
}
