<?php
/**
 * Addon Context Taxonomy Test.
 *
 * @package MHMRentiva\Tests\Integration\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Addons;

use MHMRentiva\Admin\Addons\AddonContextTaxonomy;
use WP_UnitTestCase;

final class AddonContextTaxonomyTest extends WP_UnitTestCase {

    public function test_taxonomy_is_registered(): void {
        AddonContextTaxonomy::register();
        $this->assertTrue( taxonomy_exists( 'mhmrentiva_addon_context' ) );
    }

    public function test_taxonomy_is_attached_to_vehicle_addon(): void {
        AddonContextTaxonomy::register();
        $tax = get_taxonomy( 'mhmrentiva_addon_context' );
        $this->assertContains( 'mhmrentiva_addon', $tax->object_type );
    }

    public function test_three_default_terms_are_seeded(): void {
        AddonContextTaxonomy::register();
        AddonContextTaxonomy::seed_default_terms();
        foreach ( array( 'rental', 'transfer', 'both' ) as $slug ) {
            $this->assertNotFalse(
                term_exists( $slug, 'mhmrentiva_addon_context' ),
                "Term '$slug' should exist"
            );
        }
    }

    public function test_seeding_is_idempotent(): void {
        AddonContextTaxonomy::register();
        AddonContextTaxonomy::seed_default_terms();
        AddonContextTaxonomy::seed_default_terms();
        $terms = get_terms(
            array(
                'taxonomy'   => 'mhmrentiva_addon_context',
                'hide_empty' => false,
                'fields'     => 'slugs',
            )
        );
        sort( $terms );
        $this->assertSame( array( 'both', 'rental', 'transfer' ), $terms );
    }

    public function test_show_in_rest_is_enabled(): void {
        AddonContextTaxonomy::register();
        $tax = get_taxonomy( 'mhmrentiva_addon_context' );
        $this->assertTrue( $tax->show_in_rest );
    }

    public function test_pricing_type_meta_is_registered_in_rest(): void {
        AddonContextTaxonomy::register();
        \MHMRentiva\Admin\Addons\AddonPostType::register_pricing_type_meta();

        $registered = get_registered_meta_keys( 'post', \MHMRentiva\Admin\Addons\AddonPostType::POST_TYPE );
        $this->assertArrayHasKey( '_mhmrentiva_addon_pricing_type', $registered );
        $this->assertTrue( $registered['_mhmrentiva_addon_pricing_type']['show_in_rest'] );
        $this->assertSame( 'per_booking', $registered['_mhmrentiva_addon_pricing_type']['default'] );
    }
}
