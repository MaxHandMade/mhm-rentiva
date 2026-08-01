<?php
/**
 * Addon Context Filter Test.
 *
 * @package MHMRentiva\Tests\Integration\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Addons;

use MHMRentiva\Admin\Addons\AddonContextTaxonomy;
use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Addons\AddonPostType;
use WP_UnitTestCase;

final class AddonContextFilterTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		AddonPostType::register();
		AddonContextTaxonomy::register();
		AddonContextTaxonomy::seed_default_terms();
	}

	private function make_addon( string $title, string $context ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => AddonPostType::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $id, 'mhmrentiva_addon_price', 10.0 );
		update_post_meta( $id, 'mhmrentiva_addon_enabled', '1' );
		wp_set_object_terms( $id, $context, AddonContextTaxonomy::TAXONOMY, false );
		return $id;
	}

	public function test_rental_context_includes_rental_and_both(): void {
		$rental_id   = $this->make_addon( 'Child Seat',  AddonContextTaxonomy::TERM_RENTAL );
		$transfer_id = $this->make_addon( 'Welcome Banner', AddonContextTaxonomy::TERM_TRANSFER );
		$both_id     = $this->make_addon( 'GPS', AddonContextTaxonomy::TERM_BOTH );

		$ids = array_column( AddonManager::get_available_addons( 'rental' ), 'id' );
		$this->assertContains( $rental_id, $ids );
		$this->assertContains( $both_id, $ids );
		$this->assertNotContains( $transfer_id, $ids );
	}

	public function test_transfer_context_includes_transfer_and_both(): void {
		$rental_id   = $this->make_addon( 'Child Seat', AddonContextTaxonomy::TERM_RENTAL );
		$transfer_id = $this->make_addon( 'Welcome Banner', AddonContextTaxonomy::TERM_TRANSFER );
		$both_id     = $this->make_addon( 'GPS', AddonContextTaxonomy::TERM_BOTH );

		$ids = array_column( AddonManager::get_available_addons( 'transfer' ), 'id' );
		$this->assertNotContains( $rental_id, $ids );
		$this->assertContains( $transfer_id, $ids );
		$this->assertContains( $both_id, $ids );
	}

	public function test_default_argument_is_rental_for_back_compat(): void {
		$rental_id   = $this->make_addon( 'Child Seat', AddonContextTaxonomy::TERM_RENTAL );
		$transfer_id = $this->make_addon( 'Welcome Banner', AddonContextTaxonomy::TERM_TRANSFER );

		$ids = array_column( AddonManager::get_available_addons(), 'id' );
		$this->assertContains( $rental_id, $ids );
		$this->assertNotContains( $transfer_id, $ids );
	}

	public function test_disabled_addons_are_excluded_from_both_contexts(): void {
		$id = $this->make_addon( 'Disabled', AddonContextTaxonomy::TERM_BOTH );
		update_post_meta( $id, 'mhmrentiva_addon_enabled', '0' );

		$rental_ids   = array_column( AddonManager::get_available_addons( 'rental' ), 'id' );
		$transfer_ids = array_column( AddonManager::get_available_addons( 'transfer' ), 'id' );

		$this->assertNotContains( $id, $rental_ids );
		$this->assertNotContains( $id, $transfer_ids );
	}
}
