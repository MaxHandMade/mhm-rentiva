# Veritabanı Dokümantasyonu

Bu klasör, MHM Rentiva eklentisi için veritabanı yapısı ve meta key dokümantasyonunu içerir.

## Dosyalar

Tüm veritabanı ile ilgili dosyalar `src/Admin/Utilities/Database/` klasöründe bulunur:

### MetaKeysDocumentation.php
Eklentide kullanılan tüm meta key'ler için standart dokümantasyon. Meta key'lerin kategori, tip, değer ve kullanım alanlarına göre listesini içerir. Yeni meta key'ler bu dokümantasyona göre eklenmelidir.

**Konum:** `src/Admin/Utilities/Database/MetaKeysDocumentation.php`

### DatabaseInitialization.php
Eklenti ilk kurulduğunda veritabanını oluşturan kod. Meta key kayıtları, varsayılan ayarlar ve cron job'ları içerir. Eklenti aktivasyonunda otomatik olarak çalışır.

**Konum:** `src/Admin/Utilities/Database/DatabaseInitialization.php`

### DatabaseCleanupPage.php
Admin panel için veritabanı temizleme sayfası. Gereksiz meta key'leri temizlemek ve veritabanı performansını optimize etmek için araçlar sağlar.

**Konum:** `src/Admin/Utilities/Database/DatabaseCleanupPage.php`

## Meta Key Kategorileri

### Araç Meta Key'leri
- `_mhm_vehicle_availability` - Araç müsaitlik durumu (STANDART)
- `_mhm_vehicle_status` - Araç durumu (yedek)
- `_mhm_rentiva_*` - Araç bilgileri (marka, model, yıl, vb.)

### Rezervasyon Meta Key'leri
- `_mhm_vehicle_id` - Rezerve edilen aracın ID'si
- `_mhm_status` - Rezervasyon durumu
- `_mhm_*_date` - Tarih bilgileri
- `_mhm_*_time` - Saat bilgileri

### Müşteri Meta Key'leri
- `_mhm_customer_*` - Müşteri bilgileri (ad, soyad, e-posta, telefon)

### Ödeme Meta Key'leri
- `_mhm_payment_*` - Ödeme bilgileri (yöntem, durum, tutar)

### Makbuz Meta Key'leri
- `_mhm_receipt_*` - Makbuz bilgileri (durum, dosya, tarih)

### Sistem Meta Key'leri
- `_mhm_shortcode` - Shortcode bilgileri
- `_mhm_auto_created` - Otomatik oluşturuldu
- `_mhm_booking_*` - Rezervasyon sistemi bilgileri

## Önemli Kurallar

### Yapılması Gerekenler:
1. Yeni meta key'leri bu dokümantasyona göre ekleyin
2. Meta key isimlerini tutarlı oluşturun
3. Her zaman gerekli meta key'leri ekleyin
4. Meta key tiplerini doğru tanımlayın (string, number, array)
5. Değer aralıklarını açıkça belirtin

### Yapılmaması Gerekenler:
1. Eski meta key'leri kullanmayın
2. Tutarsız meta key isimleri oluşturmayın
3. Gereksiz meta key'ler eklemeyin
4. Meta key tiplerini karıştırmayın
5. Değer aralıklarını tanımsız bırakmayın

## Standart Meta Key Formatı

```
_mhm_[category]_[field_name]
```

**Örnekler:**
- `_mhm_vehicle_availability` - Araç müsaitlik durumu
- `_mhm_booking_status` - Rezervasyon durumu
- `_mhm_customer_email` - Müşteri e-postası
- `_mhm_payment_method` - Ödeme yöntemi

## Meta Key Tipleri

### String Meta Key'leri
- **Tip:** `string`
- **Kullanım:** Metin bilgileri (ad, soyad, e-posta, vb.)
- **Sanitize:** `sanitize_text_field`, `sanitize_email`

### Number Meta Key'leri
- **Tip:** `number`
- **Kullanım:** Sayısal bilgiler (ID, fiyat, yıl, vb.)
- **Sanitize:** `absint`, `floatval`

### Array Meta Key'leri
- **Tip:** `array`
- **Kullanım:** Çoklu değerler (özellikler, ekipman, vb.)
- **Sanitize:** Özel array sanitization callback'leri

## Kullanım

### Meta Key Ekleme:
```php
register_post_meta('vehicle', '_mhm_vehicle_availability', [
    'type' => 'string',
    'single' => true,
    'sanitize_callback' => 'sanitize_text_field',
    'auth_callback' => '__return_true',
    'show_in_rest' => true,
    'description' => 'Vehicle availability status (active/inactive)'
]);
```

### Meta Key Kullanımı:
```php
// Değer alma
$availability = get_post_meta($vehicle_id, '_mhm_vehicle_availability', true);

// Değer kaydetme
update_post_meta($vehicle_id, '_mhm_vehicle_availability', 'active');
```

## Sorun Giderme

### Meta Key Bulunamadı:
1. Meta key'in `DatabaseInitialization.php` dosyasında kayıtlı olup olmadığını kontrol edin
2. Eklenti aktivasyonunu yeniden çalıştırın
3. Veritabanında meta key'in varlığını kontrol edin

### Tutarsız Meta Key'ler:
1. `MetaKeysDocumentation.php` dosyasını kontrol edin
2. Standart formatı kullanın: `_mhm_[category]_[field_name]`
3. Eski meta key'leri temizleyin

### Performans Sorunları:
1. Gereksiz meta key'leri temizleyin
2. Meta key'leri doğru tiplerle tanımlayın
3. Sanitize callback'lerini kullanın

