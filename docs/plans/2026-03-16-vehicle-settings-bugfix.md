# Vehicle Settings — Kapsamlı Bug Fix & Temizlik Planı

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** VehicleSettings sayfasındaki tüm kritik bugları, güvenlik açıklarını, UX tutarsızlıklarını ve ölü kodu temizlemek.

**Architecture:** Değişiklikler yalnızca iki dosyayı etkiliyor:
`src/Admin/Vehicle/Settings/VehicleSettings.php` (PHP backend + inline JS) ve
`src/Admin/Vehicle/Helpers/VehicleFeatureHelper.php` (yardımcı metod). Tüm düzeltmeler geriye dönük uyumlu — database şeması değişmiyor.

**Tech Stack:** PHP 8.x (strict_types), WordPress AJAX, jQuery, inline JS in PHP

---

## Bağlam

Sayfanın iki sekmesi var:
- **Alan Tanımları** — Araç düzenleme sayfasında hangi meta alanların görüneceğini belirler (Details / Features / Equipment).
- **Görüntüleme Seçenekleri** — Frontend araç kartlarında, detay sayfasında ve karşılaştırma tablosunda hangi alanların gösterileceğini belirler.

İki sayfa birbirine bağlı: Alan Tanımları'nda aktif edilmeyen bir alan, Görüntüleme Seçenekleri'nde de seçilmemeli. Bu ilişki mevcut kodda korunuyor, değiştirmeye gerek yok.

---

## Görev Sırası

```
Task 1  → deposit'i core_fields'a ekle          (VehicleFeatureHelper)
Task 2  → Default seçili key mismatch düzelt    (VehicleSettings)
Task 3  → DATA RECOVERY kaldır                  (VehicleSettings)
Task 4  → Custom fields UI çift gösterimi düzelt (VehicleSettings - render)
Task 5  → current_user_can() eksikliğini gider  (VehicleSettings - AJAX)
Task 6  → Client-controlled key güvenliği       (VehicleSettings - AJAX)
Task 7  → Nonce standardizasyonu                (VehicleSettings - PHP + JS)
Task 8  → wp_die → wp_send_json standardizasyonu (VehicleSettings - AJAX)
Task 9  → Core fields checkbox disable          (VehicleSettings - render)
Task 10 → Reset gerçekten default'a dönsün      (VehicleSettings - AJAX + JS)
Task 11 → Display tab kayıt sonrası reload      (VehicleSettings - JS)
Task 12 → Ölü add_admin_menu() metodunu sil     (VehicleSettings)
Task 13 → Ölü AJAX handler'ları sil             (VehicleSettings)
Task 14 → Rename modal quote/XSS güvenliği      (VehicleSettings - JS)
Task 15 → Duplicate docblock kaldır             (VehicleSettings)
```

---

### Task 1: `deposit` — VehicleFeatureHelper'a taşı

**Sorun:**
`deposit`, core field olmasına rağmen `VehicleFeatureHelper::get_core_fields()`'da yok.
`VehicleSettings.php`'de iki ayrı yerde (`render_definitions_tab` satır ~698 ve
`ajax_remove_custom_field` satır ~1982) `$core_fields[] = 'deposit'` ile elle ekleniyor.
Bu tekrarlama, birinin güncellenmesi unutulursa tutarsızlığa yol açar.

**Files:**
- Modify: `src/Admin/Vehicle/Helpers/VehicleFeatureHelper.php:53-65`
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` (iki satır kaldır)

**Step 1: VehicleFeatureHelper'a deposit ekle**

`get_core_fields()` dizisine `'deposit'` ekle:

```php
public static function get_core_fields(): array
{
    return array(
        'price_per_day',
        'availability',
        'brand',
        'model',
        'year',
        'image',
        'gallery_images',
        'license_plate',
        'deposit', // Core for rental workflow
    );
}
```

**Step 2: VehicleSettings'teki iki tekrarı kaldır**

`render_definitions_tab()` içinde (~satır 698):
```php
// KALDIR bu satırı:
$core_fields[] = 'deposit'; // Promote deposit to core protected field (v4.8.2)
```

`ajax_remove_custom_field()` içinde (~satır 1982):
```php
// KALDIR bu satırı:
$core_fields[] = 'deposit'; // Block deletion of deposit field (v4.8.2)
```

**Step 3: Commit**
```bash
git add src/Admin/Vehicle/Helpers/VehicleFeatureHelper.php
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: move deposit to core_fields in VehicleFeatureHelper, remove duplicate hardcoding"
```

---

### Task 2: Default seçili key mismatch düzelt

**Sorun:**
`get_default_selected_features()` → `'abs'` döndürüyor, ama feature listesinde `'abs_brakes'` var.
`get_default_selected_equipment()` → `'gps'` döndürüyor, ama equipment listesinde `'gps_tracker'` var.
Sonuç: İlk kurulumda hiçbir default feature/equipment seçili gelmiyor.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php:1476-1485`

