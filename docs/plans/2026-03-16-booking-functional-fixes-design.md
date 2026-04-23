# Rezervasyonlar Sayfası — Fonksiyonel Düzeltmeler Tasarım Dokümanı

**Tarih:** 2026-03-16
**Kapsam:** Bug fix + fonksiyonel iyileştirmeler (UI/tasarım ayrı planda)

---

## Hedef

Rezervasyonlar (Bookings) admin sayfasındaki 3 kritik bug'ı düzeltmek ve tespit edilen fonksiyonel tutarsızlıkları gidermek. Cron sistemi ve buffer time mekanizmasına dokunulmayacak.

---

## Kapsam Dışı

- Cron (`AutoCancel.php`) — doğru çalışıyor, dokunulmayacak
- Buffer time (`Util.php`) — doğru çalışıyor, dokunulmayacak
- Nonce standardizasyonu — risk/fayda dengesi uygun değil, ertelendi
- UI/tasarım iyileştirmeleri — ayrı plan (sonraki sprint)

---

## Task 1 — Bug Fix: HTML Render, Gateway Çevirisi, Status Dropdown

### 1a. HTML Spaces Bug

**Dosya:** `assets/js/admin/manual-booking-meta.js:398`

**Sorun:** Template literal içindeki HTML tag'larında boşluk var, browser metin olarak render ediyor.

```js
// ÖNCE (bozuk):
const messageHtml = ` < div class = "mhm-message ${type}" > ${message} < / div > `;

// SONRA:
const messageHtml = `<div class="mhm-message ${type}">${message}</div>`;
```

Dosyanın tamamı taranacak — aynı pattern başka yerde varsa oralar da düzeltilecek.

---

### 1b. Gateway Çevirisi

**Dosya:** `src/Admin/Booking/ListTable/BookingColumns.php:570`

**Sorun:** `woocommerce` gateway `$labels` array'inde yok, `strtoupper()` fallback "WOOCOMMERCE" döndürüyor. Liste "Pending [WOOCOMMERCE]" ve "Cancelled [WOOCOMMERCE]" gösteriyor.

```php
// ÖNCE:
private static function get_payment_gateway_label( string $gateway ): string {
    $labels = array(
        'offline' => __( 'Offline', 'mhm-rentiva' ),
    );
    return $labels[ $gateway ] ?? strtoupper( $gateway );
}

// SONRA:
private static function get_payment_gateway_label( string $gateway ): string {
    $labels = array(
        'offline'        => __( 'Offline', 'mhm-rentiva' ),
        'woocommerce'    => __( 'WooCommerce', 'mhm-rentiva' ),
        'bank_transfer'  => __( 'Banka Transferi', 'mhm-rentiva' ),
        'cash'           => __( 'Nakit', 'mhm-rentiva' ),
    );
    return $labels[ $gateway ] ?? ucwords( str_replace( '_', ' ', $gateway ) );
}
```

Mevcut plugin'de kayıtlı diğer gateway isimleri kontrol edilip gerekirse listeye eklenir.

---

### 1c. Status Dropdown Tutarsızlığı

**Dosya:** `src/Admin/Booking/Meta/ManualBookingMetaBox.php:408`

**Sorun:** Yeni rezervasyon formunda sadece 4 hardcode status var (pending, confirmed, in_progress, completed). Filtre dropdown'ı 9 status gösteriyor.

**Karar:** Manuel rezervasyon oluştururken sadece mantıklı başlangıç durumları sunulmalı. Refunded, no_show, draft gibi durumlar yeni oluşturma sırasında seçilemez.

**İzin verilen başlangıç durumları:** `pending`, `confirmed`

```php
// ÖNCE: 4 hardcode <option>
echo '<option value="pending">Pending</option>';
echo '<option value="confirmed" selected>Confirmed</option>';
// ...

// SONRA: Status sınıfından dinamik, sadece başlangıç durumları
$initial_statuses = array( Status::PENDING, Status::CONFIRMED );
echo '<select id="mhm_manual_booking_status" name="mhm_manual_status" class="mhm-field-select">';
foreach ( $initial_statuses as $status_key ) {
    $selected = selected( $status_key, Status::CONFIRMED, false );
    printf(
        '<option value="%s" %s>%s</option>',
        esc_attr( $status_key ),
        $selected,
        esc_html( Status::get_label( $status_key ) )
    );
}
echo '</select>';
```

