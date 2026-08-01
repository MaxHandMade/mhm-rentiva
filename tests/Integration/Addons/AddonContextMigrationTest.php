<?php
/**
 * Addon Context Migration Test.
 *
 * @package MHMRentiva\Tests\Integration\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Addons;

use MHMRentiva\Admin\Addons\AddonContextMigration;
use MHMRentiva\Admin\Addons\AddonContextTaxonomy;
use MHMRentiva\Admin\Addons\AddonPostType;
use MHMRentiva\Admin\Addons\AddonPricingType;
use WP_UnitTestCase;

final class AddonContextMigrationTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		AddonPostType::register();
		AddonContextTaxonomy::register();
		AddonContextTaxonomy::seed_default_terms();
		delete_option( AddonContextMigration::FLAG_OPTION );
	}

	private function create_legacy_addon( string $title, ?float $price = 10.0 ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'  => AddonPostType::POST_TYPE,
				'post_title' => $title,
			)
		);
		if ( $price !== null ) {
			update_post_meta( $id, 'mhmrentiva_addon_price', $price );
		}
		wp_remove_object_terms( $id, array( 'rental', 'transfer', 'both' ), AddonContextTaxonomy::TAXONOMY );
		delete_post_meta( $id, '_mhmrentiva_addon_pricing_type' );
		return $id;
	}

	public function test_data_lane_assigns_rental_term_to_legacy_addons(): void {
		$a = $this->create_legacy_addon( 'Child Seat' );
		$b = $this->create_legacy_addon( 'GPS' );

		AddonContextMigration::run();

		$this->assertTrue( has_term( 'rental', AddonContextTaxonomy::TAXONOMY, $a ) );
		$this->assertTrue( has_term( 'rental', AddonContextTaxonomy::TAXONOMY, $b ) );
	}

	public function test_data_lane_assigns_per_booking_pricing_type(): void {
		$id = $this->create_legacy_addon( 'Insurance' );
		AddonContextMigration::run();
		$this->assertSame(
			AddonPricingType::PER_BOOKING,
			get_post_meta( $id, '_mhmrentiva_addon_pricing_type', true )
		);
	}

	public function test_data_lane_is_idempotent_on_second_run(): void {
		$id = $this->create_legacy_addon( 'Driver' );
		AddonContextMigration::run();

		// Operator manually flipped to transfer + per_passenger.
		wp_set_object_terms( $id, 'transfer', AddonContextTaxonomy::TAXONOMY, false );
		update_post_meta( $id, '_mhmrentiva_addon_pricing_type', AddonPricingType::PER_PASSENGER );

		// Re-run should not overwrite operator's choice.
		delete_option( AddonContextMigration::FLAG_OPTION ); // simulate manual reset
		AddonContextMigration::run();

		$this->assertTrue( has_term( 'transfer', AddonContextTaxonomy::TAXONOMY, $id ) );
		$this->assertFalse( has_term( 'rental', AddonContextTaxonomy::TAXONOMY, $id ) );
		$this->assertSame(
			AddonPricingType::PER_PASSENGER,
			get_post_meta( $id, '_mhmrentiva_addon_pricing_type', true )
		);
	}

	public function test_flag_option_is_set_after_run(): void {
		$this->create_legacy_addon( 'Welcome' );
		AddonContextMigration::run();
		$this->assertSame( '1', get_option( AddonContextMigration::FLAG_OPTION ) );
	}
}