**Step 1: Mevcut yanlış değerleri bul**

`VehicleSettings.php` içinde:
```php
// YANLIŞ — ~satır 1477
public static function get_default_selected_features(): array {
    return array( 'abs', 'air_conditioning', 'central_locking' );
}

// YANLIŞ — ~satır 1484
public static function get_default_selected_equipment(): array {
    return array( 'gps', 'child_seat' );
}
```

**Step 2: Doğru key'lerle düzelt**

```php
public static function get_default_selected_features(): array {
    return array( 'abs_brakes', 'air_conditioning', 'central_locking' );
}

public static function get_default_selected_equipment(): array {
    return array( 'gps_tracker', 'child_seat' );
}
```

**Step 3: Doğrula**
`get_default_features()` içinde `'abs_brakes'` key'inin var olduğunu kontrol et (~satır 1405).
`get_default_equipment()` içinde `'gps_tracker'` key'inin var olduğunu kontrol et (~satır 1435).

**Step 4: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: correct default selected keys — abs_brakes (not abs), gps_tracker (not gps)"
```

---

### Task 3: DATA RECOVERY mantığını kaldır

**Sorun:**
`get_all_available_details/features/equipment()` metodlarında "DATA RECOVERY" bloğu var.
Bu blok, admin tarafından bilinçli olarak silinen standart alanları sayfa yüklenince geri getiriyor.
Yan etki: `ajax_remove_custom_field()` bir standart alanı siler + post meta'yı temizler, ama DATA RECOVERY
alanı geri getirir → alan görünür ama tüm araçlardaki verisi kalıcı olarak silinmiş → sessiz veri kaybı.

**Doğru davranış:** Admin bir standart alanı silmeyi seçerse, o alan gerçekten kaldırılmalı (artık araç
düzenleme sayfasında görünmemeli). POST META silme de kaldırılmalı çünkü admin alanı "gizlemek" istiyor,
verisini silmek değil.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php`

**Step 1: `get_all_available_details()` — DATA RECOVERY bloğunu kaldır**

Mevcut kod (~satır 1550-1557):
```php
// DATA RECOVERY: If a default field is missing or empty in stored settings, ensure it has a label
$defaults = self::get_default_details();
foreach ( $defaults as $key => $label ) {
    if ( empty( $details[ $key ] ) ) {
        $details[ $key ] = $label;
    }
}
```
Bu bloğu tamamen sil.

**Step 2: `get_all_available_features()` — DATA RECOVERY bloğunu kaldır**

Mevcut kod (~satır 1581-1587):
```php
// DATA RECOVERY: If a default field is missing or empty in stored settings, ensure it has a label
$defaults = self::get_default_features();
foreach ( $defaults as $key => $label ) {
    if ( empty( $features[ $key ] ) ) {
        $features[ $key ] = $label;
    }
}
```
Bu bloğu tamamen sil.

**Step 3: `get_all_available_equipment()` — DATA RECOVERY bloğunu kaldır**

Mevcut kod (~satır 1612-1618):
```php
// DATA RECOVERY: If a default field is missing or empty in stored settings, ensure it has a label
$defaults = self::get_default_equipment();
foreach ( $defaults as $key => $label ) {
    if ( empty( $equipment[ $key ] ) ) {
        $equipment[ $key ] = $label;
    }
}
```
Bu bloğu tamamen sil.

**Step 4: `ajax_remove_custom_field()` — Standard alanlar için `$wpdb->delete` kaldır**

Standard alanlar artık gerçekten silinebiliyor. Ama post meta silinmemeli.
Admin "Bu alanı kaldır" dediğinde alanı gizlemek istiyor, 100 araçtaki verisini silmek değil.

