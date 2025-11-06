<?php declare(strict_types=1);

namespace MHMRentiva\Admin\Core\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ✅ 4. AŞAMA - Taxonomy Migration (vehicle_cat → vehicle_category)
 */
final class TaxonomyMigrator
{
    /**
     * Eski vehicle_cat taxonomy'sini vehicle_category'ye migrate et
     */
    public static function migrate_vehicle_cat_to_vehicle_category(): void
    {
        global $wpdb;
        
        // Migration yapıldı mı kontrol et
        $migration_done = get_option('mhm_rentiva_taxonomy_migrated', false);
        
        if ($migration_done) {
            return; // Zaten yapılmış
        }
        
        // vehicle_cat taxonomy'sini vehicle_category'ye dönüştür
        $updated = $wpdb->update(
            $wpdb->term_taxonomy,
            ['taxonomy' => 'vehicle_category'],
            ['taxonomy' => 'vehicle_cat'],
            ['%s'],
            ['%s']
        );
        
        if ($updated !== false) {
            // Migration başarılı, işaretle
            update_option('mhm_rentiva_taxonomy_migrated', true, false);
            
            // Rewrite rules'ı flush et
            flush_rewrite_rules();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("MHM Rentiva: Taxonomy migration completed. {$updated} terms migrated from vehicle_cat to vehicle_category.");
            }
        }
    }
    
    /**
     * Migration'ı geri al (rollback)
     */
    public static function rollback_migration(): void
    {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->term_taxonomy,
            ['taxonomy' => 'vehicle_cat'],
            ['taxonomy' => 'vehicle_category'],
            ['%s'],
            ['%s']
        );
        
        delete_option('mhm_rentiva_taxonomy_migrated');
        flush_rewrite_rules();
    }
}

