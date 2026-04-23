# Rezervasyonlar Sayfası — UI Redesign Tasarım Dokümanı

**Tarih:** 2026-03-17
**Kapsam:** Premium UI polish — takvim popup hizalaması, manuel rezervasyon formu kart yapısı, liste sayfası badge/hover/token temizliği

---

## Yaklaşım

Approach B: Çalışan ama ucuz görünen bileşenleri premium hissettiren seviyeye çekmek. Yeni özellik yok, sadece görsel kalite ve tutarlılık. Hardcoded değerler token'lara taşınır, animasyonlar eklenir, CSS mimari temizlenir.

---

## Kapsam Dışı

- Takvim grid yapısı (CSS Grid → Table dönüşümü yok)
- Yeni kolon veya filtre ekleme
- Responsive breakpoint değişiklikleri (büyük değişiklik yok)
- BookingEditMetaBox (ayrı plan)

---

## Bölüm 1: Takvim Popup Hizalaması

### Sorun
Rezervasyon takvimi popup'ı JavaScript ile dinamik HTML üretiyor. Araçlar takvimi popup'ı pre-built HTML şablon kullanıyor. Sonuç: Edit butonu yok, ARIA yok, görsel tutarsızlık.

### Çözüm

**PHP (BookingColumns.php):**
- `render_calendar_page()` metoduna statik popup HTML şablonu eklenir
- Araçlar takvimiyle aynı yapı: header (icon + title + status badge + close), body (bölümlere ayrılmış info grid), footer (Edit + Close butonları)
- Birden fazla rezervasyon aynı günde → popup `#popup-bookings-list` div'i ile stacked cards gösterir
- ARIA: `role="dialog"`, `aria-modal="true"`, `aria-labelledby="mhm-popup-title"`

**JS (booking-calendar.js):**
- Dinamik HTML üretimi kaldırılır
- Pre-built element'lere `.text()` ile değer yazılır (XSS güvenli)
- Tek rezervasyon modu: info grid görünür, list div gizli
- Çoklu rezervasyon modu: info grid gizli, list div görünür (her rezervasyon için card oluşturulur)
- "Edit Booking" butonu href'i dinamik olarak set edilir

**CSS (booking-calendar.css):**
- `.mhm-popup-header-left`, `.mhm-popup-header-right` layout delta fix'leri
- `.mhm-popup-status-badge` status variant sınıfları (Araçlar takvimindekiyle aynı)
- `.mhm-popup-bookings-list` multi-booking card stilleri
- ARIA focus trap için `:focus-visible` stilleri

### Etkilenen Dosyalar
- `src/Admin/Booking/ListTable/BookingColumns.php`
- `assets/js/admin/booking-calendar.js`
- `assets/css/admin/booking-calendar.css`

---

## Bölüm 2: Manuel Rezervasyon Formu — Premium Redesign

### Sorun
Form bölümleri arasında görsel gruplandırma yok, section başlıkları CSS'siz, inline `display:none` style'lar var, loading state zayıf, hardcoded spacing.

### Çözüm

**Kart yapısı (4 bölüm):**

1. **Araç & Müşteri kartı** (`.mhm-form-card`)
   - Araç seçimi + müşteri seçimi + yeni müşteri alanları
   - Kart header: dashicon + "Vehicle & Customer" başlığı

2. **Tarih & Süre kartı**
   - Tarih/saat input grubu + misafir sayısı
   - Kart header: dashicon-calendar-alt + "Dates & Duration"

3. **Ek Hizmetler kartı**
   - Addon checkbox listesi
   - Kart header: dashicon-plus-alt + "Additional Services"
   - Addon fiyat etiketi → pill badge

4. **Ödeme & Notlar kartı**
   - Ödeme tipi/yöntemi + notlar textarea
   - Kart header: dashicon-money-alt + "Payment & Notes"

**Sticky fiyat özet paneli:**
- `.mhm-price-summary-panel` — sayfanın altında sticky
- Fiyat hesaplama açıldığında buraya taşınır
- Toplam, deposit, kalan tutarlar net görünür