`field_type === 'details'` bloğunda standard alan silme kısmı (~satır 1988-1999):
```php
// MEVCUT (YANLIŞ):
if ( isset( $current_details[ $field_key ] ) ) {
    unset( $current_details[ $field_key ] );
    update_option( 'mhm_vehicle_details', $current_details );
    // Clean meta
    global $wpdb;
    $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_mhm_rentiva_' . $field_key ) );
    wp_send_json_success( __( 'Standard field removed successfully.', 'mhm-rentiva' ) );
    return;
}

// DÜZELTME — meta silme satırını kaldır:
if ( isset( $current_details[ $field_key ] ) ) {
    unset( $current_details[ $field_key ] );
    update_option( 'mhm_vehicle_details', $current_details );
    wp_send_json_success( __( 'Field removed successfully.', 'mhm-rentiva' ) );
    return;
}
```

Aynı düzeltmeyi `field_type === 'features'` ve `field_type === 'equipment'` bloklarındaki
standard alan silme kısımlarına da uygula (meta silme satırlarını kaldır).

> **NOT:** Custom alanlar (mhm_custom_* optionlarından gelenler) için `$wpdb->delete` **korunmalı**.
> Custom alan silinince veri de silinmeli çünkü o alan artık var olmayacak. Sadece standard alanların
> meta silmesi kaldırılıyor.

**Step 5: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: remove DATA RECOVERY that prevented deliberate field deletion; preserve data on standard field removal"
```

---

### Task 4: Custom fields UI'da çift gösterimi düzelt

**Sorun:**
`render_definitions_tab()` içinde:
- "Standard Features" döngüsü `$all_features`'ı iterate ediyor → custom feature'lar da içinde
- Aşağıda ayrıca "Custom Features" section da `$custom_features`'ı gösteriyor
- Custom feature/equipment eklense, iki kez görünüyor
- Equipment için aynı sorun mevcut

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — `render_definitions_tab()` metodu

**Step 1: `render_definitions_tab()` başında custom key setleri oluştur**

`$all_features` ve `$all_equipment` tanımlandıktan sonra (~satır 676-679 arası):
```php
// Custom key setleri — bunları standart döngülerden hariç tutmak için
$custom_feature_keys   = array_keys( $custom_features );
$custom_equipment_keys = array_keys( $custom_equipment );
```

**Step 2: "Standard Features" döngüsünü filtrele**

Mevcut (~satır 776):
```php
<?php foreach ( $all_features as $key => $label ) : ?>
```

Düzeltme:
```php
<?php foreach ( $all_features as $key => $label ) :
    if ( in_array( $key, $custom_feature_keys, true ) ) {
        continue; // Custom features kendi section'ında gösterilecek
    }
?>
```

**Step 3: "Standard Equipment" döngüsünü filtrele**

Mevcut (~satır 823):
```php
<?php foreach ( $all_equipment as $key => $label ) : ?>
```

Düzeltme:
```php
<?php foreach ( $all_equipment as $key => $label ) :
    if ( in_array( $key, $custom_equipment_keys, true ) ) {
        continue; // Custom equipment kendi section'ında gösterilecek
    }
?>
```

**Step 4: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: exclude custom features/equipment from standard list to prevent duplicate rendering"
```

---

### Task 5: Eksik `current_user_can()` kontrollerini ekle

**Sorun:**
`ajax_remove_custom_field()` ve `ajax_add_custom_field()` sadece nonce kontrolü yapıyor.
Diğer tüm AJAX handler'larda `current_user_can('manage_options')` kontrolü de var.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — iki AJAX metod

**Step 1: `ajax_remove_custom_field()` — nonce kontrolünden hemen sonra permission ekle**

Mevcut (~satır 1969-1974):
```php
public static function ajax_remove_custom_field(): void {
    // Nonce check
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_vehicle_settings_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed', 'mhm-rentiva' ) );
        return;
    }
    // ... devam ediyor
```

Düzeltme (nonce check'ten hemen sonra):
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( __( 'You do not have permission', 'mhm-rentiva' ) );
    return;
}
```

**Step 2: `ajax_add_custom_field()` — aynı düzeltme**

Mevcut (~satır 2097-2103):
```php
public static function ajax_add_custom_field(): void {
    // Nonce check
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_vehicle_settings_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed', 'mhm-rentiva' ) );
        return;
    }
    // ... devam ediyor
