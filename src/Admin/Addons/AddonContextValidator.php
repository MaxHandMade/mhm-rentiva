<?php
/**
 * Addon Context Validator.
 *
 * @package MHMRentiva\Admin\Addons
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Addons;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side guard: when a `vehicle_addon` is saved, verify the chosen pricing type
 * is allowed for the chosen context. Invalid combos snap back to per_booking
 * and raise a settings error visible in the admin notices area.
 */
final class AddonContextValidator {

	/**
	 * Register the validator hook on `save_post_mhmrentiva_addon`.
	 * Runs at priority 20 (after AddonContextMetaBox::save at priority 10).
	 */
	public static function register(): void {
		add_action( 'save_post_' . AddonPostType::POST_TYPE, array( self::class, 'enforce_combination' ), 20 );
	}

	/**
	 * Enforce valid pricing-type↔context combinations.
	 * If the combination is invalid, reset pricing-type to per_booking and log a notice.
	 *
	 * @param int $post_id Post ID of the addon being saved.
	 */
	public static function enforce_combination( int $post_id ): void {
		// Skip revisions — only validate published drafts.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Read the persisted context term (saved by AddonContextMetaBox at priority 10).
		$context_terms = wp_get_object_terms(
			$post_id,
			AddonContextTaxonomy::TAXONOMY,
			array( 'fields' => 'slugs' )
		);
		$context       = ( ! is_wp_error( $context_terms ) && ! empty( $context_terms ) )
			? (string) $context_terms[0]
			: AddonContextTaxonomy::TERM_RENTAL;

		// Read the pricing-type meta; sanitize to a known value.
		$current = AddonPricingType::sanitize( get_post_meta( $post_id, '_mhmrentiva_addon_pricing_type', true ) );

		// Get the allow-list for this context.
		$allowed = AddonPricingType::allowed_for_context( $context );

		// If pricing-type is not in the allow-list, reset to per_booking.
		if ( ! in_array( $current, $allowed, true ) ) {
			update_post_meta( $post_id, '_mhmrentiva_addon_pricing_type', AddonPricingType::PER_BOOKING );

			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					'mhmrentiva_addon',
					'invalid_combo',
					__(
						'Invalid pricing/context combination — pricing type reset to "per booking".',
						'mhm-rentiva'
					),
					'warning'
				);
			}
		}
	}
}
