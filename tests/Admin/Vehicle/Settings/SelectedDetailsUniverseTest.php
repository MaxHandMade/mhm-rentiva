<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Settings;

use MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper;
use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * mhmrentiva_selected_details may only ever hold keys that exist as detail fields.
 *
 * get_core_fields() is a list of fields that cannot be removed, and it names two keys
 * that are NOT detail fields: 'image' and 'gallery_images'. Both are handled by their own
 * meta boxes and neither has an entry in get_all_available_details(). The settings save
 * used to append the core list wholesale, which put those two keys into the option; the
 * detail grid then rendered them, and because they have no label they came out as empty,
 * untranslated "Image" and "Gallery Images" boxes on every vehicle screen.
 *
 * That was not only cosmetic. The rendered box carried name="mhmrentiva_gallery_images",
 * which is the SAME name as the gallery meta box's hidden input. PHP keeps the last field
 * of a repeated name, so the empty text box won, save_gallery_images() received a
 * zero-length value, and saving a vehicle WIPED its gallery. Measured with a probe on
 * 2026-08-27: `has_field=Y field_len=0` while the hidden input held a full JSON payload.
 *
 * The read end already guarded against this ("Trap 4" in get_available_fields_map());
 * only the write end did not, so the two ends disagreed and the option was the casualty.
 *
 * WHAT THIS TEST DOES NOT COVER: it asserts the invariant on the option, not the rendered
 * markup. A duplicate field name introduced by some other route would not be caught here.
 */
final class SelectedDetailsUniverseTest extends WP_UnitTestCase
{
    /**
     * The keys that started this: in the core list, absent from the detail universe.
     */
    public function test_core_fields_names_keys_that_are_not_detail_fields(): void
    {
        $universe = array_keys(VehicleSettings::get_all_available_details());
        $core     = VehicleFeatureHelper::get_core_fields();

        $outside = array_values(array_diff($core, $universe));

        $this->assertNotEmpty(
            $outside,
            'If get_core_fields() no longer names non-detail keys, the hazard this test '
            . 'guards is gone and the intersection below is merely harmless. Read the '
            . 'write path before deleting either.'
        );
    }

    /**
     * The write path is locked at the source rather than by driving the AJAX handler:
     * ajax_save_settings() ends through wp_send_json_*, which leaves an output buffer open
     * and makes the test risky no matter who closes it. What matters here is that the core
     * list is intersected with the universe before it is appended, so that is what is
     * asserted -- and mutating the intersection away makes this fail.
     */
    public function test_the_write_path_intersects_the_core_list_with_the_universe(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/src/Admin/Vehicle/Settings/VehicleSettings.php'
        );

        $marker = 'Core fields are always selected';
        $at     = strpos($source, $marker);

        $this->assertNotFalse(
            $at,
            'The core-field enforcement block moved or was renamed. This lock reads the '
            . 'source, so it measures nothing once its anchor is gone -- find the block '
            . 'and re-point the anchor rather than deleting the test.'
        );

        $window = substr($source, $at, 700);

        $this->assertStringContainsString(
            'array_intersect',
            $window,
            'The settings save appends get_core_fields() without intersecting it with '
            . 'get_all_available_details(). That puts non-detail keys such as image and '
            . 'gallery_images into mhmrentiva_selected_details, where they render as empty '
            . 'boxes -- and the gallery one collides with the gallery meta box input name '
            . 'and wipes the gallery on save.'
        );

        $this->assertStringContainsString(
            'get_all_available_details',
            $window,
            'The intersection must be against the detail universe specifically.'
        );
    }

    /**
     * The route the test above says it does not cover -- found by reproducing the symptom
     * in a browser against the FIXED code on 2026-08-29.
     *
     * Fixing the write path stops NEW pollution. It does nothing for the sites that already
     * saved their settings: their option still holds 'image' and 'gallery_images', and on
     * those sites the vehicle screen still rendered both ghost boxes, still emitted a second
     * field named mhmrentiva_gallery_images, and still wiped the gallery on save. Measured:
     * two elements answered [name="mhmrentiva_gallery_images"] -- the real hidden input
     * carrying 1303 bytes of JSON, and an empty text box after it.
     *
     * Every guard downstream asks `isset($available_details[$key])`, and they all passed,
     * because build_available_fields() ends in a "last resort" branch that turns a key it
     * cannot identify into a label by prettifying the key itself. That branch converts
     * "I do not know this field" into "here is a field", which is what let a ghost key walk
     * past three separate checks.
     *
     * So the fix belongs here rather than in a data migration: a key that exists in none of
     * the three sources is not renderable, and manufacturing a label for it is what caused
     * data loss. Dropping it makes every already-polluted option harmless without touching
     * anyone's database.
     */
    public function test_a_key_in_no_known_source_is_dropped_rather_than_given_a_made_up_label(): void
    {
        $build = new \ReflectionMethod(
            \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::class,
            'build_available_fields'
        );
        $build->setAccessible(true);

        $available = $build->invoke(
            null,
            array('price_per_day', 'gallery_images'),   // selected: one real, one ghost
            array('price_per_day' => 'Daily Price'),    // stored labels
            array()                                     // customs
        );

        $this->assertArrayHasKey(
            'price_per_day',
            $available,
            'A field that IS in the stored labels must survive -- this test must not pass by '
            . 'emptying the result.'
        );

        $this->assertArrayNotHasKey(
            'gallery_images',
            $available,
            'gallery_images is in no source: not in the stored labels, not a custom field and '
            . 'not in the hardcoded defaults. Giving it a label makes the vehicle screen render '
            . 'a second input named mhmrentiva_gallery_images, and PHP keeps the last field of a '
            . 'repeated name -- so saving the vehicle writes an empty value over the gallery.'
        );
    }

    public function tearDown(): void
    {
        $_POST = array();
        wp_set_current_user(0);
        delete_option('mhmrentiva_selected_details');
        parent::tearDown();
    }
}