```

Nonce check'ten hemen sonra:
```php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_send_json_error( __( 'You do not have permission', 'mhm-rentiva' ) );
    return;
}
```

**Step 3: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "security: add missing current_user_can check to ajax_add/remove_custom_field"
```

---

### Task 6: Client-controlled field key güvenliği

**Sorun:**
JS tarafında `'custom_' + Date.now()` ile key oluşturuluyor ve POST ile gönderilip
`ajax_add_custom_field()` sunucuda olduğu gibi kullanıyor. Kötü niyetli kullanıcı
`price_per_day` gibi bir key sağlarsa mevcut alanı ezebilir.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — `ajax_add_custom_field()` metodu

**Step 1: `ajax_add_custom_field()` — sunucuda key üret, client key'ini yoksay**

Mevcut (~satır 2104):
```php
$field_key   = self::post_text( 'field_key' );
```

Düzeltme:
```php
// Key always generated server-side — never trust client-provided keys
$field_key = 'custom_' . time() . '_' . wp_rand( 1000, 9999 );
```

> **NOT:** `wp_rand()` WordPress'in kendi random fonksiyonu. `time()` ile birlikte
> kullanıldığında çakışma riski pratikte sıfır. `wp_rand()` parametresiz de kullanılabilir
> ama okunabilirlik için 4 basamak yeterli.

**Step 2: JS tarafından gönderilen `field_key`'i de kaldır (isteğe bağlı temizlik)**

VehicleSettings.php içinde, `#add-custom-detail` AJAX çağrısından (`field_key: key,`) satırını sil.
Aynısını `#add-custom-feature` ve `#add-custom-equipment` için de yap.

> **NOT:** Sunucu artık client key'ini kullanmıyor, bu satırlar gereksiz.
> Ama sunucu zaten yoksayıyor olduğundan bu adım sadece temizlik amaçlı.

**Step 3: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "security: generate custom field key server-side, ignore client-provided key"
```

---

### Task 7: Nonce standardizasyonu

**Sorun:**
İki farklı nonce değeri kullanılıyor:
- `vehicle_settings_nonce` — çoğu AJAX handler
- `mhm_vehicle_settings_nonce` — `ajax_remove_custom_field` ve `ajax_add_custom_field`
Bu JS'de iki farklı nonce değeri üretip gönderilmesine yol açıyor ve bakımı zorlaştırıyor.

**Hedef:** Her şey `vehicle_settings_nonce` kullansın.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php`

**Step 1: PHP'deki `ajax_remove_custom_field` nonce check'ini güncelle**

Mevcut (~satır 1971):
```php
if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_vehicle_settings_nonce' ) ) {
```

Düzeltme:
```php
if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'vehicle_settings_nonce' ) ) {
```

**Step 2: PHP'deki `ajax_add_custom_field` nonce check'ini güncelle**

Mevcut (~satır 2099):
```php
if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'mhm_vehicle_settings_nonce' ) ) {
```

Düzeltme:
```php
if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'vehicle_settings_nonce' ) ) {
```

**Step 3: JS'deki nonce değerlerini güncelle**

`render_definitions_tab()` içinde, `#add-custom-detail` AJAX çağrısındaki nonce satırı (~satır 906):
```javascript
// MEVCUT:
nonce: '<?php echo esc_attr( wp_create_nonce( 'mhm_vehicle_settings_nonce' ) ); ?>'

// DÜZELTME:
nonce: '<?php echo esc_attr( wp_create_nonce( 'vehicle_settings_nonce' ) ); ?>'
```

Aynı değişikliği `#add-custom-feature` (~satır 959) ve `#add-custom-equipment` (~satır 1001) için yap.
Aynı değişikliği `.remove-custom-detail` (~satır 1039), `.remove-custom-feature` (~satır 1071) ve
`.remove-custom-equipment` (~satır 1103) için yap.
Toplamda 6 nonce değeri güncelleniyor.