Edit formunda (`BookingEditMetaBox.php`) gösterilen statuses de `Status::get_transitions()` ile dinamik hale getirilecek.

---

## Task 2 — Timezone Standardizasyonu

### Bağlam

- **Cron (`AutoCancel.php`):** `post_date` + dakika farkı karşılaştırması kullanıyor. `_mhm_payment_deadline` meta'sına bakmıyor. Dokunulmayacak.
- **Buffer time (`Util.php`):** `wp_timezone()` parse + `SET time_zone` SQL session ile doğru çalışıyor. Dokunulmayacak.
- **Bozuk olan:** Deadline meta'larının storage ve karşılaştırma mantığı.

### Fix 1 — Handler.php: Payment Deadline

**Dosya:** `src/Admin/Booking/Core/Handler.php:516`

```php
// ÖNCE (yanlış — yerel timestamp + gmdate = hatalı UTC string):
$current_timestamp  = current_time( 'timestamp' );
$deadline_timestamp = $current_timestamp + ( $deadline_minutes * 60 );
return gmdate( 'Y-m-d H:i:s', $deadline_timestamp );

// SONRA (doğru — saf UTC):
return gmdate( 'Y-m-d H:i:s', time() + ( $deadline_minutes * MINUTE_IN_SECONDS ) );
```

### Fix 2 — Handler.php: Cancellation Deadline

**Dosya:** `src/Admin/Booking/Core/Handler.php:491`

```php
// ÖNCE (belirsiz — strtotime server local time'a göre davranabilir):
return gmdate( 'Y-m-d H:i:s', strtotime( "+{$deadline_hours} hours" ) );

// SONRA (açık UTC):
return gmdate( 'Y-m-d H:i:s', time() + ( $deadline_hours * HOUR_IN_SECONDS ) );
```

### Fix 3 — DepositManagementAjax.php: Deadline Karşılaştırması

**Dosya:** `src/Admin/Booking/Actions/DepositManagementAjax.php:253`

**Sorun:** `time()` (UTC timestamp) vs `strtotime($cancellation_deadline)` — deadline string'i UTC gibi parse ediliyor, oysa UTC+3 ortamında yerel saat olarak saklanmış olabilir. 3 saatlik kayma.

```php
// ÖNCE:
$now      = time();
$deadline = strtotime( $cancellation_deadline );
if ( $now <= $deadline ) { ... }

// SONRA (her iki taraf da UTC olarak normalize):
$now      = time();
$deadline = strtotime( $cancellation_deadline . ' UTC' );
if ( $now <= $deadline ) { ... }
```

> **Not:** Handler.php fix'inden sonra yeni oluşturulan rezervasyonlarda deadline zaten saf UTC string olarak saklanacak. Mevcut rezervasyonlar için `' UTC'` eki backward-compatible çalışır.

---

## Task 3 — Inline Style Temizliği

### BookingPortalMetaBox.php

**Sorun:** Inline `style="color: #4caf50;"` ve emoji karakterler (`👤`, `📊`, `💡`, `➕`, `⚠️`) kullanımı. WPCS ihlali.

**Fix:**
- Inline style'lar `booking-meta.css`'e CSS class olarak taşınır
- Emoji'ler WordPress dashicon'larla değiştirilir (`dashicons-admin-users`, `dashicons-chart-bar` vb.)
- Yeni CSS class'ları `var(--mhm-*)` token kullanacak

### BookingRefundMetaBox.php

**Sorun:** PHP render metodu içinde `<script>` bloğu (IIFE) bulunuyor. Business logic PHP'de olması gereken JS'e yazılmış.

**Fix:**
- Script bloğu `booking-meta.css`'in kardeş JS dosyasına taşınır (mevcut `booking-email-send.js` veya yeni `booking-refund.js`)
- PHP tarafında `wp_add_inline_script()` veya doğrudan enqueue kullanılır
- `wp_localize_script()` ile PHP verisi JS'e aktarılır (hardcode değer yerine)

