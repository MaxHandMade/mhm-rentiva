<?php

/**
 * MHM Rentiva - Settings Synchronization Fix
 * * Amaç: 'mhm_rentiva_settings' dizisi içine hapsolmuş ayarları
 * tekil 'wp_options' kayıtlarına dönüştürür.
 */

if (!defined('ABSPATH')) {
    if (defined('WP_CLI') && constant('WP_CLI')) { /* Devam */
    } else {
        exit;
    }
}

global $wpdb;

echo "\n============================================\n";
echo "🔧 MHM RENTIVA AYAR SENKRONİZASYONU 🔧\n";
echo "============================================\n";

// 1. Ana Ayar Çuvalını Getir
$main_settings = get_option('mhm_rentiva_settings', []);

if (empty($main_settings) || !is_array($main_settings)) {
    echo "❌ HATA: 'mhm_rentiva_settings' ana ayar grubu boş veya bozuk.\n";
    exit;
}

echo "✅ Ana ayar grubu bulundu. " . count($main_settings) . " alt ayar içeriyor.\n";
echo "🔄 Senkronizasyon başlıyor...\n\n";

$count = 0;
$updated = 0;

foreach ($main_settings as $key => $value) {
    // Sadece 'mhm_' ile başlayanları veya kritik olanları alalım
    // Gereksiz alt dizileri (features, equipment vb) patlatmayalım, onlar dizi kalabilir.

    // Değer zaten veritabanında tekil olarak var mı?
    $existing = get_option($key);

    // Eğer yoksa veya boşsa, dizideki değeri oraya kopyala
    if ($existing === false || $existing === '' || $existing !== $value) {
        update_option($key, $value);
        echo "   Rule applied: [$key] -> " . (is_array($value) ? 'Array' : substr($value, 0, 40)) . " ... ✅ OK\n";
        $updated++;
    } else {
        // Zaten aynısı varsa pas geç
        // echo "   Skipped: [$key] (Zaten güncel)\n";
    }
    $count++;
}

echo "\n--------------------------------------------\n";
echo "📊 SONUÇ RAPORU:\n";
echo "   - Taranan Ayar: $count\n";
echo "   - Onarılan/Güncellenen: $updated\n";
echo "--------------------------------------------\n";

// Ekstra: Kritik 'Dark Mode' kontrolü
$dm = get_option('mhm_rentiva_dark_mode');
echo "💡 Test: Dark Mode Ayarı Şu An: " . ($dm ? $dm : 'YOK') . "\n";

echo "\n============================================\n";
echo "🚀 İŞLEM TAMAMLANDI. ŞİMDİ SİTEYİ KONTROL EDİN.\n";