**Step 4: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "refactor: standardize all AJAX nonces to vehicle_settings_nonce"
```

---

### Task 8: `wp_die()` → `wp_send_json_*` standardizasyonu

**Sorun:**
`ajax_add_feature`, `ajax_add_equipment`, `ajax_add_detail` ve remove karşılıkları
`wp_die(wp_json_encode(...))` kullanıyor ve "PERFORMANCE OPTIMIZATION" yorumu var.
Bu yorum yanlış — aralarında gerçek bir performans farkı yok. Diğer handler'lar `wp_send_json_*` kullanıyor.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — 6 eski AJAX metod
  (bunlar Task 13'te silinecek, ama sıra o task'a gelmeden önce bu handler'lar temiz olmalı)

> **NOT:** Bu task Task 13'ten önce geldiği için handler'lara dokunuyoruz. Ama aslında
> Task 13'te zaten silinecekler. Bu task'ı atlamak ve Task 13'e geçmek de kabul edilebilir.
> Eğer atlamak istersen Task 13'e geç.

**Seçenek: Bu task'ı atla ve Task 13'e geç.** Task 13 bu handler'ları tamamen siler,
standardizasyon sorunu kendiliğinden çözülür.

---

### Task 9: Core fields checkbox'larını kilitle

**Sorun:**
"Temel Detaylar (Önemli)" başlığı altındaki checkbox'lar disable edilmemiş.
Kullanıcı bunları işaretsiz bırakıp kaydetmeye çalışabilir. Başlık "Essential" diyor ama
checkbox işaret kaldırılabilir.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — `render_definitions_tab()` içinde core details döngüsü

**Step 1: Core details checkbox'larına `disabled` ve `title` ekle**

Mevcut (~satır 706):
```php
<input type="checkbox" name="selected_details[]" value="<?php echo esc_attr( $key ); ?>"
    <?php checked( in_array( $key, $selected_details ) ); ?>>
```

Düzeltme:
```php
<input type="checkbox" name="selected_details[]" value="<?php echo esc_attr( $key ); ?>"
    <?php checked( in_array( $key, $selected_details ) ); ?>
    disabled="disabled"
    title="<?php esc_attr_e( 'Core fields cannot be disabled', 'mhm-rentiva' ); ?>">
```

> **Önemli:** `disabled` checkbox'ların değeri form submit'te gönderilmez. Bu, `ajax_save_settings`'e
> core field'ların `selected_details` array'inde gelmeyeceği anlamına gelir.
> `ajax_save_settings()` içinde, kaydetmeden önce core field'ların listeye eklendiğinden emin olmak için:

**Step 2: `ajax_save_settings()` — core fields'ı kayıt sırasında zorla ekle**

`ajax_save_settings()` içinde (~satır 1853), selected_details kaydedilmeden önce:
```php
$selected_details = array_map( 'sanitize_text_field', self::post_array( 'selected_details' ) );

// Core fields always selected regardless of form state
$core_fields = \MHMRentiva\Admin\Vehicle\Helpers\VehicleFeatureHelper::get_core_fields();
foreach ( $core_fields as $core_key ) {
    if ( ! in_array( $core_key, $selected_details, true ) ) {
        $selected_details[] = $core_key;
    }
}
```

**Step 3: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: disable core field checkboxes in UI; enforce core fields in save handler"
```

---

### Task 10: Reset — gerçekten default'a dönsün

**Sorun:**
Confirm dialog: *"reset all vehicle settings to defaults"* diyor.
Başarı mesajı: *"Settings reset to defaults."* diyor.
Ama `ajax_reset_settings()` 'definitions' tab için `array()` kaydediyor — boş liste, default değil.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — `ajax_reset_settings()` ve inline JS confirm dialog

**Step 1: `ajax_reset_settings()` — definitions tab için gerçek default'ları kullan**

Mevcut (~satır 2164-2167):
```php
update_option( 'mhm_selected_details', array() );
update_option( 'mhm_selected_features', array() );
update_option( 'mhm_selected_equipment', array() );
```

Düzeltme:
```php
update_option( 'mhm_selected_details', self::get_default_selected_details() );
update_option( 'mhm_selected_features', self::get_default_selected_features() );
update_option( 'mhm_selected_equipment', self::get_default_selected_equipment() );
```

**Step 2: Display tab reset için sensible default**

Mevcut (~satır 2157-2162):
```php
$settings['mhm_rentiva_vehicle_card_fields'] = array();
$settings['mhm_rentiva_vehicle_detail_fields'] = array();
$settings['comparison_fields'] = array();
```

Display settings reset için boş array mantıklı (frontend'in kendi default'larına döner).
Bu kısım değişmeyebilir. Ama başarı mesajını düzelt:

**Step 3: Confirm dialog metnini doğrula**

