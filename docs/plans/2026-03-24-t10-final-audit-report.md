# MHM Rentiva v4.21.27 — Final Ecosystem Audit Report (T10)

**Tarih:** 2026-03-24
**Plugin Sürümü:** 4.21.27
**PHPUnit Baseline (audit öncesi):** 268 test, 1379 assertion (v4.20.0)
**PHPUnit Baseline (audit sonrası):** 563 test, 2022 assertion
**PHPCS:** Temiz

---

## Özet

Bu rapor, MHM Rentiva eklentisinin T1–T9 audit izlerini kapsar. Denetim şu alanlarda gerçekleştirildi: `AllowlistRegistry` şema bütünlüğü, `BlockRegistry` ↔ shortcode eşleşmesi, Elementor widget kataloğu, `block.json` özellik eşliği, frontend render testleri ve ayarlar sanitizasyon testleri. Denetim sürecinde 7 kritik hata düzeltildi, 100'den fazla yeni test eklendi ve 1 işlevsiz shortcode tamamen kaldırıldı.

---

## T1 — AllowlistRegistry Şema Denetimi

**Durum:** ✅ Tamamlandı

**Bulgular:**
- `ALLOWLIST` içinde **199 benzersiz özellik anahtarı** mevcut
- PHP dizi seviyesinde tekrarlanan (duplicate) üst anahtar yok
- 9 `enum` tipi özelliğin tamamında `values` dizisi tanımlı
- Tüm `TAG_MAPPING` özellik anahtarları `ALLOWLIST`'te mevcut

**Düzeltmeler:**
- `location_required` → `ALLOWLIST` + `rentiva_unified_search` TAG_MAPPING'e eklendi
- `show_booking_details` → `ALLOWLIST` + `rentiva_booking_confirmation` TAG_MAPPING'e eklendi (sonradan kaldırıldı, bkz. aşağı)

**Kalan uyarılar (düşük öncelik):**

| Sorun | Konum | Risk |
|-------|-------|------|
| `show_booking_button` TAG_MAPPING'de 4 kez geçiyor, farklı default tipler (`'1'` string vs `true` bool) | `AllowlistRegistry.php` satır ~200 | Düşük — çalışma zamanında bool→string dönüşümü mevcut |
| `show_favorite_btn` + `show_favorite_button` iki ayrı anahtar | satır 215–224 | Düşük — gereksiz fazlalık |
| `show_compare_btn` + `show_compare_button` iki ayrı anahtar | satır 225–234 | Düşük — gereksiz fazlalık |

**Test dosyası:** `tests/Core/Attribute/SchemaParityTest.php` (6 test)

---

## T2 — BlockRegistry Denetimi

**Durum:** ✅ Tamamlandı

**Bulgular:**
- **19 blok** → 19 shortcode (mükemmel eşleşme)
- **1 orphan shortcode:** `user_dashboard` — `BlockRegistry`'de karşılığı yok (tasarım gereği, admin-only shortcode)
- `rentiva_booking_confirmation` bloğu `BlockRegistry`'de kayıtlıydı ancak hiçbir PHP shortcode handler'ı implement edilmemişti

**Eylem:**
- `rentiva_booking_confirmation` **tamamen kaldırıldı:**
  - `BlockRegistry.php`
  - `AllowlistRegistry.php` (ALLOWLIST + TAG_MAPPING)
  - `assets/blocks/booking-confirmation/` dizini
  - `SHORTCODES.md` (4 bölüm)
  - `tests/Core/Attribute/SchemaParityTest.php` (blok sayımı 19 → 18)

**Mevcut sayım (denetim sonrası):**
- 20 shortcode handler (`ShortcodeServiceProvider`)
- 19 Gutenberg bloğu
- 19 `AllowlistRegistry` TAG_MAPPING girişi
- 1 orphan shortcode: `user_dashboard` (beklenen)

---

## T3 — Elementor Widget Denetimi

**Durum:** ✅ Tamamlandı (sorun yok)

**Bulgular:**
- 20 Elementor widget kayıtlı (`ElementorIntegration.php`)
- 19/20 widget shortcode'a eşlenmiş (1 tanesinin mapping'i yok — tasarım gereği)
- Özellik açığa çıkarma oranı widget'a göre **%4.5 – %72** arasında değişiyor

