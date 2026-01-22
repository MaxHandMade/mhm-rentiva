---
name: mhm-security-guard
description: Eklenti güvenlik denetimi ve güvenli kodlama rehberi.
---

# MHM Security Guard Skill

Bu skill, MHM Rentiva eklentisinin güvenlik standartlarını (Nonce, Sanitization, Escaping, Capability) denetlemek ve doğru kodlama pratiklerini sağlamak için kullanılır.

**Temel Kural:** "Güvenme, Doğrula." (Verify, Don't Trust).

## 1. Dosya Erişim Güvenliği (Direct Access)
Her PHP dosyasının (örneğin Class dosyaları, View dosyaları) en başında, WordPress dışından doğrudan erişimi engelleyen kural olmalıdır.

```php
<?php
defined('ABSPATH') || exit;
```

## 2. Nonce ve AJAX Güvenliği
Tüm AJAX ve Form işlemleri `SecurityHelper` sınıfını **zorunlu** olarak kullanmalıdır.

### AJAX Handler Standart Yapısı

```php
public function handle_ajax_request() {
    // 1. Güvenlik Kontrolü (Nonce + Capability)
    // 'mhm_rentiva_action_nonce' -> action'a özel nonce ismi
    // 'manage_options' -> gerekli yetki (capability)
    \MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die(
        'mhm_rentiva_action_nonce', 
        'manage_options',
        __('Security check failed.', 'mhm-rentiva')
    );

    // 2. İş Mantığı...
}
```

**Yanlış Kullanım ❌:**
```php
check_ajax_referer('my_nonce'); // Yetersiz, capability kontrolü yok.
if (!current_user_can('edit_posts')) return; // Manuel kontrol hataya açık.
```

**Doğru Kullanım ✅:**
```php
\MHMRentiva\Admin\Core\SecurityHelper::verify_ajax_request_or_die('my_action_nonce', 'edit_posts');
```

## 2. Veri Doğrulama (Validation) ve Temizleme (Sanitization)
Gelen tüm veriler türüne uygun olarak temizlenmelidir. `SecurityHelper` içindeki özel validasyon metodları tercih edilmelidir.

| Veri Tipi | Kullanılacak Metod / Fonksiyon | Örnek |
| :--- | :--- | :--- |
| **Araç ID** | `SecurityHelper::validate_vehicle_id($id)` | ID > 0 kontrolü yapar. |
| **Tarih** | `SecurityHelper::validate_date($date)` | Geçerli tarih formatı kontrolü yapar. |
| **Email** | `SecurityHelper::validate_email($email)` | `is_email` ve sanitize işlemi yapar. |
| **Array** | `SecurityHelper::validate_numeric_array($arr)` | Sayısal array temizliği yapar. |
| **Text** | `mhm_rentiva_sanitize_text_field_safe($str)` | Null değerleri güvenle string'e çevirir. |

### Örnek Kod

```php
try {
    // Verileri al ve doğrula
    $vehicle_id = \MHMRentiva\Admin\Core\SecurityHelper::validate_vehicle_id($_POST['vehicle_id'] ?? 0);
    $start_date = \MHMRentiva\Admin\Core\SecurityHelper::validate_date($_POST['start_date'] ?? '');
    
    // İşlem...

} catch (\InvalidArgumentException $e) {
    // Hata yönetimi
    wp_send_json_error(['message' => $e->getMessage()]);
}
```

## 4. SQL Enjeksiyon Koruması
Özel veritabanı sorgularında değişkenler **ASLA** doğrudan SQL string'ine eklenmemelidir. `$wpdb->prepare()` kullanımı zorunludur.

```php
// YANLIŞ ❌
$wpdb->query("SELECT * FROM table WHERE id = $id");

// DOĞRU ✅
$wpdb->query($wpdb->prepare("SELECT * FROM table WHERE id = %d", $id));
```

## 5. Çıktı Güvenliği (Escaping)
Veritabanından veya kullanıcıdan gelen verileri ekrana basarken `.php` dosyalarında mutlaka escape fonksiyonları kullanılmalıdır.

- **HTML İçeriği:** `esc_html($var)` veya `esc_html__('Text', 'domain')`
- **HTML Attribute:** `esc_attr($var)`
- **URL:** `esc_url($var)`
- **JavaScript:** `esc_js($var)` veya `wp_json_encode($var)`

**Örnek (Template Dosyası):**
```php
<!-- YANLIŞ ❌ -->
<div class="vehicle-title"><?php echo $vehicle->post_title; ?></div>
<a href="<?php echo $url; ?>">Link</a>

<!-- DOĞRU ✅ -->
<div class="vehicle-title"><?php echo esc_html($vehicle->post_title); ?></div>
<a href="<?php echo esc_url($url); ?>"><?php esc_html_e('View Details', 'mhm-rentiva'); ?></a>
```

## 4. Rate Limiting (Hız Sınırlama)
Kritik ve herkese açık formlarda (örn. Rezervasyon Formu) flood saldırılarını önlemek için Rate Limiting kullanılmalıdır.

```php
// 300 saniyede (5 dakika) maksimum 5 istek
\MHMRentiva\Admin\Core\SecurityHelper::check_rate_limit_or_die(
    'booking_submission', 
    5, 
    300, 
    __('Too many requests.', 'mhm-rentiva')
);
```
## 7. Güvenlik Denetim Listesi (Audit Checklist)
Kod incelemesi (Code Review) sırasında kontrol edilecek maddeler:

- [ ] Tüm dosyalar `defined('ABSPATH') || exit;` ile başlıyor mu?
- [ ] AJAX handler'lar `SecurityHelper::verify_ajax_request_or_die` kullanıyor mu?
- [ ] `$wpdb` sorguları `prepare()` methodundan geçirilmiş mi?
- [ ] `$_POST` / `$_GET` verileri `sanitize_*` fonksiyonları ile temizlenmiş mi?
- [ ] `echo` ile basılan her veri `esc_*` fonksiyonları ile escape edilmiş mi?
- [ ] Form işlemlerinde Rate Limiting uygulanmış mı?