`render_settings_page()` içindeki JS confirm dialog (~satır 226):
```javascript
// MEVCUT:
if (confirm('Are you sure you want to reset all vehicle settings to defaults? Custom field definitions will NOT be deleted.'))

// Bu metin artık doğru — gerçekten default'lara dönüyor. Sadece Türkçe karşılığının
// doğru göründüğünden emin ol (Loco Translate'te string varsa güncelle).
```

**Step 4: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: reset definitions tab actually restores defaults instead of clearing everything"
```

---

### Task 11: Display tab kayıt sonrası sayfa yenile

**Sorun:**
Definitions tab kayıt sonrası `location.reload()` yapıyor.
Display tab sadece `alert()` gösteriyor, reload yok.
Kullanıcı drag&drop yaptıktan sonra kayıt sonucu doğrulamak için manuel yenilemek zorunda.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — `render_display_tab()` içinde form submit JS

**Step 1: Form submit success handler'a reload ekle**

Mevcut (~satır 618-622):
```javascript
$.post(ajaxurl, formData, function(response) {
    if (response.success) {
        alert('<?php echo esc_js( __( 'Settings saved successfully!', 'mhm-rentiva' ) ); ?>');
    } else {
        alert('<?php echo esc_js( __( 'Error saving settings.', 'mhm-rentiva' ) ); ?>');
    }
});
```

Düzeltme:
```javascript
$.post(ajaxurl, formData, function(response) {
    if (response.success) {
        alert('<?php echo esc_js( __( 'Settings saved successfully!', 'mhm-rentiva' ) ); ?>');
        window.location.reload();
    } else {
        alert('<?php echo esc_js( __( 'Error saving settings.', 'mhm-rentiva' ) ); ?>');
    }
});
```

**Step 2: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: reload page after display settings save for consistent behavior"
```

---

### Task 12: Ölü `add_admin_menu()` metodunu sil

**Sorun:**
`VehicleSettings::add_admin_menu()` statik metodu hâlâ var ama hiçbir yerde çağrılmıyor.
Menu.php menü kaydını üstlendi. Eğer yanlışlıkla çağrılırsa duplicate submenu oluşturur.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php:151-161`

**Step 1: add_admin_menu() metodunu sil**

```php
// Bu bloğu tamamen sil (~satır 151-161):
/**
 * Add to admin menu
 */
public static function add_admin_menu(): void {
    // Add under MHM Rentiva main menu
    add_submenu_page(
        'mhm-rentiva',
        __( 'Vehicle Settings', 'mhm-rentiva' ),
        __( 'Vehicle Settings', 'mhm-rentiva' ),
        'manage_options',
        'vehicle-settings',
        array( self::class, 'render_settings_page' )
    );
}
```

**Step 2: Çağrıldığı yer var mı kontrol et**

```bash
grep -r "add_admin_menu" src/Admin/Vehicle/
```
Sonuç boş olmalı (Plugin.php veya başka bir yerde çağrılmıyorsa).

**Step 3: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "cleanup: remove dead add_admin_menu() method, Menu.php handles registration"
```

---

### Task 13: Ölü AJAX handler'ları sil

**Sorun:**
6 AJAX action kayıtlı ama frontend JS hiç kullanmıyor:
- `add_vehicle_feature` → `ajax_add_feature()`
- `remove_vehicle_feature` → `ajax_remove_feature()`
- `add_vehicle_equipment` → `ajax_add_equipment()`
- `remove_vehicle_equipment` → `ajax_remove_equipment()`
- `add_vehicle_detail` → `ajax_add_detail()`
- `remove_vehicle_detail` → `ajax_remove_detail()`

