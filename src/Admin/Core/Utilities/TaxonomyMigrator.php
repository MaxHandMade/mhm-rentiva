<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * ✅ 4. STAGE - Taxonomy Migration (vehicle_cat → vehicle_category)
 */
final class TaxonomyMigrator
{

	/**
	 * Migrate old vehicle_cat taxonomy to vehicle_category
	 */
	public static function migrate_vehicle_cat_to_vehicle_category(): void
	{
		global $wpdb;

		// Check if migration is already done
		$migration_done = get_option('mhm_rentiva_taxonomy_migrated', false);

		if ($migration_done) {
			return; // Already done
		}

		// Convert vehicle_cat taxonomy to vehicle_category
		$updated = $wpdb->update(
			$wpdb->term_taxonomy,
			array('taxonomy' => 'vehicle_category'),
			array('taxonomy' => 'vehicle_cat'),
			array('%s'),
			array('%s')
		);

		if ($updated !== false) {
			// Migration successful, mark it
			update_option('mhm_rentiva_taxonomy_migrated', true, false);

			// Flush rewrite rules
			flush_rewrite_rules();

			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::info('Taxonomy Migrated', array('updated_terms' => $updated));
		}
	}

	/**
	 * Rollback migration
	 */
	public static function rollback_migration(): void
	{
		global $wpdb;

		$wpdb->update(
			$wpdb->term_taxonomy,
			array('taxonomy' => 'vehicle_cat'),
			array('taxonomy' => 'vehicle_category'),
			array('%s'),
			array('%s')
		);

		delete_option('mhm_rentiva_taxonomy_migrated');
		flush_rewrite_rules();
	}
}