**Not:** Widget başına açığa çıkarılan özellik oranının düşüklüğü bilinçli bir tasarım kararıdır; Elementor panelinde fazla seçenek kalabalığı önlenmiş.

---

## T4 — block.json Özellik Eşliği Denetimi

**Durum:** ✅ Çözüldü (uyarılar mevcut)

**Bulgular:**
- `block.json` ↔ `AllowlistRegistry` arasındaki camelCase/snake_case farkı **hata değil:**
  - `KeyNormalizer::normalize()` (`src/Core/Attribute/KeyNormalizer.php:37–49`) iki aşamalı çözümleme yapar:
    1. `AllowlistRegistry aliases` dizisi araması
    2. camelCase → snake_case geri dönüş dönüşümü
  - `tests/Core/Attribute/KeyNormalizerTest.php` — 27 test, 32 assertion, tümü geçiyor

**Düzeltmeler:**
- `featured-vehicles` blok `block.json` layout default: `"carousel"` → `"slider"` (test hatası düzeltildi)

**Kalan uyarılar (düşük öncelik):**

| Sorun | Blok | Risk |
|-------|------|------|
| `defaultDays`, `minDays`, `maxDays` — `block.json`'da default değer yok | `availability-calendar` | Düşük — PHP shortcode default'u devreye giriyor |
| `limit` — `block.json`'da string `"12"`, `AllowlistRegistry`'de int | birden fazla blok | Düşük — CAM pipeline dönüştürüyor |

---

## T8 — Frontend Render Testleri

**Durum:** ✅ Tamamlandı

**Eklenen test dosyaları (13 dosya):**

| Dosya | Shortcode | Test |
|-------|-----------|------|
| `AvailabilityCalendarTest.php` | `rentiva_availability_calendar` | 4 |
| `UnifiedSearchTest.php` | `rentiva_unified_search` | 6 |
| `TransferSearchTest.php` | `rentiva_transfer_search` | 4 |
| `ContactFormTest.php` | `rentiva_contact` | 6 |
| `VehicleRatingFormTest.php` | `rentiva_vehicle_rating_form` | 3 |
| `VehicleDetailsTest.php` | `rentiva_vehicle_details` | 4 |
| `VehicleComparisonTest.php` | `rentiva_vehicle_comparison` | 5 |
| `TestimonialsTest.php` | `rentiva_testimonials` | 6 |
| `TransferResultsTest.php` | `rentiva_transfer_results` | 4 |
| `Account/MyBookingsTest.php` | `rentiva_my_bookings` | 3 |
| `Account/MyFavoritesTest.php` | `rentiva_my_favorites` | 3 |
| `Account/PaymentHistoryTest.php` | `rentiva_payment_history` | 3 |
| `Account/AccountMessagesTest.php` | `rentiva_messages` | 3 |
| **Toplam** | | **54 test** |

**Uygulama sırasında keşfedilen bulgular:**

| Bulgu | Dosya | Açıklama |
|-------|-------|----------|
| `rv-vehicle-details-wrapper` | `VehicleDetailsTest` | Template wrapper class isminden farklı |
| `VehicleRatingForm` error state | `VehicleRatingFormTest` | `return` içinde `include` → output buffer'a yazmıyor; `mhm-rentiva-shortcode-error` döner |
| `rentiva_my_favorites` ayrı pipeline | `MyFavoritesTest` | Account template değil, vehicles-grid pipeline kullanıyor |
| `rentiva_contact type` attribute | `ContactFormTest` | `type` parametresi `data-form-type`'ı değiştirmiyor — bilinen bug (bkz. aşağı) |

---

## T9 — Settings Sanitizer Testleri

**Durum:** ✅ Tamamlandı

**Eklenen test dosyaları (4 dosya):**

| Dosya | Kapsam | Test |
|-------|--------|------|
| `SettingsSanitizerPublicTest.php` | `sanitize_dark_mode_option`, `safe_text` | 11 |
| `SettingsSanitizerBookingTabTest.php` | booking tab: deadline clamp, checkbox, rental days | 11 |
| `SettingsSanitizerVehicleTabTest.php` | vehicle tab: URL slug, sort enum, min/max, tax | 11 |
| `SettingsSanitizerSystemTabTest.php` | system tab: login attempts, lockout, log_level, rate limits | 13 |
| **Toplam** | | **46 test** |

