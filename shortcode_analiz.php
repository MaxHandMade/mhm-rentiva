<?php

/**
 * MHM Rentiva - Shortcode HTML Inspector
 * Amaç: Kısa kodların ürettiği GERÇEK HTML yapısını ve Class isimlerini görmek.
 */

if (!defined('ABSPATH')) {
    if (defined('WP_CLI') && WP_CLI) { /* Devam */
    } else {
        exit;
    }
}

echo "\n============================================\n";
echo "🕵️‍♂️ SHORTCODE HTML RÖNTGENİ 🕵️‍♂️\n";
echo "============================================\n";

// İncelemek istediğimiz kritik kısa kodlar
$targets = [
    'rentiva_vehicles_grid', // Izgara görünümü (En bozuk olan)
    'rentiva_search',        // Arama formu
    'rentiva_booking_form'   // Rezervasyon formu
];

foreach ($targets as $sc) {
    echo "\n\n--- KISA KOD: [$sc] ---\n";

    // Kısa kodu çalıştır ve çıktıyı al
    $html = do_shortcode("[$sc]");

    // HTML'i temizle (sadece Class ve ID'leri görmek için)
    // Çok uzun HTML gelirse terminali doldurmasın diye özetleyeceğiz.

    if (empty($html)) {
        echo "❌ Çıktı Boş! (Kısa kod çalışmadı veya hiçbir şey döndürmedi)\n";
        continue;
    }

    // Sadece div, form, input, button etiketlerini ve classlarını ayıkla
    preg_match_all('/<(div|form|input|select|button|span|a)[^>]*>/', $html, $matches);

    if (!empty($matches[0])) {
        echo "bulunan Yapı Taşları (İlk 20 satır):\n";
        $count = 0;
        foreach ($matches[0] as $tag) {
            echo "   " . $tag . "\n";
            $count++;
            if ($count >= 20) break; // Çok uzunsa kes
        }
    } else {
        echo "⚠️ HTML etiketi bulunamadı. Ham çıktı:\n";
        echo substr(strip_tags($html), 0, 200) . "...\n";
    }

    echo "\n--------------------------------------------------\n";
}
echo "\n";
