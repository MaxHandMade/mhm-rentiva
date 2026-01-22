---
name: mhm-db-master
description: Veritabanı tablolarını optimize etme ve temizleme rehberi.
---

# MHM Rentiva Database Master Skill

Bu skill, MHM Rentiva eklentisinin veritabanı işlemlerini optimize etmek, yetim (orphan) verileri temizlemek ve tablo sağlığını korumak için kullanılır.

## 1. Veritabanı Yapısı
Eklenti aşağıdaki tabloları ve veri yapılarını kullanır:

### Özel Tablolar
- **Table Name:** `{prefix}mhm_rentiva_ratings`
  - **Amaç:** Araç değerlendirmelerini ve puanlarını saklar.
  - **Kritik Alanlar:** `id`, `vehicle_id`, `user_id`, `rating`, `status`

### Post Meta Verileri
Eklenti aşağıdaki ana meta anahtarlarını (Meta Keys) kullanır. Temizlik işlemlerinde bu anahtarlar baz alınmalıdır:
- `_mhm_status` (Rezervasyon durumu)
- `_mhm_vehicle_id` (Araç ID)
- `_mhm_start_ts` (Başlangıç timestamp)
- `_mhm_end_ts` (Bitiş timestamp)
- `_mhm_total_price` (Toplam fiyat)
- `_mhm_customer_id` (Müşteri ID)
- `_mhm_contact_email`
- `_mhm_contact_name`

## 2. Temizlik ve Optimizasyon İşlemleri

### A. Orphan (Yetim) Post Meta Temizliği
Silinmiş rezervasyonlara ait ancak `wp_postmeta` tablosunda kalmış verileri temizlemek için aşağıdaki SQL mantığı kullanılır.

**Senaryo:** `_mhm_vehicle_id` meta anahtarına sahip olup, bağlı olduğu `post_id` artık `wp_posts` tablosunda olmayan satırlar.

```sql
DELETE pm
FROM wp_postmeta pm
LEFT JOIN wp_posts wp ON wp.ID = pm.post_id
WHERE wp.ID IS NULL
AND pm.meta_key LIKE '_mhm_%';
```
*(Not: Tablo öneki `wp_` sitenize göre değişebilir, `$wpdb->prefix` kullanılmalıdır.)*

### B. Geçici (Transient) Veri Temizliği
Eklenti `mhm_rate_limit_` ile başlayan transient kayıtları kullanır. Süresi dolmuş bu kayıtların temizlenmesi veritabanını rahatlatır.

```php
// PHP tarafında temizlik
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_mhm_rate_limit_%' 
     OR option_name LIKE '_transient_timeout_mhm_rate_limit_%'"
);
```

### C. Tablo Optimizasyonu
`wp_mhm_rentiva_ratings` tablosunu optimize etmek için:

```sql
OPTIMIZE TABLE wp_mhm_rentiva_ratings;
```

## 3. Kod İçinde Kullanım Örnekleri

### Örnek 1: Güvenli Meta Sorgusu
`SecurityHelper::safe_meta_query` metodunu kullanarak SQL injection korumalı sorgular oluşturun.

```php
// YANLIŞ KULLANIM ❌
$args = [
    'meta_query' => [
        [
            'key' => '_mhm_vehicle_id',
            'value' => $_POST['vehicle_id'] // sanitize edilmemiş
        ]
    ]
];

// DOĞRU KULLANIM ✅
$args = [
    'meta_query' => [
        \MHMRentiva\Admin\Core\SecurityHelper::safe_meta_query(
            '_mhm_vehicle_id', 
            $vehicle_id // Önceden sanitize edilmiş
        )
    ]
];
```

### Örnek 2: Rating Tablosuna Veri Ekleme
Veri eklerken `$wpdb->prepare` kullanımı zorunludur.

```php
global $wpdb;
$table = $wpdb->prefix . 'mhm_rentiva_ratings';

$wpdb->query($wpdb->prepare(
    "INSERT INTO $table (vehicle_id, user_id, rating, comment, status) VALUES (%d, %d, %f, %s, %s)",
    $vehicle_id,
    $user_id,
    $rating,
    $comment,
    'approved'
));
```

## 4. İndeks Yönetimi
Performans sorunu yaşandığında `DatabaseMigrator::check_index_status()` metodu çalıştırılarak eksik indeksler kontrol edilmeli ve `DatabaseMigrator::run_migrations()` ile eksikler tamamlanmalıdır.

## 5. SQL Debugging ve Sorun Giderme
Veritabanı hatalarını ayıklamak için aşağıdaki yöntemler kullanılır.

### A. Son Sorguyu İnceleme
`$wpdb->last_query` ve `$wpdb->last_error` kullanın.

```php
global $wpdb;
$wpdb->show_errors(); // Hataları ekrana basar (sadece dev ortamında!)
$result = $wpdb->get_results("...");
if ($wpdb->last_error) {
    error_log('MHM DB Error: ' . $wpdb->last_error . ' Query: ' . $wpdb->last_query);
}
```

### B. Query Monitor Entegrasyonu
Eklentinin Query Monitor ile uyumlu çalışması için `do_action('qm/start', 'mhm_query')` ve `qm/stop` hooklarını kullanabilirsiniz (eğer destekleniyorsa).

## 6. Göç Sorunları (Migration Troubleshooting)
Veritabanı güncellemeleri takılırsa `get_option('mhm_rentiva_db_version')` ile mevcut versiyonu kontrol edin.

### Versiyon Sıfırlama ve Yeniden Tetikleme
Eğer migration yarım kaldıysa, versiyonu düşürüp tekrar tetikleyebilirsiniz:

```php
// Acil Durum: Versiyonu düşürerek update'i zorla
update_option('mhm_rentiva_db_version', '1.0.0');
// Sonra eklentiyi de-activate / activate et veya SetupWizard'ı çalıştır.
```

## 7. Performans Analizi (Query Analysis)
Ağır sorguları tespit etmek için:
1. `EXPLAIN` kullanın: SQL sorgusunun başına `EXPLAIN` ekleyerek `phpmyadmin` üzerinden çalıştırın.
2. `Autoload` Kontrolü: `wp_options` tablosunda `autoload='yes'` olan ve boyutu büyük verileri tespit edin.

```sql
SELECT option_name, length(option_value) AS option_size
FROM wp_options
WHERE autoload = 'yes'
ORDER BY option_size DESC
LIMIT 10;
```
Bu sorgu, sitenizi yavaşlatan devasa option'ları bulmanızı sağlar.
