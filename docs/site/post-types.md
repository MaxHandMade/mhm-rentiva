# Araçlar (Vehicles)

MHM Rentiva, araç kiralama süreçlerini yönetmek için `vehicle` adında özel bir yazı türü (Custom Post Type) kullanır. Bu yazı türü, kiralık araçlarınızın tüm bilgilerini saklar ve yönetir.

## Özellikler

*   **Yazı Türü (Slug):** `vehicle`
*   **Desteklenen Alanlar:**
    *   Başlık (Title)
    *   İçerik Editörü (Editor) - Araç açıklaması için
    *   Öne Çıkan Görsel (Thumbnail) - Araç ana görseli
    *   Özet (Excerpt) - Kısa açıklama
    *   Yorumlar (Comments) - Araç değerlendirmeleri için

## Meta Alanları (Custom Fields)

Araçlar için aşağıdaki özel alanlar kullanılır:

### Temel Bilgiler
*   `_mhm_rentiva_plate`: Plaka
*   `_mhm_rentiva_brand`: Marka
*   `_mhm_rentiva_model`: Model
*   `_mhm_rentiva_year`: Model Yılı
*   `_mhm_rentiva_color`: Renk

### Teknik Detaylar
*   `_mhm_rentiva_transmission`: Vites Tipi (Manuel/Otomatik)
*   `_mhm_rentiva_fuel_type`: Yakıt Tipi (Benzin/Dizel/Elektrik/Hibrit)
*   `_mhm_rentiva_engine_power`: Motor Gücü
*   `_mhm_rentiva_seats`: Koltuk Sayısı
*   `_mhm_rentiva_doors`: Kapı Sayısı
*   `_mhm_rentiva_mileage`: Kilometre

### Fiyatlandırma
*   `_mhm_rentiva_price_per_day`: Günlük Fiyat
*   `_mhm_rentiva_deposit`: Depozito Tutarı

## Sınıflandırmalar (Taxonomies)

Araçları gruplandırmak için aşağıdaki taksonomiler kullanılır:

1.  **Araç Kategorileri (`vehicle_category`):**
    *   Örn: Ekonomi, Lüks, SUV, Minivan

2.  **Araç Özellikleri (`vehicle_feature`):**
    *   Örn: Klima, Bluetooth, Navigasyon, Deri Koltuk

## İlişkili Post Type'lar

*   **Rezervasyonlar (`vehicle_booking`):** Her rezervasyon bir `vehicle` ile ilişkilidir.
*   **Ek Hizmetler (`vehicle_addon`):** Araçlara eklenebilecek opsiyonel hizmetler (örn: Bebek koltuğu, GPS).
