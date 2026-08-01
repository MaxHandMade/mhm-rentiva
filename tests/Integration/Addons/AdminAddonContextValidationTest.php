<?php
/**
 * Admin Addon Context Validation Test.
 *
 * @package MHMRentiva\Tests\Integration\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Addons;

use MHMRentiva\Admin\Addons\AddonContextTaxonomy;
use MHMRentiva\Admin\Addons\AddonContextValidator;
use MHMRentiva\Admin\Addons\AddonPostType;
use MHMRentiva\Admin\Addons\AddonPricingType;
use WP_UnitTestCase;

final class AdminAddonContextValidationTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		AddonPostType::register();
		AddonContextTaxonomy::register();
		AddonContextTaxonomy::seed_default_terms();
	}

	public function test_invalid_combo_rental_per_passenger_falls_back_to_per_booking(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => AddonPostType::POST_TYPE )
		);
		wp_set_object_terms( $id, AddonContextTaxonomy::TERM_RENTAL, AddonContextTaxonomy::TAXONOMY, false );
		update_post_meta( $id, '_mhmrentiva_addon_pricing_type', AddonPricingType::PER_PASSENGER );

		AddonContextValidator::enforce_combination( $id );

		$this->assertSame(
			AddonPricingType::PER_BOOKING,
			get_post_meta( $id, '_mhmrentiva_addon_pricing_type', true )
		);
	}

	public function test_valid_combo_transfer_per_passenger_is_preserved(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => AddonPostType::POST_TYPE )
		);
		wp_set_object_terms( $id, AddonContextTaxonomy::TERM_TRANSFER, AddonContextTaxonomy::TAXONOMY, false );
		update_post_meta( $id, '_mhmrentiva_addon_pricing_type', AddonPricingType::PER_PASSENGER );

		AddonContextValidator::enforce_combination( $id );

		$this->assertSame(
			AddonPricingType::PER_PASSENGER,
			get_post_meta( $id, '_mhmrentiva_addon_pricing_type', true )
		);
	}

	public function test_both_context_allows_all_three_types(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => AddonPostType::POST_TYPE )
		);
		wp_set_object_terms( $id, AddonContextTaxonomy::TERM_BOTH, AddonContextTaxonomy::TAXONOMY, false );

		foreach ( AddonPricingType::all() as $type ) {
			update_post_meta( $id, '_mhmrentiva_addon_pricing_type', $type );
			AddonContextValidator::enforce_combination( $id );
			$this->assertSame( $type, get_post_meta( $id, '_mhmrentiva_addon_pricing_type', true ) );
		}
	}
}