Frontend bunlar yerine `add_custom_field` / `remove_custom_field` kullanıyor.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php`

**Step 1: `register()` metodundan 6 action kaydını sil**

Mevcut (~satır 89-96):
```php
add_action( 'wp_ajax_add_vehicle_feature', array( self::class, 'ajax_add_feature' ) );
add_action( 'wp_ajax_remove_vehicle_feature', array( self::class, 'ajax_remove_feature' ) );
add_action( 'wp_ajax_add_vehicle_equipment', array( self::class, 'ajax_add_equipment' ) );
add_action( 'wp_ajax_remove_vehicle_equipment', array( self::class, 'ajax_remove_equipment' ) );
add_action( 'wp_ajax_add_vehicle_detail', array( self::class, 'ajax_add_detail' ) );
add_action( 'wp_ajax_remove_vehicle_detail', array( self::class, 'ajax_remove_detail' ) );
```
Bu 6 satırı sil.

**Step 2: 6 AJAX metodunu sil**

Silinecek metodlar:
- `ajax_add_feature()` (~satır 1634-1661)
- `ajax_remove_feature()` (~satır 1663-1680)
- `ajax_add_equipment()` (~satır 1682-1712)
- `ajax_remove_equipment()` (~satır 1714-1731)
- `ajax_add_detail()` (~satır 1733-1763)
- `ajax_remove_detail()` (~satır 1765-1782)

Her metodun docblock'larıyla birlikte silindiğinden emin ol.

**Step 3: Çağrıldıkları yer var mı kontrol et**

```bash
grep -rn "ajax_add_feature\|ajax_remove_feature\|ajax_add_equipment\|ajax_remove_equipment\|ajax_add_detail\|ajax_remove_detail" src/
```
Sonuç boş olmalı (sadece sildiğin dosyada vardı).

**Step 4: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "cleanup: remove 6 dead AJAX handlers replaced by add/remove_custom_field"
```

---

### Task 14: Rename modal — label escape güvenliği

**Sorun:**
`showRenameModal()` JavaScript fonksiyonunda template literal içinde `${label}` değeri
HTML attribute'a ham olarak yazılıyor: `value="${label}"`. Label'da `"` karakteri varsa HTML bozulur.

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php` — `showRenameModal` JS fonksiyonu

**Step 1: `showRenameModal` içine bir escape helper ekle**

`showRenameModal` fonksiyonunun en başına:
```javascript
window.showRenameModal = function(type) {
    // Helper: escape double quotes for HTML attribute values
    function escAttr(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    // ...
```

**Step 2: Template literal'deki `label` kullanımlarını değiştir**

Mevcut (~satır 1274-1275):
```javascript
<label style="min-width: 120px; font-weight: bold;">${label}:</label>
<input type="text" data-key="${key}" value="${label}" ...>
```

Düzeltme:
```javascript
<label style="min-width: 120px; font-weight: bold;">${escAttr(label)}:</label>
<input type="text" data-key="${escAttr(key)}" value="${escAttr(label)}" ...>
```

**Step 3: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "fix: escape label/key in rename modal template literal to prevent HTML breakage"
```

---

### Task 15: Duplicate docblock temizle

**Sorun:**
`render_settings_page()` metodunun üstünde aynı docblock iki kez var (~satır 181-183).

**Files:**
- Modify: `src/Admin/Vehicle/Settings/VehicleSettings.php:179-183`

**Step 1: İlk tekrarlayan docblock'u sil**

Mevcut:
```php
/**
 * Render settings page
 */
/**
 * Render settings page
 */
public function render_settings_page(): void {
```

Düzeltme:
```php
/**
 * Render settings page
 */
public function render_settings_page(): void {
```

**Step 2: Commit**
```bash
git add src/Admin/Vehicle/Settings/VehicleSettings.php
git commit -m "cleanup: remove duplicate docblock on render_settings_page"
```

---

## Özet Tablosu

| Task | Kategori | Dosya | Risk |
|------|----------|-------|------|
| 1 | Temizlik | VehicleFeatureHelper + VehicleSettings | Düşük |
| 2 | Kritik Bug | VehicleSettings | Düşük |
| 3 | Kritik Bug | VehicleSettings | Orta |
| 4 | Kritik Bug | VehicleSettings | Düşük |
| 5 | Güvenlik | VehicleSettings | Düşük |
| 6 | Güvenlik | VehicleSettings | Düşük |
| 7 | Refactor | VehicleSettings | Düşük |
| 8 | Refactor | (Task 13 kapsiyor, atla) | — |
| 9 | UX Fix | VehicleSettings | Orta |
| 10 | Bug | VehicleSettings | Düşük |
| 11 | UX Fix | VehicleSettings | Düşük |
| 12 | Temizlik | VehicleSettings | Düşük |
| 13 | Temizlik | VehicleSettings | Düşük |
| 14 | Güvenlik | VehicleSettings | Düşük |
| 15 | Temizlik | VehicleSettings | Düşük |

**Toplam commit: ~14 (her task ayrı commit)**
