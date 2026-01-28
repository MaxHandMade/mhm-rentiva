<?php

/**
 * MHM Rentiva - Settings Analyzer Script
 *
 * NOT: WP-CLI 'eval-file' uyumluluğu için strict_types kaldırıldı.
 */

// Kural 1: Güvenlik Duvarı
if (!defined('ABSPATH')) {
    if (defined('WP_CLI') && constant('WP_CLI')) {
        // Devam et
    } else {
        exit('Access Denied.');
    }
}

/**
 * Analiz Sınıfı
 */
class MHMRentiva_Settings_Inspector
{
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function run()
    {
        $this->print_header();
        $this->inspect_database_options();
        $this->inspect_critical_keys();
        $this->print_footer();
    }

    private function print_header()
    {
        echo "\n============================================\n";
        echo "⚙️  MHM RENTIVA SETTINGS INSPECTOR (Safe Mode) ⚙️\n";
        echo "============================================\n";
    }

    private function print_footer()
    {
        echo "\n============================================\n";
    }

    /**
     * Veritabanındaki 'mhm_' ön ekli ayarları çeker.
     */
    private function inspect_database_options()
    {
        echo "\n--- 1. VERİTABANI DÖKÜMÜ (wp_options) ---\n";

        $prefix = 'mhm_%';
        $sql = $this->wpdb->prepare(
            "SELECT option_name, option_value 
             FROM {$this->wpdb->options} 
             WHERE option_name LIKE %s 
             AND option_name NOT LIKE %s 
             ORDER BY option_name ASC",
            $prefix,
            '%transient%'
        );

        $results = $this->wpdb->get_results($sql);

        if (empty($results)) {
            echo "❌ Veritabanında 'mhm_' ile başlayan ayar bulunamadı.\n";
            return;
        }

        foreach ($results as $opt) {
            $name = esc_html($opt->option_name);
            $val  = $opt->option_value;

            echo "🔹 [{$name}]: ";

            if (is_serialized($val)) {
                echo "(SERIALIZED DATA) \n";
                $unserialized = maybe_unserialize($val);
                print_r($unserialized);
            } else {
                $display_val = (strlen($val) > 100) ? substr($val, 0, 97) . '...' : $val;
                echo esc_html($display_val) . "\n";
            }
            echo "---------------------------------\n";
        }
    }

    /**
     * Kritik ayarları kontrol eder.
     */
    private function inspect_critical_keys()
    {
        echo "\n\n--- 2. KRİTİK AYAR KONTROLÜ (get_option) ---\n";

        $keys = [
            'mhm_rentiva_currency',
            'mhm_rentiva_dark_mode',
            'mhm_rentiva_brand_name',
            'mhm_rentiva_support_email',
            'mhm_rentiva_settings'
        ];

        foreach ($keys as $key) {
            $val = get_option($key, 'NOT_SET');

            echo "👉 " . esc_html($key) . ": ";

            if ($val === 'NOT_SET') {
                echo "❌ (Ayarlanmamış)\n";
            } elseif (is_array($val)) {
                echo "ARRAY (Büyük veri)\n";
            } else {
                echo esc_html((string)$val) . "\n";
            }
        }
    }
}

// Başlat
$inspector = new MHMRentiva_Settings_Inspector();
$inspector->run();
