<?php
/**
 * Addon Context Migration.
 *
 * @package MHMRentiva\Admin\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data lane: assigns the `rental` term and `per_booking` pricing type
 * to every legacy `vehicle_addon` post that has neither.
 *
 * Gated by the option flag `mhm_rentiva_addon_context_migrated_4_36_0`.
 * Idempotent: never overwrites a value the operator has already set.
 */
final class AddonContextMigration {

	public const FLAG_OPTION = 'mhm_rentiva_addon_context_migrated_4_36_0';

	public static function register(): void {
		add_action( 'init', array( self::class, 'maybe_run' ), 20 );
	}

	public static function maybe_run(): void {
		if ( get_option( self::FLAG_OPTION ) === '1' ) {
			return;
		}
		self::run();
	}

	public static function run(): void {
		$addons = get_posts(
			array(
				'post_type'      => AddonPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $addons as $addon_id ) {
			$existing_terms = wp_get_object_terms(
				$addon_id,
				AddonContextTaxonomy::TAXONOMY,
				array( 'fields' => 'slugs' )
			);
			if ( ! is_wp_error( $existing_terms ) && empty( $existing_terms ) ) {
				wp_set_object_terms( $addon_id, AddonContextTaxonomy::TERM_RENTAL, AddonContextTaxonomy::TAXONOMY, false );
			}

			if ( get_post_meta( $addon_id, '_mhm_addon_pricing_type', true ) === '' ) {
				update_post_meta( $addon_id, '_mhm_addon_pricing_type', AddonPricingType::PER_BOOKING );
			}
		}

		update_option( self::FLAG_OPTION, '1', false );
	}
}