**Animasyonlar:**
- Inline `style="display:none"` → `.mhm-hidden` CSS sınıfı (PHP'de)
- JS'de `.show()`/`.hide()` → `.addClass('mhm-hidden')`/`.removeClass('mhm-hidden')`
- Yeni müşteri alanları: CSS `max-height` transition (0 → auto pattern)
- Fiyat paneli: `opacity` + `transform: translateY` fade-in
- Loading state: CSS `@keyframes spin` spinner + buton metin değişimi

**Section header CSS:**
```css
.mhm-form-card-header {
    font-size: var(--mhm-text-base);      /* 16px */
    font-weight: var(--mhm-font-semibold);
    color: var(--mhm-text-primary);
    display: flex;
    align-items: center;
    gap: var(--mhm-space-2);
    padding-bottom: var(--mhm-space-3);
    border-bottom: 1px solid var(--mhm-border-primary);
    margin-bottom: var(--mhm-space-4);
}
```

**Token temizliği:**
- `20px` → `var(--mhm-space-6)` (1.5rem = 24px, en yakın)
- `12px` → `var(--mhm-space-3)`
- `8px` → `var(--mhm-space-2)`
- `16px` → `var(--mhm-space-4)`

### Etkilenen Dosyalar
- `src/Admin/Booking/Meta/ManualBookingMetaBox.php`
- `assets/css/admin/manual-booking-meta.css`
- `assets/js/admin/manual-booking-meta.js`

---

## Bölüm 3: Rezervasyon Listesi — Polish

### Sorun
Ödeme durumu düz metin, pending badge WCAG hatası, hardcoded renkler, hover efekti yok, `!important` kaçakları.

### Çözüm

**Ödeme durumu badge'i:**
```php
// ÖNCE:
echo '<div class="payment-status">' . esc_html( $label ) . '</div>';

// SONRA:
echo '<span class="badge payment-status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
```

Badge CSS sınıfları: `payment-status-paid`, `payment-status-pending`, `payment-status-partially_paid`, `payment-status-refunded`, `payment-status-free`

**WCAG fix:**
```css
/* ÖNCE: */
.badge.status-pending { background-color: var(--mhm-warning); color: #000; }

/* SONRA: */
.badge.status-pending { background-color: var(--mhm-warning); color: var(--mhm-warning-dark, #7a5500); }
```

**Gateway pill:**
```php
// ÖNCE: '[WooCommerce]'
// SONRA:
echo '<span class="mhm-gateway-pill">' . esc_html( $label ) . '</span>';
```

**Hover efekti:**
```css
.wp-list-table tbody tr:hover td { background-color: var(--mhm-bg-secondary); }
.wp-list-table tbody tr { transition: background-color 150ms ease; }
```

**Boş değer:**
```css
.mhm-empty-value { color: var(--mhm-text-tertiary); font-style: italic; }
```

**Token temizliği:**
- `#e3f2fd` → `var(--mhm-booking-type-online-bg, #e3f2fd)` (CSS variable tanımlanır)
- `#fff3e0` → `var(--mhm-booking-type-manual-bg, #fff3e0)`
- `#fd7e14` → `var(--mhm-orange, #fd7e14)` (eksik token tanımlanır)
- `!important` kaldırılır, specificity `.mhm-booking-list` ile yönetilir
- SVG dropdown ok → CSS mask: `mask-image: url("data:image/svg+xml,...")` ile renk değiştirilebilir hale getirilir

### Etkilenen Dosyalar
- `src/Admin/Booking/ListTable/BookingColumns.php`
- `assets/css/admin/booking-list.css`

---

## Test Kriterleri

- [ ] Takvim popup Araçlar popup'ıyla görsel olarak tutarlı
- [ ] Tek rezervasyonlu günde detay görünümü açılıyor
- [ ] Çok rezervasyonlu günde card listesi gösteriliyor
- [ ] Edit Booking butonu doğru booking URL'ine yönlendiriyor
- [ ] Manuel form 4 kart bölümüne ayrılmış görünüyor
- [ ] Yeni müşteri alanları animasyonlu açılıyor/kapanıyor
- [ ] Fiyat hesaplama loading state'i spinner gösteriyor
- [ ] Rezervasyon listesinde ödeme durumu badge olarak görünüyor
- [ ] Pending badge WCAG kontrast testi geçiyor
- [ ] Satır hover efekti çalışıyor
- [ ] Gateway etiketi pill badge olarak görünüyor
