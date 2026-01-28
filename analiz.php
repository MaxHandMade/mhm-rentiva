<?php
// Bu dosya WP-CLI ile çalıştırılacak
global $wpdb;

echo "\n============================================\n";
echo "🕵️‍♂️ MHM RENTIVA DETAYLI RÖNTGEN RAPORU 🕵️‍♂️\n";
echo "============================================\n";

// 1. ARAÇ META ANALİZİ (Kayıp Anahtarları Bulalım)
echo "\n--- 1. ARAÇ (VEHICLE) DETAYLARI ---\n";
// Son güncellenen aracı getir (Senin elle düzenlediğin araç)
$vehicles = get_posts(['post_type' => 'vehicle', 'numberposts' => 1, 'orderby' => 'modified']);

if ($vehicles) {
    $v = $vehicles[0];
    echo "İncelenen Araç: " . $v->post_title . " (ID: " . $v->ID . ")\n";
    echo "--------------------------------------------\n";

    $meta = get_post_meta($v->ID);

    // Özellikle aradığımız kritik değerler
    $targets = ['2030', 'Fıstık Yeşili', '5.5', '9999', '55', '11'];

    foreach ($meta as $key => $val) {
        $value = $val[0];

        // 1. Hedef değerlerden biri mi?
        foreach ($targets as $t) {
            if (strpos($value, $t) !== false) {
                echo "✅ BULUNDU! Değer: [$t]  ---> Key: [$key]\n";
            }
        }

        // 2. Transfer/VIP ile ilgili mi?
        if (strpos($key, 'service') !== false || strpos($key, 'transfer') !== false || strpos($key, 'vip') !== false) {
            echo "ℹ️ Transfer Ayarı Olabilir: [$key] => $value\n";
        }

        // 3. Fiyat/Depozito ile ilgili mi?
        if (strpos($key, 'price') !== false || strpos($key, 'deposit') !== false) {
            echo "💰 Finans Ayarı: [$key] => $value\n";
        }
    }

    echo "\n--- KATEGORİ DURUMU ---\n";
    $terms = wp_get_post_terms($v->ID, 'vehicle_category'); // Senin doğruladığın slug
    if (!empty($terms)) {
        foreach ($terms as $t) echo "✅ Kategori Atanmış: " . $t->name . " (ID: " . $t->term_id . ")\n";
    } else {
        echo "❌ Bu araca hiç kategori atanmamış. (Taxonomy: vehicle_category)\n";
        // Belki başka bir taxonomy kullanıyordur? Tüm taxonomyleri dök.
        echo "   Sistemdeki Tüm Taxonomyler:\n";
        print_r(get_object_taxonomies($v));
    }
} else {
    echo "❌ Sistemde hiç araç bulunamadı.\n";
}

// 2. TRANSFER TABLOSU SÜTUNLARI
echo "\n\n--- 2. TRANSFER SQL TABLO YAPISI ---\n";
$table_loc = $wpdb->prefix . 'mhm_rentiva_transfer_locations';
if ($wpdb->get_var("SHOW TABLES LIKE '$table_loc'") == $table_loc) {
    echo "Tablo: $table_loc\n";
    $cols = $wpdb->get_results("DESCRIBE $table_loc");
    foreach ($cols as $c) {
        echo "   👉 Sütun: " . $c->Field . "\n";
    }
} else {
    echo "❌ Transfer tablosu bulunamadı. Adı farklı olabilir.\n";
}

// 3. MÜŞTERİ TELEFONU (Admin Listesi İçin)
echo "\n\n--- 3. MÜŞTERİ TELEFON VERİSİ ---\n";
// Fatura telefonu dolu olan bir kullanıcı bul
$users = get_users(['meta_key' => 'billing_phone', 'number' => 1]);
if ($users) {
    $u = $users[0];
    echo "Müşteri: " . $u->display_name . " (ID: " . $u->ID . ")\n";
    $umeta = get_user_meta($u->ID);
    foreach ($umeta as $k => $v) {
        // İçinde 'phone', 'mobile' veya 'tel' geçen tüm anahtarları göster
        if (preg_match('/(phone|mobile|tel|billing)/i', $k)) {
            echo "📞 Telefon Adayı: [$k] => " . $v[0] . "\n";
        }
    }
} else {
    echo "❌ Telefonu kayıtlı müşteri bulunamadı.\n";
}

echo "\n============================================\n";
