<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ✅ 4. STAGE - Taxonomy Migration (vehicle_cat → vehicle_category)
 */
final class TaxonomyMigrator {

	/**
	 * Migrate old vehicle_cat taxonomy to vehicle_category
	 */
	public static function migrate_vehicle_cat_to_vehicle_category(): void {
		global $wpdb;

		// Check if migration is already done
		$migration_done = get_option( 'mhm_rentiva_taxonomy_migrated', false );

		if ( $migration_done ) {
			return; // Already done
		}

		// Convert vehicle_cat taxonomy to vehicle_category
		$updated = $wpdb->update(
			$wpdb->term_taxonomy,
			array( 'taxonomy' => 'vehicle_category' ),
			array( 'taxonomy' => 'vehicle_cat' ),
			array( '%s' ),
			array( '%s' )
		);

		if ( $updated !== false ) {
			// Migration successful, mark it
			update_option( 'mhm_rentiva_taxonomy_migrated', true, false );

			// Flush rewrite rules
			flush_rewrite_rules();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "MHM Rentiva: Taxonomy migration completed. {$updated} terms migrated from vehicle_cat to vehicle_category." );
			}
		}
	}

	/**
	 * Rollback migration
	 */
	public static function rollback_migration(): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'taxonomy' => 'vehicle_cat' ),
			array( 'taxonomy' => 'vehicle_category' ),
			array( '%s' ),
			array( '%s' )
		);

		delete_option( 'mhm_rentiva_taxonomy_migrated' );
		flush_rewrite_rules();
	}
}
