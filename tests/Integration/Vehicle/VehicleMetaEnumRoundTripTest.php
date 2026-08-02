<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vehicle;

use MHMRentiva\Admin\Vehicle\Meta\VehicleGallery;
use MHMRentiva\Admin\Vehicle\Meta\VehicleMeta;
use WP_UnitTestCase;

/**
 * Every option the vehicle editor OFFERS must survive being saved.
 *
 * The defect this locks: the sanitize_callback registered with
 * register_post_meta() carried its own hardcoded allowlist, narrower than the
 * dropdown rendered from get_transmission_types() / get_fuel_types(). A user
 * picked "CVT", saved, and the field came back "Automatic" -- no error, no
 * notice, just a different value than the one chosen. Same for LPG, CNG and
 * Hydrogen, all silently rewritten to "Petrol".
 *
 * Two lists, one decision written twice, and only one of them was maintained.
 *
 * This is a ROUND TRIP through update_post_meta() rather than a direct call to
 * the callback, because that is the path the editor actually takes: WordPress
 * runs the registered sanitize_callback inside update_metadata(). Calling the
 * callback directly would still pass if the registration were wired to some
 * other function entirely.
 *
 * The provider is derived from the canonical getters rather than typed out, so
 * an option added to a dropdown tomorrow is covered by this test the same day
 * without anyone remembering to come back here.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\VehicleMeta::register_meta_fields
 */
final class VehicleMetaEnumRoundTripTest extends WP_UnitTestCase
{
    private int $vehicle_id = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 🔴 RE-REGISTERED PER TEST, and this is a fact about the harness, not
        // about the plugin. WP_UnitTestCase::tearDown() unregisters every
        // non-core post type between tests and takes its registered meta with
        // it. Measured: the FIRST test in this class sees all 14 keys, every
        // test after it sees none.
        //
        // So a round trip needs the registration put back. What that hides --
        // whether registration would have happened on its own -- is asserted
        // separately and deliberately by
        // test_meta_registration_is_wired_for_non_admin_requests() below, which
        // is the actual lock on the is_admin() bug.
        VehicleMeta::register_meta_fields();

        $this->vehicle_id = (int) self::factory()->post->create(
            array( 'post_type' => 'mhmrentiva_vehicle' )
        );
    }

    /**
     * @dataProvider offeredTransmissionProvider
     */
    public function test_every_offered_transmission_survives_a_save(string $option): void
    {
        $this->assertMetaRoundTrips('_mhmrentiva_transmission', $option);
    }

    /**
     * @dataProvider offeredFuelTypeProvider
     */
    public function test_every_offered_fuel_type_survives_a_save(string $option): void
    {
        $this->assertMetaRoundTrips('_mhmrentiva_fuel_type', $option);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public function offeredTransmissionProvider(): array
    {
        return self::asProvider(array_keys(VehicleMeta::get_transmission_types()));
    }

    /**
     * @return array<string, array{0:string}>
     */
    public function offeredFuelTypeProvider(): array
    {
        return self::asProvider(array_keys(VehicleMeta::get_fuel_types()));
    }

    /**
     * @param array<int,string> $options
     *
     * @return array<string, array{0:string}>
     */
    private static function asProvider(array $options): array
    {
        $cases = array();
        foreach ($options as $option) {
            $cases[ $option ] = array( (string) $option );
        }

        return $cases;
    }

    /**
     * The premise assertions matter as much as the result.
     *
     * Without them a registration that silently vanished -- no sanitize_callback
     * at all -- would make every case pass, and this test would be certifying
     * nothing while looking green.
     */
    private function assertMetaRoundTrips(string $meta_key, string $option): void
    {
        $registered = get_registered_meta_keys('post', 'mhmrentiva_vehicle');

        $this->assertArrayHasKey(
            $meta_key,
            $registered,
            'Premise: ' . $meta_key . ' must be registered, or a clean round trip proves only that nothing sanitises it.'
        );
        $this->assertIsCallable(
            $registered[ $meta_key ]['sanitize_callback'] ?? null,
            'Premise: ' . $meta_key . ' must have a sanitize_callback, or there is no allowlist to be wrong.'
        );

        update_post_meta($this->vehicle_id, $meta_key, $option);

        $this->assertSame(
            $option,
            get_post_meta($this->vehicle_id, $meta_key, true),
            sprintf(
                'The editor offers "%s" for %s, but saving it produced a different value. '
                . 'The registered sanitize_callback is narrower than the dropdown that feeds it.',
                $option,
                $meta_key
            )
        );
    }

    /**
     * 🔴 The registration must be attached on a NON-ADMIN request.
     *
     * This is the lock on the bug the round trips above cannot see, because
     * their setUp puts the registration back by hand.
     *
     * VehicleMeta::register() and VehicleGallery::register() are called only
     * from Plugin::initialize_admin_services(), which runs behind
     * `if (is_admin())`. Each used to hook its own register_meta_fields() onto
     * `init` from in there, so on any request where is_admin() was false -- every
     * REST request, every front-end request -- the meta was never registered.
     * That made `'show_in_rest' => true` a claim the plugin did not honour, and
     * left sanitize_meta() with nothing to enforce: an earlier version of this
     * file proved it by storing 'rocket_powered' verbatim.
     *
     * This suite runs with is_admin() false, which is exactly the condition
     * under which the bug existed -- so asking whether the hook is attached HERE
     * is asking the real question, in the environment that matters. Re-adding
     * the admin gate makes this test red.
     */
    public function test_meta_registration_is_wired_for_non_admin_requests(): void
    {
        $this->assertFalse(
            is_admin(),
            'Premise: this suite must be a non-admin request, or the assertion below asks nothing.'
        );

        $this->assertNotFalse(
            has_action('init', array( VehicleMeta::class, 'register_meta_fields' )),
            'Vehicle meta is not registered on a non-admin request, so show_in_rest is a false claim '
            . 'and nothing sanitises a REST or front-end write.'
        );

        $this->assertNotFalse(
            has_action('init', array( VehicleGallery::class, 'register_meta_fields' )),
            'Gallery meta is not registered on a non-admin request.'
        );
    }

    /**
     * ...and a value the editor never offers is still refused.
     *
     * Widening an allowlist to the canonical list must not become "accept
     * anything". Without this, deleting the in_array() check entirely would
     * leave every test above green.
     */
    public function test_a_value_no_dropdown_offers_is_still_rejected(): void
    {
        update_post_meta($this->vehicle_id, '_mhmrentiva_transmission', 'rocket_powered');
        $this->assertSame(
            'auto',
            get_post_meta($this->vehicle_id, '_mhmrentiva_transmission', true),
            'An unoffered transmission must fall back, not be stored verbatim.'
        );

        update_post_meta($this->vehicle_id, '_mhmrentiva_fuel_type', 'plutonium');
        $this->assertSame(
            'petrol',
            get_post_meta($this->vehicle_id, '_mhmrentiva_fuel_type', true),
            'An unoffered fuel type must fall back, not be stored verbatim.'
        );
    }
}
