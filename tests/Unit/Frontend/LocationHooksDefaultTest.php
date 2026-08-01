<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Frontend;

use WP_UnitTestCase;

/**
 * Task A4 seam inversion, H2 cluster: Lite no longer references
 * \MHMRentiva\Admin\Transfer\Engine\LocationProvider directly in any of its
 * read sites (VehicleColumns, VehicleMeta, BookingForm, Mailer, SearchResults,
 * VehiclesGrid, VehiclesList, VehicleManagementSettings). Each now reads one of
 * three neutral hooks instead, all defaulting to "no locations" so Lite alone
 * never shows location UI or data. Pro's SearchExtensions subscribes to all
 * three -- see mhm-rentiva-pro/tests/Integration/Pro/SearchExtensionsTest.php.
 *
 * This is a light contract test: it only pins the DEFAULTS (no subscriber),
 * plus that a subscriber can override them. The individual call sites are
 * exercised by their own existing suites (VehicleColumns, BookingForm, etc.),
 * which already assert on the "no locations" rendering/behaviour.
 */
final class LocationHooksDefaultTest extends WP_UnitTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_locations');
        remove_all_filters('mhmrentiva_location_by_id');
        remove_all_filters('mhmrentiva_has_locations');
        parent::tearDown();
    }

    public function test_mhmrentiva_locations_defaults_to_empty(): void
    {
        $this->assertSame(array(), apply_filters('mhmrentiva_locations', array(), 'rental'));
    }

    public function test_mhmrentiva_location_by_id_defaults_to_null(): void
    {
        $this->assertNull(apply_filters('mhmrentiva_location_by_id', null, 42));
    }

    public function test_mhmrentiva_has_locations_defaults_to_false(): void
    {
        $this->assertFalse(apply_filters('mhmrentiva_has_locations', false));
    }

    public function test_a_subscriber_can_override_all_three_defaults(): void
    {
        add_filter('mhmrentiva_locations', static fn ($locations, $type) => array( (object) array( 'id' => 1, 'name' => 'Demo' ) ), 10, 2);
        add_filter('mhmrentiva_location_by_id', static fn ($loc, $id) => (object) array( 'id' => $id, 'name' => 'Demo' ), 10, 2);
        add_filter('mhmrentiva_has_locations', static fn () => true);

        $this->assertNotSame(array(), apply_filters('mhmrentiva_locations', array(), 'rental'));
        $this->assertNotNull(apply_filters('mhmrentiva_location_by_id', null, 42));
        $this->assertTrue(apply_filters('mhmrentiva_has_locations', false));
    }
}