---

## Task 4 — History Pagination

**Sorun:** `_mhm_booking_logs` tüm kayıtlar tek meta value'da. Kayıt sayısı arttıkça meta box uzuyor.

**Çözüm: DOM-based "Daha fazla göster" — AJAX yok, YAGNI.**

### PHP (BookingMeta.php)

```php
$all_logs   = array_reverse( $logs ); // en yeni önce
$threshold  = 5;
$total      = count( $all_logs );

foreach ( $all_logs as $index => $log ) {
    $hidden_class = ( $index >= $threshold ) ? ' mhm-history-item--hidden' : '';
    echo '<li class="mhm-history-item' . $hidden_class . '">';
    // ... log render
    echo '</li>';
}

if ( $total > $threshold ) {
    printf(
        '<button type="button" class="mhm-history-show-more">+ %d kayıt daha</button>',
        $total - $threshold
    );
}
```

### CSS (booking-edit-meta.css)

```css
.mhm-history-item--hidden {
    display: none;
}

.mhm-history-show-more {
    background: none;
    border: none;
    color: var(--mhm-primary);
    cursor: pointer;
    font-size: 12px;
    padding: var(--mhm-space-1) 0;
    width: 100%;
    text-align: left;
}

.mhm-history-show-more:hover {
    text-decoration: underline;
}
```

### JS (booking-email-send.js)

```js
$( document ).on( 'click', '.mhm-history-show-more', function () {
    $( '.mhm-history-item--hidden' ).show();
    $( this ).remove();
} );
```

**Eşik:** Hardcode 5 — gelecekte gerekirse ayarlanabilir sabit yapılır.

---

## Etkilenen Dosyalar Özeti

| Dosya | Task | Değişiklik Türü |
|-------|------|-----------------|
| `assets/js/admin/manual-booking-meta.js` | 1a | HTML tag boşlukları düzelt |
| `src/Admin/Booking/ListTable/BookingColumns.php` | 1b | Gateway label array genişlet |
| `src/Admin/Booking/Meta/ManualBookingMetaBox.php` | 1c | Status dropdown dinamikleştir |
| `src/Admin/Booking/Meta/BookingEditMetaBox.php` | 1c | Status transitions dinamikleştir |
| `src/Admin/Booking/Core/Handler.php` | 2 | Deadline hesaplama UTC'ye standardize |
| `src/Admin/Booking/Actions/DepositManagementAjax.php` | 2 | Deadline karşılaştırma UTC normalize |
| `src/Admin/Booking/Meta/BookingPortalMetaBox.php` | 3 | Inline style → CSS, emoji → dashicon |
| `src/Admin/Booking/Meta/BookingRefundMetaBox.php` | 3 | Inline script → JS dosyası |
| `assets/css/admin/booking-meta.css` | 3 | Yeni CSS class'ları |
| `src/Admin/Booking/Meta/BookingMeta.php` | 4 | History threshold + hidden class |
| `assets/css/admin/booking-edit-meta.css` | 4 | `.mhm-history-item--hidden` + show-more |
| `assets/js/admin/booking-email-send.js` | 4 | Show-more click handler |

---

## Test Kriterleri

- [ ] Hata mesajları HTML olarak render ediliyor, metin olarak değil
- [ ] Liste sayfasında ödeme sütunu Türkçe gateway ismi gösteriyor
- [ ] Yeni rezervasyon formunda sadece pending/confirmed durumları var
- [ ] UTC+3 ortamında iade deadline kontrolü doğru çalışıyor (3 saatlik kayma yok)
- [ ] Cron auto-cancel beklendiği gibi çalışmaya devam ediyor
- [ ] Buffer time koruması bozulmadı
- [ ] BookingPortalMetaBox'ta inline style yok
- [ ] BookingRefundMetaBox'ta inline script yok
- [ ] History kayıtları 5'ten fazlaysa "daha fazla göster" butonu çıkıyor
- [ ] Butona tıklanınca tüm kayıtlar açılıyor
