<?php
/**
 * Transfer Addon Cart Integration Test.
 *
 * @package MHMRentiva\Tests\Integration\Transfer
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Transfer;

use MHMRentiva\Admin\Addons\AddonContextTaxonomy;
use MHMRentiva\Admin\Addons\AddonPostType;
use MHMRentiva\Admin\Transfer\Integration\TransferCartIntegration;
use WP_UnitTestCase;

final class TransferAddonCartIntegrationTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		AddonPostType::register();
		AddonContextTaxonomy::register();
		AddonContextTaxonomy::seed_default_terms();
	}

	private function make_transfer_addon( float $price = 15.0, string $type = 'per_passenger', bool $required = false ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => AddonPostType::POST_TYPE,
				'post_title'  => 'VIP Asistan',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, 'addon_price', $price );
		update_post_meta( $id, 'addon_enabled', '1' );
		update_post_meta( $id, '_mhm_addon_pricing_type', $type );
		update_post_meta( $id, 'addon_required', $required ? '1' : '0' );
		wp_set_object_terms( $id, AddonContextTaxonomy::TERM_TRANSFER, AddonContextTaxonomy::TAXONOMY, false );
		return $id;
	}

	public function test_validate_addon_ids_keeps_only_transfer_or_both_publish_enabled(): void {
		$valid_id    = $this->make_transfer_addon();
		$rental_only = self::factory()->post->create(
			array( 'post_type' => AddonPostType::POST_TYPE, 'post_status' => 'publish' )
		);
		update_post_meta( $rental_only, 'addon_enabled', '1' );
		wp_set_object_terms( $rental_only, AddonContextTaxonomy::TERM_RENTAL, AddonContextTaxonomy::TAXONOMY, false );

		$allowed = TransferCartIntegration::filter_valid_addon_ids(
			array( $valid_id, $rental_only, 99999 )
		);
		$this->assertSame( array( $valid_id ), $allowed );
	}

	public function test_compute_addon_total_uses_passenger_count_for_per_passenger(): void {
		$id = $this->make_transfer_addon( 15.0, 'per_passenger' );
		$total = TransferCartIntegration::compute_addon_total(
			array( $id ),
			array( 'adults' => 2, 'children' => 1 )
		);
		$this->assertSame( 45.0, $total );
	}

	public function test_compute_addon_total_uses_flat_price_for_per_booking(): void {
		$id = $this->make_transfer_addon( 50.0, 'per_booking' );
		$total = TransferCartIntegration::compute_addon_total(
			array( $id ),
			array( 'adults' => 5, 'children' => 0 )
		);
		$this->assertSame( 50.0, $total );
	}

	public function test_required_addon_validation_fails_when_missing(): void {
		$required_id = $this->make_transfer_addon( 50.0, 'per_booking', true );
		$error = TransferCartIntegration::validate_required_addons(
			array(),
			'transfer'
		);
		$this->assertNotNull( $error );
		$this->assertStringContainsString( 'VIP Asistan', $error );
	}

	public function test_required_addon_validation_passes_when_present(): void {
		$required_id = $this->make_transfer_addon( 50.0, 'per_booking', true );
		$error = TransferCartIntegration::validate_required_addons(
			array( $required_id ),
			'transfer'
		);
		$this->assertNull( $error );
	}

	public function test_build_addon_details_includes_pricing_metadata(): void {
		$id = $this->make_transfer_addon( 15.0, 'per_passenger' );
		$details = TransferCartIntegration::build_addon_details(
			array( $id ),
			array( 'adults' => 2, 'children' => 1 )
		);
		$this->assertCount( 1, $details );
		$this->assertSame( $id, $details[0]['id'] );
		$this->assertSame( 'per_passenger', $details[0]['pricing_type'] );
		$this->assertSame( 3, $details[0]['multiplier'] );
		$this->assertSame( 45.0, $details[0]['line_total'] );
	}
}