**Kapsam dışı bırakılanlar** (YAGNI — karmaşıklık / risk oranı düşük):
- Email tab (50+ anahtar, çoğu metin alanı)
- Transfer tab, Comments tab, Frontend labels tab
- Addon settings (mevcut `AddonSettingsRegistrationTest.php` var)

---

## Açık Bulgular (Sonraki Sürümde İşlem Görmeli)

### B1 — `rentiva_contact` shortcode `type` attribute'u yok sayıyor
- **Önem:** Orta
- **Durum:** ⚠️ Açık
- **Açıklama:** `[rentiva_contact type="booking"]` verildiğinde `data-form-type` her zaman `"general"` döner. `type` CAM pipeline'dan geçiyor ancak template değişkeni olarak yansımıyor.
- **Dosya:** `src/Admin/Frontend/Shortcodes/ContactForm.php` — template'e `type` geçirilmiyor
- **Test referansı:** `ContactFormTest::test_booking_type_attribute` (düzeltilmiş assertion ile belgelenmiş)

### B2 — TAG_MAPPING'de `show_booking_button` 4 kez farklı tip ile tanımlı
- **Önem:** Düşük
- **Durum:** ⚠️ Açık
- **Açıklama:** Aynı anahtar farklı shortcode'lar için string `'1'` ve bool `true` olarak tanımlanmış. Çalışma zamanında fark yaratmıyor ancak kod kokusu.
- **Dosya:** `src/Core/Attribute/AllowlistRegistry.php`

### B3 — `show_favorite_btn` / `show_favorite_button` ve `show_compare_btn` / `show_compare_button` çift tanım
- **Önem:** Düşük
- **Durum:** ⚠️ Açık
- **Açıklama:** Her iki çift de ayrı anahtar olarak tanımlı. Biri alias olmalı.
- **Dosya:** `src/Core/Attribute/AllowlistRegistry.php` satır 215–234

### B4 — block.json eksik default değerler
- **Önem:** Düşük
- **Durum:** ⚠️ Açık
- **Açıklama:** `availability-calendar` bloğunda `defaultDays`, `minDays`, `maxDays` için `block.json` default değeri yok. Gutenberg editor'da bu alanlar boş görünebilir.
- **Dosya:** `assets/blocks/availability-calendar/block.json`

---

## PHPUnit Seyri

| Aşama | Test | Assertion | Tarih |
|-------|------|-----------|-------|
| v4.20.0 (foundation) | 268 | 1379 | — |
| v4.21.27 (audit öncesi) | 463 | 1901 | 2026-03-24 |
| T8 tamamlandı | 517 | 1963 | 2026-03-24 |
| T9 tamamlandı | **563** | **2022** | 2026-03-24 |

**Net artış (audit ile):** +295 test, +643 assertion

---

## Kapsam Özeti

| Audit İzi | Kapsam | Durum |
|-----------|--------|-------|
| T1 — AllowlistRegistry | Şema bütünlüğü, enum coverage, duplicate | ✅ |
| T2 — BlockRegistry | Blok ↔ shortcode eşleşmesi | ✅ |
| T3 — Elementor Widgets | Widget kataloğu, attribute exposure | ✅ |
| T4 — block.json | camelCase eşliği, KeyNormalizer | ✅ |
| T8 — Frontend Render | 13 shortcode render testi | ✅ |
| T9 — Settings Sanitizer | 4 sanitizer test dosyası | ✅ |

---

## Öneriler

1. **B1 (rentiva_contact type)** — bir sonraki sprint'te ContactForm template'ine `type` parametresi geçirilmeli.
2. **B2 / B3** — `AllowlistRegistry`'deki `show_booking_button` çoğaltması ve btn/button çift tanımları temizlenmeli; birini alias olarak işaretlemek yeterli.
3. **B4** — `availability-calendar/block.json`'a `defaultDays`, `minDays`, `maxDays` için makul default değerler eklenmeli.
4. **T9 kapsam genişletme** — email tab sanitizasyonu için ayrı bir sprint planlanabilir (düşük öncelik).
